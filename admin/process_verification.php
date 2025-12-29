<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$submission_id = intval($_POST['submission_id'] ?? 0);
$action = $_POST['action'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

// Validate inputs
if (!$submission_id) {
    header("Location: pending_verifications.php?error=invalid_id");
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    header("Location: verify_submission.php?id=$submission_id&error=invalid_action");
    exit;
}

// Check submission exists and is pending
$check_sql = "SELECT id, teacher_id, school_id, verification_status 
              FROM teaching_activity_submissions 
              WHERE id = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "i", $submission_id);
mysqli_stmt_execute($stmt);
$submission = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$submission) {
    header("Location: pending_verifications.php?error=not_found");
    exit;
}

if ($submission['verification_status'] !== 'pending') {
    header("Location: verify_submission.php?id=$submission_id&error=already_verified");
    exit;
}

// Set status
$status = $action === 'approve' ? 'approved' : 'rejected';
$admin_id = $_SESSION['admin_id'];

// Update submission
$update_sql = "UPDATE teaching_activity_submissions
               SET verification_status = ?,
                   verified_by = ?,
                   verified_at = NOW(),
                   admin_remarks = ?
               WHERE id = ? AND verification_status = 'pending'";

$stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($stmt, "sisi", $status, $admin_id, $remarks, $submission_id);

if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    
    // Log admin action
    $action_details = "Submission #$submission_id " . ($action === 'approve' ? 'approved' : 'rejected');
    if ($remarks) {
        $action_details .= ". Remarks: " . substr($remarks, 0, 100);
    }
    logAdminAction($conn, $admin_id, 'verify_' . $action, 'teaching_activity_submissions', $submission_id, $action_details);

    // Redirect with success message
    header("Location: pending_verifications.php?success=" . $action);
} else {
    mysqli_stmt_close($stmt);
    header("Location: verify_submission.php?id=$submission_id&error=failed");
}
exit;
?>
