<?php

/**
 * Upload Answer Key - Teacher View
 * 
 * Allows teachers to upload a PDF/text answer key for an exam
 * and processes it using OCR if it's a PDF.
 */

date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/objective_exam_utils.php';
require_once '../utils/ocr_processor.php';

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
$exam_sql = "SELECT * FROM objective_exm_list WHERE exam_id = ?";
$exam_stmt = mysqli_prepare($conn, $exam_sql);
mysqli_stmt_bind_param($exam_stmt, "i", $exam_id);
mysqli_stmt_execute($exam_stmt);
$exam = mysqli_fetch_assoc(mysqli_stmt_get_result($exam_stmt));

if (!$exam) {
    header("Location: objective_exams.php?error=" . urlencode("Exam not found."));
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_key'])) {
    if (isset($_FILES['answer_key_file']) && $_FILES['answer_key_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['answer_key_file'];
        $allowed_types = ['application/pdf', 'text/plain', 'image/jpeg', 'image/png', 'image/gif'];
        $max_size = 10 * 1024 * 1024; // 10MB

        // Get file info
        $file_type = mime_content_type($file['tmp_name']);
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_type, $allowed_types)) {
            $error_msg = 'Invalid file type. Allowed: PDF, TXT, JPG, PNG, GIF.';
        } elseif ($file_size > $max_size) {
            $error_msg = 'File too large. Maximum size: 10MB.';
        } else {
            // Create upload directory for this teacher
            $upload_dir = getTeacherAnswerKeyPath($teacher_id);
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique filename
            $new_filename = 'exam_' . $exam_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . '/' . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old file if exists
                if (!empty($exam['answer_key_path']) && file_exists($exam['answer_key_path'])) {
                    unlink($exam['answer_key_path']);
                }

                // Extract text based on file type
                $extracted_text = '';

                if ($file_ext === 'txt') {
                    // Plain text file
                    $extracted_text = file_get_contents($upload_path);
                } elseif ($file_ext === 'pdf') {
                    // PDF - use OCR
                    $ocr = new OCRProcessor();
                    if ($ocr->checkStatus()['installed']) {
                        $result = $ocr->extractTextFromPDF($upload_path);
                        if ($result['success']) {
                            $extracted_text = $result['text'];
                        } else {
                            $error_msg = 'Warning: Could not extract text from PDF. ' . $result['error'];
                        }
                    } else {
                        $error_msg = 'Warning: OCR not installed. PDF text extraction unavailable.';
                    }
                } else {
                    // Image - use OCR
                    $ocr = new OCRProcessor();
                    if ($ocr->checkStatus()['installed']) {
                        $result = $ocr->extractText($upload_path);
                        if ($result['success']) {
                            $extracted_text = $result['text'];
                        } else {
                            $error_msg = 'Warning: Could not extract text from image. ' . $result['error'];
                        }
                    } else {
                        $error_msg = 'Warning: OCR not installed. Image text extraction unavailable.';
                    }
                }

                // Update database
                $update_sql = "UPDATE objective_exm_list SET answer_key_path = ?, answer_key_text = ? WHERE exam_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "ssi", $upload_path, $extracted_text, $exam_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    $exam['answer_key_path'] = $upload_path;
                    $exam['answer_key_text'] = $extracted_text;
                    $success_msg = 'Answer key uploaded successfully.' . ($error_msg ? ' ' . $error_msg : '');
                    $error_msg = '';
                } else {
                    $error_msg = 'Failed to save answer key to database.';
                }
            } else {
                $error_msg = 'Failed to upload file.';
            }
        }
    } else {
        $error_msg = 'Please select a file to upload.';
    }
}

