<?php

/**
 * Edit Objective Exam - Teacher View
 * 
 * Edit exam details (name, schedule, marks, instructions).
 * Note: Grading mode cannot be changed after creation.
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

// Check if exam has submissions
$sub_count_sql = "SELECT COUNT(*) as cnt FROM objective_submissions WHERE exam_id = ?";
$sub_count_stmt = mysqli_prepare($conn, $sub_count_sql);
mysqli_stmt_bind_param($sub_count_stmt, "i", $exam_id);
mysqli_stmt_execute($sub_count_stmt);
$has_submissions = mysqli_fetch_assoc(mysqli_stmt_get_result($sub_count_stmt))['cnt'] > 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_exam'])) {
    $exam_name = trim(mysqli_real_escape_string($conn, $_POST['exam_name']));
    $school_id = intval($_POST['school_id']);
    $total_marks = intval($_POST['total_marks']);
    $passing_marks = intval($_POST['passing_marks']);
    $exam_date = mysqli_real_escape_string($conn, $_POST['exam_date']);
    $submission_deadline = mysqli_real_escape_string($conn, $_POST['submission_deadline']);
    $duration_minutes = intval($_POST['duration_minutes']);
    $exam_instructions = trim(mysqli_real_escape_string($conn, $_POST['exam_instructions']));

    // Validation
    if (empty($exam_name)) {
        $error_msg = 'Exam name is required.';
    } elseif ($school_id <= 0) {
        $error_msg = 'Please select a school.';
    } elseif ($total_marks <= 0) {
        $error_msg = 'Total marks must be greater than 0.';
    } elseif ($passing_marks < 0 || $passing_marks > $total_marks) {
        $error_msg = 'Passing marks must be between 0 and total marks.';
    } elseif (empty($exam_date) || empty($submission_deadline)) {
        $error_msg = 'Exam date and submission deadline are required.';
    } elseif (strtotime($submission_deadline) <= strtotime($exam_date)) {
        $error_msg = 'Submission deadline must be after exam date.';
    } elseif ($duration_minutes <= 0) {
        $error_msg = 'Duration must be greater than 0 minutes.';
    } else {
        // Verify teacher is enrolled in the selected school
        $verify_sql = "SELECT * FROM teacher_schools 
                       WHERE teacher_id = ? AND school_id = ? AND enrollment_status = 'active'";
        $verify_stmt = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($verify_stmt, "ii", $teacher_id, $school_id);
        mysqli_stmt_execute($verify_stmt);

        if (mysqli_num_rows(mysqli_stmt_get_result($verify_stmt)) == 0) {
            $error_msg = 'You are not enrolled in this school.';
        } else {
            // Update the exam
            $update_sql = "UPDATE objective_exm_list SET 
                           exam_name = ?, school_id = ?, total_marks = ?, passing_marks = ?,
                           exam_date = ?, submission_deadline = ?, duration_minutes = ?, exam_instructions = ?
                           WHERE exam_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param(
                $update_stmt,
                "siiiisisi",
                $exam_name,
                $school_id,
                $total_marks,
                $passing_marks,
                $exam_date,
                $submission_deadline,
                $duration_minutes,
                $exam_instructions,
                $exam_id
            );

            if (mysqli_stmt_execute($update_stmt)) {
                $success_msg = 'Exam updated successfully.';

                // Refresh exam data
                mysqli_stmt_execute($exam_stmt);
                $exam = mysqli_fetch_assoc(mysqli_stmt_get_result($exam_stmt));
            } else {
                $error_msg = 'Failed to update exam.';
            }
        }
    }
}

// Format datetime for input fields
$exam_date_formatted = date('Y-m-d\TH:i', strtotime($exam['exam_date']));
$deadline_formatted = date('Y-m-d\TH:i', strtotime($exam['submission_deadline']));
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Edit Exam - <?php echo htmlspecialchars($exam['exam_name']); ?></title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-card {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .form-card h2 {
            color: #17684f;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #17684f;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #17684f;
            outline: none;
        }

        .form-group input:disabled,
        .form-group select:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .locked-field {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .locked-field i {
            color: #856404;
            font-size: 20px;
        }

        .locked-field .value {
            font-weight: 600;
            color: #333;
        }

        .locked-field .note {
            font-size: 12px;
            color: #856404;
        }

        .grading-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
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

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .btn-submit {
            background: #17684f;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-submit:hover {
            background: #11533e;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .quick-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .quick-link {
            padding: 10px 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-link:hover {
            border-color: #17684f;
            color: #17684f;
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
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="school_management.php"><i class='bx bx-building-house'></i><span class="links_name">Schools</span></a></li>
            <li><a href="upload_material.php"><i class='bx bx-cloud-upload'></i><span class="links_name">Upload Material</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Edit Objective Exam</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="form-container">
                <?php if ($error_msg): ?>
                    <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?php echo $error_msg; ?></div>
                <?php endif; ?>
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?php echo $success_msg; ?></div>
                <?php endif; ?>

                <?php if ($has_submissions): ?>
                    <div class="alert alert-warning">
                        <i class='bx bx-info-circle'></i>
                        This exam has student submissions. Some changes may affect existing data.
                    </div>
                <?php endif; ?>

                <!-- Quick Links -->
                <div class="quick-links">
                    <a href="objective_questions.php?exam_id=<?php echo $exam_id; ?>" class="quick-link">
                        <i class='bx bx-list-ul'></i> Manage Questions
                    </a>
                    <a href="upload_answer_key.php?exam_id=<?php echo $exam_id; ?>" class="quick-link">
                        <i class='bx bx-key'></i> Answer Key
                    </a>
                    <a href="grade_objective.php?exam_id=<?php echo $exam_id; ?>" class="quick-link">
                        <i class='bx bx-check-double'></i> Grade Submissions
                    </a>
                </div>

                <form method="POST" action="edit_objective_exam.php?exam_id=<?php echo $exam_id; ?>">
                    <!-- Basic Information -->
                    <div class="form-card">
                        <h2><i class='bx bx-info-circle'></i> Exam Information</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="exam_name">Exam Name <span class="required">*</span></label>
                                <input type="text" id="exam_name" name="exam_name"
                                    value="<?php echo htmlspecialchars($exam['exam_name']); ?>"
                                    required minlength="3" maxlength="255">
                            </div>
                            <div class="form-group">
                                <label for="school_id">School <span class="required">*</span></label>
                                <select id="school_id" name="school_id" required <?php echo $has_submissions ? 'disabled' : ''; ?>>
                                    <option value="">-- Select School --</option>
                                    <?php
                                    while ($school = mysqli_fetch_assoc($enrolled_schools)) {
                                        $selected = ($exam['school_id'] == $school['school_id']) ? 'selected' : '';
                                        echo "<option value='{$school['school_id']}' $selected>{$school['school_name']}</option>";
                                    }
                                    ?>
                                </select>
                                <?php if ($has_submissions): ?>
                                    <input type="hidden" name="school_id" value="<?php echo $exam['school_id']; ?>">
                                    <div class="help-text">Cannot change school after students have submitted.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Locked Grading Mode -->
                        <div class="form-group">
                            <label>Grading Mode</label>
                            <div class="locked-field">
                                <i class='bx bx-lock'></i>
                                <div>
                                    <span class="grading-badge grading-<?php echo $exam['grading_mode']; ?>">
                                        <i class='bx bx-<?php echo $exam['grading_mode'] == 'ai' ? 'brain' : 'user'; ?>'></i>
                                        <?php echo strtoupper($exam['grading_mode']); ?> Grading
                                    </span>
                                    <div class="note">Grading mode cannot be changed after exam creation.</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="exam_instructions">Exam Instructions</label>
                            <textarea id="exam_instructions" name="exam_instructions"
                                placeholder="Enter any instructions for students..."><?php echo htmlspecialchars($exam['exam_instructions']); ?></textarea>
                        </div>
                    </div>

                    <!-- Marks & Duration -->
                    <div class="form-card">
                        <h2><i class='bx bx-trophy'></i> Marks & Duration</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="total_marks">Total Marks <span class="required">*</span></label>
                                <input type="number" id="total_marks" name="total_marks"
                                    value="<?php echo $exam['total_marks']; ?>"
                                    required min="1" max="1000">
                            </div>
                            <div class="form-group">
                                <label for="passing_marks">Passing Marks <span class="required">*</span></label>
                                <input type="number" id="passing_marks" name="passing_marks"
                                    value="<?php echo $exam['passing_marks']; ?>"
                                    required min="0" max="1000">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes) <span class="required">*</span></label>
                            <input type="number" id="duration_minutes" name="duration_minutes"
                                value="<?php echo $exam['duration_minutes']; ?>"
                                required min="1" max="480">
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="form-card">
                        <h2><i class='bx bx-calendar'></i> Schedule</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="exam_date">Exam Start Date & Time <span class="required">*</span></label>
                                <input type="datetime-local" id="exam_date" name="exam_date"
                                    value="<?php echo $exam_date_formatted; ?>"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="submission_deadline">Submission Deadline <span class="required">*</span></label>
                                <input type="datetime-local" id="submission_deadline" name="submission_deadline"
                                    value="<?php echo $deadline_formatted; ?>"
                                    required>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="form-actions">
                        <button type="submit" name="update_exam" class="btn-submit">
                            <i class='bx bx-save'></i> Save Changes
                        </button>
                        <a href="objective_exams.php" class="btn-secondary">
                            <i class='bx bx-arrow-back'></i> Back to List
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
    <script>
        // Validate passing marks
        document.getElementById('passing_marks').addEventListener('change', function() {
            const total = parseInt(document.getElementById('total_marks').value) || 0;
            if (parseInt(this.value) > total) {
                this.value = total;
            }
        });
    </script>
</body>

</html>