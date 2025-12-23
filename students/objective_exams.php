<?php

/**
 * Student Objective Exams List
 * 
 * Displays all available objective exams for the student's school.
 * Shows exam status, submission status, and results.
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
$school_id = $_SESSION['school_id'] ?? 1;
$uname = $_SESSION['uname'];

// Get unread message count
$unread_count = getUnreadMessageCount($uname, $conn);

// Get all active objective exams for student's school
$exams_sql = "SELECT oe.*, t.fname as teacher_name,
              (SELECT COUNT(*) FROM objective_questions WHERE exam_id = oe.exam_id) as question_count,
              (SELECT SUM(max_marks) FROM objective_questions WHERE exam_id = oe.exam_id) as total_question_marks
              FROM objective_exm_list oe
              LEFT JOIN teacher t ON oe.teacher_id = t.id
              WHERE oe.school_id = ? AND oe.status IN ('active', 'closed', 'graded')
              ORDER BY oe.exam_date DESC";
$exams_stmt = mysqli_prepare($conn, $exams_sql);
mysqli_stmt_bind_param($exams_stmt, "i", $school_id);
mysqli_stmt_execute($exams_stmt);
$exams_result = mysqli_stmt_get_result($exams_stmt);

// Get student's submissions
$submissions_sql = "SELECT exam_id, submission_id, submission_status, total_marks, submitted_at 
                    FROM objective_submissions WHERE student_id = ?";
$submissions_stmt = mysqli_prepare($conn, $submissions_sql);
mysqli_stmt_bind_param($submissions_stmt, "i", $student_id);
mysqli_stmt_execute($submissions_stmt);
$submissions_result = mysqli_stmt_get_result($submissions_stmt);

$student_submissions = [];
while ($sub = mysqli_fetch_assoc($submissions_result)) {
    $student_submissions[$sub['exam_id']] = $sub;
}

// Messages
$success_msg = isset($_GET['success']) ? $_GET['success'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Objective Exams</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .exam-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .exam-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .exam-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .exam-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .exam-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-active {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-closed {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge-graded {
            background: #e8f5e9;
            color: #388e3c;
        }

        .badge-submitted {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .badge-pending {
            background: #fff8e1;
            color: #ffa000;
        }

        .exam-info {
            margin-bottom: 15px;
        }

        .exam-info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: #666;
            font-size: 13px;
        }

        .exam-info-row i {
            color: #17684f;
            width: 18px;
        }

        .exam-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #17684f;
            color: white;
        }

        .btn-primary:hover {
            background: #124d3a;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-disabled {
            background: #e0e0e0;
            color: #999;
            cursor: not-allowed;
        }

        .result-score {
            font-size: 24px;
            font-weight: 700;
            color: #17684f;
        }

        .result-total {
            font-size: 14px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
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

        .submission-status {
            margin-top: 10px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 8px;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }

        .status-label {
            color: #666;
        }

        .status-value {
            font-weight: 600;
        }

        .status-processing {
            color: #f57c00;
        }

        .status-graded {
            color: #388e3c;
        }

        .status-error {
            color: #c62828;
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
                <a href="#" class="active">
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
                <span class="dashboard">Objective Exams</span>
                <?php if (isset($_SESSION['school_name'])): ?>
                    <span style="font-size: 12px; color: #666; margin-left: 15px; padding: 4px 10px; background: #e8f5e9; border-radius: 12px;">
                        <i class='bx bx-building-house' style="margin-right: 4px;"></i><?php echo htmlspecialchars($_SESSION['school_name']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle'></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (mysqli_num_rows($exams_result) > 0): ?>
                <div class="exam-grid">
                    <?php while ($exam = mysqli_fetch_assoc($exams_result)):
                        $submission = $student_submissions[$exam['exam_id']] ?? null;
                        $has_submitted = $submission !== null;
                        $is_graded = $submission && $submission['submission_status'] === 'graded';
                        $is_active = $exam['status'] === 'active';
                        $deadline_passed = strtotime($exam['submission_deadline']) < time();
                    ?>
                        <div class="exam-card">
                            <div class="exam-header">
                                <h3 class="exam-title"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                                <span class="exam-badge badge-<?php echo $exam['status']; ?>">
                                    <?php echo ucfirst($exam['status']); ?>
                                </span>
                            </div>

                            <div class="exam-info">
                                <div class="exam-info-row">
                                    <i class='bx bx-user'></i>
                                    <span>By <?php echo htmlspecialchars($exam['teacher_name']); ?></span>
                                </div>
                                <div class="exam-info-row">
                                    <i class='bx bx-calendar'></i>
                                    <span><?php echo date('M d, Y h:i A', strtotime($exam['exam_date'])); ?></span>
                                </div>
                                <div class="exam-info-row">
                                    <i class='bx bx-time'></i>
                                    <span>Duration: <?php echo $exam['duration_minutes']; ?> minutes</span>
                                </div>
                                <div class="exam-info-row">
                                    <i class='bx bx-list-ol'></i>
                                    <span><?php echo $exam['question_count']; ?> Questions • <?php echo $exam['total_question_marks'] ?: $exam['total_marks']; ?> Marks</span>
                                </div>
                                <div class="exam-info-row">
                                    <i class='bx bx-stopwatch'></i>
                                    <span>Deadline: <?php echo date('M d, Y h:i A', strtotime($exam['submission_deadline'])); ?></span>
                                </div>
                            </div>

                            <?php if ($has_submitted): ?>
                                <div class="submission-status">
                                    <div class="status-row">
                                        <span class="status-label">Submitted:</span>
                                        <span class="status-value"><?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></span>
                                    </div>
                                    <div class="status-row" style="margin-top: 8px;">
                                        <span class="status-label">Status:</span>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($submission['submission_status']) {
                                            case 'pending':
                                            case 'ocr_processing':
                                                $status_class = 'status-processing';
                                                $status_text = 'Processing...';
                                                break;
                                            case 'ocr_complete':
                                            case 'grading':
                                                $status_class = 'status-processing';
                                                $status_text = 'Grading in progress...';
                                                break;
                                            case 'graded':
                                                $status_class = 'status-graded';
                                                $status_text = 'Graded';
                                                break;
                                            case 'error':
                                                $status_class = 'status-error';
                                                $status_text = 'Error - Contact teacher';
                                                break;
                                        }
                                        ?>
                                        <span class="status-value <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </div>
                                    <?php if ($is_graded): ?>
                                        <div class="status-row" style="margin-top: 8px;">
                                            <span class="status-label">Score:</span>
                                            <span class="result-score"><?php echo $submission['total_marks']; ?></span>
                                            <span class="result-total">/ <?php echo $exam['total_question_marks'] ?: $exam['total_marks']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="exam-actions">
                                <?php if (!$has_submitted && $is_active && !$deadline_passed): ?>
                                    <a href="objective_exam_portal.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-primary">
                                        <i class='bx bx-play'></i> Start Exam
                                    </a>
                                <?php elseif (!$has_submitted && $deadline_passed): ?>
                                    <span class="btn btn-disabled">
                                        <i class='bx bx-time'></i> Deadline Passed
                                    </span>
                                <?php elseif ($has_submitted && $is_graded): ?>
                                    <a href="objective_results.php?submission_id=<?php echo $submission['submission_id']; ?>" class="btn btn-primary">
                                        <i class='bx bx-bar-chart'></i> View Results
                                    </a>
                                <?php elseif ($has_submitted && in_array($submission['submission_status'], ['pending', 'ocr_processing', 'error'])): ?>
                                    <a href="../process_submission.php?id=<?php echo $submission['submission_id']; ?>" class="btn btn-warning" title="Click to retry processing">
                                        <i class='bx bx-refresh'></i> Retry Processing
                                    </a>
                                <?php elseif ($has_submitted): ?>
                                    <span class="btn btn-secondary">
                                        <i class='bx bx-loader-alt bx-spin'></i> Awaiting Results
                                    </span>
                                <?php else: ?>
                                    <span class="btn btn-disabled">
                                        <i class='bx bx-lock'></i> Not Available
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-book-open'></i>
                    <h3>No Objective Exams Available</h3>
                    <p>There are no objective exams available for your school at this time.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="../js/script.js"></script>
</body>

</html>