<?php

/**
 * AI Grading Queue Processor
 * 
 * Processes submissions that have completed OCR and are ready for AI grading.
 * Can be run via cron job or triggered manually.
 * 
 * Usage:
 *   CLI: php process_ai_grading.php [--limit=10] [--verbose]
 *   Web: process_ai_grading.php?key=YOUR_SECRET_KEY&limit=10
 * 
 * Recommended cron schedule: Every 2 minutes
 *   * /2 * * * * php /path/to/cron/process_ai_grading.php --limit=5
 */

// Configuration
define('AI_GRADING_SECRET_KEY', 'ai_grading_secret_2024'); // Change this!
define('DEFAULT_BATCH_LIMIT', 5);
define('MAX_EXECUTION_TIME', 300); // 5 minutes

// Increase execution time for grading
set_time_limit(MAX_EXECUTION_TIME);

// Determine if running from CLI or web
$is_cli = php_sapi_name() === 'cli';

// Parse arguments
$options = [
    'limit' => DEFAULT_BATCH_LIMIT,
    'verbose' => false,
    'submission_id' => null // For grading a specific submission
];

if ($is_cli) {
    $args = getopt('', ['limit:', 'verbose', 'submission:', 'help']);
    if (isset($args['help'])) {
        echo "AI Grading Queue Processor\n";
        echo "Usage: php process_ai_grading.php [options]\n\n";
        echo "Options:\n";
        echo "  --limit=N       Process up to N submissions (default: 5)\n";
        echo "  --submission=ID Grade a specific submission ID\n";
        echo "  --verbose       Show detailed output\n";
        echo "  --help          Show this help message\n";
        exit(0);
    }
    $options['limit'] = isset($args['limit']) ? intval($args['limit']) : DEFAULT_BATCH_LIMIT;
    $options['verbose'] = isset($args['verbose']);
    $options['submission_id'] = isset($args['submission']) ? intval($args['submission']) : null;
} else {
    // Web access - require secret key
    if (!isset($_GET['key']) || $_GET['key'] !== AI_GRADING_SECRET_KEY) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid or missing secret key']));
    }
    header('Content-Type: application/json');
    $options['limit'] = isset($_GET['limit']) ? intval($_GET['limit']) : DEFAULT_BATCH_LIMIT;
    $options['verbose'] = isset($_GET['verbose']);
    $options['submission_id'] = isset($_GET['submission']) ? intval($_GET['submission']) : null;
}

// Load dependencies
$base_path = dirname(__DIR__);
require_once $base_path . '/config.php';
require_once $base_path . '/utils/groq_grader.php';
require_once $base_path . '/utils/objective_exam_utils.php';

/**
 * Logger class for output
 */
class AIGradingLogger
{
    private $is_cli;
    private $verbose;
    private $log_messages = [];

    public function __construct($is_cli, $verbose)
    {
        $this->is_cli = $is_cli;
        $this->verbose = $verbose;
    }

    public function log($message, $level = 'info')
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[$timestamp] [$level] $message";

        $this->log_messages[] = ['level' => $level, 'message' => $message, 'time' => $timestamp];

        if ($this->is_cli) {
            $color = match ($level) {
                'error' => "\033[31m",
                'success' => "\033[32m",
                'warning' => "\033[33m",
                default => ""
            };
            $reset = $level !== 'info' ? "\033[0m" : "";
            echo "$color$formatted$reset\n";
        }

        // Always log to error_log for debugging
        if ($level === 'error' || $this->verbose) {
            error_log("AI_GRADING: $formatted");
        }
    }

    public function getMessages()
    {
        return $this->log_messages;
    }
}

/**
 * AI Grading Queue Processor class
 */
class AIGradingQueueProcessor
{
    private $conn;
    private $logger;
    private $grader;
    private $stats = [
        'started_at' => null,
        'finished_at' => null,
        'total_processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'total_marks_awarded' => 0,
        'details' => []
    ];

    public function __construct($conn, $logger)
    {
        $this->conn = $conn;
        $this->logger = $logger;
        $this->grader = new GroqGrader($conn);
    }

    /**
     * Get pending submissions for AI grading
     */
    public function getPendingSubmissions($limit)
    {
        $sql = "SELECT os.submission_id, os.exam_id, os.student_id, os.submitted_at,
                       oe.exam_name, st.fname as student_name
                FROM objective_submissions os
                JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                LEFT JOIN student st ON os.student_id = st.id
                WHERE os.submission_status = 'ocr_complete' 
                AND oe.grading_mode = 'ai'
                ORDER BY os.submitted_at ASC
                LIMIT ?";

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $submissions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $submissions[] = $row;
        }

