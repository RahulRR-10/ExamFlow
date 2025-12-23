<?php

/**
 * Objective Exam Utility Functions
 * 
 * Helper functions for the Objective/Descriptive Answer Exam system.
 * This file should be included where objective exam functionality is needed.
 */

// ============================================
// AUTHORIZATION FUNCTIONS
// ============================================

/**
 * Check if a teacher owns a specific exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $teacher_id Teacher ID
 * @return bool True if teacher owns the exam
 */
function teacherOwnsExam($conn, $exam_id, $teacher_id)
{
    $sql = "SELECT exam_id FROM objective_exm_list WHERE exam_id = ? AND teacher_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

/**
 * Check if a teacher can access a submission (owns the exam)
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @param int $teacher_id Teacher ID
 * @return bool True if teacher can access the submission
 */
function teacherCanAccessSubmission($conn, $submission_id, $teacher_id)
{
    $sql = "SELECT os.submission_id 
            FROM objective_submissions os
            JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
            WHERE os.submission_id = ? AND oe.teacher_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $submission_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

/**
 * Check if a student belongs to a school that has the exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $student_id Student ID
 * @return bool True if student can access the exam
 */
function studentCanAccessExam($conn, $exam_id, $student_id)
{
    $sql = "SELECT oe.exam_id 
            FROM objective_exm_list oe
            JOIN student s ON oe.school_id = s.school_id
            WHERE oe.exam_id = ? AND s.id = ? AND oe.status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

/**
 * Check if a student owns a submission
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @param int $student_id Student ID
 * @return bool True if student owns the submission
 */
function studentOwnsSubmission($conn, $submission_id, $student_id)
{
    $sql = "SELECT submission_id FROM objective_submissions WHERE submission_id = ? AND student_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $submission_id, $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

/**
 * Require teacher to own an exam, otherwise redirect with error
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $teacher_id Teacher ID
 * @param string $redirect_url URL to redirect on failure
 */
function requireTeacherOwnsExam($conn, $exam_id, $teacher_id, $redirect_url = 'objective_exams.php')
{
    if (!teacherOwnsExam($conn, $exam_id, $teacher_id)) {
        header("Location: $redirect_url?error=" . urlencode("You don't have permission to access this exam."));
        exit;
    }
}

/**
 * Require teacher can access a submission, otherwise redirect with error
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @param int $teacher_id Teacher ID
 * @param string $redirect_url URL to redirect on failure
 */
function requireTeacherCanAccessSubmission($conn, $submission_id, $teacher_id, $redirect_url = 'objective_exams.php')
{
    if (!teacherCanAccessSubmission($conn, $submission_id, $teacher_id)) {
        header("Location: $redirect_url?error=" . urlencode("You don't have permission to access this submission."));
        exit;
    }
}

/**
 * Require student can access an exam, otherwise redirect with error
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $student_id Student ID
 * @param string $redirect_url URL to redirect on failure
 */
function requireStudentCanAccessExam($conn, $exam_id, $student_id, $redirect_url = 'objective_exams.php')
{
    if (!studentCanAccessExam($conn, $exam_id, $student_id)) {
        header("Location: $redirect_url?error=" . urlencode("You don't have access to this exam."));
        exit;
    }
}

/**
 * Get teacher-specific upload path for answer keys
 * Ensures each teacher's files are isolated
 * 
 * @param int $teacher_id Teacher ID
 * @param int $exam_id Exam ID (optional)
 * @return string Path to teacher's answer key directory
 */
function getTeacherAnswerKeyPath($teacher_id, $exam_id = null)
{
    $base_path = __DIR__ . '/../uploads/answer_keys/teacher_' . $teacher_id;
    if (!file_exists($base_path)) {
        mkdir($base_path, 0755, true);
    }
    if ($exam_id) {
        $exam_path = $base_path . '/exam_' . $exam_id;
        if (!file_exists($exam_path)) {
            mkdir($exam_path, 0755, true);
        }
        return $exam_path;
    }
    return $base_path;
}

/**
 * Get student-specific upload path for answer submissions
 * Ensures each student's files are isolated
 * 
 * @param int $student_id Student ID
 * @param int $exam_id Exam ID
 * @param int $submission_id Submission ID (optional)
 * @return string Path to student's answer directory
 */
function getStudentAnswerPath($student_id, $exam_id, $submission_id = null)
{
    $base_path = __DIR__ . '/../uploads/student_answers/exam_' . $exam_id . '/student_' . $student_id;
    if (!file_exists($base_path)) {
        mkdir($base_path, 0755, true);
    }
    if ($submission_id) {
        $submission_path = $base_path . '/submission_' . $submission_id;
        if (!file_exists($submission_path)) {
            mkdir($submission_path, 0755, true);
        }
        return $submission_path;
    }
    return $base_path;
}

/**
 * Validate that a file path belongs to the expected user
 * Prevents directory traversal attacks
 * 
 * @param string $file_path Path to validate
 * @param string $expected_base Expected base directory
 * @return bool True if path is valid
 */
function validateFilePath($file_path, $expected_base)
{
    $real_path = realpath($file_path);
    $real_base = realpath($expected_base);

    if ($real_path === false || $real_base === false) {
        return false;
    }

    return strpos($real_path, $real_base) === 0;
}

// ============================================
// DATA RETRIEVAL FUNCTIONS (WITH AUTH SUPPORT)
// ============================================

/**
 * Get all objective exams for a specific school
 * 
 * @param mysqli $conn Database connection
 * @param int $school_id School ID
 * @param string $status Filter by status (optional)
 * @return array Array of exams
 */
function getObjectiveExamsBySchool($conn, $school_id, $status = null)
{
    $sql = "SELECT oe.*, t.fname as teacher_name, s.school_name 
            FROM objective_exm_list oe
            LEFT JOIN teacher t ON oe.teacher_id = t.id
            LEFT JOIN schools s ON oe.school_id = s.school_id
            WHERE oe.school_id = ?";

    if ($status) {
        $sql .= " AND oe.status = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $school_id, $status);
    } else {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $school_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $exams = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $exams[] = $row;
    }

    return $exams;
}

/**
 * Get all objective exams created by a specific teacher
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @param int $school_id Filter by school (optional)
 * @return array Array of exams
 */
function getObjectiveExamsByTeacher($conn, $teacher_id, $school_id = null)
{
    $sql = "SELECT oe.*, s.school_name,
            (SELECT COUNT(*) FROM objective_questions WHERE exam_id = oe.exam_id) as question_count,
            (SELECT COUNT(*) FROM objective_submissions WHERE exam_id = oe.exam_id) as submission_count,
            (SELECT COUNT(*) FROM objective_submissions WHERE exam_id = oe.exam_id AND submission_status = 'graded') as graded_count
            FROM objective_exm_list oe
            LEFT JOIN schools s ON oe.school_id = s.school_id
            WHERE oe.teacher_id = ?";

    if ($school_id) {
        $sql .= " AND oe.school_id = ?";
        $sql .= " ORDER BY oe.created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
    } else {
        $sql .= " ORDER BY oe.created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $exams = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $exams[] = $row;
    }

    return $exams;
}

/**
 * Get a single objective exam by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $teacher_id Optional - if provided, verifies teacher owns exam
 * @return array|null Exam data or null if not found/not authorized
 */
function getObjectiveExamById($conn, $exam_id, $teacher_id = null)
{
    $sql = "SELECT oe.*, t.fname as teacher_name, s.school_name,
            (SELECT COUNT(*) FROM objective_questions WHERE exam_id = oe.exam_id) as question_count,
            (SELECT SUM(max_marks) FROM objective_questions WHERE exam_id = oe.exam_id) as total_question_marks
            FROM objective_exm_list oe
            LEFT JOIN teacher t ON oe.teacher_id = t.id
            LEFT JOIN schools s ON oe.school_id = s.school_id
            WHERE oe.exam_id = ?";

    // Add teacher ownership check if teacher_id is provided
    if ($teacher_id !== null) {
        $sql .= " AND oe.teacher_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $exam_id, $teacher_id);
    } else {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $exam_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

/**
 * Get questions for an objective exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @return array Array of questions
 */
function getObjectiveExamQuestions($conn, $exam_id)
{
    $sql = "SELECT * FROM objective_questions WHERE exam_id = ? ORDER BY question_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $exam_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $questions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $questions[] = $row;
    }

    return $questions;
}

/**
 * Check if a student has already submitted for an exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $student_id Student ID
 * @return array|null Submission data or null if not submitted
 */
function getStudentObjectiveSubmission($conn, $exam_id, $student_id)
{
    $sql = "SELECT os.*, 
            (SELECT COUNT(*) FROM objective_answer_images WHERE submission_id = os.submission_id) as image_count
            FROM objective_submissions os
            WHERE os.exam_id = ? AND os.student_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

/**
 * Get all submissions for an exam (for teacher view)
 * IMPORTANT: Always verify teacher owns the exam before calling this
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $teacher_id Teacher ID - required to verify ownership
 * @param string $status Filter by status (optional)
 * @return array Array of submissions (empty if teacher doesn't own exam)
 */
function getExamSubmissions($conn, $exam_id, $teacher_id, $status = null)
{
    // First verify teacher owns this exam
    if (!teacherOwnsExam($conn, $exam_id, $teacher_id)) {
        return []; // Return empty if not authorized
    }

    $sql = "SELECT os.*, st.fname as student_name, st.email as student_email,
            (SELECT COUNT(*) FROM objective_answer_images WHERE submission_id = os.submission_id) as image_count
            FROM objective_submissions os
            LEFT JOIN student st ON os.student_id = st.id
            WHERE os.exam_id = ?";

    if ($status) {
        $sql .= " AND os.submission_status = ?";
        $sql .= " ORDER BY os.submitted_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $exam_id, $status);
    } else {
        $sql .= " ORDER BY os.submitted_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $exam_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $submissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $submissions[] = $row;
    }

    return $submissions;
}

/**
 * Get uploaded images for a submission
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @return array Array of images
 */
function getSubmissionImages($conn, $submission_id)
{
    $sql = "SELECT * FROM objective_answer_images 
            WHERE submission_id = ? 
            ORDER BY image_order ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $submission_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $images = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = $row;
    }

    return $images;
}

/**
 * Get grades for a submission
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @return array Array of grades keyed by question_id
 */
function getSubmissionGrades($conn, $submission_id)
{
    $sql = "SELECT oag.*, oq.question_text, oq.question_number 
            FROM objective_answer_grades oag
            LEFT JOIN objective_questions oq ON oag.question_id = oq.question_id
            WHERE oag.submission_id = ?
            ORDER BY oq.question_number ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $submission_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $grades = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $grades[$row['question_id']] = $row;
    }

    return $grades;
}

/**
 * Check if exam is currently accepting submissions
 * 
 * @param array $exam Exam data
 * @return array ['can_submit' => bool, 'reason' => string]
 */
function canSubmitToExam($exam)
{
    $now = new DateTime();
    $exam_date = new DateTime($exam['exam_date']);
    $deadline = new DateTime($exam['submission_deadline']);

    if ($exam['status'] !== 'active') {
        return ['can_submit' => false, 'reason' => 'Exam is not active'];
    }

    if ($now < $exam_date) {
        return ['can_submit' => false, 'reason' => 'Exam has not started yet'];
    }

    if ($now > $deadline) {
        return ['can_submit' => false, 'reason' => 'Submission deadline has passed'];
    }

    return ['can_submit' => true, 'reason' => 'Submissions open'];
}

/**
 * Create a new objective exam
 * 
 * @param mysqli $conn Database connection
 * @param array $data Exam data
 * @return int|false Exam ID on success, false on failure
 */
function createObjectiveExam($conn, $data)
{
    $sql = "INSERT INTO objective_exm_list 
            (exam_name, school_id, teacher_id, grading_mode, total_marks, passing_marks, 
             exam_instructions, exam_date, submission_deadline, duration_minutes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "siisiiisssi",
        $data['exam_name'],
        $data['school_id'],
        $data['teacher_id'],
        $data['grading_mode'],
        $data['total_marks'],
        $data['passing_marks'],
        $data['exam_instructions'],
        $data['exam_date'],
        $data['submission_deadline'],
        $data['duration_minutes'],
        $data['status']
    );

    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($conn);
    }

    return false;
}

/**
 * Add a question to an objective exam
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param array $question Question data
 * @return int|false Question ID on success, false on failure
 */
function addObjectiveQuestion($conn, $exam_id, $question)
{
    $sql = "INSERT INTO objective_questions 
            (exam_id, question_number, question_text, max_marks, answer_key_text)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "iisis",
        $exam_id,
        $question['question_number'],
        $question['question_text'],
        $question['max_marks'],
        $question['answer_key_text']
    );

    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($conn);
    }

    return false;
}

/**
 * Create a submission record
 * 
 * @param mysqli $conn Database connection
 * @param int $exam_id Exam ID
 * @param int $student_id Student ID
 * @return int|false Submission ID on success, false on failure
 */
function createObjectiveSubmission($conn, $exam_id, $student_id)
{
    $sql = "INSERT INTO objective_submissions (exam_id, student_id, submission_status)
            VALUES (?, ?, 'pending')";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $student_id);

    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($conn);
    }

    return false;
}

