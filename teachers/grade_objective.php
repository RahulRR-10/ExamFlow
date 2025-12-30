<?php

/**
 * Grade Objective Exam - Teacher Interface
 * 
 * Allows teachers to:
 * - View list of pending submissions
 * - Grade individual submissions
 * - Override AI grades
 * - Add manual feedback
 */

date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/message_utils.php';
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$teacher_id = $_SESSION['user_id'] ?? 0;
$fname = $_SESSION['fname'];

// Get unread message count
$unread_count = getUnreadMessageCount($fname, $conn);

// Check if viewing specific submission
$submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $submission_id = intval($_POST['submission_id']);
    $grades = $_POST['grades'] ?? [];
    $feedbacks = $_POST['feedbacks'] ?? [];
    
    // Verify teacher owns this exam and check if grading is allowed
    $verify_sql = "SELECT os.submission_id, os.submission_status, oe.teacher_id, oe.grading_mode 
                   FROM objective_submissions os
                   JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                   WHERE os.submission_id = ? AND oe.teacher_id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "ii", $submission_id, $teacher_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    $verify_row = mysqli_fetch_assoc($verify_result);
    
    // Block manual grading if AI grading is not complete
    if ($verify_row && $verify_row['grading_mode'] === 'ai' && 
        !in_array($verify_row['submission_status'], ['graded', 'error'])) {
        $error_msg = "Cannot save grades: AI grading is still in progress. Please wait for AI grading to complete.";
    } elseif ($verify_row) {
        $total_score = 0;
        $graded_count = 0;
        
        foreach ($grades as $question_id => $score) {
            $question_id = intval($question_id);
            $score = floatval($score);
            $feedback = $feedbacks[$question_id] ?? '';
            
            // Get max marks for this question
            $max_sql = "SELECT max_marks FROM objective_questions WHERE question_id = ?";
            $max_stmt = mysqli_prepare($conn, $max_sql);
            mysqli_stmt_bind_param($max_stmt, "i", $question_id);
            mysqli_stmt_execute($max_stmt);
            $max_result = mysqli_fetch_assoc(mysqli_stmt_get_result($max_stmt));
            $max_marks = $max_result['max_marks'] ?? 10;
            
            // Clamp score to valid range
            $score = max(0, min($score, $max_marks));
            
            // Check if grade record exists
            $check_sql = "SELECT grade_id, ai_score FROM objective_answer_grades 
                          WHERE submission_id = ? AND question_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $submission_id, $question_id);
            mysqli_stmt_execute($check_stmt);
            $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
            
            if ($existing) {
                // Update existing grade
                $grading_method = $existing['ai_score'] !== null ? 'ai_override' : 'manual';
                $update_sql = "UPDATE objective_answer_grades 
                               SET manual_score = ?, manual_feedback = ?, final_score = ?, 
                                   grading_method = ?, graded_at = NOW()
                               WHERE submission_id = ? AND question_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "dsssii", $score, $feedback, $score, $grading_method, $submission_id, $question_id);
                mysqli_stmt_execute($update_stmt);
            } else {
                // Insert new grade
                $insert_sql = "INSERT INTO objective_answer_grades 
                               (submission_id, question_id, max_marks, manual_score, manual_feedback, 
                                final_score, grading_method, graded_at)
                               VALUES (?, ?, ?, ?, ?, ?, 'manual', NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "iiddsd", $submission_id, $question_id, $max_marks, $score, $feedback, $score);
                mysqli_stmt_execute($insert_stmt);
            }
            
            $total_score += $score;
            $graded_count++;
        }
        
        // Update submission status to graded
        $update_status = "UPDATE objective_submissions 
                          SET submission_status = 'graded', total_marks = ?, total_score = ?
                          WHERE submission_id = ?";
        $status_stmt = mysqli_prepare($conn, $update_status);
        mysqli_stmt_bind_param($status_stmt, "ddi", $total_score, $total_score, $submission_id);
        mysqli_stmt_execute($status_stmt);
        
        $success_msg = "Grades saved successfully! Total: $total_score marks";
    } else if (!$verify_row) {
        $error_msg = "You don't have permission to grade this submission";
    }
}

