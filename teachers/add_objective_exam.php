<?php

/**
 * Add Objective Exam - Teacher View
 * 
 * Form to create a new objective/descriptive exam.
 * Includes grading mode selection (AI or Manual) which is locked after creation.
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    // Validate required fields
    $exam_name = trim(mysqli_real_escape_string($conn, $_POST['exam_name']));
    $school_id = intval($_POST['school_id']);
    $grading_mode = in_array($_POST['grading_mode'], ['ai', 'manual']) ? $_POST['grading_mode'] : 'manual';
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
        // Verify teacher is enrolled in this school
        $verify_sql = "SELECT * FROM teacher_schools 
                       WHERE teacher_id = ? 
                       AND school_id = ? 
                       AND enrollment_status = 'active'";
        $verify_stmt = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($verify_stmt, "ii", $teacher_id, $school_id);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);

        if (mysqli_num_rows($verify_result) == 0) {
            $error_msg = 'You are not enrolled in this school.';
        } else {
            // Insert the exam
            $insert_sql = "INSERT INTO objective_exm_list 
                           (exam_name, school_id, teacher_id, grading_mode, total_marks, passing_marks, 
                            exam_date, submission_deadline, duration_minutes, exam_instructions, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param(
                $insert_stmt,
                "siisiiisss",
                $exam_name,
                $school_id,
                $teacher_id,
                $grading_mode,
                $total_marks,
                $passing_marks,
                $exam_date,
                $submission_deadline,
                $duration_minutes,
                $exam_instructions
            );

            if (mysqli_stmt_execute($insert_stmt)) {
                $exam_id = mysqli_insert_id($conn);

                // Redirect to add questions
                header("Location: objective_questions.php?exam_id=$exam_id&new=1");
                exit;
            } else {
                $error_msg = 'Failed to create exam. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Create Objective Exam</title>
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

        .form-row.full {
            grid-template-columns: 1fr;
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

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .grading-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .grading-option {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .grading-option:hover {
            border-color: #17684f;
        }

        .grading-option.selected {
            border-color: #17684f;
            background: #f0f9f6;
        }

        .grading-option input {
            display: none;
        }

        .grading-option h4 {
            margin: 0 0 10px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .grading-option h4 i {
            font-size: 24px;
            color: #17684f;
        }

        .grading-option p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }

        .grading-option .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 10px;
        }

        .badge-ai {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-manual {
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

        .btn-cancel {
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

        .btn-cancel:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .warning-box h4 {
            color: #856404;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-box p {
            margin: 0;
            color: #856404;
            font-size: 13px;
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
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Create Objective Exam</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="form-container">
                <?php if ($error_msg): ?>
                    <div class="alert alert-error">
                        <i class='bx bx-error-circle'></i> <?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="add_objective_exam.php">
                    <!-- Basic Information -->
                    <div class="form-card">
                        <h2><i class='bx bx-info-circle'></i> Exam Information</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="exam_name">Exam Name <span class="required">*</span></label>
                                <input type="text" id="exam_name" name="exam_name"
                                    placeholder="e.g., Physics Mid-Term Exam"
                                    value="<?php echo isset($_POST['exam_name']) ? htmlspecialchars($_POST['exam_name']) : ''; ?>"
                                    required minlength="3" maxlength="255">
                            </div>
                            <div class="form-group">
                                <label for="school_id">School <span class="required">*</span></label>
                                <select id="school_id" name="school_id" required>
                                    <option value="">-- Select School --</option>
                                    <?php
                                    while ($school = mysqli_fetch_assoc($enrolled_schools)) {
                                        $selected = (isset($_POST['school_id']) && $_POST['school_id'] == $school['school_id']) ? 'selected' : '';
                                        echo "<option value='{$school['school_id']}' $selected>{$school['school_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="exam_instructions">Exam Instructions</label>
                            <textarea id="exam_instructions" name="exam_instructions"
                                placeholder="Enter any instructions for students taking this exam..."><?php echo isset($_POST['exam_instructions']) ? htmlspecialchars($_POST['exam_instructions']) : ''; ?></textarea>
                            <div class="help-text">These instructions will be shown to students before they start the exam.</div>
                        </div>
                    </div>

                    <!-- Grading Mode -->
                    <div class="form-card">
                        <h2><i class='bx bx-check-double'></i> Grading Mode</h2>

                        <div class="warning-box">
                            <h4><i class='bx bx-lock'></i> Important</h4>
                            <p>The grading mode cannot be changed after the exam is created. Choose carefully.</p>
                        </div>

                        <div class="grading-options">
                            <label class="grading-option" id="option-ai">
                                <input type="radio" name="grading_mode" value="ai"
                                    <?php echo (!isset($_POST['grading_mode']) || $_POST['grading_mode'] == 'ai') ? 'checked' : ''; ?>>
                                <h4>
                                    <i class='bx bx-brain'></i> AI Grading
                                    <span class="badge badge-ai">Automatic</span>
                                </h4>
                                <p>Uses Groq AI to automatically grade student answers by comparing them with the answer key.
                                    Provides semantic analysis and partial credit.</p>
                            </label>
                            <label class="grading-option" id="option-manual">
                                <input type="radio" name="grading_mode" value="manual"
                                    <?php echo (isset($_POST['grading_mode']) && $_POST['grading_mode'] == 'manual') ? 'checked' : ''; ?>>
                                <h4>
                                    <i class='bx bx-user'></i> Manual Grading
                                    <span class="badge badge-manual">Teacher Review</span>
                                </h4>
                                <p>You will manually review and grade each student's submission.
                                    OCR will still extract text to assist your review.</p>
                            </label>
                        </div>
                    </div>

                    <!-- Marks & Duration -->
                    <div class="form-card">
                        <h2><i class='bx bx-trophy'></i> Marks & Duration</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="total_marks">Total Marks <span class="required">*</span></label>
                                <input type="number" id="total_marks" name="total_marks"
                                    value="<?php echo isset($_POST['total_marks']) ? $_POST['total_marks'] : '100'; ?>"
                                    required min="1" max="1000">
                            </div>
                            <div class="form-group">
                                <label for="passing_marks">Passing Marks <span class="required">*</span></label>
                                <input type="number" id="passing_marks" name="passing_marks"
                                    value="<?php echo isset($_POST['passing_marks']) ? $_POST['passing_marks'] : '40'; ?>"
                                    required min="0" max="1000">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes) <span class="required">*</span></label>
                            <input type="number" id="duration_minutes" name="duration_minutes"
                                value="<?php echo isset($_POST['duration_minutes']) ? $_POST['duration_minutes'] : '60'; ?>"
                                required min="1" max="480">
                            <div class="help-text">Maximum time allowed for students to complete the exam.</div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="form-card">
                        <h2><i class='bx bx-calendar'></i> Schedule</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="exam_date">Exam Start Date & Time <span class="required">*</span></label>
                                <input type="datetime-local" id="exam_date" name="exam_date"
                                    value="<?php echo isset($_POST['exam_date']) ? $_POST['exam_date'] : ''; ?>"
                                    required>
                                <div class="help-text">When students can start taking the exam.</div>
                            </div>
                            <div class="form-group">
                                <label for="submission_deadline">Submission Deadline <span class="required">*</span></label>
                                <input type="datetime-local" id="submission_deadline" name="submission_deadline"
                                    value="<?php echo isset($_POST['submission_deadline']) ? $_POST['submission_deadline'] : ''; ?>"
                                    required>
                                <div class="help-text">Last date/time for students to submit answers.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="form-actions">
                        <button type="submit" name="create_exam" class="btn-submit">
                            <i class='bx bx-plus'></i> Create Exam & Add Questions
                        </button>
                        <a href="objective_exams.php" class="btn-cancel">
                            <i class='bx bx-x'></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
    <script>
        // Handle grading option selection
        document.querySelectorAll('.grading-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.grading-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
            });

            // Set initial selected state
            const radio = option.querySelector('input[type="radio"]');
            if (radio.checked) {
                option.classList.add('selected');
            }
        });

        // Validate passing marks doesn't exceed total marks
        document.getElementById('passing_marks').addEventListener('change', function() {
            const total = parseInt(document.getElementById('total_marks').value) || 0;
            if (parseInt(this.value) > total) {
                this.value = total;
            }
        });

        document.getElementById('total_marks').addEventListener('change', function() {
            const passing = document.getElementById('passing_marks');
            if (parseInt(passing.value) > parseInt(this.value)) {
                passing.value = this.value;
            }
        });
    </script>
</body>

</html>