// Handle text-only answer key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_text_key'])) {
    $answer_key_text = trim($_POST['answer_key_text']);

    if (empty($answer_key_text)) {
        $error_msg = 'Answer key text cannot be empty.';
    } else {
        $update_sql = "UPDATE objective_exm_list SET answer_key_text = ? WHERE exam_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $answer_key_text, $exam_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $exam['answer_key_text'] = $answer_key_text;
            $success_msg = 'Answer key text saved successfully.';
        } else {
            $error_msg = 'Failed to save answer key.';
        }
    }
}

// Handle delete answer key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_key'])) {
    // Delete file if exists
    if (!empty($exam['answer_key_path']) && file_exists($exam['answer_key_path'])) {
        unlink($exam['answer_key_path']);
    }

    // Clear from database
    $update_sql = "UPDATE objective_exm_list SET answer_key_path = NULL, answer_key_text = NULL WHERE exam_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $exam_id);

    if (mysqli_stmt_execute($update_stmt)) {
        $exam['answer_key_path'] = null;
        $exam['answer_key_text'] = null;
        $success_msg = 'Answer key deleted.';
    }
}

// Check OCR status
$ocr = new OCRProcessor();
$ocr_status = $ocr->checkStatus();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Upload Answer Key - <?php echo htmlspecialchars($exam['exam_name']); ?></title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .page-container {
            max-width: 900px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #17684f;
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #17684f;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .exam-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .exam-info-item {
            display: flex;
            flex-direction: column;
        }

        .exam-info-item label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .exam-info-item span {
            font-weight: 600;
            color: #333;
        }

        .upload-zone {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: #17684f;
            background: #f0f9f6;
        }

        .upload-zone i {
            font-size: 48px;
            color: #17684f;
            margin-bottom: 15px;
        }

        .upload-zone p {
            color: #666;
            margin: 0;
        }

        .upload-zone input[type="file"] {
            display: none;
        }

        .upload-zone .file-types {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #17684f;
            color: white;
        }

        .btn-primary:hover {
            background: #11533e;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .current-key {
            background: #e8f5e9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .current-key h4 {
            color: #2e7d32;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .current-key .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .current-key .text-preview {
            background: #fff;
            padding: 15px;
            border-radius: 6px;
            max-height: 200px;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .current-key .actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .text-input-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .text-input-section h4 {
            color: #333;
            margin: 0 0 15px 0;
        }

        .text-input-section textarea {
            width: 100%;
            min-height: 200px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
            resize: vertical;
            box-sizing: border-box;
        }

        .text-input-section textarea:focus {
            border-color: #17684f;
            outline: none;
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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .ocr-status {
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .ocr-installed {
            background: #d4edda;
            color: #155724;
        }

        .ocr-not-installed {
            background: #fff3cd;
            color: #856404;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 25px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            color: #666;
            transition: all 0.3s;
        }

        .tab:hover {
            color: #17684f;
        }

        .tab.active {
            color: #17684f;
            border-bottom-color: #17684f;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
                <span class="dashboard">Upload Answer Key</span>
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

                <div class="card">
                    <h2><i class='bx bx-key'></i> Answer Key for: <?php echo htmlspecialchars($exam['exam_name']); ?></h2>

                    <div class="exam-info">
                        <div class="exam-info-item">
                            <label>Grading Mode</label>
                            <span><i class='bx bx-<?php echo $exam['grading_mode'] == 'ai' ? 'brain' : 'user'; ?>'></i>
                                <?php echo strtoupper($exam['grading_mode']); ?></span>
                        </div>
                        <div class="exam-info-item">
                            <label>Total Marks</label>
                            <span><?php echo $exam['total_marks']; ?></span>
                        </div>
                        <div class="exam-info-item">
                            <label>Status</label>
                            <span><?php echo ucfirst($exam['status']); ?></span>
                        </div>
                    </div>

                    <!-- OCR Status -->
                    <div class="ocr-status <?php echo $ocr_status['installed'] ? 'ocr-installed' : 'ocr-not-installed'; ?>">
                        <i class='bx bx-<?php echo $ocr_status['installed'] ? 'check-circle' : 'error-circle'; ?>'></i>
                        <?php if ($ocr_status['installed']): ?>
                            OCR is available. PDF and image answer keys will be automatically processed.
                        <?php else: ?>
                            OCR is not installed. PDF/image processing will be limited.
                            <a href="ocr_status.php">Check OCR Status</a>
                        <?php endif; ?>
                    </div>

                    <!-- Current Answer Key -->
                    <?php if (!empty($exam['answer_key_path']) || !empty($exam['answer_key_text'])): ?>
                        <div class="current-key">
                            <h4><i class='bx bx-check-shield'></i> Current Answer Key</h4>

                            <?php if (!empty($exam['answer_key_path'])): ?>
                                <div class="file-info">
                                    <i class='bx bx-file' style="font-size:24px; color:#17684f;"></i>
                                    <span><?php echo basename($exam['answer_key_path']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($exam['answer_key_text'])): ?>
                                <div class="text-preview"><?php echo htmlspecialchars($exam['answer_key_text']); ?></div>
                            <?php else: ?>
                                <p style="color:#666; font-style:italic;">No text content extracted.</p>
                            <?php endif; ?>

                            <div class="actions">
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete the answer key?');">
                                    <button type="submit" name="delete_key" class="btn btn-danger">
                                        <i class='bx bx-trash'></i> Delete Answer Key
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tabs -->
                    <div class="tabs">
                        <div class="tab active" onclick="showTab('upload')">
                            <i class='bx bx-upload'></i> Upload File
                        </div>
                        <div class="tab" onclick="showTab('text')">
                            <i class='bx bx-text'></i> Enter Text
                        </div>
                    </div>

                    <!-- Upload Tab -->
                    <div class="tab-content active" id="tab-upload">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <label class="upload-zone" for="answer_key_file" id="dropZone">
                                <i class='bx bx-cloud-upload'></i>
                                <p>Click to select file or drag and drop here</p>
                                <p class="file-types">Supported: PDF, TXT, JPG, PNG, GIF (Max 10MB)</p>
                                <input type="file" name="answer_key_file" id="answer_key_file"
                                    accept=".pdf,.txt,.jpg,.jpeg,.png,.gif">
                            </label>

                            <div id="selectedFile" style="display:none; margin: 15px 0; padding: 10px; background: #e3f2fd; border-radius: 6px;">
                                <i class='bx bx-file'></i> <span id="fileName"></span>
                            </div>

                            <button type="submit" name="upload_key" class="btn btn-primary" style="margin-top: 15px;">
                                <i class='bx bx-upload'></i> Upload Answer Key
                            </button>
                        </form>
                    </div>

                    <!-- Text Tab -->
                    <div class="tab-content" id="tab-text">
                        <form method="POST">
                            <textarea name="answer_key_text" placeholder="Enter the answer key text here..."><?php echo htmlspecialchars($exam['answer_key_text'] ?? ''); ?></textarea>

                            <button type="submit" name="save_text_key" class="btn btn-primary" style="margin-top: 15px;">
                                <i class='bx bx-save'></i> Save Answer Key Text
                            </button>
                        </form>
                    </div>

                    <div style="margin-top: 20px; text-align: center;">
                        <a href="objective_questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-secondary">
                            <i class='bx bx-arrow-back'></i> Back to Questions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
    <script>
        // Tab switching
        function showTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            event.target.closest('.tab').classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
        }

        // File input change
        document.getElementById('answer_key_file').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('selectedFile').style.display = 'block';
                document.getElementById('fileName').textContent = this.files[0].name;
            } else {
                document.getElementById('selectedFile').style.display = 'none';
            }
        });

        // Drag and drop
        const dropZone = document.getElementById('dropZone');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('answer_key_file').files = files;

            if (files.length > 0) {
                document.getElementById('selectedFile').style.display = 'block';
                document.getElementById('fileName').textContent = files[0].name;
            }
        });
    </script>
</body>

</html>