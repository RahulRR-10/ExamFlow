<?php

/**
 * OCR Queue Processor
 * 
 * Processes pending OCR jobs in the background.
 * Can be run as a cron job or triggered manually.
 * 
 * Usage:
 *   php process_ocr_queue.php              # Process all pending
 *   php process_ocr_queue.php --limit=10   # Process max 10 items
 *   php process_ocr_queue.php --status     # Show queue status
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_ACCESS')) {
    // Allow web access with proper authentication for manual triggering
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    }
}

// Configuration
define('BATCH_SIZE', 10); // Default number of images to process per run
define('MAX_RETRIES', 3); // Maximum retry attempts for failed OCR
define('LOCK_TIMEOUT', 300); // Lock timeout in seconds (5 minutes)

// Include dependencies
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/ocr_processor.php';
require_once __DIR__ . '/../utils/objective_exam_utils.php';

/**
 * Main processor class
 */
class OCRQueueProcessor
{

    private $conn;
    private $ocr;
    private $processed_count = 0;
    private $error_count = 0;
    private $start_time;
    private $log_messages = [];

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->ocr = new OCRProcessor();
        $this->start_time = microtime(true);
    }

    /**
     * Log a message
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $log_line = "[$timestamp] [$level] $message";
        $this->log_messages[] = $log_line;

        if (php_sapi_name() === 'cli') {
            echo $log_line . PHP_EOL;
        }
    }

    /**
     * Get queue status
     */
    public function getStatus()
    {
        $status = [];

        // Count by status
        $sql = "SELECT ocr_status, COUNT(*) as count 
                FROM objective_answer_images 
                GROUP BY ocr_status";
        $result = mysqli_query($this->conn, $sql);

        while ($row = mysqli_fetch_assoc($result)) {
            $status[$row['ocr_status']] = $row['count'];
        }

        // Check OCR availability
        $ocr_status = $this->ocr->checkStatus();
        $status['ocr_available'] = $ocr_status['installed'];
        $status['tesseract_version'] = $ocr_status['version'];

        return $status;
    }

    /**
     * Get pending images to process
     */
    private function getPendingImages($limit)
    {
        $sql = "SELECT oai.*, os.exam_id, os.student_id
                FROM objective_answer_images oai
                JOIN objective_submissions os ON oai.submission_id = os.submission_id
                WHERE oai.ocr_status IN ('pending', 'failed')
                ORDER BY oai.uploaded_at ASC
                LIMIT ?";

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $images = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $images[] = $row;
        }

        return $images;
    }

    /**
     * Mark image as processing
     */
    private function markAsProcessing($image_id)
    {
        $sql = "UPDATE objective_answer_images 
                SET ocr_status = 'processing' 
                WHERE image_id = ? AND ocr_status IN ('pending', 'failed')";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $image_id);
        return mysqli_stmt_execute($stmt) && mysqli_affected_rows($this->conn) > 0;
    }

    /**
     * Process a single image
     */
    private function processImage($image_data)
    {
        $image_id = $image_data['image_id'];
        $image_path = __DIR__ . '/../' . $image_data['image_path'];

        $this->log("Processing image ID: $image_id");

        // Check if file exists
        if (!file_exists($image_path)) {
            $this->updateImageStatus($image_id, 'failed', null, 0, 'Image file not found');
            $this->log("Image file not found: $image_path", 'ERROR');
            return false;
        }

        // Perform OCR
        $result = $this->ocr->extractText($image_path, [
            'language' => 'eng',
            'psm' => 3,
            'preprocess' => true
        ]);

        if ($result['success']) {
            $this->updateImageStatus(
                $image_id,
                'completed',
                $result['text'],
                $result['confidence'],
                null
            );
            $this->log("OCR completed for image $image_id (confidence: {$result['confidence']}%)");
            $this->processed_count++;

            // Check if all images for this submission are processed
            $this->checkSubmissionComplete($image_data['submission_id']);

            return true;
        } else {
            $this->updateImageStatus($image_id, 'failed', null, 0, $result['error']);
            $this->log("OCR failed for image $image_id: {$result['error']}", 'ERROR');
            $this->error_count++;
            return false;
        }
    }

    /**
     * Update image OCR status
     */
    private function updateImageStatus($image_id, $status, $text, $confidence, $error)
    {
        $sql = "UPDATE objective_answer_images 
                SET ocr_status = ?, 
                    ocr_text = ?, 
                    ocr_confidence = ?,
                    ocr_error_message = ?,
                    processed_at = NOW()
                WHERE image_id = ?";

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssdsi", $status, $text, $confidence, $error, $image_id);
        return mysqli_stmt_execute($stmt);
    }

    /**
     * Check if all images for a submission are processed
     * If so, update submission status
     */
    private function checkSubmissionComplete($submission_id)
    {
        // Count pending/processing images
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN ocr_status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN ocr_status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM objective_answer_images 
                WHERE submission_id = ?";

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $submission_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        $total = $result['total'];
        $completed = $result['completed'];
        $failed = $result['failed'];

        if ($completed + $failed >= $total) {
            // All images processed
            if ($failed == 0) {
                // All successful - move to ocr_complete
                $new_status = 'ocr_complete';
                $this->log("Submission $submission_id: All images processed successfully");
            } else {
                // Some failed - still mark as ocr_complete but with note
                $new_status = 'ocr_complete';
                $this->log("Submission $submission_id: Completed with $failed failed images", 'WARN');
            }

            $update_sql = "UPDATE objective_submissions 
                           SET submission_status = ?, ocr_completed_at = NOW()
                           WHERE submission_id = ?";
            $update_stmt = mysqli_prepare($this->conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $new_status, $submission_id);
            mysqli_stmt_execute($update_stmt);

            // Trigger AI grading if applicable
            $this->triggerAIGradingIfNeeded($submission_id);
        }
    }

    /**
     * Trigger AI grading if exam is set to AI mode
     */
    private function triggerAIGradingIfNeeded($submission_id)
    {
        $sql = "SELECT oe.grading_mode 
                FROM objective_submissions os
                JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                WHERE os.submission_id = ?";

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $submission_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($result && $result['grading_mode'] === 'ai') {
            $this->log("Submission $submission_id ready for AI grading, attempting immediate grading...");

            // Try to grade immediately if groq_grader is available
            $grader_path = dirname(__DIR__) . '/utils/groq_grader.php';
            if (file_exists($grader_path)) {
                require_once $grader_path;

                try {
                    $grader = new GroqGrader($this->conn);
                    $grade_result = $grader->gradeSubmission($submission_id);

                    if ($grade_result['success']) {
                        $this->log("AI grading complete for submission $submission_id: {$grade_result['total_marks']}/{$grade_result['max_marks']} marks");
                    } else {
                        $this->log("AI grading failed for submission $submission_id: " . implode(', ', $grade_result['errors']), 'warning');
                    }
                } catch (Exception $e) {
                    $this->log("AI grading exception for submission $submission_id: " . $e->getMessage(), 'error');
                }
            } else {
                $this->log("Groq grader not found, submission $submission_id left in ocr_complete status for later processing");
            }
        }
    }

    /**
     * Process the queue
     */
    public function process($limit = BATCH_SIZE)
    {
        $this->log("Starting OCR queue processing (limit: $limit)");

        // Check OCR availability
        $ocr_status = $this->ocr->checkStatus();
        if (!$ocr_status['installed']) {
            $this->log("Tesseract OCR not available: " . $ocr_status['error'], 'ERROR');
            return false;
        }

        $this->log("Tesseract version: " . $ocr_status['version']);

        // Get pending images
        $images = $this->getPendingImages($limit);
        $total = count($images);

        if ($total === 0) {
            $this->log("No pending images to process");
            return true;
        }

        $this->log("Found $total images to process");

        // Process each image
        foreach ($images as $image) {
            // Try to acquire lock
            if (!$this->markAsProcessing($image['image_id'])) {
                $this->log("Could not acquire lock for image {$image['image_id']}", 'WARN');
                continue;
            }

            $this->processImage($image);
        }

        // Summary
        $elapsed = round(microtime(true) - $this->start_time, 2);
        $this->log("Processing complete. Processed: {$this->processed_count}, Errors: {$this->error_count}, Time: {$elapsed}s");

        return true;
    }

    /**
     * Get log messages
     */
    public function getLogMessages()
    {
        return $this->log_messages;
    }

    /**
     * Get processing stats
     */
    public function getStats()
    {
        return [
            'processed' => $this->processed_count,
            'errors' => $this->error_count,
            'elapsed_time' => round(microtime(true) - $this->start_time, 2)
        ];
    }
}

