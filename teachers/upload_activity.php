<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config.php';
require_once '../utils/image_upload_handler.php';
require_once '../utils/rate_limiter.php';

$teacher_id = $_SESSION['user_id'];
$school_id = intval($_POST['school_id'] ?? 0);
$activity_date = $_POST['activity_date'] ?? '';

// Check rate limit first
$rateLimiter = new RateLimiter($conn);
$rateCheck = $rateLimiter->canUpload($teacher_id);
if (!$rateCheck['allowed']) {
    echo json_encode(['error' => $rateCheck['message']]);
    exit;
}

// Validate inputs
if (!$school_id) {
    echo json_encode(['error' => 'Please select a school']);
    exit;
}

if (!$activity_date) {
    echo json_encode(['error' => 'Please select an activity date']);
    exit;
}

// Validate date format and range
$date = DateTime::createFromFormat('Y-m-d', $activity_date);
if (!$date || $date->format('Y-m-d') !== $activity_date) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Check date is not in the future
if ($date > new DateTime()) {
    echo json_encode(['error' => 'Activity date cannot be in the future']);
    exit;
}

// Check file was uploaded
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['error' => 'Please select a photo to upload']);
    exit;
}

// Verify teacher is enrolled in this school
$check_sql = "SELECT 1 FROM teacher_schools WHERE teacher_id = ? AND school_id = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
mysqli_stmt_execute($stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
    echo json_encode(['error' => 'You are not enrolled in this school']);
    exit;
}
mysqli_stmt_close($stmt);

// Process upload
$handler = new ImageUploadHandler($conn);
$result = $handler->processUpload($_FILES['photo'], $teacher_id, $school_id, $activity_date);

if (isset($result['error'])) {
    echo json_encode(['error' => $result['error']]);
    exit;
}

// Save to database
$saveResult = $handler->saveSubmission($teacher_id, $school_id, $activity_date, $result);

if (isset($saveResult['error'])) {
    echo json_encode(['error' => $saveResult['error']]);
    exit;
}

// Record the upload for rate limiting
$rateLimiter->recordUpload($teacher_id);
$uploadStats = $rateLimiter->getUploadStats($teacher_id);

echo json_encode([
    'success' => true,
    'message' => 'Photo uploaded successfully! Your submission is pending verification.',
    'location_status' => $result['location_validation']['message'] ?? 'Location data not available',
    'submission_id' => $saveResult['submission_id'],
    'remaining_uploads' => $uploadStats['remaining']
]);
?>
