<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config.php';
require_once '../utils/image_upload_handler.php';

$teacher_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$submission_id = intval($input['id'] ?? 0);

if (!$submission_id) {
    echo json_encode(['error' => 'Invalid submission ID']);
    exit;
}

// Delete submission
$handler = new ImageUploadHandler($conn);
$result = $handler->deleteSubmission($submission_id, $teacher_id);

echo json_encode($result);
?>