// Get specific submission details if ID provided
$submission = null;
$questions = [];
$scan_pages = [];

if ($submission_id > 0) {
    // Get submission with exam details
    $sub_sql = "SELECT os.*, oe.exam_name, oe.total_marks, oe.grading_mode, oe.school_id,
                       st.fname as student_name, st.uname as student_uname, s.school_name
                FROM objective_submissions os
                JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                JOIN student st ON os.student_id = st.id
                LEFT JOIN schools s ON oe.school_id = s.school_id
                WHERE os.submission_id = ? AND oe.teacher_id = ?";
    $sub_stmt = mysqli_prepare($conn, $sub_sql);
    mysqli_stmt_bind_param($sub_stmt, "ii", $submission_id, $teacher_id);
    mysqli_stmt_execute($sub_stmt);
    $submission = mysqli_fetch_assoc(mysqli_stmt_get_result($sub_stmt));
    
    if ($submission) {
        $exam_id = $submission['exam_id'];
        
        // Get questions with grades
        $q_sql = "SELECT oq.*, 
                         oag.grade_id, oag.extracted_answer, oag.ai_score, oag.ai_feedback, 
                         oag.ai_confidence, oag.manual_score, oag.manual_feedback, 
                         oag.final_score, oag.grading_method
                  FROM objective_questions oq
                  LEFT JOIN objective_answer_grades oag ON oq.question_id = oag.question_id 
                       AND oag.submission_id = ?
                  WHERE oq.exam_id = ?
                  ORDER BY oq.question_number ASC";
        $q_stmt = mysqli_prepare($conn, $q_sql);
        mysqli_stmt_bind_param($q_stmt, "ii", $submission_id, $exam_id);
        mysqli_stmt_execute($q_stmt);
        $questions = mysqli_stmt_get_result($q_stmt);
        
        // Get scanned pages (from both tables for compatibility)
        $pages_sql = "SELECT page_id as id, image_path, page_number, ocr_text 
                      FROM objective_scan_pages 
                      WHERE submission_id = ? 
                      UNION ALL
                      SELECT image_id as id, image_path, image_order as page_number, ocr_text 
                      FROM objective_answer_images 
                      WHERE submission_id = ?
                      ORDER BY page_number ASC";
        $pages_stmt = mysqli_prepare($conn, $pages_sql);
        mysqli_stmt_bind_param($pages_stmt, "ii", $submission_id, $submission_id);
        mysqli_stmt_execute($pages_stmt);
        $scan_pages = mysqli_stmt_get_result($pages_stmt);
        
        // Get combined OCR text for display
        $ocr_sql = "SELECT ocr_text, image_order FROM objective_answer_images 
                    WHERE submission_id = ? AND ocr_status = 'completed'
                    ORDER BY image_order ASC";
        $ocr_stmt = mysqli_prepare($conn, $ocr_sql);
        mysqli_stmt_bind_param($ocr_stmt, "i", $submission_id);
        mysqli_stmt_execute($ocr_stmt);
        $ocr_result = mysqli_stmt_get_result($ocr_stmt);
        $combined_ocr_text = '';
        while ($ocr_row = mysqli_fetch_assoc($ocr_result)) {
            if (!empty($ocr_row['ocr_text'])) {
                $combined_ocr_text .= $ocr_row['ocr_text'] . "\n";
            }
        }
    }
}

// Get list of submissions for this exam (or all pending)
$submissions_list = [];
$list_sql = "SELECT os.*, oe.exam_name, oe.total_marks, oe.grading_mode,
                    st.fname as student_name, st.uname as student_uname, s.school_name
             FROM objective_submissions os
             JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
             JOIN student st ON os.student_id = st.id
             LEFT JOIN schools s ON oe.school_id = s.school_id
             WHERE oe.teacher_id = ?";

if ($exam_id > 0) {
    $list_sql .= " AND oe.exam_id = ?";
}
$list_sql .= " ORDER BY 
               CASE os.submission_status 
                   WHEN 'ocr_complete' THEN 1 
                   WHEN 'grading' THEN 2 
                   WHEN 'pending' THEN 3
                   WHEN 'ocr_processing' THEN 4
                   WHEN 'graded' THEN 5 
                   ELSE 6 
               END, 
               os.submitted_at DESC";

