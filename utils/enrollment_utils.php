<?php
/**
 * Enrollment Utilities
 * Phase 7: Functions for controlled unenrollment and enrollment checks
 */

/**
 * Check if a teacher can unenroll from a school
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @param int $school_id School ID
 * @return array ['can_unenroll' => bool, 'reasons' => array, 'details' => array]
 */
function canTeacherUnenroll($conn, $teacher_id, $school_id) {
    $result = [
        'can_unenroll' => true,
        'reasons' => [],
        'details' => [
            'upcoming_slots' => 0,
            'pending_photos' => 0,
            'pending_reviews' => 0
        ]
    ];
    
    $today = date('Y-m-d');
    
    // Check for upcoming booked slots
    $upcoming_sql = "SELECT COUNT(*) as cnt FROM slot_teacher_enrollments ste
                     JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                     WHERE ste.teacher_id = ? 
                     AND sts.school_id = ?
                     AND sts.slot_date >= ?
                     AND ste.enrollment_status = 'booked'";
    $stmt = mysqli_prepare($conn, $upcoming_sql);
    mysqli_stmt_bind_param($stmt, "iis", $teacher_id, $school_id, $today);
    mysqli_stmt_execute($stmt);
    $upcoming = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($upcoming['cnt'] > 0) {
        $result['can_unenroll'] = false;
        $result['reasons'][] = "You have {$upcoming['cnt']} upcoming slot(s) booked at this school";
        $result['details']['upcoming_slots'] = $upcoming['cnt'];
    }
    
    // Check for sessions pending photo submission (past slots with no photo)
    $pending_photo_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions ts
                          WHERE ts.teacher_id = ? 
                          AND ts.school_id = ?
                          AND ts.session_status = 'pending'
                          AND ts.photo_path IS NULL";
    $stmt = mysqli_prepare($conn, $pending_photo_sql);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($stmt);
    $pending_photo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($pending_photo['cnt'] > 0) {
        $result['can_unenroll'] = false;
        $result['reasons'][] = "{$pending_photo['cnt']} session(s) need photo submission";
        $result['details']['pending_photos'] = $pending_photo['cnt'];
    }
    
    // Check for sessions pending admin review
    $pending_review_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions ts
                           WHERE ts.teacher_id = ? 
                           AND ts.school_id = ?
                           AND ts.session_status = 'photo_submitted'";
    $stmt = mysqli_prepare($conn, $pending_review_sql);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($stmt);
    $pending_review = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($pending_review['cnt'] > 0) {
        $result['can_unenroll'] = false;
        $result['reasons'][] = "{$pending_review['cnt']} session(s) awaiting admin approval";
        $result['details']['pending_reviews'] = $pending_review['cnt'];
    }
    
    return $result;
}

/**
 * Get teacher's pending obligations for a school
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @param int $school_id School ID
 * @return array Detailed list of obligations
 */
function getTeacherObligations($conn, $teacher_id, $school_id) {
    $obligations = [
        'upcoming_slots' => [],
        'pending_photos' => [],
        'pending_reviews' => []
    ];
    
    $today = date('Y-m-d');
    
    // Get upcoming slots
    $upcoming_sql = "SELECT ste.enrollment_id, sts.slot_date, sts.start_time, sts.end_time
                     FROM slot_teacher_enrollments ste
                     JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                     WHERE ste.teacher_id = ? 
                     AND sts.school_id = ?
                     AND sts.slot_date >= ?
                     AND ste.enrollment_status = 'booked'
                     ORDER BY sts.slot_date ASC";
    $stmt = mysqli_prepare($conn, $upcoming_sql);
    mysqli_stmt_bind_param($stmt, "iis", $teacher_id, $school_id, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $obligations['upcoming_slots'][] = $row;
    }
    
    // Get pending photo sessions
    $pending_photo_sql = "SELECT ts.session_id, ts.session_date, sts.slot_date, sts.start_time
                          FROM teaching_sessions ts
                          JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                          WHERE ts.teacher_id = ? 
                          AND ts.school_id = ?
                          AND ts.session_status = 'pending'
                          AND ts.photo_path IS NULL
                          ORDER BY sts.slot_date ASC";
    $stmt = mysqli_prepare($conn, $pending_photo_sql);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $obligations['pending_photos'][] = $row;
    }
    
    // Get pending review sessions
    $pending_review_sql = "SELECT ts.session_id, ts.session_date, sts.slot_date, sts.start_time
                           FROM teaching_sessions ts
                           JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                           WHERE ts.teacher_id = ? 
                           AND ts.school_id = ?
                           AND ts.session_status = 'photo_submitted'
                           ORDER BY sts.slot_date ASC";
    $stmt = mysqli_prepare($conn, $pending_review_sql);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $obligations['pending_reviews'][] = $row;
    }
    
    return $obligations;
}

/**
 * Force unenroll a teacher from a school (Admin only)
 * Cancels all upcoming slots and marks sessions appropriately
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @param int $school_id School ID
 * @param int $admin_id Admin ID performing the action
 * @param string $reason Reason for force unenrollment
 * @return array ['success' => bool, 'message' => string, 'cancelled_slots' => int]
 */
