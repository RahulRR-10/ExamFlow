<?php

/**
 * Objective Exam Questions Management
 * 
 * Add, edit, and delete questions for an objective exam.
 * Also handles answer key upload for each question.
 */

date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/objective_exam_utils.php';

$teacher_id = $_SESSION['user_id'];
$error_msg = '';
$success_msg = '';

// Get exam ID from URL
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$is_new_exam = isset($_GET['new']) && $_GET['new'] == '1';

if ($exam_id <= 0) {
    header("Location: objective_exams.php?error=" . urlencode("Invalid exam ID."));
    exit;
}

// Verify teacher owns this exam
requireTeacherOwnsExam($conn, $exam_id, $teacher_id);

// Get exam details
$exam_sql = "SELECT oe.*, s.school_name 
             FROM objective_exm_list oe
             LEFT JOIN schools s ON oe.school_id = s.school_id
             WHERE oe.exam_id = ?";
$exam_stmt = mysqli_prepare($conn, $exam_sql);
mysqli_stmt_bind_param($exam_stmt, "i", $exam_id);
mysqli_stmt_execute($exam_stmt);
$exam = mysqli_fetch_assoc(mysqli_stmt_get_result($exam_stmt));

if (!$exam) {
    header("Location: objective_exams.php?error=" . urlencode("Exam not found."));
    exit;
}

// Handle add question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = trim(mysqli_real_escape_string($conn, $_POST['question_text']));
    $max_marks = intval($_POST['max_marks']);
    $answer_key_text = trim(mysqli_real_escape_string($conn, $_POST['answer_key_text']));

    if (empty($question_text)) {
        $error_msg = 'Question text is required.';
    } elseif ($max_marks <= 0) {
        $error_msg = 'Maximum marks must be greater than 0.';
    } else {
        // Get next question number
        $count_sql = "SELECT MAX(question_number) as max_num FROM objective_questions WHERE exam_id = ?";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        mysqli_stmt_bind_param($count_stmt, "i", $exam_id);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
        $question_number = ($count_result['max_num'] ?? 0) + 1;

        $insert_sql = "INSERT INTO objective_questions (exam_id, question_number, question_text, max_marks, answer_key_text)
                       VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "iisis", $exam_id, $question_number, $question_text, $max_marks, $answer_key_text);

        if (mysqli_stmt_execute($insert_stmt)) {
            $success_msg = "Question $question_number added successfully.";
        } else {
            $error_msg = 'Failed to add question.';
        }
    }
}

// Handle edit question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_question'])) {
    $question_id = intval($_POST['question_id']);
    $question_text = trim(mysqli_real_escape_string($conn, $_POST['question_text']));
    $max_marks = intval($_POST['max_marks']);
    $answer_key_text = trim(mysqli_real_escape_string($conn, $_POST['answer_key_text']));

    if (empty($question_text)) {
        $error_msg = 'Question text is required.';
    } elseif ($max_marks <= 0) {
        $error_msg = 'Maximum marks must be greater than 0.';
    } else {
        // Verify question belongs to this exam
        $verify_sql = "SELECT question_id FROM objective_questions WHERE question_id = ? AND exam_id = ?";
        $verify_stmt = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($verify_stmt, "ii", $question_id, $exam_id);
        mysqli_stmt_execute($verify_stmt);

        if (mysqli_num_rows(mysqli_stmt_get_result($verify_stmt)) > 0) {
            $update_sql = "UPDATE objective_questions SET question_text = ?, max_marks = ?, answer_key_text = ? WHERE question_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sisi", $question_text, $max_marks, $answer_key_text, $question_id);

            if (mysqli_stmt_execute($update_stmt)) {
                $success_msg = "Question updated successfully.";
            } else {
                $error_msg = 'Failed to update question.';
            }
        } else {
            $error_msg = 'Invalid question.';
        }
    }
}

