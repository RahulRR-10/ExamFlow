<?php

/**
 * Groq AI Grader for Objective Exams
 * 
 * Uses Groq API to automatically grade student answers by comparing
 * them with teacher-provided answer keys. Supports per-question marks
 * allocation and partial credit scoring.
 * 
 * Answer Key Format (Teacher provides):
 * Q1. {question}
 * A1. {answer}
 * Q2. {question}
 * A2. {answer}
 * ...
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/../.env');

// Groq API Configuration - MUST be set in .env file
define('GROQ_API_KEY', env('GROQ_API_KEY', ''));
define('GROQ_MODEL', env('GROQ_MODEL', 'llama-3.3-70b-versatile'));
define('GROQ_API_URL', env('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions'));

// Warn if API key is not configured
if (empty(GROQ_API_KEY)) {
    error_log("WARNING: GROQ_API_KEY not configured in .env file");
}

/**
 * GroqGrader class for AI-based answer evaluation
 */
class GroqGrader
{
    private $conn;
    private $api_key;
    private $model;
    private $max_retries = 3;
    private $retry_delay = 2; // seconds

    public function __construct($conn, $api_key = null, $model = null)
    {
        $this->conn = $conn;
        $this->api_key = $api_key ?? GROQ_API_KEY;
        $this->model = $model ?? GROQ_MODEL;
    }

