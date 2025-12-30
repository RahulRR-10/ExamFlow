<?php
/**
 * Teaching Slots Compatibility Layer
 * Phase 8: Backward Compatibility & Safety
 * 
 * Provides graceful degradation when teaching slots tables don't exist
 * and ensures existing functionality continues to work.
 */

/**
 * Check if teaching slots feature is available (tables exist)
 * 
 * @param mysqli $conn Database connection
 * @return bool True if feature is available
 */
function isTeachingSlotsEnabled($conn) {
    static $enabled = null;
    
    if ($enabled !== null) {
        return $enabled;
    }
    
    $tables = ['school_teaching_slots', 'slot_teacher_enrollments', 'teaching_sessions'];
    
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (!$result || mysqli_num_rows($result) == 0) {
            $enabled = false;
            return false;
        }
    }
    
    $enabled = true;
    return true;
}

/**
 * Check if audit log table exists
 * 
 * @param mysqli $conn Database connection
 * @return string|null Table name if exists, null otherwise
 */
function getAuditLogTable($conn) {
    // Check for admin_audit_log first (newer)
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'admin_audit_log'");
    if ($result && mysqli_num_rows($result) > 0) {
        return 'admin_audit_log';
    }
    
    // Check for audit_log (older)
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'audit_log'");
    if ($result && mysqli_num_rows($result) > 0) {
        return 'audit_log';
    }
    
    return null;
}

/**
 * Log an admin action to the audit log
 * 
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin performing the action
 * @param string $action_type Type of action
 * @param string $target_table Target table
 * @param int $target_id Target record ID
 * @param array|string $details Action details
 * @return bool Success status
 */
function logAdminAction($conn, $admin_id, $action_type, $target_table = null, $target_id = null, $details = null) {
    $table = getAuditLogTable($conn);
    
    if (!$table) {
        // No audit table available, log to error_log instead
        error_log("AUDIT: Admin $admin_id - $action_type on $target_table:$target_id - " . json_encode($details));
        return true;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $details_json = is_array($details) ? json_encode($details) : ($details ?: '{}');
    
    if ($table === 'admin_audit_log') {
        $sql = "INSERT INTO admin_audit_log (admin_id, action_type, target_table, target_id, action_details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ississ", $admin_id, $action_type, $target_table, $target_id, $details_json, $ip_address);
    } else {
        // Generic audit_log table structure
        $sql = "INSERT INTO audit_log (action, performed_by, details, created_at) 
                VALUES (?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sis", $action_type, $admin_id, $details_json);
    }
    
    return mysqli_stmt_execute($stmt);
}

/**
 * Get teacher's upcoming slot count (graceful if tables don't exist)
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @return int Number of upcoming slots
 */
function getTeacherUpcomingSlots($conn, $teacher_id) {
    if (!isTeachingSlotsEnabled($conn)) {
        return 0;
    }
    
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as cnt FROM slot_teacher_enrollments ste
            JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
            WHERE ste.teacher_id = ? AND ste.enrollment_status = 'booked' 
            AND sts.slot_date >= ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $teacher_id, $today);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
}

/**
 * Get teacher's pending sessions count (graceful if tables don't exist)
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @return int Number of pending sessions
 */
function getTeacherPendingSessions($conn, $teacher_id) {
    if (!isTeachingSlotsEnabled($conn)) {
        return 0;
    }
    
    $sql = "SELECT COUNT(*) as cnt FROM teaching_sessions 
            WHERE teacher_id = ? AND session_status IN ('pending', 'photo_submitted')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
}

/**
 * Check if a slot booking would create a time conflict
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @param int $slot_id Slot ID to book
 * @return array ['conflict' => bool, 'conflicting_slot' => array|null]
 */
