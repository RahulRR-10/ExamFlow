<?php
/**
 * Process Objective Exam Submission
 * 
 * Immediately processes OCR and AI grading for a specific submission.
 * Can be called after submission or manually via URL.
 * 
 * Usage: process_submission.php?id=SUBMISSION_ID
 */

session_start();

// Allow access from logged-in teachers or students, or via CLI
$is_cli = php_sapi_name() === 'cli';
if (!$is_cli && !isset($_SESSION['user_id']) && !isset($_SESSION['uname'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied. Please log in.');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes max

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/ocr_processor.php';
require_once __DIR__ . '/utils/groq_grader.php';
require_once __DIR__ . '/utils/objective_exam_utils.php';

// Get submission ID
if ($is_cli) {
    $submission_id = isset($argv[1]) ? intval($argv[1]) : 0;
    $auto_redirect = false;
} else {
    $submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $auto_redirect = isset($_GET['redirect']) && $_GET['redirect'] == '1';
}

if ($submission_id <= 0) {
    if ($auto_redirect) {
        header("Location: students/objective_exams.php?error=" . urlencode("Invalid submission ID"));
        exit;
    }
    outputError("Invalid submission ID");
    exit;
}

// For auto-redirect mode, suppress HTML output
$silent_mode = $auto_redirect;

function outputMessage($msg, $type = 'info') {
    global $silent_mode;
    if ($silent_mode) return;
    
    $colors = ['info' => 'blue', 'success' => 'green', 'error' => 'red', 'warning' => 'orange'];
    $color = $colors[$type] ?? 'black';
    
    if (php_sapi_name() === 'cli') {
        echo "[$type] $msg\n";
    } else {
        echo "<p style='color:$color'>[$type] $msg</p>";
    }
    flush();
}

function outputError($msg) {
    outputMessage($msg, 'error');
}

// Start HTML output for web access (only if not in silent mode)
if (!$is_cli && !$silent_mode) {
    echo "<!DOCTYPE html><html><head><title>Processing Submission #$submission_id</title></head><body>";
    echo "<h2>Processing Objective Exam Submission #$submission_id</h2>";
}

// Get submission details
$sql = "SELECT os.*, oe.exam_name, oe.grading_mode, oe.answer_key_text 
        FROM objective_submissions os
        JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
        WHERE os.submission_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $submission_id);
mysqli_stmt_execute($stmt);
$submission = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$submission) {
    outputError("Submission not found: $submission_id");
    exit;
}

outputMessage("Exam: " . $submission['exam_name']);
outputMessage("Current Status: " . $submission['submission_status']);
outputMessage("Grading Mode: " . $submission['grading_mode']);

// ============================================
// STEP 1: OCR PROCESSING
// ============================================
outputMessage("\n=== STEP 1: OCR Processing ===", 'info');

// Get images for this submission
$images_sql = "SELECT * FROM objective_answer_images WHERE submission_id = ? ORDER BY image_order";
$img_stmt = mysqli_prepare($conn, $images_sql);
mysqli_stmt_bind_param($img_stmt, "i", $submission_id);
mysqli_stmt_execute($img_stmt);
$images_result = mysqli_stmt_get_result($img_stmt);

$image_count = mysqli_num_rows($images_result);
outputMessage("Found $image_count answer sheet image(s)");

if ($image_count == 0) {
    outputError("No images found for this submission!");
    exit;
}

// Initialize OCR
$ocr = new OCRProcessor();
$status = $ocr->checkStatus();

if (!$status['installed']) {
    outputError("Tesseract OCR is not installed or not found!");
    outputMessage("Path checked: " . ($status['path'] ?? 'none'));
    outputMessage("Error: " . ($status['error'] ?? 'unknown'));
    exit;
}

outputMessage("Tesseract Version: " . $status['version'], 'success');

$all_ocr_text = '';
$ocr_success_count = 0;

while ($image = mysqli_fetch_assoc($images_result)) {
    $image_id = $image['image_id'];
    $db_path = $image['image_path'];
    
    // Handle both relative and absolute paths
    if (strpos($db_path, ':') !== false || strpos($db_path, '/') === 0) {
        // Absolute path
        $image_path = $db_path;
    } else {
        // Relative path
        $image_path = __DIR__ . '/' . $db_path;
    }
    
    // Normalize path separators
    $image_path = str_replace(['\\', '//'], '/', $image_path);
    
    outputMessage("Processing image #$image_id: " . basename($db_path));
    
    if (!file_exists($image_path)) {
        outputError("Image file not found: $image_path");
        continue;
    }
    
    // Run OCR
    $ocr_result = $ocr->extractText($image_path);
    
    if ($ocr_result['success']) {
        $extracted_text = $ocr_result['text'];
        $confidence = $ocr_result['confidence'] ?? 0;
        
        outputMessage("✓ OCR successful (confidence: " . round($confidence, 1) . "%)", 'success');
        outputMessage("Extracted " . strlen($extracted_text) . " characters");
        
        // Update image record
        $update_img = "UPDATE objective_answer_images 
                       SET ocr_text = ?, ocr_status = 'completed', 
                           ocr_confidence = ?, processed_at = NOW()
                       WHERE image_id = ?";
        $upd_stmt = mysqli_prepare($conn, $update_img);
        mysqli_stmt_bind_param($upd_stmt, "sdi", $extracted_text, $confidence, $image_id);
        mysqli_stmt_execute($upd_stmt);
        
        $all_ocr_text .= "\n--- Page " . $image['image_order'] . " ---\n" . $extracted_text . "\n";
        $ocr_success_count++;
    } else {
        outputError("OCR failed: " . ($ocr_result['error'] ?? 'Unknown error'));
        
        // Update image as failed
        $update_img = "UPDATE objective_answer_images 
                       SET ocr_status = 'failed', ocr_error_message = ?
                       WHERE image_id = ?";
        $upd_stmt = mysqli_prepare($conn, $update_img);
        $error_msg = $ocr_result['error'] ?? 'Unknown error';
        mysqli_stmt_bind_param($upd_stmt, "si", $error_msg, $image_id);
        mysqli_stmt_execute($upd_stmt);
    }
}

if ($ocr_success_count == 0) {
    outputError("All OCR processing failed!");
    
    // Update submission status to error
    $upd_sub = "UPDATE objective_submissions SET submission_status = 'error' WHERE submission_id = ?";
    $stmt = mysqli_prepare($conn, $upd_sub);
    mysqli_stmt_bind_param($stmt, "i", $submission_id);
    mysqli_stmt_execute($stmt);
    exit;
}

outputMessage("OCR completed for $ocr_success_count/$image_count images", 'success');

// Update submission status to OCR complete
$upd_sub = "UPDATE objective_submissions 
            SET submission_status = 'ocr_complete', ocr_completed_at = NOW() 
            WHERE submission_id = ?";
$stmt = mysqli_prepare($conn, $upd_sub);
mysqli_stmt_bind_param($stmt, "i", $submission_id);
mysqli_stmt_execute($stmt);

// ============================================
// STEP 2: AI GRADING (if enabled)
// ============================================
if ($submission['grading_mode'] === 'ai') {
    outputMessage("\n=== STEP 2: AI Grading ===", 'info');
    
    // Get questions for this exam
    $q_sql = "SELECT * FROM objective_questions WHERE exam_id = ? ORDER BY question_number";
    $q_stmt = mysqli_prepare($conn, $q_sql);
    mysqli_stmt_bind_param($q_stmt, "i", $submission['exam_id']);
    mysqli_stmt_execute($q_stmt);
    $questions_result = mysqli_stmt_get_result($q_stmt);
    $question_count = mysqli_num_rows($questions_result);
    
    outputMessage("Found $question_count questions to grade");
    
    if ($question_count == 0) {
        outputError("No questions found for this exam! Cannot grade.");
    } else {
        // Initialize grader
        $grader = new GroqGrader($conn);
        
        // Don't update status here - the grader will do it
        // The submission should be in 'ocr_complete' status
        
        // Grade the submission
        outputMessage("Calling Groq AI for grading...");
        $grade_result = $grader->gradeSubmission($submission_id);
        
        if ($grade_result['success']) {
            outputMessage("✓ AI Grading completed!", 'success');
            outputMessage("Total Score: " . $grade_result['total_marks'] . "/" . $grade_result['max_marks']);
            outputMessage("Questions Graded: " . $grade_result['questions_graded']);
            
            // Show individual question scores
            if (!empty($grade_result['grades'])) {
                outputMessage("\nQuestion Scores:");
                foreach ($grade_result['grades'] as $qid => $grade) {
                    $score = $grade['score'] ?? $grade['final_score'] ?? $grade['awarded'] ?? 0;
                    $max = $grade['max_marks'] ?? $grade['max'] ?? 0;
                    $feedback = $grade['feedback'] ?? $grade['ai_feedback'] ?? 'No feedback';
                    outputMessage("  Q$qid: $score/$max - $feedback");
                }
            }
        } else {
            outputError("AI Grading failed!");
            foreach ($grade_result['errors'] as $error) {
                outputError("  - $error");
            }
        }
    }
} else {
    outputMessage("\n=== Manual Grading Mode ===", 'info');
    outputMessage("This exam uses manual grading. Teacher must grade manually.");
    
    // Update status to OCR complete (ready for manual grading)
    $upd_sub = "UPDATE objective_submissions SET submission_status = 'ocr_complete' WHERE submission_id = ?";
    $stmt = mysqli_prepare($conn, $upd_sub);
    mysqli_stmt_bind_param($stmt, "i", $submission_id);
    mysqli_stmt_execute($stmt);
}

// ============================================
// FINAL SUMMARY
// ============================================
outputMessage("\n=== Processing Complete ===", 'success');

// Get final status
$final_sql = "SELECT * FROM objective_submissions WHERE submission_id = ?";
$stmt = mysqli_prepare($conn, $final_sql);
mysqli_stmt_bind_param($stmt, "i", $submission_id);
mysqli_stmt_execute($stmt);
$final_sub = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

outputMessage("Final Status: " . $final_sub['submission_status']);
if ($final_sub['total_marks'] !== null) {
    outputMessage("Final Score: " . $final_sub['total_marks']);
}

// Handle auto-redirect after processing
if ($auto_redirect) {
    $score_msg = "";
    if ($final_sub['total_marks'] !== null) {
        $score_msg = " Your score: " . number_format($final_sub['total_marks'], 1) . " marks.";
    }
    
    if ($final_sub['submission_status'] === 'graded') {
        header("Location: students/objective_exams.php?success=" . urlencode("Your exam has been submitted and graded!" . $score_msg));
    } else {
        header("Location: students/objective_exams.php?success=" . urlencode("Your exam has been submitted successfully! Status: " . ucfirst(str_replace('_', ' ', $final_sub['submission_status']))));
    }
    exit;
}

if (!$is_cli) {
    echo "<hr>";
    echo "<p><a href='teachers/grade_objective.php?id=$submission_id&exam_id=" . $submission['exam_id'] . "'>View in Grading Interface</a></p>";
    echo "<p><a href='students/objective_results.php?id=$submission_id'>View Student Results</a></p>";
    echo "</body></html>";
}
?>
