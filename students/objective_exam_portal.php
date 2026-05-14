<?php

/**
 * Student Objective Exam Portal
 * 
 * Displays exam questions and allows students to upload answer sheets.
 * Multi-image upload interface with drag-and-drop support.
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

// Get exam ID
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id <= 0) {
    header("Location: objective_exams.php?error=" . urlencode("Invalid exam ID."));
    exit;
}

// Verify student can access this exam
if (!studentCanAccessExam($conn, $exam_id, $student_id)) {
    header("Location: objective_exams.php?error=" . urlencode("You don't have access to this exam."));
    exit;
}

// Get exam details
$exam = getObjectiveExamById($conn, $exam_id);
if (!$exam) {
    header("Location: objective_exams.php?error=" . urlencode("Exam not found."));
    exit;
}

// Check if exam is active
if ($exam['status'] !== 'active') {
    header("Location: objective_exams.php?error=" . urlencode("This exam is not currently active."));
    exit;
}

// Check deadline
if (strtotime($exam['submission_deadline']) < time()) {
    header("Location: objective_exams.php?error=" . urlencode("The submission deadline has passed."));
    exit;
}

// Check if student already submitted
$existing_submission = getStudentObjectiveSubmission($conn, $exam_id, $student_id);
if ($existing_submission) {
    header("Location: objective_exams.php?error=" . urlencode("You have already submitted this exam."));
    exit;
}

// Get exam questions
$questions = getObjectiveExamQuestions($conn, $exam_id);

// Get unread message count
$unread_count = getUnreadMessageCount($uname, $conn);

// Calculate total marks from questions
$total_marks = 0;
foreach ($questions as $q) {
    $total_marks += $q['max_marks'];
}
if ($total_marks == 0) {
    $total_marks = $exam['total_marks'];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($exam['exam_name']); ?> - Exam Portal</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body.exam-mode .sidebar,
        body.exam-mode .home-section nav .sidebar-button {
            display: none !important;
        }

        body.exam-mode .home-section {
            left: 0 !important;
            width: 100% !important;
        }

        body.exam-mode .home-section nav {
            left: 0 !important;
            width: 100% !important;
        }

        body.exam-mode .home-section .home-content {
            padding-top: 104px;
        }

        .exam-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .exam-header-card {
            background: linear-gradient(135deg, #17684f 0%, #1a8a6a 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .exam-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 15px 0;
        }

        .exam-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .exam-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            opacity: 0.95;
        }

        .instructions-card {
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
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: #17684f;
        }

        .instructions-text {
            color: #666;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .questions-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .question-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 12px;
            background: #fafafa;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .question-number {
            font-weight: 600;
            color: #17684f;
        }

        .question-marks {
            background: #e8f5e9;
            color: #388e3c;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .question-text {
            color: #333;
            line-height: 1.6;
        }

        .upload-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .upload-zone {
            border: 2px dashed #ccc;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: #17684f;
            background: #e8f5e9;
        }

        .upload-zone i {
            font-size: 48px;
            color: #999;
            margin-bottom: 15px;
        }

        .upload-zone:hover i,
        .upload-zone.dragover i {
            color: #17684f;
        }

        .upload-zone h4 {
            color: #333;
            margin: 0 0 10px 0;
        }

        .upload-zone p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .file-input {
            display: none;
        }

        .preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .preview-item {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .preview-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .preview-info {
            padding: 8px;
            font-size: 12px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .preview-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 24px;
            height: 24px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .preview-remove:hover {
            background: #c82333;
        }

        .submit-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .submit-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 10px;
            background: #fff3e0;
            border-radius: 8px;
            color: #f57c00;
            font-size: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
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

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .timer-display {
            position: fixed;
            top: 80px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            z-index: 100;
        }

        .timer-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .timer-value {
            font-size: 24px;
            font-weight: 700;
            color: #17684f;
        }

        .timer-value.warning {
            color: #f57c00;
        }

        .timer-value.danger {
            color: #dc3545;
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

        .upload-progress {
            display: none;
            margin-top: 15px;
        }

        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #17684f;
            width: 0%;
            transition: width 0.3s;
        }

        .progress-text {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
    </style>
        </style>
</head>

<body class="exam-mode">
    <div class="sidebar" aria-hidden="true">
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
                <a href="study_material.php">
                    <i class='bx bx-book-open'></i>
                    <span class="links_name">Study Material</span>
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
            <div class="sidebar-button" aria-hidden="true">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Exam Portal</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <!-- Timer Display -->
        <div class="timer-display">
            <div class="timer-label">Time Remaining</div>
            <div class="timer-value" id="timer"><?php echo floor($exam['duration_minutes'] / 60); ?>:<?php echo str_pad($exam['duration_minutes'] % 60, 2, '0', STR_PAD_LEFT); ?>:00</div>
        </div>

        <div class="home-content">
            <div class="exam-container">
                <!-- Exam Header -->
                <div class="exam-header-card">
                    <h1 class="exam-title"><?php echo htmlspecialchars($exam['exam_name']); ?></h1>
                    <div class="exam-meta">
                        <div class="exam-meta-item">
                            <i class='bx bx-list-ol'></i>
                            <span><?php echo count($questions); ?> Questions</span>
                        </div>
                        <div class="exam-meta-item">
                            <i class='bx bx-star'></i>
                            <span><?php echo $total_marks; ?> Total Marks</span>
                        </div>
                        <div class="exam-meta-item">
                            <i class='bx bx-time'></i>
                            <span><?php echo $exam['duration_minutes']; ?> Minutes</span>
                        </div>
                        <div class="exam-meta-item">
                            <i class='bx bx-check-circle'></i>
                            <span>Pass: <?php echo $exam['passing_marks']; ?> Marks</span>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <?php if (!empty($exam['exam_instructions'])): ?>
                    <div class="instructions-card">
                        <h3 class="card-title">
                            <i class='bx bx-info-circle'></i>
                            Instructions
                        </h3>
                        <div class="instructions-text"><?php echo nl2br(htmlspecialchars($exam['exam_instructions'])); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Questions -->
                <div class="questions-card">
                    <h3 class="card-title">
                        <i class='bx bx-list-check'></i>
                        Questions
                    </h3>
                    <?php foreach ($questions as $q): ?>
                        <div class="question-item">
                            <div class="question-header">
                                <span class="question-number">Question <?php echo $q['question_number']; ?></span>
                                <span class="question-marks"><?php echo $q['max_marks']; ?> marks</span>
                            </div>
                            <div class="question-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Upload Section -->
                <form id="uploadForm" action="submit_objective.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">

                    <div class="upload-card">
                        <h3 class="card-title">
                            <i class='bx bx-upload'></i>
                            Upload Answer Sheets
                        </h3>

                        <div class="upload-zone" id="uploadZone">
                            <i class='bx bx-cloud-upload'></i>
                            <h4>Drag & Drop or Click to Upload</h4>
                            <p>Upload scanned images of your answer sheets (JPG, PNG, or PDF)</p>
                            <p style="font-size: 12px; margin-top: 10px;">Maximum 10 images, 10MB each</p>
                        </div>
                        <input type="file" name="answer_sheets[]" id="fileInput" class="file-input"
                            multiple accept="image/jpeg,image/png,image/gif,application/pdf">

                        <div class="preview-container" id="previewContainer"></div>

                        <div class="upload-progress" id="uploadProgress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                            <div class="progress-text" id="progressText">Uploading...</div>
                        </div>

                        <div class="submit-section">
                            <div class="submit-info">
                                <i class='bx bx-info-circle'></i>
                                <span>Make sure all your answer sheets are clear and readable before submitting.</span>
                            </div>
                            <div style="display: flex; gap: 15px;">
                                <a href="objective_exams.php" class="btn btn-secondary">
                                    <i class='bx bx-arrow-back'></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                    <i class='bx bx-send'></i> Submit Exam
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
    <script>
        // Fullscreen exam security
        let examInitialized = false;
        let isFullScreen = false;

        // Create warning container for anti-cheat messages
        const warningContainer = document.createElement('div');
        warningContainer.id = 'anti-cheat-warning';
        warningContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            max-width: 300px;
            display: none;
            font-weight: bold;
        `;
        document.body.appendChild(warningContainer);

        // Create full screen prompt overlay
        const fullScreenContainer = document.createElement('div');
        fullScreenContainer.id = 'fullscreen-prompt';
        fullScreenContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;

        const fullScreenMessage = document.createElement('div');
        fullScreenMessage.style.cssText = `
            color: white;
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
            max-width: 600px;
        `;
        fullScreenMessage.innerHTML = '<strong>EXAM SECURITY NOTICE</strong><br><br>This exam requires full screen mode to maintain integrity.<br>Please click the button below to enter full screen mode.';

        const fullScreenButton = document.createElement('button');
        fullScreenButton.textContent = 'Enter Full Screen Mode';
        fullScreenButton.style.cssText = `
            background-color: #17684f;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 18px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        `;

        fullScreenContainer.appendChild(fullScreenMessage);
        fullScreenContainer.appendChild(fullScreenButton);
        document.body.appendChild(fullScreenContainer);

        // Create test info modal
        const testInfoModal = document.createElement('div');
        testInfoModal.id = 'test-info-modal';
        testInfoModal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;

        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            text-align: center;
        `;

        modalContent.innerHTML = `
            <h2 style="color: #17684f; margin-bottom: 20px; font-size: 24px;">Exam Information</h2>
            <div style="text-align: left; margin-bottom: 25px;">
                <p style="margin-bottom: 10px; font-size: 16px;"><strong>Exam Name:</strong> <?php echo htmlspecialchars($exam['exam_name']); ?></p>
                <p style="margin-bottom: 10px; font-size: 16px;"><strong>Number of Questions:</strong> <?php echo count($questions); ?></p>
                <p style="margin-bottom: 10px; font-size: 16px;"><strong>Total Marks:</strong> <?php echo $total_marks; ?></p>
                <p style="margin-bottom: 10px; font-size: 16px;"><strong>Duration:</strong> <?php echo $exam['duration_minutes']; ?> minutes</p>
                <p style="margin-bottom: 10px; font-size: 16px;"><strong>Passing Marks:</strong> <?php echo $exam['passing_marks']; ?></p>
            </div>
            <h3 style="color: #dc3545; margin-bottom: 15px; font-size: 18px;">Important: Anti-Cheat System</h3>
            <div style="text-align: left; margin-bottom: 25px; background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;">
                <p style="margin-bottom: 10px; font-size: 15px;">This exam employs the following integrity monitoring features:</p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li style="margin-bottom: 8px; font-size: 15px;">Full-screen mode is required throughout the exam</li>
                    <li style="margin-bottom: 8px; font-size: 15px;">Exiting full-screen mode will trigger a warning</li>
                </ul>
                <p style="font-size: 15px; color: #dc3545; font-weight: bold;">Full-screen mode is mandatory during this exam.</p>
            </div>
            <button id="start-test-btn" style="
                background-color: #17684f;
                color: white;
                border: none;
                padding: 12px 24px;
                font-size: 18px;
                border-radius: 5px;
                cursor: pointer;
                font-weight: bold;
                margin-top: 10px;
            ">I Understand & Start Exam</button>
        `;

        testInfoModal.appendChild(modalContent);
        document.body.appendChild(testInfoModal);

        // Show test info modal
        testInfoModal.style.display = 'flex';
        fullScreenContainer.style.display = 'none';

        // Start test button handler
        document.getElementById('start-test-btn').addEventListener('click', function() {
            testInfoModal.style.display = 'none';
            requestFullScreen();
        });

        // Fullscreen functions
        function requestFullScreen() {
            const element = document.documentElement;
            if (element.requestFullscreen) {
                element.requestFullscreen();
            } else if (element.mozRequestFullScreen) {
                element.mozRequestFullScreen();
            } else if (element.webkitRequestFullscreen) {
                element.webkitRequestFullscreen();
            } else if (element.msRequestFullscreen) {
                element.msRequestFullscreen();
            }
            isFullScreen = true;
            examInitialized = true;
            fullScreenContainer.style.display = 'none';
            showWarning('Full screen mode activated. Do not exit full screen during the exam.');
        }

        function exitFullScreen() {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
            isFullScreen = false;
        }

        function isInFullScreen() {
            return (
                document.fullscreenElement ||
                document.webkitFullscreenElement ||
                document.mozFullScreenElement ||
                document.msFullscreenElement
            );
        }

        // Fullscreen change event listeners
        document.addEventListener('fullscreenchange', handleFullScreenChange);
        document.addEventListener('webkitfullscreenchange', handleFullScreenChange);
        document.addEventListener('mozfullscreenchange', handleFullScreenChange);
        document.addEventListener('MSFullscreenChange', handleFullScreenChange);

        function handleFullScreenChange() {
            if (examInitialized && !isInFullScreen()) {
                showWarning('WARNING: You exited full screen mode! This may be flagged as suspicious behavior.');
                fullScreenContainer.style.display = 'flex';
            } else if (isInFullScreen()) {
                fullScreenContainer.style.display = 'none';
            }
        }

        // Fullscreen button listener
        fullScreenButton.addEventListener('click', requestFullScreen);

        // Function to show warning message
        function showWarning(message) {
            warningContainer.innerHTML = message;
            warningContainer.style.display = 'block';
            setTimeout(() => {
                warningContainer.style.display = 'none';
            }, 5000);
        }

        // File upload handling
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const previewContainer = document.getElementById('previewContainer');
        const submitBtn = document.getElementById('submitBtn');
        let selectedFiles = [];
        const maxFiles = 10;
        const maxFileSize = 10 * 1024 * 1024; // 10MB

        // Click to upload
        uploadZone.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            for (let file of files) {
                if (selectedFiles.length >= maxFiles) {
                    alert('Maximum ' + maxFiles + ' files allowed.');
                    break;
                }

                if (file.size > maxFileSize) {
                    alert(file.name + ' is too large. Maximum file size is 10MB.');
                    continue;
                }

                if (!file.type.match(/image\/(jpeg|png|gif)|application\/pdf/)) {
                    alert(file.name + ' is not a supported file type.');
                    continue;
                }

                selectedFiles.push(file);
                addPreview(file, selectedFiles.length - 1);
            }

            updateSubmitButton();
            updateFileInput();
        }

        function addPreview(file, index) {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.dataset.index = index;

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    div.innerHTML = `
                        <img src="${e.target.result}" class="preview-image" alt="${file.name}">
                        <div class="preview-info">${file.name}</div>
                        <button type="button" class="preview-remove" onclick="removeFile(${index})">&times;</button>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                div.innerHTML = `
                    <div class="preview-image" style="display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
                        <i class='bx bxs-file-pdf' style="font-size: 48px; color: #dc3545;"></i>
                    </div>
                    <div class="preview-info">${file.name}</div>
                    <button type="button" class="preview-remove" onclick="removeFile(${index})">&times;</button>
                `;
            }

            previewContainer.appendChild(div);
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            renderPreviews();
            updateSubmitButton();
            updateFileInput();
        }

        function renderPreviews() {
            previewContainer.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                addPreview(file, index);
            });
        }

        function updateSubmitButton() {
            submitBtn.disabled = selectedFiles.length === 0;
        }

        function updateFileInput() {
            // Create a new DataTransfer to hold our files
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        // Timer
        let totalSeconds = <?php echo $exam['duration_minutes'] * 60; ?>;
        const timerEl = document.getElementById('timer');

        function updateTimer() {
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            timerEl.textContent =
                (hours > 0 ? hours + ':' : '') +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');

            if (totalSeconds <= 300) {
                timerEl.classList.add('danger');
            } else if (totalSeconds <= 600) {
                timerEl.classList.add('warning');
            }

            if (totalSeconds <= 0) {
                alert('Time is up! Your exam will be submitted automatically.');
                document.getElementById('uploadForm').submit();
            } else {
                totalSeconds--;
                setTimeout(updateTimer, 1000);
            }
        }

        updateTimer();

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (selectedFiles.length === 0) {
                e.preventDefault();
                alert('Please upload at least one answer sheet.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Submitting...';
            document.getElementById('uploadProgress').style.display = 'block';
        });

        // Prevent accidental navigation
        window.addEventListener('beforeunload', function(e) {
            if (selectedFiles.length > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>

</html>