        return $submissions;
    }

    /**
     * Get a specific submission
     */
    public function getSubmission($submission_id)
    {
        $sql = "SELECT os.submission_id, os.exam_id, os.student_id, os.submitted_at, os.submission_status,
                       oe.exam_name, oe.grading_mode, st.fname as student_name
                FROM objective_submissions os
                JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                LEFT JOIN student st ON os.student_id = st.id
                WHERE os.submission_id = ?";

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $submission_id);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    /**
     * Process a single submission
     */
    public function processSubmission($submission)
    {
        $submission_id = $submission['submission_id'];
        $this->logger->log("Processing submission #$submission_id - {$submission['exam_name']} by {$submission['student_name']}");

        $start_time = microtime(true);
        $result = $this->grader->gradeSubmission($submission_id);
        $duration = round(microtime(true) - $start_time, 2);

        if ($result['success']) {
            $this->stats['successful']++;
            $this->stats['total_marks_awarded'] += $result['total_marks'];
            $this->logger->log(
                "✓ Submission #$submission_id graded: {$result['total_marks']}/{$result['max_marks']} marks ({$result['questions_graded']} questions) in {$duration}s",
                'success'
            );
        } else {
            $this->stats['failed']++;
            $this->logger->log(
                "✗ Submission #$submission_id failed: " . implode('; ', $result['errors']),
                'error'
            );
        }

        $this->stats['details'][] = [
            'submission_id' => $submission_id,
            'exam_name' => $submission['exam_name'],
            'student_name' => $submission['student_name'],
            'success' => $result['success'],
            'marks' => $result['success'] ? "{$result['total_marks']}/{$result['max_marks']}" : 'N/A',
            'questions_graded' => $result['questions_graded'],
            'duration' => $duration,
            'errors' => $result['errors']
        ];

        $this->stats['total_processed']++;

        return $result['success'];
    }

    /**
     * Process batch of submissions
     */
    public function processBatch($limit)
    {
        $this->stats['started_at'] = date('Y-m-d H:i:s');
        $this->logger->log("Starting AI grading batch (limit: $limit)");

        $submissions = $this->getPendingSubmissions($limit);

        if (empty($submissions)) {
            $this->logger->log("No pending submissions found for AI grading");
            $this->stats['finished_at'] = date('Y-m-d H:i:s');
            return $this->stats;
        }

        $this->logger->log("Found " . count($submissions) . " pending submissions");

        foreach ($submissions as $submission) {
            $this->processSubmission($submission);
        }

        $this->stats['finished_at'] = date('Y-m-d H:i:s');

        $this->logger->log(
            "Batch complete: {$this->stats['successful']} successful, {$this->stats['failed']} failed",
            $this->stats['failed'] > 0 ? 'warning' : 'success'
        );

        return $this->stats;
    }

    /**
     * Process a specific submission by ID
     */
    public function processSpecificSubmission($submission_id)
    {
        $this->stats['started_at'] = date('Y-m-d H:i:s');

        $submission = $this->getSubmission($submission_id);

        if (!$submission) {
            $this->logger->log("Submission #$submission_id not found", 'error');
            $this->stats['finished_at'] = date('Y-m-d H:i:s');
            return $this->stats;
        }

        if ($submission['grading_mode'] !== 'ai') {
            $this->logger->log("Submission #$submission_id uses manual grading mode", 'warning');
            $this->stats['finished_at'] = date('Y-m-d H:i:s');
            return $this->stats;
        }

        if ($submission['submission_status'] !== 'ocr_complete') {
            $this->logger->log("Submission #$submission_id status is '{$submission['submission_status']}', not ready for grading", 'warning');
            // Allow forcing re-grading of already graded submissions
            if ($submission['submission_status'] !== 'graded') {
                $this->stats['finished_at'] = date('Y-m-d H:i:s');
                return $this->stats;
            }
            $this->logger->log("Forcing re-grade of submission #$submission_id", 'info');
        }

        $this->processSubmission($submission);

        $this->stats['finished_at'] = date('Y-m-d H:i:s');
        return $this->stats;
    }

    public function getStats()
    {
        return $this->stats;
    }
}

// Main execution
$logger = new AIGradingLogger($is_cli, $options['verbose']);
$processor = new AIGradingQueueProcessor($conn, $logger);

if ($options['submission_id']) {
    // Process specific submission
    $stats = $processor->processSpecificSubmission($options['submission_id']);
} else {
    // Process batch
    $stats = $processor->processBatch($options['limit']);
}

// Output results
if ($is_cli) {
    echo "\n=== AI Grading Summary ===\n";
    echo "Started: {$stats['started_at']}\n";
    echo "Finished: {$stats['finished_at']}\n";
    echo "Processed: {$stats['total_processed']}\n";
    echo "Successful: {$stats['successful']}\n";
    echo "Failed: {$stats['failed']}\n";
    echo "Total marks awarded: {$stats['total_marks_awarded']}\n";
} else {
    // JSON output for web
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'logs' => $logger->getMessages()
    ], JSON_PRETTY_PRINT);
}

mysqli_close($conn);