/**
 * Add an answer image to a submission
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @param string $image_path Path to the image file
 * @param int $order Image order/sequence
 * @return int|false Image ID on success, false on failure
 */
function addAnswerImage($conn, $submission_id, $image_path, $order)
{
    $sql = "INSERT INTO objective_answer_images 
            (submission_id, image_path, image_order, ocr_status)
            VALUES (?, ?, ?, 'pending')";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isi", $submission_id, $image_path, $order);

    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($conn);
    }

    return false;
}

/**
 * Update submission status
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @param string $status New status
 * @return bool Success
 */
function updateSubmissionStatus($conn, $submission_id, $status)
{
    $sql = "UPDATE objective_submissions SET submission_status = ? WHERE submission_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $status, $submission_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Update OCR status for an image
 * 
 * @param mysqli $conn Database connection
 * @param int $image_id Image ID
 * @param string $status OCR status
 * @param string $ocr_text Extracted text (optional)
 * @param float $confidence OCR confidence score (optional)
 * @param string $error_message Error message if failed (optional)
 * @return bool Success
 */
function updateImageOCRStatus($conn, $image_id, $status, $ocr_text = null, $confidence = null, $error_message = null)
{
    $sql = "UPDATE objective_answer_images 
            SET ocr_status = ?, ocr_text = ?, ocr_confidence = ?, ocr_error_message = ?, processed_at = NOW()
            WHERE image_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssdsi", $status, $ocr_text, $confidence, $error_message, $image_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Save grade for a question
 * 
 * @param mysqli $conn Database connection
 * @param array $grade_data Grade data
 * @return int|false Grade ID on success, false on failure
 */
function saveQuestionGrade($conn, $grade_data)
{
    // Check if grade already exists
    $check_sql = "SELECT grade_id FROM objective_answer_grades 
                  WHERE submission_id = ? AND question_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $grade_data['submission_id'], $grade_data['question_id']);
    mysqli_stmt_execute($check_stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    if ($existing) {
        // Update existing grade
        $sql = "UPDATE objective_answer_grades SET
                extracted_answer = ?,
                marks_obtained = ?,
                max_marks = ?,
                ai_score = ?,
                ai_feedback = ?,
                ai_confidence = ?,
                manual_score = ?,
                manual_feedback = ?,
                final_score = ?,
                grading_method = ?,
                graded_at = NOW()
                WHERE grade_id = ?";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "sdddsdddssi",
            $grade_data['extracted_answer'],
            $grade_data['marks_obtained'],
            $grade_data['max_marks'],
            $grade_data['ai_score'],
            $grade_data['ai_feedback'],
            $grade_data['ai_confidence'],
            $grade_data['manual_score'],
            $grade_data['manual_feedback'],
            $grade_data['final_score'],
            $grade_data['grading_method'],
            $existing['grade_id']
        );

        if (mysqli_stmt_execute($stmt)) {
            return $existing['grade_id'];
        }
    } else {
        // Insert new grade
        $sql = "INSERT INTO objective_answer_grades 
                (submission_id, question_id, extracted_answer, marks_obtained, max_marks,
                 ai_score, ai_feedback, ai_confidence, manual_score, manual_feedback,
                 final_score, grading_method, graded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "iisdddsddsds",
            $grade_data['submission_id'],
            $grade_data['question_id'],
            $grade_data['extracted_answer'],
            $grade_data['marks_obtained'],
            $grade_data['max_marks'],
            $grade_data['ai_score'],
            $grade_data['ai_feedback'],
            $grade_data['ai_confidence'],
            $grade_data['manual_score'],
            $grade_data['manual_feedback'],
            $grade_data['final_score'],
            $grade_data['grading_method']
        );

        if (mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($conn);
        }
    }

    return false;
}

/**
 * Calculate and update total score for a submission
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @return bool Success
 */
function calculateSubmissionScore($conn, $submission_id)
{
    // Get submission and exam info
    $sql = "SELECT os.*, oe.total_marks, oe.passing_marks 
            FROM objective_submissions os
            JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
            WHERE os.submission_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $submission_id);
    mysqli_stmt_execute($stmt);
    $submission = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$submission) {
        return false;
    }

    // Calculate total score from grades
    $score_sql = "SELECT SUM(COALESCE(final_score, marks_obtained, 0)) as total_obtained,
                         SUM(max_marks) as total_max
                  FROM objective_answer_grades
                  WHERE submission_id = ?";
    $score_stmt = mysqli_prepare($conn, $score_sql);
    mysqli_stmt_bind_param($score_stmt, "i", $submission_id);
    mysqli_stmt_execute($score_stmt);
    $scores = mysqli_fetch_assoc(mysqli_stmt_get_result($score_stmt));

    $total_score = $scores['total_obtained'] ?? 0;
    $max_possible = $scores['total_max'] ?? $submission['total_marks'];

    // Calculate percentage based on exam total marks
    $percentage = ($max_possible > 0) ? ($total_score / $max_possible) * 100 : 0;
    $pass_status = ($percentage >= ($submission['passing_marks'] / $submission['total_marks'] * 100)) ? 'pass' : 'fail';

    // Update submission
    $update_sql = "UPDATE objective_submissions 
                   SET total_score = ?, percentage = ?, pass_status = ?,
                       submission_status = 'graded', graded_at = NOW()
                   WHERE submission_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ddsi", $total_score, $percentage, $pass_status, $submission_id);

    return mysqli_stmt_execute($update_stmt);
}

