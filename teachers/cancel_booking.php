<?php
/**
 * Teacher Portal - Cancel a Slot Booking
 * Phase 3: AJAX/Form Handler for booking cancellation
 * Phase 8: Added time-based cancellation restrictions
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}

include '../config.php';
require_once '../utils/teaching_slots_compat.php';

$teacher_id = $_SESSION['user_id'];

// Cancellation deadline (hours before slot)
define('CANCELLATION_DEADLINE_HOURS', 24);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_slots.php?error=Invalid request");
    exit;
}

$enrollment_id = intval($_POST['enrollment_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if ($enrollment_id <= 0) {
    header("Location: my_slots.php?error=Invalid enrollment ID");
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get the enrollment and verify ownership
    $enrollment_sql = "SELECT ste.*, sts.slot_date, sts.slot_id, s.school_name
                       FROM slot_teacher_enrollments ste
                       JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                       JOIN schools s ON sts.school_id = s.school_id
                       WHERE ste.enrollment_id = ? AND ste.teacher_id = ? FOR UPDATE";
    $stmt = mysqli_prepare($conn, $enrollment_sql);
    mysqli_stmt_bind_param($stmt, "ii", $enrollment_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    $enrollment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$enrollment) {
        throw new Exception("Booking not found or you don't have permission to cancel it");
    }
    
    // Validation 1: Check if already cancelled
    if ($enrollment['enrollment_status'] !== 'booked') {
        throw new Exception("This booking is already {$enrollment['enrollment_status']}");
    }
    
    // Validation 2: Cannot cancel past bookings
    if (strtotime($enrollment['slot_date']) < strtotime(date('Y-m-d'))) {
        throw new Exception("Cannot cancel past bookings");
    }
    
    // Validation 3: Cannot cancel within 24 hours of slot start
    $slot_start = strtotime($enrollment['slot_date'] . ' ' . ($enrollment['start_time'] ?? '00:00:00'));
    $hours_until_slot = ($slot_start - time()) / 3600;
    
    if ($hours_until_slot > 0 && $hours_until_slot < CANCELLATION_DEADLINE_HOURS) {
        throw new Exception(
            "Cannot cancel within " . CANCELLATION_DEADLINE_HOURS . " hours of slot start. " .
            "Please contact the administrator for assistance."
        );
    }
    
    // Update enrollment status to cancelled
    // Note: The trigger will automatically update teachers_enrolled and slot_status
    $cancel_sql = "UPDATE slot_teacher_enrollments 
                   SET enrollment_status = 'cancelled', 
                       cancelled_at = NOW(), 
                       cancellation_reason = ?
                   WHERE enrollment_id = ?";
    $stmt = mysqli_prepare($conn, $cancel_sql);
    mysqli_stmt_bind_param($stmt, "si", $reason, $enrollment_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to cancel booking: " . mysqli_error($conn));
    }
    
    // Also update the teaching session if it exists
    $session_sql = "UPDATE teaching_sessions 
                    SET session_status = 'rejected', 
                        admin_remarks = 'Cancelled by teacher'
                    WHERE enrollment_id = ?";
    $stmt = mysqli_prepare($conn, $session_sql);
    mysqli_stmt_bind_param($stmt, "i", $enrollment_id);
    mysqli_stmt_execute($stmt); // Ignore errors - session might not exist
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Redirect with success
    header("Location: my_slots.php?cancelled=1");
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    header("Location: my_slots.php?error=" . urlencode($e->getMessage()));
    exit;
}
