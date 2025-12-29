<?php

/**
 * Objective Exams List - Teacher View
 * 
 * Lists all objective/descriptive exams created by the teacher.
 * Allows filtering by school and shows exam status.
 */

date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/objective_exam_utils.php';
error_reporting(0);

$teacher_id = $_SESSION['user_id'];

// Get schools the teacher is enrolled in
$enrolled_schools_sql = "SELECT s.school_id, s.school_name 
                         FROM schools s 
                         INNER JOIN teacher_schools ts ON s.school_id = ts.school_id 
                         WHERE ts.teacher_id = ? AND s.status = 'active'
                         ORDER BY ts.is_primary DESC, s.school_name ASC";
$stmt_schools = mysqli_prepare($conn, $enrolled_schools_sql);
mysqli_stmt_bind_param($stmt_schools, "i", $teacher_id);
mysqli_stmt_execute($stmt_schools);
$enrolled_schools = mysqli_stmt_get_result($stmt_schools);

// Get selected school filter
$filter_school = isset($_GET['school_filter']) ? intval($_GET['school_filter']) : 0;

// Build query based on filter
if ($filter_school > 0) {
    $sql = "SELECT oe.*, s.school_name,
                   (SELECT COUNT(*) FROM objective_questions WHERE exam_id = oe.exam_id) as question_count,
                   (SELECT COUNT(*) FROM objective_submissions WHERE exam_id = oe.exam_id) as submission_count,
                   (SELECT COUNT(*) FROM objective_submissions WHERE exam_id = oe.exam_id AND submission_status = 'graded') as graded_count
            FROM objective_exm_list oe
            LEFT JOIN schools s ON oe.school_id = s.school_id
            WHERE oe.teacher_id = ? AND oe.school_id = ?
            ORDER BY oe.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $filter_school);
} else {
    $sql = "SELECT oe.*, s.school_name,
                   (SELECT COUNT(*) FROM objective_questions WHERE exam_id = oe.exam_id) as question_count,
                   (SELECT COUNT(*) FROM objective_submissions WHERE exam_id = oe.exam_id) as submission_count,
                   (SELECT COUNT(*) FROM objective_submissions WHERE exam_id = oe.exam_id AND submission_status = 'graded') as graded_count
            FROM objective_exm_list oe
            LEFT JOIN schools s ON oe.school_id = s.school_id
            WHERE oe.teacher_id = ?
            ORDER BY oe.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Handle success/error messages
$success_msg = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
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
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
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

        .status-graded {
            background: #cce5ff;
            color: #004085;
        }

        .grading-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .grading-ai {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .grading-manual {
            background: #e3f2fd;
            color: #1565c0;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin: 2px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-edit {
            background: #17684f;
            color: white;
        }

        .btn-questions {
            background: #2196F3;
            color: white;
        }

        .btn-grade {
            background: #ff9800;
            color: white;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-view {
            background: #6c757d;
            color: white;
        }

        .action-btn:hover {
            opacity: 0.85;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 15px;
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

        .exam-stats {
            display: flex;
            gap: 10px;
            font-size: 12px;
            color: #666;
        }

        .exam-stats span {
            background: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .add-exam-card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .add-exam-card h3 {
            color: #17684f;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #17684f;
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .quick-action-btn {
            padding: 12px 25px;
            background: #17684f;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-action-btn:hover {
            background: #11533e;
        }

        .quick-action-btn.secondary {
            background: #6c757d;
        }

        .quick-action-btn.secondary:hover {
            background: #5a6268;
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
            <li><a href="#" class="active"><i class='bx bx-edit'></i><span class="links_name">Objective Exams</span></a></li>
            <li><a href="results.php"><i class='bx bxs-bar-chart-alt-2'></i><span class="links_name">Results</span></a></li>
            <li><a href="records.php"><i class='bx bxs-user-circle'></i><span class="links_name">Records</span></a></li>
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="school_management.php"><i class='bx bx-building-house'></i><span class="links_name">Schools</span></a></li>
            <li><a href="teaching_activity.php"><i class='bx bx-map-pin'></i><span class="links_name">Teaching Activity</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Objective Exams</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?php echo $error_msg; ?></div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="add_objective_exam.php" class="quick-action-btn">
                    <i class='bx bx-plus'></i> Create New Objective Exam
                </a>
                <a href="ocr_status.php" class="quick-action-btn secondary">
                    <i class='bx bx-scan'></i> OCR Status
                </a>
            </div>

            <!-- School Filter -->
            <div style="margin-bottom: 20px; padding: 15px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <form method="GET" action="objective_exams.php" style="display: flex; align-items: center; gap: 15px;">
                    <label for="school_filter" style="font-weight: 600; color: #17684f;">Filter by School:</label>
                    <select name="school_filter" id="school_filter" style="padding: 8px 15px; border-radius: 6px; border: 1px solid #ddd; min-width: 200px;" onchange="this.form.submit()">
                        <option value="0">All Schools</option>
                        <?php
                        mysqli_data_seek($enrolled_schools, 0);
                        while ($school = mysqli_fetch_assoc($enrolled_schools)) {
                            $selected = ($filter_school == $school['school_id']) ? 'selected' : '';
                            echo "<option value='{$school['school_id']}' $selected>{$school['school_name']}</option>";
                        }
                        ?>
                    </select>
                </form>
            </div>

            <!-- Exams Table -->
            <div class="stat-boxes">
                <div class="recent-stat box" style="padding: 0; width: 100%;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Exam Name</th>
                                <th>School</th>
                                <th>Grading</th>
                                <th>Status</th>
                                <th>Questions</th>
                                <th>Submissions</th>
                                <th>Exam Date</th>
                                <th>Deadline</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            if (mysqli_num_rows($result) > 0):
                                while ($row = mysqli_fetch_assoc($result)):
                                    $status_class = 'status-' . $row['status'];
                                    $grading_class = 'grading-' . $row['grading_mode'];
                            ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['exam_name']); ?></strong>
                                            <div class="exam-stats">
                                                <span><i class='bx bx-trophy'></i> <?php echo $row['total_marks']; ?> marks</span>
                                                <span><i class='bx bx-time'></i> <?php echo $row['duration_minutes']; ?> min</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                                <?php echo htmlspecialchars($row['school_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="grading-badge <?php echo $grading_class; ?>">
                                                <i class='bx <?php echo $row['grading_mode'] == 'ai' ? 'bx-brain' : 'bx-user'; ?>'></i>
                                                <?php echo strtoupper($row['grading_mode']); ?>
                                            </span>
                                        </td>
                                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $row['status']; ?></span></td>
                                        <td>
                                            <?php echo $row['question_count']; ?>
                                            <?php if ($row['question_count'] == 0): ?>
                                                <span style="color: #dc3545; font-size: 11px;"><br>No questions!</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $row['graded_count']; ?>/<?php echo $row['submission_count']; ?>
                                            <?php if ($row['submission_count'] > $row['graded_count']): ?>
                                                <span style="color: #ff9800; font-size: 11px;"><br>Pending grading</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($row['exam_date'])); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($row['submission_deadline'])); ?></td>
                                        <td>
                                            <a href="objective_questions.php?exam_id=<?php echo $row['exam_id']; ?>" class="action-btn btn-questions" title="Manage Questions">
                                                <i class='bx bx-list-ul'></i>
                                            </a>
                                            <?php if ($row['submission_count'] > 0): ?>
                                                <a href="grade_objective.php?exam_id=<?php echo $row['exam_id']; ?>" class="action-btn btn-grade" title="Grade Submissions">
                                                    <i class='bx bx-check-double'></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="edit_objective_exam.php?exam_id=<?php echo $row['exam_id']; ?>" class="action-btn btn-edit" title="Edit Exam">
                                                <i class='bx bx-edit'></i>
                                            </a>
                                            <a href="delete_objective_exam.php?exam_id=<?php echo $row['exam_id']; ?>" class="action-btn btn-delete" title="Delete Exam" onclick="return confirm('Are you sure you want to delete this exam? This action cannot be undone.');">
                                                <i class='bx bx-trash'></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php
                                endwhile;
                            else:
                                ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
                                        <i class='bx bx-folder-open' style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                                        No objective exams found.
                                        <a href="add_objective_exam.php" style="color: #17684f;">Create your first exam</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
</body>

</html>