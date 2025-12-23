<?php

/**
 * Delete Objective Exam
 * 
 * Handles deletion of objective exams with proper authorization.
 * Deletes all associated data (questions, submissions, images, grades).
 */

session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}

include '../config.php';
require_once '../utils/objective_exam_utils.php';

$teacher_id = $_SESSION['user_id'];

// Get exam ID from URL
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id <= 0) {
    header("Location: objective_exams.php?error=" . urlencode("Invalid exam ID."));
    exit;
}

// Verify teacher owns this exam
if (!teacherOwnsExam($conn, $exam_id, $teacher_id)) {
    header("Location: objective_exams.php?error=" . urlencode("You don't have permission to delete this exam."));
    exit;
}

// Get exam details for confirmation and file cleanup
$exam_sql = "SELECT * FROM objective_exm_list WHERE exam_id = ?";
$exam_stmt = mysqli_prepare($conn, $exam_sql);
mysqli_stmt_bind_param($exam_stmt, "i", $exam_id);
mysqli_stmt_execute($exam_stmt);
$exam = mysqli_fetch_assoc(mysqli_stmt_get_result($exam_stmt));

if (!$exam) {
    header("Location: objective_exams.php?error=" . urlencode("Exam not found."));
    exit;
}

// Check for submissions
$sub_count_sql = "SELECT COUNT(*) as cnt FROM objective_submissions WHERE exam_id = ?";
$sub_count_stmt = mysqli_prepare($conn, $sub_count_sql);
mysqli_stmt_bind_param($sub_count_stmt, "i", $exam_id);
mysqli_stmt_execute($sub_count_stmt);
$submission_count = mysqli_fetch_assoc(mysqli_stmt_get_result($sub_count_stmt))['cnt'];

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Begin transaction
    mysqli_begin_transaction($conn);

    try {
        // 1. Get all image paths for cleanup
        $images_sql = "SELECT oai.image_path 
                       FROM objective_answer_images oai
                       JOIN objective_submissions os ON oai.submission_id = os.submission_id
                       WHERE os.exam_id = ?";
        $images_stmt = mysqli_prepare($conn, $images_sql);
        mysqli_stmt_bind_param($images_stmt, "i", $exam_id);
        mysqli_stmt_execute($images_stmt);
        $images_result = mysqli_stmt_get_result($images_stmt);

        $image_paths = [];
        while ($img = mysqli_fetch_assoc($images_result)) {
            $image_paths[] = $img['image_path'];
        }

        // 2. Delete grades
        $delete_grades_sql = "DELETE oag FROM objective_answer_grades oag
                              JOIN objective_submissions os ON oag.submission_id = os.submission_id
                              WHERE os.exam_id = ?";
        $delete_grades_stmt = mysqli_prepare($conn, $delete_grades_sql);
        mysqli_stmt_bind_param($delete_grades_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_grades_stmt);

        // 3. Delete answer images
        $delete_images_sql = "DELETE oai FROM objective_answer_images oai
                              JOIN objective_submissions os ON oai.submission_id = os.submission_id
                              WHERE os.exam_id = ?";
        $delete_images_stmt = mysqli_prepare($conn, $delete_images_sql);
        mysqli_stmt_bind_param($delete_images_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_images_stmt);

        // 4. Delete submissions
        $delete_subs_sql = "DELETE FROM objective_submissions WHERE exam_id = ?";
        $delete_subs_stmt = mysqli_prepare($conn, $delete_subs_sql);
        mysqli_stmt_bind_param($delete_subs_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_subs_stmt);

        // 5. Delete questions
        $delete_questions_sql = "DELETE FROM objective_questions WHERE exam_id = ?";
        $delete_questions_stmt = mysqli_prepare($conn, $delete_questions_sql);
        mysqli_stmt_bind_param($delete_questions_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_questions_stmt);

        // 6. Delete the exam
        $delete_exam_sql = "DELETE FROM objective_exm_list WHERE exam_id = ?";
        $delete_exam_stmt = mysqli_prepare($conn, $delete_exam_sql);
        mysqli_stmt_bind_param($delete_exam_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_exam_stmt);

        // Commit transaction
        mysqli_commit($conn);

        // 7. Clean up files
        // Delete answer key if exists
        if (!empty($exam['answer_key_path']) && file_exists($exam['answer_key_path'])) {
            @unlink($exam['answer_key_path']);
        }

        // Delete student answer images
        foreach ($image_paths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        header("Location: objective_exams.php?success=" . urlencode("Exam '{$exam['exam_name']}' has been deleted successfully."));
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: objective_exams.php?error=" . urlencode("Failed to delete exam: " . $e->getMessage()));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Delete Exam - <?php echo htmlspecialchars($exam['exam_name']); ?></title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .confirm-container {
            max-width: 600px;
            margin: 50px auto;
        }

        .confirm-card {
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .confirm-card .icon {
            width: 80px;
            height: 80px;
            background: #f8d7da;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .confirm-card .icon i {
            font-size: 40px;
            color: #dc3545;
        }

        .confirm-card h2 {
            color: #333;
            margin: 0 0 15px 0;
        }

        .confirm-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .exam-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }

        .exam-details .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .exam-details .detail-row:last-child {
            border-bottom: none;
        }

        .exam-details .label {
            color: #666;
        }

        .exam-details .value {
            font-weight: 600;
            color: #333;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }

        .warning-box h4 {
            color: #856404;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-box ul {
            margin: 0;
            padding-left: 20px;
            color: #856404;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
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
                <span class="dashboard">Delete Exam</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="confirm-container">
                <div class="confirm-card">
                    <div class="icon">
                        <i class='bx bx-trash'></i>
                    </div>

                    <h2>Delete Objective Exam?</h2>
                    <p>You are about to permanently delete this exam. This action cannot be undone.</p>

                    <div class="exam-details">
                        <div class="detail-row">
                            <span class="label">Exam Name</span>
                            <span class="value"><?php echo htmlspecialchars($exam['exam_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Grading Mode</span>
                            <span class="value"><?php echo strtoupper($exam['grading_mode']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Status</span>
                            <span class="value"><?php echo ucfirst($exam['status']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Submissions</span>
                            <span class="value"><?php echo $submission_count; ?></span>
                        </div>
                    </div>

                    <div class="warning-box">
                        <h4><i class='bx bx-error'></i> This will permanently delete:</h4>
                        <ul>
                            <li>All exam questions</li>
                            <li>All student submissions (<?php echo $submission_count; ?> total)</li>
                            <li>All uploaded answer images</li>
                            <li>All grades and feedback</li>
                            <li>The answer key file (if any)</li>
                        </ul>
                    </div>

                    <div class="actions">
                        <form method="POST">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class='bx bx-trash'></i> Yes, Delete Permanently
                            </button>
                        </form>
                        <a href="objective_exams.php" class="btn btn-secondary">
                            <i class='bx bx-x'></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
</body>

</html>