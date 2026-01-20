<?php
/**
 * Teacher Portal - Book a Teaching Slot
 * Phase 3: AJAX/Form Handler for slot booking
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}

include '../config.php';

$teacher_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: browse_slots.php?error=Invalid request");
    exit;
}

$slot_id = intval($_POST['slot_id'] ?? 0);

if ($slot_id <= 0) {
    header("Location: browse_slots.php?error=Invalid slot ID");
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // NEW Validation: Check if teacher already has an active slot booking
    // Teachers can only book one slot at a time (must wait until completed or rejected)
    $active_booking_sql = "SELECT ste.enrollment_id, ste.enrollment_status, sts.slot_date, sts.start_time, sts.end_time, s.school_name
                           FROM slot_teacher_enrollments ste
                           JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                           JOIN schools s ON sts.school_id = s.school_id
                           WHERE ste.teacher_id = ? 
                           AND ste.enrollment_status = 'booked'
                           AND sts.slot_status NOT IN ('completed', 'cancelled')
                           AND sts.slot_date >= CURDATE()
                           LIMIT 1";
    $stmt = mysqli_prepare($conn, $active_booking_sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $active_booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($active_booking) {
        $booking_date = date('M d, Y', strtotime($active_booking['slot_date']));
        $booking_time = date('h:i A', strtotime($active_booking['start_time']));
        throw new Exception("You already have an active slot booking at {$active_booking['school_name']} on {$booking_date} at {$booking_time}. You can only book another slot after the current one is completed or cancelled.");
    }
    
    // Lock the slot row for update
    $slot_sql = "SELECT sts.*, s.school_name 
                 FROM school_teaching_slots sts
                 JOIN schools s ON sts.school_id = s.school_id
                 WHERE sts.slot_id = ? FOR UPDATE";
    $stmt = mysqli_prepare($conn, $slot_sql);
    mysqli_stmt_bind_param($stmt, "i", $slot_id);
    mysqli_stmt_execute($stmt);
    $slot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$slot) {
        throw new Exception("Slot not found");
    }
    
    // Validation 1: Check if slot is still available
    if ($slot['slot_status'] === 'full') {
        throw new Exception("This slot is already full");
    }
    
    if ($slot['slot_status'] === 'cancelled') {
        throw new Exception("This slot has been cancelled");
    }
    
    if ($slot['slot_status'] === 'completed') {
        throw new Exception("This slot has already been completed");
    }
    
    // Validation 2: Check if slot date is in the future
    if (strtotime($slot['slot_date']) < strtotime(date('Y-m-d'))) {
        throw new Exception("Cannot book slots in the past");
    }
    
    // Validation 3: Check if teacher is already booked for this slot
    $existing_sql = "SELECT enrollment_id FROM slot_teacher_enrollments 
                     WHERE slot_id = ? AND teacher_id = ? AND enrollment_status = 'booked'";
    $stmt = mysqli_prepare($conn, $existing_sql);
    mysqli_stmt_bind_param($stmt, "ii", $slot_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($existing) {
        throw new Exception("You have already booked this slot");
    }
    
    // Validation 4: Check for overlapping bookings on the same date/time
    $overlap_sql = "SELECT ste.enrollment_id, sts.slot_date, sts.start_time, sts.end_time, s.school_name
                    FROM slot_teacher_enrollments ste
                    JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                    JOIN schools s ON sts.school_id = s.school_id
                    WHERE ste.teacher_id = ? 
                    AND ste.enrollment_status = 'booked'
                    AND sts.slot_date = ?
                    AND ((sts.start_time <= ? AND sts.end_time > ?) 
                         OR (sts.start_time < ? AND sts.end_time >= ?)
                         OR (sts.start_time >= ? AND sts.end_time <= ?))";
    $stmt = mysqli_prepare($conn, $overlap_sql);
    mysqli_stmt_bind_param($stmt, "isssssss", 
        $teacher_id, 
        $slot['slot_date'],
        $slot['start_time'], $slot['start_time'],
        $slot['end_time'], $slot['end_time'],
        $slot['start_time'], $slot['end_time']
    );
    mysqli_stmt_execute($stmt);
    $overlap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($overlap) {
        throw new Exception("You have an overlapping booking at {$overlap['school_name']} on this date");
    }
    
    // Validation 5: Check if there's still room
    if ($slot['teachers_enrolled'] >= $slot['teachers_required']) {
        throw new Exception("This slot just became full. Please try another slot.");
    }
    
    // All validations passed - create the enrollment
    // Note: The trigger will automatically update teachers_enrolled and slot_status
    // And also create the teaching_sessions record
    $insert_sql = "INSERT INTO slot_teacher_enrollments (slot_id, teacher_id, enrollment_status, booked_at) 
                   VALUES (?, ?, 'booked', NOW())";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "ii", $slot_id, $teacher_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to book slot: " . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Redirect with success
    header("Location: browse_slots.php?booked=1");
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    header("Location: browse_slots.php?error=" . urlencode($e->getMessage()));
    exit;
}