function checkSlotConflict($conn, $teacher_id, $slot_id) {
    if (!isTeachingSlotsEnabled($conn)) {
        return ['conflict' => false, 'conflicting_slot' => null];
    }
    
    // Get the slot details
    $slot_sql = "SELECT slot_date, start_time, end_time FROM school_teaching_slots WHERE slot_id = ?";
    $stmt = mysqli_prepare($conn, $slot_sql);
    mysqli_stmt_bind_param($stmt, "i", $slot_id);
    mysqli_stmt_execute($stmt);
    $slot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$slot) {
        return ['conflict' => false, 'conflicting_slot' => null];
    }
    
    // Check for overlapping slots
    $conflict_sql = "SELECT sts.slot_id, sts.slot_date, sts.start_time, sts.end_time, s.school_name
                     FROM slot_teacher_enrollments ste
                     JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                     JOIN schools s ON sts.school_id = s.school_id
                     WHERE ste.teacher_id = ?
                     AND ste.enrollment_status = 'booked'
                     AND sts.slot_date = ?
                     AND sts.slot_id != ?
                     AND (
                         (sts.start_time < ? AND sts.end_time > ?)
                         OR (sts.start_time >= ? AND sts.start_time < ?)
                         OR (sts.end_time > ? AND sts.end_time <= ?)
                     )";
    $stmt = mysqli_prepare($conn, $conflict_sql);
    mysqli_stmt_bind_param($stmt, "isisssssss", 
        $teacher_id, 
        $slot['slot_date'], 
        $slot_id,
        $slot['end_time'], $slot['start_time'],
        $slot['start_time'], $slot['end_time'],
        $slot['start_time'], $slot['end_time']
    );
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        return [
            'conflict' => true,
            'conflicting_slot' => mysqli_fetch_assoc($result)
        ];
    }
    
    return ['conflict' => false, 'conflicting_slot' => null];
}

/**
 * Safely book a slot with transaction and race condition handling
 * 
 * @param mysqli $conn Database connection
 * @param int $slot_id Slot ID
 * @param int $teacher_id Teacher ID
 * @return array ['success' => bool, 'message' => string, 'enrollment_id' => int|null]
 */
function safeBookSlot($conn, $slot_id, $teacher_id) {
    if (!isTeachingSlotsEnabled($conn)) {
        return ['success' => false, 'message' => 'Teaching slots feature not available', 'enrollment_id' => null];
    }
    
    // Check for conflicts first
    $conflict = checkSlotConflict($conn, $teacher_id, $slot_id);
    if ($conflict['conflict']) {
        $cs = $conflict['conflicting_slot'];
        return [
            'success' => false,
            'message' => "Time conflict with existing booking at {$cs['school_name']} ({$cs['start_time']} - {$cs['end_time']})",
            'enrollment_id' => null
        ];
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Lock the slot row for update (prevents race condition)
        $lock_sql = "SELECT slot_id, teachers_required, teachers_enrolled, slot_status 
                     FROM school_teaching_slots 
                     WHERE slot_id = ? 
                     FOR UPDATE";
        $stmt = mysqli_prepare($conn, $lock_sql);
        mysqli_stmt_bind_param($stmt, "i", $slot_id);
        mysqli_stmt_execute($stmt);
        $slot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$slot) {
            mysqli_rollback($conn);
            return ['success' => false, 'message' => 'Slot not found', 'enrollment_id' => null];
        }
        
        // Check if slot is still available
        if ($slot['teachers_enrolled'] >= $slot['teachers_required']) {
            mysqli_rollback($conn);
            return ['success' => false, 'message' => 'Slot is full', 'enrollment_id' => null];
        }
        
        if ($slot['slot_status'] === 'cancelled' || $slot['slot_status'] === 'completed') {
            mysqli_rollback($conn);
            return ['success' => false, 'message' => 'Slot is no longer available', 'enrollment_id' => null];
        }
        
        // Check if already booked
        $check_sql = "SELECT enrollment_id FROM slot_teacher_enrollments 
                      WHERE slot_id = ? AND teacher_id = ? AND enrollment_status = 'booked'";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, "ii", $slot_id, $teacher_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            mysqli_rollback($conn);
            return ['success' => false, 'message' => 'Already booked this slot', 'enrollment_id' => null];
        }
        
        // Create enrollment
        $enroll_sql = "INSERT INTO slot_teacher_enrollments (slot_id, teacher_id, enrollment_status, booked_at) 
                       VALUES (?, ?, 'booked', NOW())";
        $stmt = mysqli_prepare($conn, $enroll_sql);
        mysqli_stmt_bind_param($stmt, "ii", $slot_id, $teacher_id);
        mysqli_stmt_execute($stmt);
        $enrollment_id = mysqli_insert_id($conn);
        
        // Update slot counts
        $new_enrolled = $slot['teachers_enrolled'] + 1;
        $new_status = ($new_enrolled >= $slot['teachers_required']) ? 'full' : 'partially_filled';
        
        $update_sql = "UPDATE school_teaching_slots 
                       SET teachers_enrolled = ?, slot_status = ? 
                       WHERE slot_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "isi", $new_enrolled, $new_status, $slot_id);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        
        return [
            'success' => true,
            'message' => 'Successfully booked slot',
            'enrollment_id' => $enrollment_id
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return [
            'success' => false,
            'message' => 'Booking failed: ' . $e->getMessage(),
            'enrollment_id' => null
        ];
    }
}