// Handle delete question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    $question_id = intval($_POST['question_id']);

    // Verify question belongs to this exam
    $verify_sql = "SELECT question_number FROM objective_questions WHERE question_id = ? AND exam_id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "ii", $question_id, $exam_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);

    if ($row = mysqli_fetch_assoc($verify_result)) {
        $deleted_num = $row['question_number'];

        // Delete the question
        $delete_sql = "DELETE FROM objective_questions WHERE question_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $question_id);

        if (mysqli_stmt_execute($delete_stmt)) {
            // Renumber remaining questions
            $renumber_sql = "UPDATE objective_questions SET question_number = question_number - 1 
                             WHERE exam_id = ? AND question_number > ?";
            $renumber_stmt = mysqli_prepare($conn, $renumber_sql);
            mysqli_stmt_bind_param($renumber_stmt, "ii", $exam_id, $deleted_num);
            mysqli_stmt_execute($renumber_stmt);

            $success_msg = "Question deleted successfully.";
        } else {
            $error_msg = 'Failed to delete question.';
        }
    } else {
        $error_msg = 'Invalid question.';
    }
}

// Handle exam status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $new_status = in_array($_POST['new_status'], ['draft', 'active', 'closed']) ? $_POST['new_status'] : 'draft';

    // Check if there are questions before activating
    $q_count_sql = "SELECT COUNT(*) as cnt FROM objective_questions WHERE exam_id = ?";
    $q_count_stmt = mysqli_prepare($conn, $q_count_sql);
    mysqli_stmt_bind_param($q_count_stmt, "i", $exam_id);
    mysqli_stmt_execute($q_count_stmt);
    $q_count = mysqli_fetch_assoc(mysqli_stmt_get_result($q_count_stmt))['cnt'];

    if ($new_status == 'active' && $q_count == 0) {
        $error_msg = 'Cannot activate exam without questions. Add at least one question first.';
    } else {
        $status_sql = "UPDATE objective_exm_list SET status = ? WHERE exam_id = ?";
        $status_stmt = mysqli_prepare($conn, $status_sql);
        mysqli_stmt_bind_param($status_stmt, "si", $new_status, $exam_id);

        if (mysqli_stmt_execute($status_stmt)) {
            $exam['status'] = $new_status;
            $success_msg = "Exam status changed to " . ucfirst($new_status) . ".";
        }
    }
}

// Get all questions for this exam
$questions_sql = "SELECT * FROM objective_questions WHERE exam_id = ? ORDER BY question_number ASC";
$questions_stmt = mysqli_prepare($conn, $questions_sql);
mysqli_stmt_bind_param($questions_stmt, "i", $exam_id);
mysqli_stmt_execute($questions_stmt);
$questions = mysqli_stmt_get_result($questions_stmt);