/**
 * Get pending OCR images count
 * 
 * @param mysqli $conn Database connection
 * @return int Count of pending images
 */
function getPendingOCRCount($conn)
{
    $sql = "SELECT COUNT(*) as count FROM objective_answer_images WHERE ocr_status = 'pending'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

/**
 * Get submissions pending grading for a teacher
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @return int Count of submissions pending grading
 */
function getPendingGradingCount($conn, $teacher_id)
{
    $sql = "SELECT COUNT(*) as count 
            FROM objective_submissions os
            JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
            WHERE oe.teacher_id = ? 
            AND os.submission_status IN ('ocr_complete', 'grading')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

/**
 * Get active objective exams count for a school
 * 
 * @param mysqli $conn Database connection
 * @param int $school_id School ID
 * @return int Count of active exams
 */
function getActiveObjectiveExamsCount($conn, $school_id)
{
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT COUNT(*) as count 
            FROM objective_exm_list 
            WHERE school_id = ? 
            AND status = 'active'
            AND submission_deadline >= ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $school_id, $now);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

/**
 * Validate file is an allowed image type
 * 
 * @param string $filename Filename
 * @param string $mime_type MIME type
 * @return bool Valid image
 */
function isValidImageFile($filename, $mime_type)
{
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    return in_array($extension, $allowed_extensions) && in_array($mime_type, $allowed_mimes);
}

/**
 * Validate file is an allowed document type (for answer keys)
 * 
 * @param string $filename Filename
 * @param string $mime_type MIME type
 * @return bool Valid document
 */
function isValidDocumentFile($filename, $mime_type)
{
    $allowed_extensions = ['pdf', 'txt', 'doc', 'docx'];
    $allowed_mimes = [
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    return in_array($extension, $allowed_extensions) && in_array($mime_type, $allowed_mimes);
}

/**
 * Generate safe filename for uploads
 * 
 * @param string $original_name Original filename
 * @param string $prefix Prefix for the filename
 * @return string Safe filename
 */
function generateSafeFilename($original_name, $prefix = '')
{
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $safe_name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    return $safe_name;
}

/**
 * Get combined OCR text from all images in a submission
 * 
 * @param mysqli $conn Database connection
 * @param int $submission_id Submission ID
 * @return string Combined OCR text
 */
function getCombinedOCRText($conn, $submission_id)
{
    $images = getSubmissionImages($conn, $submission_id);
    $combined_text = '';

    foreach ($images as $image) {
        if ($image['ocr_status'] === 'completed' && !empty($image['ocr_text'])) {
            $combined_text .= "\n--- Page " . $image['image_order'] . " ---\n";
            $combined_text .= $image['ocr_text'];
        }
    }

    return trim($combined_text);
}
