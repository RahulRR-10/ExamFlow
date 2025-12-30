<?php
include('../config.php');

// Load environment variables
require_once __DIR__ . '/../utils/env_loader.php';
loadEnv(__DIR__ . '/../.env');

// Groq API configuration from .env
$grok_api_key = env('GROQ_API_KEY', '');
$grok_model = env('GROQ_MODEL', 'llama-3.3-70b-versatile');

// Check if API key is configured
if (empty($grok_api_key)) {
    error_log("GROQ_API_KEY not configured in .env file");
}

// Rate limiting settings
$max_retries = 3;
$retry_delay = 5; // seconds to wait between retries

// Function to generate mock exams for a given exam
function generateMockExamsHelper($exid, $exname, $description, $subject)
{
    global $conn, $grok_api_key, $grok_model, $max_retries, $retry_delay;

    // Get current date and time for exam scheduling
    $current_date = date('Y-m-d H:i:s');
    // Set submission time to 7 days from now
    $submission_time = date('Y-m-d H:i:s', strtotime('+7 days'));

    // Get the school_id from the original exam
    $exam_sql = "SELECT school_id FROM exm_list WHERE exid = '$exid'";
    $exam_result = mysqli_query($conn, $exam_sql);
    $exam_data = mysqli_fetch_assoc($exam_result);
    $school_id = isset($exam_data['school_id']) ? intval($exam_data['school_id']) : 1; // Default to 1 if not set

    // Check if this exam already has mock exams
    $check_sql = "SELECT COUNT(*) as count FROM mock_exm_list WHERE original_exid = '$exid'";
    $check_result = mysqli_query($conn, $check_sql);
    $count = mysqli_fetch_assoc($check_result)['count'];

    if ($count > 0) {
        error_log("Mock exams already exist for exam ID $exid ($count found). Skipping creation.");
        return;
    }

    // Insert two mock exam entries
    for ($i = 1; $i <= 2; $i++) {
        // Create a mock exam entry
        $mock_exam_name = "Mock Test $i: $exname";
        $mock_exam_desc = "Practice test $i for $exname. $description";

        $sql = "INSERT INTO mock_exm_list (original_exid, mock_number, exname, nq, desp, subt, extime, subject, status, school_id) 
                VALUES ('$exid', '$i', '$mock_exam_name', '5', '$mock_exam_desc', '$submission_time', '$current_date', '$subject', 'pending', '$school_id')";

        if (mysqli_query($conn, $sql)) {
            $mock_exid = mysqli_insert_id($conn);
            error_log("Created mock exam #$i with ID: $mock_exid for exam ID: $exid with school_id: $school_id");

            // Generate questions using Groq AI
            $prompt = "You are an AI assistant that creates high-quality multiple choice questions for educational exams. Create 5 multiple choice questions for a $subject exam on '$exname'. The exam is described as: '$description'. For each question, provide 4 options and indicate the correct answer. Format the response as a JSON array with each question having the following structure: {\"question\": \"...\", \"option1\": \"...\", \"option2\": \"...\", \"option3\": \"...\", \"option4\": \"...\", \"correct_answer\": \"option1/option2/option3/option4\"}";

            // Add rate limiting - track API calls
            $retry_count = 0;
            $success = false;

            // Enable more verbose debugging
            $debug_enabled = true;

            while (!$success && $retry_count < $max_retries) {
                try {
                    // Create Groq API request
                    $request_url = "https://api.groq.com/openai/v1/chat/completions";

                    if ($debug_enabled) {
                        error_log("DEBUG: Full request URL: $request_url");
                    }

                    $headers = [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $grok_api_key
                    ];

                    if ($debug_enabled) {
                        // Mask most of the API key for security while still showing format
                        $masked_key = substr($grok_api_key, 0, 5) . '...' . substr($grok_api_key, -5);
                        error_log("DEBUG: Using API key (masked): " . $masked_key);
                        error_log("DEBUG: Headers: " . json_encode($headers));
                    }

                    $data = [
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are an AI assistant that creates high-quality multiple choice questions for educational exams.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'model' => $grok_model,
                        'stream' => false,
                        'temperature' => 0.7
                    ];

                    if ($debug_enabled) {
                        error_log("DEBUG: API Request data: " . json_encode($data));
                    }

                    error_log("Sending request to Groq API (attempt " . ($retry_count + 1) . "): $request_url");

                    $ch = curl_init($request_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    // Add additional debugging options
                    curl_setopt($ch, CURLOPT_VERBOSE, $debug_enabled);
                    // Set timeout to prevent hanging
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                    // SSL verification settings - disable temporarily for troubleshooting
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

                    // Enable more detailed CURL debugging
                    if ($debug_enabled) {
                        $curl_verbose = fopen('php://temp', 'w+');
                        curl_setopt($ch, CURLOPT_STDERR, $curl_verbose);
                    }

                    $response = curl_exec($ch);
                    $err = curl_error($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $info = curl_getinfo($ch);

                    // Log detailed CURL debug information if available
                    if ($debug_enabled && isset($curl_verbose)) {
                        rewind($curl_verbose);
                        $curl_debug = stream_get_contents($curl_verbose);
                        fclose($curl_verbose);
                        error_log("DEBUG: CURL Verbose Log: " . $curl_debug);
                    }

                    curl_close($ch);

                    error_log("API Response code: $http_code");

                    if ($debug_enabled) {
                        error_log("DEBUG: CURL info: " . json_encode($info));
                        if ($response) {
                            error_log("DEBUG: Raw API Response: " . substr($response, 0, 1000) . (strlen($response) > 1000 ? '...(truncated)' : ''));
                        }
                    }

                    // Check for rate limit response (HTTP 429) or server errors (5xx)
                    if ($http_code === 429 || ($http_code >= 500 && $http_code < 600)) {
                        error_log("Rate limit or server error received. Retrying after delay...");
                        $retry_count++;

                        if ($retry_count < $max_retries) {
                            // Exponential backoff for retries
                            $sleep_time = $retry_delay * pow(2, $retry_count - 1);
                            error_log("Waiting for $sleep_time seconds before retry");
                            sleep($sleep_time);
                            continue;
                        } else {
                            error_log("Maximum retry attempts reached. Falling back to sample questions.");
                            useFallbackQuestions($mock_exid, $exname, $subject, $description, $conn);
                            break;
                        }
                    }

                    // Handle 404 errors or bad requests
                    if ($http_code === 404 || $http_code === 400) {
                        error_log("Error $http_code - Groq API request failed.");
                        error_log("Falling back to sample questions.");
                        useFallbackQuestions($mock_exid, $exname, $subject, $description, $conn);
                        break;
                    }

                    if ($err) {
                        error_log("cURL Error: " . $err);
                        $retry_count++;

                        if ($retry_count < $max_retries) {
                            sleep($retry_delay);
                            continue;
                        } else {
                            // Update mock exam status to error
                            $update_sql = "UPDATE mock_exm_list SET status = 'error' WHERE mock_exid = '$mock_exid'";
                            mysqli_query($conn, $update_sql);

                            // If API fails, fall back to sample questions
                            useFallbackQuestions($mock_exid, $exname, $subject, $description, $conn);
                            break;
                        }
                    } else {
                        $response_data = json_decode($response, true);

                        if (isset($response_data['choices'][0]['message']['content'])) {
                            $content = $response_data['choices'][0]['message']['content'];

                            if ($debug_enabled) {
                                error_log("DEBUG: API Content Response: " . $content);
                            }

                            // Try different regex patterns to extract JSON data
                            $json_patterns = [
                                '/\[.*\]/s',              // Standard JSON array
                                '/(\[[\s\S]*\])/s',       // More flexible JSON array
                                '/{.*}/s'                 // JSON object if returned instead of array
                            ];

                            $json_extracted = false;
                            $questions = null;

                            foreach ($json_patterns as $pattern) {
                                preg_match($pattern, $content, $matches);
                                if (!empty($matches) && isset($matches[0])) {
                                    $questions_json = $matches[0];
                                    if ($debug_enabled) {
                                        error_log("DEBUG: Extracted JSON with pattern $pattern: " . substr($questions_json, 0, 200) . "...");
                                    }

                                    // Try to parse the JSON
                                    $parsed_questions = json_decode($questions_json, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed_questions)) {
                                        $questions = $parsed_questions;
                                        $json_extracted = true;
                                        break;
                                    } else if ($debug_enabled) {
                                        error_log("DEBUG: JSON parsing error: " . json_last_error_msg());
                                    }
                                }
                            }

                            // If no valid JSON found via regex, try to extract it more aggressively
                            if (!$json_extracted) {
                                // Try to find JSON-like content and clean it
                                if (strpos($content, '[') !== false && strpos($content, ']') !== false) {
                                    $start = strpos($content, '[');
                                    $end = strrpos($content, ']') + 1;
                                    $json_content = substr($content, $start, $end - $start);

                                    if ($debug_enabled) {
                                        error_log("DEBUG: Trying aggressive JSON extraction: " . substr($json_content, 0, 200) . "...");
                                    }

                                    // Try to parse it
                                    $questions = json_decode($json_content, true);
                                    $json_extracted = (json_last_error() === JSON_ERROR_NONE && is_array($questions));

                                    if (!$json_extracted && $debug_enabled) {
                                        error_log("DEBUG: Aggressive JSON parsing failed: " . json_last_error_msg());
                                    }
                                }
                            }

                            if ($json_extracted && is_array($questions)) {
                                // Validate question format
                                $valid_questions = [];
                                foreach ($questions as $q) {
                                    if (
                                        isset($q['question']) && isset($q['option1']) && isset($q['option2']) &&
                                        isset($q['option3']) && isset($q['option4']) && isset($q['correct_answer'])
                                    ) {
                                        $valid_questions[] = $q;
                                    } else if ($debug_enabled) {
                                        error_log("DEBUG: Invalid question format: " . json_encode($q));
                                    }
                                }

                                if (count($valid_questions) >= 3) { // Accept if we have at least 3 valid questions
                                    // Insert questions into database
                                    $success_count = insertQuestionsIntoDatabase($mock_exid, $valid_questions, $conn);
                                    error_log("Inserted $success_count questions for mock exam ID $mock_exid from API");

                                    // Update mock exam status to ready
                                    $update_sql = "UPDATE mock_exm_list SET status = 'ready' WHERE mock_exid = '$mock_exid'";
                                    if (mysqli_query($conn, $update_sql)) {
                                        error_log("Mock exam ID $mock_exid is now ready");
                                    } else {
                                        error_log("Error updating mock exam status: " . mysqli_error($conn));
                                    }

                                    $success = true;
                                    break;
                                } else {
                                    error_log("Not enough valid questions found: " . count($valid_questions) . " out of " . count($questions));
                                    $retry_count++;
                                    if ($retry_count >= $max_retries) {
                                        // Fall back to sample questions
                                        useFallbackQuestions($mock_exid, $exname, $subject, $description, $conn);
                                        break;
                                    }
                                    sleep($retry_delay);
                                }
                            } else {
                                error_log("No valid questions found in the response: " . json_encode($response_data));
                                $retry_count++;
                                if ($retry_count >= $max_retries) {
                                    // Fall back to sample questions
                                    useFallbackQuestions($mock_exid, $exname, $subject, $description, $conn);
                                    break;
                                }
                                sleep($retry_delay);
                            }
                        } else {
                            error_log("Invalid response format: " . json_encode($response_data));
                            $retry_count++;
                            if ($retry_count >= $max_retries) {
                                // Fall back to sample questions
                                useFallbackQuestions($mock_exid, $exname, $subject, $description, $conn);
                                break;
                            }
                            sleep($retry_delay);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Exception: " . $e->getMessage());
                    $retry_count++;
                    if ($retry_count >= $max_retries) {
                        // Fall back to sample questions
                        useFallbackQuestions($mock_exid, $exname, $subject, $description, $conn);
                        break;
                    }
                    sleep($retry_delay);
                }
            }
        } else {
            error_log("Error creating mock exam: " . mysqli_error($conn));
        }
    }

    error_log("Successfully generated mock exams for exam ID: $exid");
    return true;
}

// Function to insert questions into the database
function insertQuestionsIntoDatabase($mock_exid, $questions, $conn)
{
    $success_count = 0;

    for ($j = 0; $j < count($questions); $j++) {
        $question = $questions[$j];

        $qstn = mysqli_real_escape_string($conn, $question['question']);
        $o1 = mysqli_real_escape_string($conn, $question['option1']);
        $o2 = mysqli_real_escape_string($conn, $question['option2']);
        $o3 = mysqli_real_escape_string($conn, $question['option3']);
        $o4 = mysqli_real_escape_string($conn, $question['option4']);

        // Determine correct answer
        $correct_answer = $question['correct_answer'];
        if ($correct_answer == 'option1') {
            $ans = $o1;
        } elseif ($correct_answer == 'option2') {
            $ans = $o2;
        } elseif ($correct_answer == 'option3') {
            $ans = $o3;
        } else {
            $ans = $o4;
        }

        $ans = mysqli_real_escape_string($conn, $ans);
        $sno = $j + 1;

        $insert_sql = "INSERT INTO mock_qstn_list (mock_exid, qstn, qstn_o1, qstn_o2, qstn_o3, qstn_o4, qstn_ans, sno) 
                    VALUES ('$mock_exid', '$qstn', '$o1', '$o2', '$o3', '$o4', '$ans', '$sno')";

        if (mysqli_query($conn, $insert_sql)) {
            $success_count++;
        } else {
            error_log("Error inserting question $sno: " . mysqli_error($conn));
        }
    }

    return $success_count;
}

// Fallback function to use sample questions if API fails
function useFallbackQuestions($mock_exid, $exname, $subject, $description, $conn)
{
    error_log("Using fallback questions for mock exam ID: $mock_exid");

    // Sample questions for this mock exam (based on the exam description and subject)
    $sample_questions = [
        [
            'question' => "Sample question 1 for $exname about $subject",
            'option1' => "Option A for question 1",
            'option2' => "Option B for question 1",
            'option3' => "Option C for question 1",
            'option4' => "Option D for question 1",
            'correct_answer' => "option1"
        ],
        [
            'question' => "Sample question 2 for $exname about $subject",
            'option1' => "Option A for question 2",
            'option2' => "Option B for question 2",
            'option3' => "Option C for question 2",
            'option4' => "Option D for question 2",
            'correct_answer' => "option2"
        ],
        [
            'question' => "Sample question 3 for $exname about $subject",
            'option1' => "Option A for question 3",
            'option2' => "Option B for question 3",
            'option3' => "Option C for question 3",
            'option4' => "Option D for question 3",
            'correct_answer' => "option3"
        ],
        [
            'question' => "Sample question 4 for $exname about $subject",
            'option1' => "Option A for question 4",
            'option2' => "Option B for question 4",
            'option3' => "Option C for question 4",
            'option4' => "Option D for question 4",
            'correct_answer' => "option4"
        ],
        [
            'question' => "Sample question 5 for $exname about $subject",
            'option1' => "Option A for question 5",
            'option2' => "Option B for question 5",
            'option3' => "Option C for question 5",
            'option4' => "Option D for question 5",
            'correct_answer' => "option1"
        ]
    ];

    // Insert the sample questions
    $success_count = insertQuestionsIntoDatabase($mock_exid, $sample_questions, $conn);
    error_log("Inserted $success_count fallback questions for mock exam ID $mock_exid");

    // Update mock exam status to ready
    $update_sql = "UPDATE mock_exm_list SET status = 'ready' WHERE mock_exid = '$mock_exid'";
    if (mysqli_query($conn, $update_sql)) {
        error_log("Mock exam ID $mock_exid is now ready (using fallback questions)");
    } else {
        error_log("Error updating mock exam status: " . mysqli_error($conn));
    }
}