// Calculate total marks from questions
$total_marks_sql = "SELECT COALESCE(SUM(max_marks), 0) as total FROM objective_questions WHERE exam_id = ?";
$total_stmt = mysqli_prepare($conn, $total_marks_sql);
mysqli_stmt_bind_param($total_stmt, "i", $exam_id);
mysqli_stmt_execute($total_stmt);
$current_total = mysqli_fetch_assoc(mysqli_stmt_get_result($total_stmt))['total'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Manage Questions - <?php echo htmlspecialchars($exam['exam_name']); ?></title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .page-container {
            max-width: 1200px;
        }

        .exam-header {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }

        .exam-header-info h2 {
            margin: 0 0 10px 0;
            color: #17684f;
        }

        .exam-header-info .meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #666;
        }

        .exam-header-info .meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-closed {
            background: #f8d7da;
            color: #721c24;
        }

        .exam-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .exam-actions button,
        .exam-actions a {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-activate {
            background: #28a745;
            color: white;
        }

        .btn-close-exam {
            background: #dc3545;
            color: white;
        }

        .btn-draft {
            background: #ffc107;
            color: #333;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .marks-summary {
            background: #e8f5e9;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .marks-summary .current {
            font-size: 24px;
            font-weight: 700;
            color: #17684f;
        }

        .marks-summary .target {
            color: #666;
        }

        .marks-match {
            color: #28a745 !important;
        }

        .marks-mismatch {
            color: #dc3545 !important;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .questions-card,
        .add-question-card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .questions-card h3,
        .add-question-card h3 {
            color: #17684f;
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #17684f;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .question-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }

        .question-item:hover {
            border-color: #17684f;
        }

        .question-number {
            position: absolute;
            top: -10px;
            left: 15px;
            background: #17684f;
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .question-text {
            margin: 10px 0;
            color: #333;
            line-height: 1.5;
        }

        .question-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .question-marks {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .question-actions {
            display: flex;
            gap: 8px;
        }

        .question-actions button {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-edit-q {
            background: #17684f;
            color: white;
        }

        .btn-delete-q {
            background: #dc3545;
            color: white;
        }

        .answer-key-preview {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            color: #666;
            margin-top: 10px;
            max-height: 60px;
            overflow: hidden;
            position: relative;
        }

        .answer-key-preview::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(transparent, #f8f9fa);
        }

        .answer-key-label {
            font-size: 11px;
            color: #888;
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #17684f;
            outline: none;
        }

        .form-group .help-text {
            font-size: 11px;
            color: #888;
            margin-top: 4px;
        }

        .btn-add {
            background: #17684f;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-add:hover {
            background: #11533e;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
            color: #ddd;
        }

        /* Edit Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            color: #17684f;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-save {
            background: #17684f;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-details">
            <i class='bx bx-diamond'></i>
            <span class="logo_name">Welcome</span>
        </div>
        <ul class="nav-links">
            <li><a href="dash.php"><i class='bx bx-grid-alt'></i><span class="links_name">Dashboard</span></a></li>
            <li><a href="exams.php"><i class='bx bx-book-content'></i><span class="links_name">MCQ Exams</span></a></li>
            <li><a href="objective_exams.php" class="active"><i class='bx bx-edit'></i><span class="links_name">Objective Exams</span></a></li>
            <li><a href="results.php"><i class='bx bxs-bar-chart-alt-2'></i><span class="links_name">Results</span></a></li>
            <li><a href="records.php"><i class='bx bxs-user-circle'></i><span class="links_name">Records</span></a></li>
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="school_management.php"><i class='bx bx-building-house'></i><span class="links_name">Schools</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Manage Questions</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="page-container">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?php echo $success_msg; ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?php echo $error_msg; ?></div>
                <?php endif; ?>
                <?php if ($is_new_exam): ?>
                    <div class="alert alert-info"><i class='bx bx-info-circle'></i> Exam created successfully! Now add your questions below.</div>
                <?php endif; ?>

                <!-- Exam Header -->
                <div class="exam-header">
                    <div class="exam-header-info">
                        <h2><?php echo htmlspecialchars($exam['exam_name']); ?></h2>
                        <div class="meta">
                            <span><i class='bx bx-building'></i> <?php echo htmlspecialchars($exam['school_name']); ?></span>
                            <span><i class='bx bx-<?php echo $exam['grading_mode'] == 'ai' ? 'brain' : 'user'; ?>'></i>
                                <?php echo strtoupper($exam['grading_mode']); ?> Grading</span>
                            <span><i class='bx bx-calendar'></i> <?php echo date('M d, Y', strtotime($exam['exam_date'])); ?></span>
                            <span class="status-badge status-<?php echo $exam['status']; ?>"><?php echo $exam['status']; ?></span>
                        </div>
                    </div>
                    <div class="exam-actions">
                        <?php if ($exam['status'] == 'draft'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="new_status" value="active">
                                <button type="submit" name="change_status" class="btn-activate">
                                    <i class='bx bx-check'></i> Activate Exam
                                </button>
                            </form>
                        <?php elseif ($exam['status'] == 'active'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="new_status" value="closed">
                                <button type="submit" name="change_status" class="btn-close-exam">
                                    <i class='bx bx-lock'></i> Close Exam
                                </button>
                            </form>
                        <?php elseif ($exam['status'] == 'closed'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="new_status" value="draft">
                                <button type="submit" name="change_status" class="btn-draft">
                                    <i class='bx bx-undo'></i> Revert to Draft
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="objective_exams.php" class="btn-back">
                            <i class='bx bx-arrow-back'></i> Back to List
                        </a>
                    </div>
                </div>

                <!-- Marks Summary -->
                <div class="marks-summary">
                    <div>
                        <div class="current <?php echo ($current_total == $exam['total_marks']) ? 'marks-match' : 'marks-mismatch'; ?>">
                            <?php echo $current_total; ?> / <?php echo $exam['total_marks']; ?> marks
                        </div>
                        <div class="target">
                            <?php if ($current_total != $exam['total_marks']): ?>
                                <?php echo ($current_total < $exam['total_marks']) ? 'Add ' . ($exam['total_marks'] - $current_total) . ' more marks' : 'Remove ' . ($current_total - $exam['total_marks']) . ' marks'; ?>
                            <?php else: ?>
                                All marks allocated
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        Passing: <?php echo $exam['passing_marks']; ?> marks
                    </div>
                </div>

                <!-- Main Content -->
                <div class="content-grid">
                    <!-- Questions List -->
                    <div class="questions-card">
                        <h3><i class='bx bx-list-ul'></i> Questions (<?php echo mysqli_num_rows($questions); ?>)</h3>

                        <?php if (mysqli_num_rows($questions) > 0): ?>
                            <?php while ($q = mysqli_fetch_assoc($questions)): ?>
                                <div class="question-item">
                                    <span class="question-number">Q<?php echo $q['question_number']; ?></span>
                                    <div class="question-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>

                                    <?php if (!empty($q['answer_key_text'])): ?>
                                        <div class="answer-key-preview">
                                            <div class="answer-key-label"><i class='bx bx-key'></i> Answer Key:</div>
                                            <?php echo htmlspecialchars(substr($q['answer_key_text'], 0, 150)); ?>...
                                        </div>
                                    <?php endif; ?>

                                    <div class="question-meta">
                                        <span class="question-marks"><?php echo $q['max_marks']; ?> marks</span>
                                        <div class="question-actions">
                                            <button type="button" class="btn-edit-q" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($q)); ?>)">
                                                <i class='bx bx-edit'></i> Edit
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this question?');">
                                                <input type="hidden" name="question_id" value="<?php echo $q['question_id']; ?>">
                                                <button type="submit" name="delete_question" class="btn-delete-q">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class='bx bx-help-circle'></i>
                                <p>No questions added yet.<br>Add your first question using the form.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Add Question Form -->
                    <div class="add-question-card">
                        <h3><i class='bx bx-plus-circle'></i> Add Question</h3>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="question_text">Question Text *</label>
                                <textarea id="question_text" name="question_text" required
                                    placeholder="Enter the question..."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="max_marks">Maximum Marks *</label>
                                <input type="number" id="max_marks" name="max_marks"
                                    value="10" min="1" max="100" required>
                            </div>

                            <div class="form-group">
                                <label for="answer_key_text">Answer Key (Optional)</label>
                                <textarea id="answer_key_text" name="answer_key_text"
                                    placeholder="Enter the expected answer or key points..."></textarea>
                                <div class="help-text">
                                    <?php if ($exam['grading_mode'] == 'ai'): ?>
                                        The AI will compare student answers against this key.
                                    <?php else: ?>
                                        This will be shown to you during manual grading.
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button type="submit" name="add_question" class="btn-add">
                                <i class='bx bx-plus'></i> Add Question
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class='bx bx-edit'></i> Edit Question</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="question_id" id="edit_question_id">

                <div class="form-group">
                    <label for="edit_question_text">Question Text *</label>
                    <textarea id="edit_question_text" name="question_text" required></textarea>
                </div>

                <div class="form-group">
                    <label for="edit_max_marks">Maximum Marks *</label>
                    <input type="number" id="edit_max_marks" name="max_marks" min="1" max="100" required>
                </div>

                <div class="form-group">
                    <label for="edit_answer_key_text">Answer Key</label>
                    <textarea id="edit_answer_key_text" name="answer_key_text"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="edit_question" class="btn-save">
                        <i class='bx bx-save'></i> Save Changes
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/script.js"></script>
    <script>
        function openEditModal(question) {
            document.getElementById('edit_question_id').value = question.question_id;
            document.getElementById('edit_question_text').value = question.question_text;
            document.getElementById('edit_max_marks').value = question.max_marks;
            document.getElementById('edit_answer_key_text').value = question.answer_key_text || '';
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>

</html>