    /**
     * Grade a complete submission
     * 
     * @param int $submission_id Submission ID to grade
     * @return array Result with success status and details
     */
    public function gradeSubmission($submission_id)
    {
        $result = [
            'success' => false,
            'submission_id' => $submission_id,
            'questions_graded' => 0,
            'total_marks' => 0,
            'max_marks' => 0,
            'errors' => [],
            'grades' => []
        ];

        try {
            // Get submission details
            $submission = $this->getSubmissionDetails($submission_id);
            if (!$submission) {
                $result['errors'][] = "Submission not found: $submission_id";
                return $result;
            }

            // Check if exam uses AI grading
            if ($submission['grading_mode'] !== 'ai') {
                $result['errors'][] = "This exam uses manual grading mode";
                return $result;
            }

            // Check OCR status
            if ($submission['submission_status'] !== 'ocr_complete') {
                $result['errors'][] = "OCR processing not complete. Status: " . $submission['submission_status'];
                return $result;
            }

            // Update status to grading
            $this->updateSubmissionStatus($submission_id, 'grading');

            // Get OCR-extracted student answer text
            $student_text = $this->getCombinedOCRText($submission_id);
            if (empty($student_text)) {
                $result['errors'][] = "No OCR text available for grading";
                $this->updateSubmissionStatus($submission_id, 'error');
                return $result;
            }

            // Get exam questions with their max marks and answer keys
            $questions = $this->getExamQuestions($submission['exam_id']);
            if (empty($questions)) {
                $result['errors'][] = "No questions found for this exam";
                $this->updateSubmissionStatus($submission_id, 'error');
                return $result;
            }

            // Get exam-level answer key if available
            $exam_answer_key = $submission['answer_key_text'] ?? '';

            // Parse answer key into Q&A pairs if in Q1/A1 format
            $parsed_answers = $this->parseAnswerKey($exam_answer_key);

            // Grade each question
            $total_awarded = 0;
            $total_max = 0;

            foreach ($questions as $question) {
                $question_id = $question['question_id'];
                $question_num = $question['question_number'];
                $question_text = $question['question_text'];
                $max_marks = $question['max_marks'];

                // Get answer key for this question
                // Priority: 1) Per-question answer key, 2) Parsed from exam answer key
                $answer_key = $question['answer_key_text'];
                if (empty($answer_key) && isset($parsed_answers[$question_num])) {
                    $answer_key = $parsed_answers[$question_num];
                }

                if (empty($answer_key)) {
                    // No answer key - cannot grade this question
                    $result['errors'][] = "No answer key for question $question_num";
                    continue;
                }

                // Extract student's answer for this question from OCR text
                $student_answer = $this->extractStudentAnswer($student_text, $question_num, count($questions));

                // Call Groq API for grading
                $grade_result = $this->gradeQuestion(
                    $question_text,
                    $student_answer,
                    $answer_key,
                    $max_marks,
                    $question_num
                );

                if ($grade_result['success']) {
                    // Save grade to database
                    $this->saveGrade(
                        $submission_id,
                        $question_id,
                        $grade_result['marks'],
                        $grade_result['feedback'],
                        $grade_result['confidence']
                    );

                    $result['grades'][] = [
                        'question_id' => $question_id,
                        'question_number' => $question_num,
                        'marks_awarded' => $grade_result['marks'],
                        'max_marks' => $max_marks,
                        'feedback' => $grade_result['feedback'],
                        'confidence' => $grade_result['confidence']
                    ];

                    $total_awarded += $grade_result['marks'];
                    $result['questions_graded']++;
                } else {
                    $result['errors'][] = "Failed to grade Q$question_num: " . $grade_result['error'];
                }

                $total_max += $max_marks;

                // Small delay to avoid rate limiting
                usleep(500000); // 0.5 second
            }

            $result['total_marks'] = $total_awarded;
            $result['max_marks'] = $total_max;

            // Update submission with final results
            if ($result['questions_graded'] > 0) {
                $this->finalizeSubmission($submission_id, $total_awarded);
                $result['success'] = true;
            } else {
                $this->updateSubmissionStatus($submission_id, 'error');
            }
        } catch (Exception $e) {
            $result['errors'][] = "Exception: " . $e->getMessage();
            $this->updateSubmissionStatus($submission_id, 'error');
            error_log("GroqGrader Exception: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Grade a single question using Groq API
     */
    private function gradeQuestion($question_text, $student_answer, $answer_key, $max_marks, $question_num)
    {
        $result = [
            'success' => false,
            'marks' => 0,
            'feedback' => '',
            'confidence' => 0,
            'error' => ''
        ];

        // Build the grading prompt
        $prompt = $this->buildGradingPrompt($question_text, $student_answer, $answer_key, $max_marks, $question_num);

        // Call Groq API with retry logic
        for ($attempt = 1; $attempt <= $this->max_retries; $attempt++) {
            try {
                $response = $this->callGroqAPI($prompt);

                if ($response['success']) {
                    $parsed = $this->parseGradingResponse($response['content'], $max_marks);
                    if ($parsed['success']) {
                        $result['success'] = true;
                        $result['marks'] = $parsed['marks'];
                        $result['feedback'] = $parsed['feedback'];
                        $result['confidence'] = $parsed['confidence'];
                        return $result;
                    } else {
                        $result['error'] = "Failed to parse response: " . $parsed['error'];
                    }
                } else {
                    $result['error'] = $response['error'];
                }

                if ($attempt < $this->max_retries) {
                    sleep($this->retry_delay * $attempt);
                }
            } catch (Exception $e) {
                $result['error'] = $e->getMessage();
                if ($attempt < $this->max_retries) {
                    sleep($this->retry_delay * $attempt);
                }
            }
        }

        return $result;
    }

    /**
     * Build the grading prompt for Groq
     */
    private function buildGradingPrompt($question_text, $student_answer, $answer_key, $max_marks, $question_num)
    {
        $prompt = <<<PROMPT
You are an expert exam grader. Your task is to evaluate a student's answer and assign marks.

## Question {$question_num}:
{$question_text}

## Model Answer (Answer Key):
{$answer_key}

## Student's Answer (extracted from handwritten text via OCR):
{$student_answer}

## Grading Instructions:
- Maximum marks for this question: {$max_marks}
- Award marks based on semantic correctness, not just keyword matching
- Consider partial credit for partially correct answers
- Account for OCR errors - focus on meaning, not spelling
- Be fair but rigorous in evaluation
- If student answer is empty or completely irrelevant, award 0 marks

## Response Format:
Respond ONLY with a valid JSON object in this exact format:
{
  "marks": <number between 0 and {$max_marks}>,
  "feedback": "<brief constructive feedback for the student, 1-2 sentences>",
  "confidence": <number between 0 and 100 indicating your confidence in this grade>
}

Important: Return ONLY the JSON object, no additional text.
PROMPT;

        return $prompt;
    }

    /**
     * Call Groq API
     */
    private function callGroqAPI($prompt)
    {
        $result = [
            'success' => false,
            'content' => '',
            'error' => ''
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ];

        $data = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert exam grader. Always respond with valid JSON only.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'model' => $this->model,
            'temperature' => 0.3, // Lower temperature for more consistent grading
            'max_tokens' => 500,
            'stream' => false
        ];

        $ch = curl_init(GROQ_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $result['error'] = "cURL Error: $err";
            error_log("Groq API cURL Error: $err");
            return $result;
        }

        if ($http_code !== 200) {
            $result['error'] = "HTTP Error: $http_code";
            error_log("Groq API HTTP Error $http_code: $response");
            return $result;
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['choices'][0]['message']['content'])) {
            $result['success'] = true;
            $result['content'] = $response_data['choices'][0]['message']['content'];
        } else {
            $result['error'] = "Invalid API response structure";
            error_log("Groq API Invalid Response: " . print_r($response_data, true));
        }

        return $result;
    }

    /**
     * Parse the grading response from Groq
     */
    private function parseGradingResponse($content, $max_marks)
    {
        $result = [
            'success' => false,
            'marks' => 0,
            'feedback' => '',
            'confidence' => 0,
            'error' => ''
        ];

        // Clean up the response
        $content = trim($content);

        // Try to extract JSON from the response
        if (preg_match('/\{[^{}]*\}/s', $content, $matches)) {
            $json_str = $matches[0];
            $data = json_decode($json_str, true);

            if ($data && isset($data['marks'])) {
                $marks = floatval($data['marks']);

                // Validate marks
                if ($marks < 0) $marks = 0;
                if ($marks > $max_marks) $marks = $max_marks;

                $result['success'] = true;
                $result['marks'] = round($marks, 1);
                $result['feedback'] = $data['feedback'] ?? 'No feedback provided';
                $result['confidence'] = intval($data['confidence'] ?? 70);

                return $result;
            }
        }

        $result['error'] = "Could not parse JSON from response: " . substr($content, 0, 200);
        return $result;
    }

    /**
     * Parse teacher's answer key in Q1/A1 format
     */
    private function parseAnswerKey($answer_key_text)
    {
        $parsed = [];

        if (empty($answer_key_text)) {
            return $parsed;
        }

        // Pattern to match Q1. ... A1. ... format
        // Supports: Q1., Q1:, Q1), Question 1., etc.
        $pattern = '/(?:Q|Question)\s*(\d+)[.:\)]\s*(.*?)(?:A|Answer|Ans)\s*\1[.:\)]\s*(.*?)(?=(?:Q|Question)\s*\d+[.:\)]|$)/is';

        if (preg_match_all($pattern, $answer_key_text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $question_num = intval($match[1]);
                $answer = trim($match[3]);
                $parsed[$question_num] = $answer;
            }
        }

        // Alternative simpler pattern: A1. or Ans1. at line start
        if (empty($parsed)) {
            $lines = explode("\n", $answer_key_text);
            $current_answer = '';
            $current_num = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^(?:A|Ans|Answer)\s*(\d+)[.:\)]\s*(.*)/i', $line, $match)) {
                    if ($current_num > 0 && !empty($current_answer)) {
                        $parsed[$current_num] = trim($current_answer);
                    }
                    $current_num = intval($match[1]);
                    $current_answer = $match[2];
                } elseif ($current_num > 0 && !empty($line)) {
                    $current_answer .= ' ' . $line;
                }
            }