function forceUnenrollTeacher($conn, $teacher_id, $school_id, $admin_id, $reason = '') {
    mysqli_begin_transaction($conn);
    
    try {
        $today = date('Y-m-d');
        $cancelled_slots = 0;
        
        // Cancel all upcoming slot enrollments
        $cancel_sql = "UPDATE slot_teacher_enrollments ste
                       JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                       SET ste.enrollment_status = 'cancelled',
                           ste.cancelled_at = NOW(),
                           ste.cancellation_reason = ?
                       WHERE ste.teacher_id = ?
                       AND sts.school_id = ?
                       AND sts.slot_date >= ?
                       AND ste.enrollment_status = 'booked'";
        $stmt = mysqli_prepare($conn, $cancel_sql);
        $cancel_reason = "Admin force unenroll: " . ($reason ?: 'No reason provided');
        mysqli_stmt_bind_param($stmt, "siis", $cancel_reason, $teacher_id, $school_id, $today);
        mysqli_stmt_execute($stmt);
        $cancelled_slots = mysqli_affected_rows($conn);
        
        // Mark pending sessions as cancelled
        $cancel_sessions_sql = "UPDATE teaching_sessions 
                                SET session_status = 'rejected',
                                    admin_remarks = ?,
                                    verified_by = ?,
                                    verified_at = NOW()
                                WHERE teacher_id = ?
                                AND school_id = ?
                                AND session_status IN ('pending', 'photo_submitted')";
        $stmt = mysqli_prepare($conn, $cancel_sessions_sql);
        $session_remark = "Session cancelled due to unenrollment: " . ($reason ?: 'No reason provided');
        mysqli_stmt_bind_param($stmt, "siii", $session_remark, $admin_id, $teacher_id, $school_id);
        mysqli_stmt_execute($stmt);
        
        // Check if was primary school
        $check_primary = mysqli_prepare($conn, "SELECT is_primary FROM teacher_schools WHERE teacher_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($check_primary, "ii", $teacher_id, $school_id);
        mysqli_stmt_execute($check_primary);
        $primary_result = mysqli_fetch_assoc(mysqli_stmt_get_result($check_primary));
        $was_primary = $primary_result ? $primary_result['is_primary'] : 0;
        
        // Remove from teacher_schools
        $delete_sql = "DELETE FROM teacher_schools WHERE teacher_id = ? AND school_id = ?";
        $stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
        mysqli_stmt_execute($stmt);
        
        // If was primary, reassign
        if ($was_primary) {
            $reassign = mysqli_prepare($conn, "UPDATE teacher_schools SET is_primary = 1 WHERE teacher_id = ? ORDER BY enrolled_at ASC LIMIT 1");
            mysqli_stmt_bind_param($reassign, "i", $teacher_id);
            mysqli_stmt_execute($reassign);
        }
        
        mysqli_commit($conn);
        
        return [
            'success' => true,
            'message' => "Teacher successfully unenrolled. $cancelled_slots slot(s) cancelled.",
            'cancelled_slots' => $cancelled_slots
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return [
            'success' => false,
            'message' => "Failed to unenroll teacher: " . $e->getMessage(),
            'cancelled_slots' => 0
        ];
    }
}

/**
 * Get teacher's enrollment summary for a school
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @param int $school_id School ID
 * @return array Enrollment statistics
 */
function getTeacherSchoolStats($conn, $teacher_id, $school_id) {
    $stats = [
        'total_slots_booked' => 0,
        'completed_sessions' => 0,
        'approved_sessions' => 0,
        'rejected_sessions' => 0,
        'pending_sessions' => 0,
        'upcoming_slots' => 0
    ];
    
    $today = date('Y-m-d');
    
    // Total slots ever booked
    $sql = "SELECT COUNT(*) as cnt FROM slot_teacher_enrollments ste
            JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
            WHERE ste.teacher_id = ? AND sts.school_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($stmt);
    $stats['total_slots_booked'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
    
    // Session stats
    $session_sql = "SELECT 
                    SUM(CASE WHEN session_status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN session_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN session_status IN ('pending', 'photo_submitted') THEN 1 ELSE 0 END) as pending
                    FROM teaching_sessions
                    WHERE teacher_id = ? AND school_id = ?";
    $stmt = mysqli_prepare($conn, $session_sql);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($stmt);
    $session_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $stats['approved_sessions'] = $session_stats['approved'] ?? 0;
    $stats['rejected_sessions'] = $session_stats['rejected'] ?? 0;
    $stats['pending_sessions'] = $session_stats['pending'] ?? 0;
    $stats['completed_sessions'] = $stats['approved_sessions'];
    
    // Upcoming slots
    $upcoming_sql = "SELECT COUNT(*) as cnt FROM slot_teacher_enrollments ste
                     JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                     WHERE ste.teacher_id = ? AND sts.school_id = ? 
                     AND sts.slot_date >= ? AND ste.enrollment_status = 'booked'";
    $stmt = mysqli_prepare($conn, $upcoming_sql);
    mysqli_stmt_bind_param($stmt, "iis", $teacher_id, $school_id, $today);
    mysqli_stmt_execute($stmt);
    $stats['upcoming_slots'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
    
    return $stats;
}
