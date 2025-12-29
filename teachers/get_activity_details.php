<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config.php';

$teacher_id = $_SESSION['user_id'];
$submission_id = intval($_GET['id'] ?? 0);

if (!$submission_id) {
    echo json_encode(['error' => 'Invalid submission ID']);
    exit;
}

// Get submission details (only if owned by this teacher)
$sql = "SELECT tas.*, s.school_name, a.fname as admin_name
        FROM teaching_activity_submissions tas
        JOIN schools s ON tas.school_id = s.school_id
        LEFT JOIN admin a ON tas.verified_by = a.id
        WHERE tas.id = ? AND tas.teacher_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $submission_id, $teacher_id);
mysqli_stmt_execute($stmt);
$result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$result) {
    echo json_encode(['error' => 'Submission not found or access denied']);
    exit;
}

// Format dates for display
$result['activity_date'] = date('F d, Y', strtotime($result['activity_date']));
$result['upload_date'] = date('F d, Y H:i:s', strtotime($result['upload_date']));
$result['photo_taken_at'] = $result['photo_taken_at'] 
    ? date('F d, Y H:i:s', strtotime($result['photo_taken_at'])) 
    : null;
$result['verified_at'] = $result['verified_at'] 
    ? date('F d, Y H:i:s', strtotime($result['verified_at'])) 
    : null;

// Remove sensitive data
unset($result['exif_data']);
unset($result['teacher_id']);

echo json_encode($result);
?>