/**
 * Safely cancel a slot booking
 * 
 * @param mysqli $conn Database connection
 * @param int $enrollment_id Enrollment ID
 * @param int $teacher_id Teacher ID (for verification)
 * @param string $reason Cancellation reason
 * @param int $hours_before Minimum hours before slot for self-cancellation
 * @return array ['success' => bool, 'message' => string, 'needs_admin' => bool]
 */
function safeCancelBooking($conn, $enrollment_id, $teacher_id, $reason = '', $hours_before = 24) {
    if (!isTeachingSlotsEnabled($conn)) {
        return ['success' => false, 'message' => 'Teaching slots feature not available', 'needs_admin' => false];
    }
    
    // Get enrollment and slot details
    $sql = "SELECT ste.*, sts.slot_date, sts.start_time, sts.school_id
            FROM slot_teacher_enrollments ste
            JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
            WHERE ste.enrollment_id = ? AND ste.teacher_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $enrollment_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    $enrollment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$enrollment) {
        return ['success' => false, 'message' => 'Enrollment not found', 'needs_admin' => false];
    }
    
    if ($enrollment['enrollment_status'] !== 'booked') {
        return ['success' => false, 'message' => 'Booking already cancelled or completed', 'needs_admin' => false];
    }
    
    // Check time before slot
    $slot_datetime = strtotime($enrollment['slot_date'] . ' ' . $enrollment['start_time']);
    $now = time();
    $hours_left = ($slot_datetime - $now) / 3600;
    
    if ($hours_left < $hours_before && $hours_left > 0) {
        return [
            'success' => false,
            'message' => "Cannot cancel within {$hours_before} hours of slot start. Please contact admin.",
            'needs_admin' => true
        ];
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Cancel the enrollment
        $cancel_sql = "UPDATE slot_teacher_enrollments 
                       SET enrollment_status = 'cancelled', 
                           cancelled_at = NOW(),
                           cancellation_reason = ?
                       WHERE enrollment_id = ?";
        $stmt = mysqli_prepare($conn, $cancel_sql);
        mysqli_stmt_bind_param($stmt, "si", $reason, $enrollment_id);
        mysqli_stmt_execute($stmt);
        
        // Update slot counts
        $update_sql = "UPDATE school_teaching_slots 
                       SET teachers_enrolled = GREATEST(0, teachers_enrolled - 1),
                           slot_status = CASE 
                               WHEN teachers_enrolled - 1 <= 0 THEN 'open'
                               WHEN teachers_enrolled - 1 < teachers_required THEN 'partially_filled'
                               ELSE slot_status
                           END
                       WHERE slot_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "i", $enrollment['slot_id']);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        
        return ['success' => true, 'message' => 'Booking cancelled successfully', 'needs_admin' => false];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Cancellation failed: ' . $e->getMessage(), 'needs_admin' => false];
    }
}

/**
 * Get slot availability status with detailed info
 * 
 * @param mysqli $conn Database connection
 * @param int $slot_id Slot ID
 * @return array Slot availability info
 */
