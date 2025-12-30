<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/enrollment_utils.php';

$teacher_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;

if (!$action || !$school_id) {
    header("Location: school_management.php?error=Invalid request");
    exit;
}

// Verify school exists and is active
$school_check = mysqli_prepare($conn, "SELECT school_id, school_name FROM schools WHERE school_id = ? AND status = 'active'");
mysqli_stmt_bind_param($school_check, "i", $school_id);
mysqli_stmt_execute($school_check);
$school_result = mysqli_stmt_get_result($school_check);

if (mysqli_num_rows($school_result) == 0) {
    header("Location: school_management.php?error=School not found or inactive");
    exit;
}

$school = mysqli_fetch_assoc($school_result);

switch ($action) {
    case 'enroll':
        // Check if already enrolled
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM teacher_schools WHERE teacher_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($check_stmt, "ii", $teacher_id, $school_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            header("Location: school_management.php?error=You are already enrolled in this school");
            exit;
        }

        // Check if teacher has any schools (to set is_primary)
        $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM teacher_schools WHERE teacher_id = ?");
        mysqli_stmt_bind_param($count_stmt, "i", $teacher_id);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $count_row = mysqli_fetch_assoc($count_result);
        $is_primary = ($count_row['cnt'] == 0) ? 1 : 0; // First school becomes primary

        // Enroll teacher
        $enroll_stmt = mysqli_prepare($conn, "INSERT INTO teacher_schools (teacher_id, school_id, is_primary, enrolled_at) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($enroll_stmt, "iii", $teacher_id, $school_id, $is_primary);

        if (mysqli_stmt_execute($enroll_stmt)) {
            $msg = "Successfully enrolled in " . $school['school_name'];
            if ($is_primary) {
                $msg .= " (set as primary)";
            }
            header("Location: school_management.php?success=" . urlencode($msg));
        } else {
            header("Location: school_management.php?error=Failed to enroll. Please try again.");
        }
        break;

    case 'leave':
        // Check if enrolled
        $check_stmt = mysqli_prepare($conn, "SELECT id, is_primary FROM teacher_schools WHERE teacher_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($check_stmt, "ii", $teacher_id, $school_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) == 0) {
            header("Location: school_management.php?error=You are not enrolled in this school");
            exit;
        }

        // Check if teacher can unenroll (Phase 7: Controlled Unenrollment)
        $unenroll_check = canTeacherUnenroll($conn, $teacher_id, $school_id);
        if (!$unenroll_check['can_unenroll']) {
            $error_msg = "Cannot leave school: " . implode("; ", $unenroll_check['reasons']);
            header("Location: school_management.php?error=" . urlencode($error_msg));
            exit;
        }

        $enrollment = mysqli_fetch_assoc($check_result);
        $was_primary = $enrollment['is_primary'];

        // Remove from school
        $leave_stmt = mysqli_prepare($conn, "DELETE FROM teacher_schools WHERE teacher_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($leave_stmt, "ii", $teacher_id, $school_id);

        if (mysqli_stmt_execute($leave_stmt)) {
            // If this was the primary school, assign another school as primary
            if ($was_primary) {
                $reassign_stmt = mysqli_prepare($conn, "UPDATE teacher_schools SET is_primary = 1 WHERE teacher_id = ? ORDER BY enrolled_at ASC LIMIT 1");
                mysqli_stmt_bind_param($reassign_stmt, "i", $teacher_id);
                mysqli_stmt_execute($reassign_stmt);
            }
            header("Location: school_management.php?success=" . urlencode("Successfully left " . $school['school_name']));
        } else {
            header("Location: school_management.php?error=Failed to leave school. Please try again.");
        }
        break;

    case 'set_primary':
        // Check if enrolled
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM teacher_schools WHERE teacher_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($check_stmt, "ii", $teacher_id, $school_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) == 0) {
            header("Location: school_management.php?error=You are not enrolled in this school");
            exit;
        }

        // Remove primary from all teacher's schools
        $remove_primary_stmt = mysqli_prepare($conn, "UPDATE teacher_schools SET is_primary = 0 WHERE teacher_id = ?");
        mysqli_stmt_bind_param($remove_primary_stmt, "i", $teacher_id);
        mysqli_stmt_execute($remove_primary_stmt);

        // Set new primary
        $set_primary_stmt = mysqli_prepare($conn, "UPDATE teacher_schools SET is_primary = 1 WHERE teacher_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($set_primary_stmt, "ii", $teacher_id, $school_id);

        if (mysqli_stmt_execute($set_primary_stmt)) {
            header("Location: school_management.php?success=" . urlencode($school['school_name'] . " is now your primary school"));
        } else {
            header("Location: school_management.php?error=Failed to set primary school. Please try again.");
        }
        break;

    default:
        header("Location: school_management.php?error=Invalid action");
        break;
}