            if ($current_num > 0 && !empty($current_answer)) {
                $parsed[$current_num] = trim($current_answer);
            }
        }

        return $parsed;
    }

    /**
     * Extract student's answer for a specific question from OCR text
     */
    private function extractStudentAnswer($ocr_text, $question_num, $total_questions)
    {
        // Try to find answer marked with question number
        $patterns = [
            // Q1., A1., Ans1., Answer 1., 1., 1), (1), etc.
            '/(?:Q|A|Ans|Answer)?\s*' . $question_num . '[.:\)]\s*(.*?)(?=(?:Q|A|Ans|Answer)?\s*' . ($question_num + 1) . '[.:\)]|$)/is',
            // Just the number followed by content
            '/\b' . $question_num . '[.:\)]\s*(.*?)(?=\b' . ($question_num + 1) . '[.:\)]|$)/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $ocr_text, $match)) {
                $answer = trim($match[1]);
                if (strlen($answer) > 10) { // Minimum reasonable answer length
                    return $answer;
                }
            }
        }

        // If no specific answer found, divide text roughly by questions
        $text_length = strlen($ocr_text);
        $chunk_size = intval($text_length / max($total_questions, 1));
        $start = ($question_num - 1) * $chunk_size;
        $end = min($start + $chunk_size, $text_length);

        return substr($ocr_text, $start, $end - $start);
    }

    // ============================================
    // DATABASE HELPER METHODS
    // ============================================

    private function getSubmissionDetails($submission_id)
    {
        $sql = "SELECT os.*, oe.grading_mode, oe.answer_key_text
                FROM objective_submissions os
                JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                WHERE os.submission_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $submission_id);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    private function getExamQuestions($exam_id)
    {
        $sql = "SELECT * FROM objective_questions WHERE exam_id = ? ORDER BY question_number ASC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $exam_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $questions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $questions[] = $row;
        }
        return $questions;
    }

    private function getCombinedOCRText($submission_id)
    {
        $sql = "SELECT ocr_text, image_order FROM objective_answer_images 
                WHERE submission_id = ? AND ocr_status = 'completed'
                ORDER BY image_order ASC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $submission_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $combined = '';
        while ($row = mysqli_fetch_assoc($result)) {
            if (!empty($row['ocr_text'])) {
                $combined .= "\n--- Page " . $row['image_order'] . " ---\n";
                $combined .= $row['ocr_text'];
            }
        }

        return trim($combined);
    }

    private function updateSubmissionStatus($submission_id, $status)
    {
        $sql = "UPDATE objective_submissions SET submission_status = ? WHERE submission_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $status, $submission_id);
        mysqli_stmt_execute($stmt);
    }

    private function saveGrade($submission_id, $question_id, $marks, $feedback, $confidence)
    {
        // Get max_marks for this question
        $max_sql = "SELECT max_marks FROM objective_questions WHERE question_id = ?";
        $max_stmt = mysqli_prepare($this->conn, $max_sql);
        mysqli_stmt_bind_param($max_stmt, "i", $question_id);
        mysqli_stmt_execute($max_stmt);
        $max_result = mysqli_fetch_assoc(mysqli_stmt_get_result($max_stmt));
        $max_marks = $max_result ? $max_result['max_marks'] : 10;

        // Check if grade already exists
        $check_sql = "SELECT grade_id FROM objective_answer_grades WHERE submission_id = ? AND question_id = ?";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $submission_id, $question_id);
        mysqli_stmt_execute($check_stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

        if ($existing) {
            // Update existing grade
            $sql = "UPDATE objective_answer_grades 
                    SET ai_score = ?, ai_feedback = ?, ai_confidence = ?, final_score = ?, 
                        grading_method = 'ai', graded_at = NOW()
                    WHERE grade_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "dsddi", $marks, $feedback, $confidence, $marks, $existing['grade_id']);
        } else {
            // Insert new grade
            $sql = "INSERT INTO objective_answer_grades 
                    (submission_id, question_id, max_marks, ai_score, ai_feedback, ai_confidence, 
                     final_score, grading_method, graded_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'ai', NOW())";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "iiddsdd",
                $submission_id,
                $question_id,
                $max_marks,
                $marks,
                $feedback,
                $confidence,
                $marks
            );
        }

        mysqli_stmt_execute($stmt);
    }

    private function finalizeSubmission($submission_id, $total_marks)
    {
        $sql = "UPDATE objective_submissions 
                SET submission_status = 'graded', total_marks = ?, graded_at = NOW()
                WHERE submission_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "di", $total_marks, $submission_id);
        mysqli_stmt_execute($stmt);
    }
}

/**
 * Helper function to grade a single submission
 */
function gradeObjectiveSubmission($conn, $submission_id)
{
    $grader = new GroqGrader($conn);
    return $grader->gradeSubmission($submission_id);
}

/**
 * Process all pending AI grading submissions
 */
function processAllPendingAIGrading($conn, $limit = 10)
{
    // Find submissions that are ready for AI grading
    $sql = "SELECT os.submission_id 
            FROM objective_submissions os
            JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
            WHERE os.submission_status = 'ocr_complete' 
            AND oe.grading_mode = 'ai'
            ORDER BY os.submitted_at ASC
            LIMIT ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $results = [];
    $grader = new GroqGrader($conn);

    while ($row = mysqli_fetch_assoc($result)) {
        $grade_result = $grader->gradeSubmission($row['submission_id']);
        $results[] = $grade_result;

        // Log result
        if ($grade_result['success']) {
            error_log("AI Grading completed for submission {$row['submission_id']}: " .
                "{$grade_result['total_marks']}/{$grade_result['max_marks']} marks");
        } else {
            error_log("AI Grading failed for submission {$row['submission_id']}: " .
                implode(', ', $grade_result['errors']));
        }
    }

    return $results;
}