$list_stmt = mysqli_prepare($conn, $list_sql);
if ($exam_id > 0) {
    mysqli_stmt_bind_param($list_stmt, "ii", $teacher_id, $exam_id);
} else {
    mysqli_stmt_bind_param($list_stmt, "i", $teacher_id);
}
mysqli_stmt_execute($list_stmt);
$submissions_list = mysqli_stmt_get_result($list_stmt);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Grade Objective Exams</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .grading-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            padding: 20px;
        }

        .submissions-panel {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }

        .submissions-panel h3 {
            padding: 15px 20px;
            margin: 0;
            background: #17684f;
            color: white;
            border-radius: 10px 10px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .submission-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }

        .submission-item:hover {
            background: #f8f9fa;
        }

        .submission-item.active {
            background: #e8f5e9;
            border-left: 4px solid #17684f;
        }

        .submission-item .student-name {
            font-weight: 600;
            color: #333;
        }

        .submission-item .exam-name {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }

        .submission-item .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-ocr_processing { background: #cce5ff; color: #004085; }
        .status-ocr_complete { background: #d4edda; color: #155724; }
        .status-grading { background: #fff3cd; color: #856404; }
        .status-graded { background: #d1e7dd; color: #0f5132; }
        .status-error { background: #f8d7da; color: #721c24; }

        .grading-panel {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }

        .grading-header {
            padding: 20px;
            background: linear-gradient(135deg, #17684f, #11533e);
            color: white;
            border-radius: 10px 10px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .grading-header h2 {
            margin: 0 0 10px 0;
        }

        .grading-header .meta {
            font-size: 14px;
            opacity: 0.9;
        }

        .scan-images {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .scan-images h4 {
            margin: 0 0 15px 0;
            color: #333;
        }

        .scan-thumbnails {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .scan-thumb {
            width: 120px;
            height: 160px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s, border-color 0.2s;
        }

        .scan-thumb:hover {
            transform: scale(1.05);
            border-color: #17684f;
        }

        .questions-container {
            padding: 20px;
        }

        .question-grade-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .question-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .question-number {
            font-weight: 700;
            color: #17684f;
            font-size: 16px;
        }

        .question-marks {
            font-size: 14px;
            color: #666;
        }

        .question-body {
            padding: 20px;
        }

        .question-text {
            background: #f0f7f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            color: #333;
        }

        .answer-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .answer-box {
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .answer-box.model {
            background: #e8f5e9;
            border-color: #c8e6c9;
        }

        .answer-box.student {
            background: #e3f2fd;
            border-color: #bbdefb;
        }

        .answer-box h5 {
            margin: 0 0 10px 0;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
        }

        .ai-grade-info {
            background: #fff3e0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #ffe0b2;
        }

        .ai-grade-info h5 {
            margin: 0 0 10px 0;
            color: #e65100;
            font-size: 14px;
        }

        .ai-details {
            display: flex;
            gap: 20px;
            font-size: 14px;
        }

        .ai-details .score {
            font-weight: 600;
            color: #2e7d32;
        }

        .ai-details .confidence {
            color: #666;
        }

        .grading-inputs {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            align-items: start;
        }

        .score-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
        }

        .score-input:focus {
            outline: none;
            border-color: #17684f;
        }

        .feedback-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
        }

        .feedback-input:focus {
            outline: none;
            border-color: #17684f;
        }

        .save-btn {
            position: sticky;
            bottom: 0;
            background: #17684f;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            border-radius: 0 0 10px 10px;
        }

        .save-btn:hover {
            background: #11533e;
        }

        .no-submission {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 400px;
            color: #666;
        }

        .no-submission i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin: 10px 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        /* Image modal */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .image-modal.active {
            display: flex;
        }

        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }

        .image-modal .close-btn {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 40px;
            color: white;
            cursor: pointer;
        }

        .submission-score {
            font-size: 12px;
            color: #17684f;
            font-weight: 600;
            margin-top: 5px;
        }

        @media (max-width: 1024px) {
            .grading-container {
                grid-template-columns: 1fr;
            }
            
            .submissions-panel {
                max-height: 300px;
            }
            
            .answer-section {
                grid-template-columns: 1fr;
            }
            
            .grading-inputs {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-details">
            <i class='bx bxs-graduation'></i>
            <span class="logo_name">ExamPortal</span>
        </div>
        <ul class="nav-links">
            <li><a href="dash.php"><i class='bx bx-grid-alt'></i><span class="links_name">Dashboard</span></a></li>
            <li><a href="exams.php"><i class='bx bx-book'></i><span class="links_name">MCQ Exams</span></a></li>
            <li><a href="objective_exams.php"><i class='bx bx-file'></i><span class="links_name">Objective Exams</span></a></li>
            <li><a href="grade_objective.php" class="active"><i class='bx bx-check-circle'></i><span class="links_name">Grade Submissions</span></a></li>
            <li><a href="results.php"><i class='bx bx-bar-chart-alt-2'></i><span class="links_name">Results</span></a></li>
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu siderbar-btn'></i>
                <span class="dashboard">Grade Objective Exams</span>
            </div>
            <div class="profile-details">
                <a href="messages.php" style="text-decoration: none; position: relative; margin-right: 15px;">
                    <i class='bx bx-bell' style="font-size: 24px; color: #17684f;"></i>
                    <?php if ($unread_count > 0): ?>
                        <span style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; 
                                     border-radius: 50%; width: 18px; height: 18px; font-size: 10px; 
                                     display: flex; align-items: center; justify-content: center;">
                            <?php echo $unread_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <span class="admin_name"><?php echo htmlspecialchars($fname); ?></span>
            </div>
        </nav>

        <div class="grading-container">
            <!-- Submissions List Panel -->
            <div class="submissions-panel">
                <h3>
                    <i class='bx bx-list-ul'></i> 
                    Submissions
                    <?php if ($exam_id > 0): ?>
                        <a href="grade_objective.php" style="color: white; font-size: 12px; float: right;">View All</a>
                    <?php endif; ?>
                </h3>
                
                <?php if (mysqli_num_rows($submissions_list) > 0): ?>
                    <?php while ($sub = mysqli_fetch_assoc($submissions_list)): ?>
                        <div class="submission-item <?php echo $sub['submission_id'] == $submission_id ? 'active' : ''; ?>"
                             onclick="window.location.href='grade_objective.php?id=<?php echo $sub['submission_id']; ?>&exam_id=<?php echo $sub['exam_id']; ?>'">
                            <div class="student-name">
                                <i class='bx bx-user'></i> <?php echo htmlspecialchars($sub['student_name']); ?>
                            </div>
                            <div class="exam-name"><?php echo htmlspecialchars($sub['exam_name']); ?></div>
                            <div class="exam-name"><?php echo htmlspecialchars($sub['school_name']); ?></div>
                            <span class="status-badge status-<?php echo $sub['submission_status']; ?>">
                                <?php echo str_replace('_', ' ', $sub['submission_status']); ?>
                            </span>
                            <?php if ($sub['submission_status'] == 'graded' && $sub['scored_marks'] !== null): ?>
                                <div class="submission-score">
                                    Score: <?php echo number_format($sub['scored_marks'], 1); ?>/<?php echo $sub['total_marks']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-submission" style="padding: 40px;">
                        <i class='bx bx-inbox'></i>
                        <p>No submissions yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Grading Panel -->
            <div class="grading-panel">
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success"><?php echo $success_msg; ?></div>
                <?php endif; ?>
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-error"><?php echo $error_msg; ?></div>
                <?php endif; ?>
                
                <?php if ($submission): ?>
                    <div class="grading-header">
                        <h2><?php echo htmlspecialchars($submission['exam_name']); ?></h2>
                        <div class="meta">
                            <strong>Student:</strong> <?php echo htmlspecialchars($submission['student_name']); ?> 
                            (<?php echo htmlspecialchars($submission['student_uname']); ?>)
                            &nbsp;|&nbsp;
                            <strong>School:</strong> <?php echo htmlspecialchars($submission['school_name']); ?>
                            &nbsp;|&nbsp;
                            <strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?>
                            &nbsp;|&nbsp;
                            <strong>Mode:</strong> <?php echo strtoupper($submission['grading_mode']); ?>
                        </div>
                    </div>

                    <!-- Scanned Images -->
                    <?php if (mysqli_num_rows($scan_pages) > 0): ?>
                        <div class="scan-images">
                            <h4><i class='bx bx-image'></i> Scanned Answer Sheets</h4>
                            <div class="scan-thumbnails">
                                <?php while ($page = mysqli_fetch_assoc($scan_pages)): 
                                    // Convert absolute path to web-relative path
                                    $img_path = $page['image_path'];
                                    // Remove absolute path prefix and normalize
                                    $img_path = str_replace(['C:/xampp/htdocs/Hackfest25-42/', 'C:\\xampp\\htdocs\\Hackfest25-42\\'], '', $img_path);
                                    $img_path = str_replace('utils/../', '', $img_path);
                                    $img_path = '../' . $img_path;
                                ?>
                                    <img src="<?php echo htmlspecialchars($img_path); ?>" 
                                         class="scan-thumb" 
                                         alt="Page <?php echo $page['page_number']; ?>"
                                         onclick="openImageModal(this.src)">
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Full OCR Extracted Text -->
                    <?php if (!empty($combined_ocr_text)): ?>
                        <div class="ocr-text-section" style="background: #e3f2fd; border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 10px 0; color: #1976d2;"><i class='bx bx-text'></i> Student's Written Answers (OCR Extracted)</h4>
                            <div style="background: white; padding: 15px; border-radius: 8px; max-height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 13px; line-height: 1.5;">
<?php echo htmlspecialchars($combined_ocr_text); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php 
                    // Check if AI grading is pending
                    $ai_grading_pending = ($submission['grading_mode'] === 'ai' && 
                                          !in_array($submission['submission_status'], ['graded', 'error']));
                    ?>
                    
                    <?php if ($ai_grading_pending): ?>
                        <!-- AI Grading In Progress Message -->
                        <div class="ai-pending-notice" style="background: #fff3e0; border: 2px solid #ff9800; border-radius: 10px; padding: 20px; margin-bottom: 20px; text-align: center;">
                            <i class='bx bx-loader-alt bx-spin' style="font-size: 48px; color: #ff9800;"></i>
                            <h3 style="color: #e65100; margin: 15px 0 10px 0;">AI Grading In Progress</h3>
                            <p style="color: #666; margin: 0 0 15px 0;">
                                This exam uses AI grading. Please wait for the AI to complete grading before making manual adjustments.
                            </p>
                            <p style="color: #999; font-size: 14px; margin: 0 0 15px 0;">
                                Current Status: <strong><?php echo ucfirst(str_replace('_', ' ', $submission['submission_status'])); ?></strong>
                            </p>
                            <a href="../process_submission.php?id=<?php echo $submission_id; ?>" 
                               class="btn" style="background: #ff9800; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;">
                                <i class='bx bx-refresh'></i> Trigger AI Grading Now
                            </a>
                            <p style="color: #999; font-size: 12px; margin-top: 15px;">
                                The page will refresh automatically when grading completes.
                            </p>
                        </div>
                        <script>
                            // Auto-refresh every 10 seconds to check for grading completion
                            setTimeout(function() {
                                location.reload();
                            }, 10000);
                        </script>
                    <?php else: ?>
                    <!-- Grading Form -->
                    <form method="POST" action="">
                        <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                        
                        <div class="questions-container">
                            <?php 
                            $total_max = 0;
                            $total_current = 0;
                            while ($q = mysqli_fetch_assoc($questions)): 
                                $total_max += $q['max_marks'];
                                $current_score = $q['final_score'] ?? $q['manual_score'] ?? $q['ai_score'] ?? 0;
                                $total_current += $current_score;
                                $current_feedback = $q['manual_feedback'] ?? $q['ai_feedback'] ?? '';
                            ?>
                                <div class="question-grade-card">
                                    <div class="question-header">
                                        <span class="question-number">Question <?php echo $q['question_number']; ?></span>
                                        <span class="question-marks">Max Marks: <?php echo $q['max_marks']; ?></span>
                                    </div>
                                    <div class="question-body">
                                        <div class="question-text">
                                            <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                                        </div>
                                        
                                        <div class="answer-section">
                                            <?php if (!empty($q['answer_key_text'])): ?>
                                                <div class="answer-box model">
                                                    <h5><i class='bx bx-check-circle'></i> Model Answer</h5>
                                                    <?php echo nl2br(htmlspecialchars($q['answer_key_text'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="answer-box student">
                                                <h5><i class='bx bx-user'></i> Student Answer (OCR)</h5>
                                                <?php if (!empty($q['extracted_answer'])): ?>
                                                    <?php echo nl2br(htmlspecialchars($q['extracted_answer'])); ?>
                                                <?php else: ?>
                                                    <em style="color:#666">See "Student's Written Answers" section above for full OCR text. AI graded based on combined response.</em>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($q['ai_score'] !== null): ?>
                                            <div class="ai-grade-info">
                                                <h5><i class='bx bx-brain'></i> AI Grading Result</h5>
                                                <div class="ai-details">
                                                    <span class="score">Score: <?php echo number_format($q['ai_score'], 1); ?>/<?php echo $q['max_marks']; ?></span>
                                                    <span class="confidence">Confidence: <?php echo number_format($q['ai_confidence'] ?? 0, 0); ?>%</span>
                                                </div>
                                                <?php if (!empty($q['ai_feedback'])): ?>
                                                    <div style="margin-top: 10px; font-size: 14px; color: #666;">
                                                        <?php echo nl2br(htmlspecialchars($q['ai_feedback'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="grading-inputs">
                                            <div>
                                                <label style="font-size: 12px; color: #666; display: block; margin-bottom: 5px;">
                                                    Score (0-<?php echo $q['max_marks']; ?>)
                                                </label>
                                                <input type="number" 
                                                       name="grades[<?php echo $q['question_id']; ?>]" 
                                                       class="score-input"
                                                       min="0" 
                                                       max="<?php echo $q['max_marks']; ?>" 
                                                       step="0.5"
                                                       value="<?php echo $current_score; ?>"
                                                       required>
                                            </div>
                                            <div>
                                                <label style="font-size: 12px; color: #666; display: block; margin-bottom: 5px;">
                                                    Feedback (optional)
                                                </label>
                                                <textarea name="feedbacks[<?php echo $q['question_id']; ?>]" 
                                                          class="feedback-input"
                                                          placeholder="Add feedback for the student..."><?php echo htmlspecialchars($current_feedback); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <button type="submit" name="save_grades" class="save-btn">
                            <i class='bx bx-save'></i> Save Grades 
                            (Current Total: <span id="currentTotal"><?php echo number_format($total_current, 1); ?></span>/<?php echo $total_max; ?>)
                        </button>
                    </form>
                    <?php endif; // End of AI grading pending check ?>
                    
                <?php else: ?>
                    <div class="no-submission">
                        <i class='bx bx-file-find'></i>
                        <h3>Select a Submission</h3>
                        <p>Choose a submission from the left panel to start grading</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Image Modal -->
    <div class="image-modal" id="imageModal">
        <span class="close-btn" onclick="closeImageModal()">&times;</span>
        <img id="modalImage" src="" alt="Scanned Page">
    </div>

    <script>
        // Sidebar toggle
        let sidebar = document.querySelector(".sidebar");
        let sidebarBtn = document.querySelector(".siderbar-btn");
        if (sidebarBtn) {
            sidebarBtn.onclick = function() {
                sidebar.classList.toggle("active");
            }
        }

        // Image modal
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('active');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Click outside modal to close
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Update total score dynamically
        document.querySelectorAll('.score-input').forEach(input => {
            input.addEventListener('input', updateTotal);
        });

        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.score-input').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('currentTotal').textContent = total.toFixed(1);
        }
    </script>
</body>

</html>
