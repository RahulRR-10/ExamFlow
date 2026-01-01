<?php
/**
 * Groq AI Analytics Helper
 * 
 * Uses Groq API to generate AI-powered insights for exam analytics.
 * Provides natural language summaries, performance insights, and recommendations.
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/../.env');

// Groq API Configuration
define('GROQ_ANALYTICS_API_KEY', env('GROQ_API_KEY', ''));
define('GROQ_ANALYTICS_MODEL', env('GROQ_MODEL', 'llama-3.3-70b-versatile'));
define('GROQ_ANALYTICS_API_URL', env('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions'));

/**
 * GroqAnalytics class for AI-based exam analytics
 */
class GroqAnalytics
{
    private $api_key;
    private $model;
    private $max_retries = 2;

    public function __construct($api_key = null, $model = null)
    {
        $this->api_key = $api_key ?? GROQ_ANALYTICS_API_KEY;
        $this->model = $model ?? GROQ_ANALYTICS_MODEL;
    }

    /**
     * Generate AI insights for exam analytics data
     * 
     * @param array $analytics_data Analytics data from simple_analytics.php
     * @return array AI-generated insights
     */
    public function generateInsights($analytics_data)
    {
        $result = [
            'success' => false,
            'summary' => '',
            'insights' => [],
            'recommendations' => [],
            'error' => ''
        ];

        if (empty($this->api_key)) {
            $result['error'] = 'GROQ_API_KEY not configured in .env file';
            return $result;
        }

        if (!isset($analytics_data['questions']) || empty($analytics_data['questions'])) {
            $result['error'] = 'No question data available for analysis';
            return $result;
        }

        // Build the analytics prompt
        $prompt = $this->buildAnalyticsPrompt($analytics_data);

        // Call Groq API
        for ($attempt = 1; $attempt <= $this->max_retries; $attempt++) {
            try {
                $response = $this->callGroqAPI($prompt);

                if ($response['success']) {
                    $parsed = $this->parseAnalyticsResponse($response['content']);
                    if ($parsed['success']) {
                        return $parsed;
                    } else {
                        $result['error'] = $parsed['error'];
                    }
                } else {
                    $result['error'] = $response['error'];
                }

                if ($attempt < $this->max_retries) {
                    sleep(1);
                }
            } catch (Exception $e) {
                $result['error'] = $e->getMessage();
                error_log("GroqAnalytics Exception: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Build the analytics prompt for Groq
     */
    private function buildAnalyticsPrompt($analytics_data)
    {
        $exam_name = $analytics_data['exam_name'] ?? 'Exam';
        $total_students = $analytics_data['total_students'] ?? 0;

        // Build question summary for the prompt
        $question_summaries = [];
        foreach ($analytics_data['questions'] as $q) {
            $summary = "Q{$q['question_id']}: \"{$q['question_text']}\" - {$q['correct_percentage']}% correct";
            
            // Add option distribution
            $options = [];
            foreach ($q['options'] as $opt) {
                $options[] = "{$opt['text']}: {$opt['percentage']}%";
            }
            $summary .= " | Options: " . implode(", ", $options);
            
            $question_summaries[] = $summary;
        }

        $questions_text = implode("\n", $question_summaries);

        $prompt = <<<PROMPT
You are an expert educational analyst. Analyze the following exam results and provide actionable insights.

## Exam: {$exam_name}
## Total Students: {$total_students}

## Question Performance Data:
{$questions_text}

## Your Analysis Task:
Provide a comprehensive analysis in valid JSON format with the following structure:

{
  "summary": "A 2-3 sentence overall summary of exam performance",
  "insights": [
    "Specific insight about student performance pattern 1",
    "Specific insight about student performance pattern 2",
    "Specific insight about question difficulty or common mistakes"
  ],
  "recommendations": [
    "Actionable recommendation for teachers to improve outcomes 1",
    "Topics or concepts that need re-teaching 2",
    "Suggested changes for future assessments 3"
  ],
  "difficulty_analysis": "Brief analysis of question difficulty distribution",
  "areas_of_concern": ["Topic 1 that students struggled with", "Topic 2"]
}

Guidelines:
- Focus on actionable, specific insights
- Identify patterns in wrong answers
- Suggest topics that may need re-teaching
- Be constructive and helpful

Return ONLY valid JSON, no additional text.
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
                    'content' => 'You are an expert educational analyst. Always respond with valid JSON only.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'model' => $this->model,
            'temperature' => 0.5,
            'max_tokens' => 1000,
            'stream' => false
        ];

        $ch = curl_init(GROQ_ANALYTICS_API_URL);
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
            error_log("Groq Analytics API cURL Error: $err");
            return $result;
        }

        if ($http_code !== 200) {
            $result['error'] = "HTTP Error: $http_code";
            error_log("Groq Analytics API HTTP Error $http_code: $response");
            return $result;
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['choices'][0]['message']['content'])) {
            $result['success'] = true;
            $result['content'] = $response_data['choices'][0]['message']['content'];
        } else {
            $result['error'] = "Invalid API response structure";
            error_log("Groq Analytics API Invalid Response: " . print_r($response_data, true));
        }

        return $result;
    }

    /**
     * Parse the analytics response from Groq
     */
    private function parseAnalyticsResponse($content)
    {
        $result = [
            'success' => false,
            'summary' => '',
            'insights' => [],
            'recommendations' => [],
            'difficulty_analysis' => '',
            'areas_of_concern' => [],
            'error' => ''
        ];

        // Clean up the response
        $content = trim($content);

        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json_str = $matches[0];
            $data = json_decode($json_str, true);

            if ($data) {
                $result['success'] = true;
                $result['summary'] = $data['summary'] ?? 'Analysis completed.';
                $result['insights'] = $data['insights'] ?? [];
                $result['recommendations'] = $data['recommendations'] ?? [];
                $result['difficulty_analysis'] = $data['difficulty_analysis'] ?? '';
                $result['areas_of_concern'] = $data['areas_of_concern'] ?? [];
                return $result;
            }
        }

        $result['error'] = "Could not parse JSON from response";
        return $result;
    }
}

/**
 * Helper function to get AI insights for analytics
 */
function getAIAnalyticsInsights($analytics_data)
{
    $analyzer = new GroqAnalytics();
    return $analyzer->generateInsights($analytics_data);
}
