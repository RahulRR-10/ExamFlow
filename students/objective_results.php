<?php

/**
 * Student Objective Exam Results
 * 
 * Displays detailed results for a graded objective exam submission.
 * Shows per-question grades, feedback, and total score.
 */

session_start();
if (!isset($_SESSION["uname"])) {
    header("Location: ../login_student.php");
    exit;
}

include '../config.php';
require_once '../utils/message_utils.php';
require_once '../utils/objective_exam_utils.php';

$student_id = $_SESSION['user_id'];
$uname = $_SESSION['uname'];

// Get submission ID
$submission_id = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 0;

if ($submission_id <= 0) {
    header("Location: objective_exams.php?error=" . urlencode("Invalid submission ID."));
    exit;
}

// Verify student owns this submission
if (!studentOwnsSubmission($conn, $submission_id, $student_id)) {
    header("Location: objective_exams.php?error=" . urlencode("You don't have access to this submission."));
    exit;
}

// Get submission details
$submission_sql = "SELECT os.*, oe.exam_name, oe.total_marks as exam_total_marks, oe.passing_marks,
                   oe.grading_mode, t.fname as teacher_name,
                   (SELECT SUM(max_marks) FROM objective_questions WHERE exam_id = oe.exam_id) as total_question_marks
                   FROM objective_submissions os
                   JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                   LEFT JOIN teacher t ON oe.teacher_id = t.id
                   WHERE os.submission_id = ?";
$submission_stmt = mysqli_prepare($conn, $submission_sql);
mysqli_stmt_bind_param($submission_stmt, "i", $submission_id);
mysqli_stmt_execute($submission_stmt);
$submission = mysqli_fetch_assoc(mysqli_stmt_get_result($submission_stmt));

if (!$submission) {
    header("Location: objective_exams.php?error=" . urlencode("Submission not found."));
    exit;
}

// Check if graded
if ($submission['submission_status'] !== 'graded') {
    header("Location: objective_exams.php?error=" . urlencode("This submission has not been graded yet."));
    exit;
}

$exam_id = $submission['exam_id'];
$total_marks = $submission['total_question_marks'] ?: $submission['exam_total_marks'];
$scored_marks = $submission['total_marks'];
$passing_marks = $submission['passing_marks'];
$passed = $scored_marks >= $passing_marks;
$percentage = $total_marks > 0 ? round(($scored_marks / $total_marks) * 100, 1) : 0;

// Get questions with grades
$grades_sql = "SELECT oq.*, oag.final_score, oag.ai_score, oag.ai_feedback, oag.manual_feedback, oag.grading_method, oag.graded_at
               FROM objective_questions oq
               LEFT JOIN objective_answer_grades oag ON oq.question_id = oag.question_id AND oag.submission_id = ?
               WHERE oq.exam_id = ?
               ORDER BY oq.question_number ASC";
$grades_stmt = mysqli_prepare($conn, $grades_sql);
mysqli_stmt_bind_param($grades_stmt, "ii", $submission_id, $exam_id);
mysqli_stmt_execute($grades_stmt);
$grades_result = mysqli_stmt_get_result($grades_stmt);

