<?php

/**
 * Submit Objective Exam
 * 
 * Handles answer sheet uploads, creates database records,
 * and queues images for OCR processing.
 */

session_start();
if (!isset($_SESSION["uname"])) {
    header("Location: ../login_student.php");
    exit;
}

include '../config.php';
require_once '../utils/objective_exam_utils.php';

$student_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'] ?? 1;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: objective_exams.php?error=" . urlencode("Invalid request method."));
    exit;
}

// Get exam ID
$exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;

if ($exam_id <= 0) {
    header("Location: objective_exams.php?error=" . urlencode("Invalid exam ID."));
    exit;
}

// Verify student can access this exam
if (!studentCanAccessExam($conn, $exam_id, $student_id)) {
    header("Location: objective_exams.php?error=" . urlencode("You don't have access to this exam."));
    exit;
}

// Get exam details
$exam = getObjectiveExamById($conn, $exam_id);
if (!$exam) {
    header("Location: objective_exams.php?error=" . urlencode("Exam not found."));
    exit;
}

// Check if exam is active
if ($exam['status'] !== 'active') {
    header("Location: objective_exams.php?error=" . urlencode("This exam is not currently active."));
    exit;
}

// Check deadline (with 5 minute grace period for uploads in progress)
if (strtotime($exam['submission_deadline']) + 300 < time()) {
    header("Location: objective_exams.php?error=" . urlencode("The submission deadline has passed."));
    exit;
}

// Check if student already submitted
$existing_submission = getStudentObjectiveSubmission($conn, $exam_id, $student_id);
if ($existing_submission) {
    header("Location: objective_exams.php?error=" . urlencode("You have already submitted this exam."));
    exit;
}

// Validate files
if (!isset($_FILES['answer_sheets']) || empty($_FILES['answer_sheets']['name'][0])) {
    header("Location: objective_exam_portal.php?exam_id=" . $exam_id . "&error=" . urlencode("Please upload at least one answer sheet."));
    exit;
}

$files = $_FILES['answer_sheets'];
$total_files = count($files['name']);
$max_files = 10;
$max_file_size = 10 * 1024 * 1024; // 10MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

// Validate file count
if ($total_files > $max_files) {
    header("Location: objective_exam_portal.php?exam_id=" . $exam_id . "&error=" . urlencode("Maximum $max_files files allowed."));
    exit;
}

// Validate each file
$valid_files = [];
for ($i = 0; $i < $total_files; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        continue;
    }

    $filename = $files['name'][$i];
    $filesize = $files['size'][$i];
    $filetype = $files['type'][$i];
    $tmp_name = $files['tmp_name'][$i];

    // Check file extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        header("Location: objective_exam_portal.php?exam_id=" . $exam_id . "&error=" . urlencode("File type .$ext not allowed."));
        exit;
    }

    // Check file size
    if ($filesize > $max_file_size) {
        header("Location: objective_exam_portal.php?exam_id=" . $exam_id . "&error=" . urlencode("$filename exceeds the 10MB size limit."));
        exit;
    }

    // Verify it's a valid image or PDF
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actual_type = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);

    if (!in_array($actual_type, $allowed_types)) {
        header("Location: objective_exam_portal.php?exam_id=" . $exam_id . "&error=" . urlencode("$filename is not a valid image or PDF file."));
        exit;
    }

    $valid_files[] = [
        'name' => $filename,
        'tmp_name' => $tmp_name,
        'type' => $actual_type,
        'ext' => $ext,
        'size' => $filesize
    ];
}

if (empty($valid_files)) {
    header("Location: objective_exam_portal.php?exam_id=" . $exam_id . "&error=" . urlencode("No valid files uploaded."));
    exit;
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Create submission record
    $submit_sql = "INSERT INTO objective_submissions (exam_id, student_id, submission_status, submitted_at) VALUES (?, ?, 'pending', NOW())";
    $submit_stmt = mysqli_prepare($conn, $submit_sql);
    mysqli_stmt_bind_param($submit_stmt, "ii", $exam_id, $student_id);

    if (!mysqli_stmt_execute($submit_stmt)) {
        throw new Exception("Failed to create submission record: " . mysqli_error($conn));
    }

    $submission_id = mysqli_insert_id($conn);

    // Create upload directory
    $upload_dir = getStudentAnswerPath($student_id, $exam_id, $submission_id);
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception("Failed to create upload directory.");
        }
    }

    // Process each file
    $image_order = 1;
    foreach ($valid_files as $file) {
        // Generate unique filename
        $new_filename = 'answer_' . $image_order . '_' . time() . '_' . uniqid() . '.' . $file['ext'];
        $destination = $upload_dir . '/' . $new_filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception("Failed to save file: " . $file['name']);
        }

        // Calculate relative path for database
        $relative_path = str_replace(__DIR__ . '/../', '', $destination);
        $relative_path = str_replace('\\', '/', $relative_path);

        // Insert image record
        $image_sql = "INSERT INTO objective_answer_images 
                      (submission_id, image_path, image_order, ocr_status, uploaded_at) 
                      VALUES (?, ?, ?, 'pending', NOW())";
        $image_stmt = mysqli_prepare($conn, $image_sql);
        mysqli_stmt_bind_param($image_stmt, "isi", $submission_id, $relative_path, $image_order);

        if (!mysqli_stmt_execute($image_stmt)) {
            throw new Exception("Failed to save image record: " . mysqli_error($conn));
        }

        $image_order++;
    }

    // Update submission status to trigger OCR processing
    $update_sql = "UPDATE objective_submissions SET submission_status = 'ocr_processing' WHERE submission_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $submission_id);
    mysqli_stmt_execute($update_stmt);

    // Commit transaction
    mysqli_commit($conn);

    // Log successful submission
    error_log("Objective exam submission successful: Student $student_id, Exam $exam_id, Submission $submission_id, Files: " . count($valid_files));

    // Redirect to the processing page which will handle OCR + AI grading
    // and then redirect to the results
    header("Location: ../process_submission.php?id=" . $submission_id . "&redirect=1");
    exit;
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);

    // Log error
    error_log("Objective exam submission failed: " . $e->getMessage());

    // Try to clean up any uploaded files
    if (isset($upload_dir) && is_dir($upload_dir)) {
        $files_in_dir = glob($upload_dir . '/*');
        foreach ($files_in_dir as $f) {
            @unlink($f);
        }
        @rmdir($upload_dir);
    }

    header("Location: objective_exam_portal.php?exam_id=" . $exam_id . "&error=" . urlencode("Submission failed: " . $e->getMessage()));
    exit;
}