// ============================================
// CLI Execution
// ============================================

if (php_sapi_name() === 'cli') {
    // Parse command line arguments
    $options = getopt('', ['limit:', 'status', 'help']);

    if (isset($options['help'])) {
        echo "OCR Queue Processor\n";
        echo "Usage: php process_ocr_queue.php [options]\n\n";
        echo "Options:\n";
        echo "  --limit=N    Process maximum N images (default: " . BATCH_SIZE . ")\n";
        echo "  --status     Show queue status and exit\n";
        echo "  --help       Show this help message\n";
        exit(0);
    }

    $processor = new OCRQueueProcessor($conn);

    if (isset($options['status'])) {
        $status = $processor->getStatus();
        echo "OCR Queue Status:\n";
        echo "================\n";
        echo "Tesseract Available: " . ($status['ocr_available'] ? 'Yes' : 'No') . "\n";
        if ($status['tesseract_version']) {
            echo "Tesseract Version: " . $status['tesseract_version'] . "\n";
        }
        echo "\nQueue:\n";
        foreach (['pending', 'processing', 'completed', 'failed'] as $s) {
            echo "  $s: " . ($status[$s] ?? 0) . "\n";
        }
        exit(0);
    }

    $limit = isset($options['limit']) ? intval($options['limit']) : BATCH_SIZE;
    $processor->process($limit);
}

// ============================================
// Web Trigger (for manual execution)
// ============================================

if (php_sapi_name() !== 'cli' && defined('ALLOW_WEB_ACCESS')) {
    header('Content-Type: application/json');

    $processor = new OCRQueueProcessor($conn);

    if (isset($_GET['status'])) {
        echo json_encode($processor->getStatus());
    } else {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : BATCH_SIZE;
        $processor->process($limit);
        echo json_encode([
            'stats' => $processor->getStats(),
            'log' => $processor->getLogMessages()
        ]);
    }
}