// Get unread message count
$unread_count = getUnreadMessageCount($uname, $conn);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Exam Results - <?php echo htmlspecialchars($submission['exam_name']); ?></title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .results-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .results-header {
            background: <?php echo $passed ? 'linear-gradient(135deg, #17684f 0%, #1a8a6a 100%)' : 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)'; ?>;
            color: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            text-align: center;
        }

        .results-header h1 {
            font-size: 28px;
            margin: 0 0 10px 0;
        }

        .results-header .exam-name {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .score-display {
            display: flex;
            justify-content: center;
            align-items: baseline;
            gap: 5px;
            margin-bottom: 15px;
        }

        .score-value {
            font-size: 64px;
            font-weight: 700;
        }

        .score-total {
            font-size: 24px;
            opacity: 0.8;
        }

        .result-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(255, 255, 255, 0.2);
        }

        .stats-row {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 600;
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.8;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .card-title i {
            color: #17684f;
        }

        .question-grade {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 15px;
            background: #fafafa;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .question-number {
            font-weight: 600;
            color: #333;
        }

        .marks-display {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .marks-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .marks-full {
            background: #e8f5e9;
            color: #388e3c;
        }

        .marks-partial {
            background: #fff3e0;
            color: #f57c00;
        }

        .marks-zero {
            background: #ffebee;
            color: #c62828;
        }

        .question-text {
            color: #666;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .feedback-section {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .feedback-label {
            font-size: 12px;
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 5px;
        }

        .feedback-text {
            color: #333;
            line-height: 1.5;
        }

        .grading-info {
            font-size: 11px;
            color: #999;
            margin-top: 8px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .summary-item {
            text-align: center;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
        }

        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: #17684f;
        }

        .summary-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .notification-badge {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            position: absolute;
            top: -5px;
            right: 10px;
            min-width: 18px;
            height: 18px;
            background-color: #ff3e55;
            color: white;
            border-radius: 50%;
            font-size: 11px;
            font-weight: bold;
            padding: 0 4px;
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            margin-top: 15px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: white;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .btn-print {
            background: #17684f;
            color: white;
        }

        .btn-print:hover {
            background: #11533e;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        @media print {
            .sidebar, nav, .action-buttons, .no-print {
                display: none !important;
            }
            .home-section {
                left: 0 !important;
                width: 100% !important;
            }
            .results-container {
                max-width: 100%;
                padding: 0;
            }
            body {
                background: white;
            }
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
            <li>
                <a href="dash.php">
                    <i class='bx bx-grid-alt'></i>
                    <span class="links_name">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="exams.php">
                    <i class='bx bx-book-content'></i>
                    <span class="links_name">MCQ Exams</span>
                </a>
            </li>
            <li>
                <a href="objective_exams.php" class="active">
                    <i class='bx bx-edit'></i>
                    <span class="links_name">Objective Exams</span>
                </a>
            </li>
            <li>
                <a href="mock_exams.php">
                    <i class='bx bx-test-tube'></i>
                    <span class="links_name">Mock Exams</span>
                </a>
            </li>
            <li>
                <a href="results.php">
                    <i class='bx bxs-bar-chart-alt-2'></i>
                    <span class="links_name">Results</span>
                </a>
            </li>
            <li>
                <a href="messages.php">
                    <i class='bx bx-message'></i>
                    <span class="links_name">Announcements</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <i class='bx bx-cog'></i>
                    <span class="links_name">Settings</span>
                </a>
            </li>
            <li>
                <a href="help.php">
                    <i class='bx bx-help-circle'></i>
                    <span class="links_name">Help</span>
                </a>
            </li>
            <li class="log_out">
                <a href="../logout.php">
                    <i class='bx bx-log-out-circle'></i>
                    <span class="links_name">Log out</span>
                </a>
            </li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Exam Results</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="results-container">
                <!-- Results Header -->
                <div class="results-header">
                    <h1><?php echo $passed ? '🎉 Congratulations!' : '📚 Keep Learning!'; ?></h1>
                    <div class="exam-name"><?php echo htmlspecialchars($submission['exam_name']); ?></div>

                    <div class="score-display">
                        <span class="score-value"><?php echo $scored_marks; ?></span>
                        <span class="score-total">/ <?php echo $total_marks; ?></span>
                    </div>

                    <div class="result-badge">
                        <?php echo $passed ? '✓ PASSED' : '✗ NOT PASSED'; ?>
                    </div>

                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>

                    <div class="stats-row">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $percentage; ?>%</div>
                            <div class="stat-label">Score</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $passing_marks; ?></div>
                            <div class="stat-label">Passing Marks</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo ucfirst($submission['grading_mode']); ?></div>
                            <div class="stat-label">Grading</div>
                        </div>
                    </div>
                </div>

                <!-- Summary Card -->
                <div class="card">
                    <h3 class="card-title">
                        <i class='bx bx-pie-chart-alt-2'></i>
                        Submission Summary
                    </h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-value"><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></div>
                            <div class="summary-label">Submitted On</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo date('M d, Y', strtotime($submission['graded_at'] ?? $submission['submitted_at'])); ?></div>
                            <div class="summary-label">Graded On</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo htmlspecialchars($submission['teacher_name']); ?></div>
                            <div class="summary-label">Instructor</div>
                        </div>
                    </div>
                </div>

                <!-- Per-Question Grades -->
                <div class="card">
                    <h3 class="card-title">
                        <i class='bx bx-list-check'></i>
                        Question-wise Results
                    </h3>

                    <?php while ($grade = mysqli_fetch_assoc($grades_result)):
                        $awarded = $grade['final_score'] ?? $grade['ai_score'] ?? 0;
                        $max = $grade['max_marks'];
                        $percent = $max > 0 ? ($awarded / $max) * 100 : 0;

                        if ($percent >= 80) {
                            $marks_class = 'marks-full';
                        } elseif ($percent > 0) {
                            $marks_class = 'marks-partial';
                        } else {
                            $marks_class = 'marks-zero';
                        }
                    ?>
                        <div class="question-grade">
                            <div class="question-header">
                                <span class="question-number">Question <?php echo $grade['question_number']; ?></span>
                                <div class="marks-display">
                                    <span class="marks-badge <?php echo $marks_class; ?>">
                                        <?php echo $awarded; ?> / <?php echo $max; ?> marks
                                    </span>
                                </div>
                            </div>

                            <div class="question-text"><?php echo nl2br(htmlspecialchars($grade['question_text'])); ?></div>

                            <?php
                            $feedback = !empty($grade['manual_feedback']) ? $grade['manual_feedback'] : $grade['ai_feedback'];
                            if (!empty($feedback)): ?>
                                <div class="feedback-section">
                                    <div class="feedback-label">Feedback</div>
                                    <div class="feedback-text"><?php echo nl2br(htmlspecialchars($feedback)); ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="grading-info">
                                Graded by: <?php
                                            switch ($grade['grading_method']) {
                                                case 'ai':
                                                    echo 'AI (Automated)';
                                                    break;
                                                case 'manual':
                                                    echo 'Teacher';
                                                    break;
                                                case 'ai_override':
                                                    echo 'Teacher (AI Override)';
                                                    break;
                                                default:
                                                    echo 'Pending';
                                                    break;
                                            }
                                            ?>
                                <?php if ($grade['graded_at']): ?>
                                    • <?php echo date('M d, Y h:i A', strtotime($grade['graded_at'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-print" onclick="window.print()">
                        <i class='bx bx-printer'></i> Print Results
                    </button>
                    <a href="objective_exams.php" class="btn btn-secondary">
                        <i class='bx bx-arrow-back'></i> Back to Objective Exams
                    </a>
                </div>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
</body>

</html>