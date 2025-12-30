<?php
include('config.php');

// Load environment variables
require_once __DIR__ . '/utils/env_loader.php';
loadEnv(__DIR__ . '/.env');

// Groq API configuration - MUST be set in .env file
$grok_api_key = env('GROQ_API_KEY', '');
$grok_model = env('GROQ_MODEL', 'llama-3.3-70b-versatile');

// Check if API key is configured
if (empty($grok_api_key)) {
    error_log("GROQ_API_KEY not configured in .env file");
}



// Function to create mock exams using Groq AI
function generateMockExams($original_exid, $exname, $description, $subject, $conn)
{
    global $grok_api_key, $grok_model;

    // Get current date and time for exam scheduling
    $current_date = date('Y-m-d H:i:s');
    // Set submission time to 7 days from now
    $submission_time = date('Y-m-d H:i:s', strtotime('+7 days'));

    // Insert two mock exam entries
    for ($i = 1; $i <= 2; $i++) {
        // Create a mock exam entry
        $mock_exam_name = "Mock Test $i: $exname";
        $mock_exam_desc = "Practice test $i for $exname. $description";

        $sql = "INSERT INTO mock_exm_list (original_exid, mock_number, exname, nq, desp, subt, extime, subject, status) 
                VALUES ('$original_exid', '$i', '$mock_exam_name', '5', '$mock_exam_desc', '$submission_time', '$current_date', '$subject', 'pending')";

        if (mysqli_query($conn, $sql)) {
            $mock_exid = mysqli_insert_id($conn);

            // Generate questions using Groq AI
            $prompt = "You are an AI assistant that creates high-quality multiple choice questions for educational exams. Create 5 multiple choice questions for a $subject exam on '$exname'. The exam is described as: '$description'. For each question, provide 4 options and indicate the correct answer. Format the response as a JSON array with each question having the following structure: {\"question\": \"...\", \"option1\": \"...\", \"option2\": \"...\", \"option3\": \"...\", \"option4\": \"...\", \"correct_answer\": \"option1/option2/option3/option4\"}";

            try {
                // Create Groq API request
                $request_url = "https://api.groq.com/openai/v1/chat/completions";

                $headers = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $grok_api_key
                ];

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

                error_log("Sending request to Groq API: $request_url");

                $ch = curl_init($request_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $response = curl_exec($ch);
                $err = curl_error($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);

                error_log("API Response code: $http_code");

                if ($err) {
                    error_log("cURL Error: " . $err);
                    // Update mock exam status to error
                    $update_sql = "UPDATE mock_exm_list SET status = 'error' WHERE mock_exid = '$mock_exid'";
                    mysqli_query($conn, $update_sql);
                } else {
                    $response_data = json_decode($response, true);

                    if (isset($response_data['choices'][0]['message']['content'])) {
                        $content = $response_data['choices'][0]['message']['content'];

                        // Extract JSON data from the response
                        preg_match('/\[.*?\]/s', $content, $matches);

                        if (!empty($matches)) {
                            $questions_json = $matches[0];
                            $questions = json_decode($questions_json, true);

                            if (is_array($questions)) {
                                // Insert questions into database
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
                                    mysqli_query($conn, $insert_sql);
                                }

                                // Update mock exam status to ready
                                $update_sql = "UPDATE mock_exm_list SET status = 'ready' WHERE mock_exid = '$mock_exid'";
                                mysqli_query($conn, $update_sql);
                            } else {
                                // Update mock exam status to error
                                $update_sql = "UPDATE mock_exm_list SET status = 'error' WHERE mock_exid = '$mock_exid'";
                                mysqli_query($conn, $update_sql);
                                error_log("Failed to parse questions JSON: " . $questions_json);
                            }
                        } else {
                            // Update mock exam status to error
                            $update_sql = "UPDATE mock_exm_list SET status = 'error' WHERE mock_exid = '$mock_exid'";
                            mysqli_query($conn, $update_sql);
                            error_log("No JSON found in the response: " . $content);
                        }
                    } else {
                        // Update mock exam status to error
                        $update_sql = "UPDATE mock_exm_list SET status = 'error' WHERE mock_exid = '$mock_exid'";
                        mysqli_query($conn, $update_sql);
                        error_log("Invalid response format: " . json_encode($response_data));
                    }
                }
            } catch (Exception $e) {
                // Update mock exam status to error
                $update_sql = "UPDATE mock_exm_list SET status = 'error' WHERE mock_exid = '$mock_exid'";
                mysqli_query($conn, $update_sql);
                error_log("Exception: " . $e->getMessage());
            }
        } else {
            error_log("Error creating mock exam: " . mysqli_error($conn));
        }
    }
}

// API endpoint for generating mock exams
// Only run this code if the file is being accessed directly, not when included in another file
$included_file = get_included_files();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($included_file) <= 1) {
    // Log incoming request for debugging
    error_log("Mock exam request received: " . json_encode($_POST));

    // Check if this is an API call with required parameters
    if (isset($_POST['exid']) && isset($_POST['exname']) && isset($_POST['desp']) && isset($_POST['subject'])) {
        $exid = $_POST['exid'];
        $exname = $_POST['exname'];
        $description = $_POST['desp'];
        $subject = $_POST['subject'];

        error_log("Generating mock exams for: $exname (ID: $exid)");
        generateMockExams($exid, $exname, $description, $subject, $conn);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Mock exams generation initiated']);
        exit;
    } else {
        error_log("Mock exam request missing required parameters: " . json_encode($_POST));
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        exit;
    }
}