function getSlotAvailability($conn, $slot_id) {
    if (!isTeachingSlotsEnabled($conn)) {
        return ['available' => false, 'reason' => 'Feature not available'];
    }
    
    $sql = "SELECT sts.*, s.school_name,
            (sts.teachers_required - sts.teachers_enrolled) as spots_left
            FROM school_teaching_slots sts
            JOIN schools s ON sts.school_id = s.school_id
            WHERE sts.slot_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $slot_id);
    mysqli_stmt_execute($stmt);
    $slot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$slot) {
        return ['available' => false, 'reason' => 'Slot not found', 'slot' => null];
    }
    
    $available = true;
    $reason = '';
    
    if ($slot['slot_status'] === 'full') {
        $available = false;
        $reason = 'Slot is full';
    } elseif ($slot['slot_status'] === 'cancelled') {
        $available = false;
        $reason = 'Slot has been cancelled';
    } elseif ($slot['slot_status'] === 'completed') {
        $available = false;
        $reason = 'Slot has already completed';
    } elseif (strtotime($slot['slot_date']) < strtotime(date('Y-m-d'))) {
        $available = false;
        $reason = 'Slot date has passed';
    }
    
    return [
        'available' => $available,
        'reason' => $reason,
        'slot' => $slot,
        'spots_left' => $slot['spots_left']
    ];
}

/**
 * Verify database tables and return status
 * 
 * @param mysqli $conn Database connection
 * @return array Database status information
 */
function verifyTeachingSlotsDatabase($conn) {
    $status = [
        'all_tables_exist' => true,
        'tables' => [],
        'indexes' => [],
        'issues' => []
    ];
    
    $required_tables = [
        'school_teaching_slots' => ['slot_id', 'school_id', 'slot_date', 'start_time', 'end_time', 'teachers_required', 'teachers_enrolled', 'slot_status'],
        'slot_teacher_enrollments' => ['enrollment_id', 'slot_id', 'teacher_id', 'enrollment_status', 'booked_at'],
        'teaching_sessions' => ['session_id', 'enrollment_id', 'teacher_id', 'school_id', 'session_status']
    ];
    
    foreach ($required_tables as $table => $columns) {
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        $exists = $result && mysqli_num_rows($result) > 0;
        
        $status['tables'][$table] = [
            'exists' => $exists,
            'columns_ok' => false
        ];
        
        if (!$exists) {
            $status['all_tables_exist'] = false;
            $status['issues'][] = "Table '$table' does not exist";
            continue;
        }
        
        // Check columns
        $col_result = mysqli_query($conn, "DESCRIBE $table");
        $existing_cols = [];
        while ($row = mysqli_fetch_assoc($col_result)) {
            $existing_cols[] = $row['Field'];
        }
        
        $missing_cols = array_diff($columns, $existing_cols);
        if (empty($missing_cols)) {
            $status['tables'][$table]['columns_ok'] = true;
        } else {
            $status['issues'][] = "Table '$table' missing columns: " . implode(', ', $missing_cols);
        }
    }
    
    // Check indexes
    $required_indexes = [
        'school_teaching_slots' => ['idx_school_date', 'idx_status', 'idx_date'],
        'slot_teacher_enrollments' => ['unique_slot_teacher', 'idx_teacher'],
        'teaching_sessions' => ['idx_teacher_date', 'idx_status']
    ];
    
    foreach ($required_indexes as $table => $indexes) {
        if (!$status['tables'][$table]['exists']) continue;
        
        $idx_result = mysqli_query($conn, "SHOW INDEX FROM $table");
        $existing_indexes = [];
        while ($row = mysqli_fetch_assoc($idx_result)) {
            $existing_indexes[$row['Key_name']] = true;
        }
        
        foreach ($indexes as $idx) {
            $exists = isset($existing_indexes[$idx]);
            $status['indexes'][$table . '.' . $idx] = $exists;
            if (!$exists) {
                $status['issues'][] = "Index '$idx' missing on table '$table'";
            }
        }
    }
    
    return $status;
}
