<?php

/**
 * OCR Status Check Page
 * 
 * Allows administrators to check if OCR is properly configured
 * and test OCR functionality.
 */

session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}

require_once '../config.php';
require_once '../utils/ocr_processor.php';

$ocr = new OCRProcessor();
$status = $ocr->checkStatus();

// Handle test OCR
$test_result = null;
if (isset($_POST['test_ocr']) && isset($_FILES['test_image'])) {
    $upload = $_FILES['test_image'];

    if ($upload['error'] === UPLOAD_ERR_OK) {
        $temp_path = $upload['tmp_name'];
        $test_result = $ocr->extractText($temp_path);
    } else {
        $test_result = ['success' => false, 'error' => 'File upload failed'];
    }
}

// Get queue status
$queue_status = [];
$sql = "SELECT ocr_status, COUNT(*) as count FROM objective_answer_images GROUP BY ocr_status";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $queue_status[$row['ocr_status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>OCR System Status</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .status-container {
            padding: 20px;
            max-width: 1000px;
        }

        .status-card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .status-card h2 {
            color: #17684f;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #17684f;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-label {
            font-weight: 600;
            color: #333;
        }

        .status-value {
            color: #666;
        }

        .status-ok {
            color: #28a745;
            font-weight: 600;
        }

        .status-error {
            color: #dc3545;
            font-weight: 600;
        }

        .status-warning {
            color: #ffc107;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .test-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .test-form input[type="file"] {
            margin: 10px 0;
        }

        .test-form button {
            background: #17684f;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
        }

        .test-form button:hover {
            background: #11533e;
        }

        .ocr-result {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .ocr-result pre {
            background: #fff;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            max-height: 300px;
            border: 1px solid #ddd;
        }

        .queue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .queue-stat {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .queue-stat .number {
            font-size: 32px;
            font-weight: 700;
            color: #17684f;
        }

        .queue-stat .label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .install-instructions {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .install-instructions h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        .install-instructions code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .install-instructions ol {
            margin-left: 20px;
            line-height: 1.8;
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
            <li><a href="objective_exams.php"><i class='bx bx-edit'></i><span class="links_name">Objective Exams</span></a></li>
            <li><a href="results.php"><i class='bx bxs-bar-chart-alt-2'></i><span class="links_name">Results</span></a></li>
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="school_management.php"><i class='bx bx-building-house'></i><span class="links_name">Schools</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="#" class="active"><i class='bx bx-scan'></i><span class="links_name">OCR Status</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">OCR System Status</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="status-container">

                <!-- Tesseract Status -->
                <div class="status-card">
                    <h2><i class='bx bx-scan'></i> Tesseract OCR Status</h2>

                    <div class="status-item">
                        <span class="status-label">Installation Status</span>
                        <span class="status-value">
                            <?php if ($status['installed']): ?>
                                <span class="badge badge-success"><i class='bx bx-check'></i> Installed</span>
                            <?php else: ?>
                                <span class="badge badge-danger"><i class='bx bx-x'></i> Not Installed</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if ($status['installed']): ?>
                        <div class="status-item">
                            <span class="status-label">Version</span>
                            <span class="status-value"><?php echo htmlspecialchars($status['version']); ?></span>
                        </div>

                        <div class="status-item">
                            <span class="status-label">Executable Path</span>
                            <span class="status-value"><code><?php echo htmlspecialchars($status['path']); ?></code></span>
                        </div>

                        <div class="status-item">
                            <span class="status-label">Available Languages</span>
                            <span class="status-value">
                                <?php echo count($status['languages']); ?> language(s):
                                <?php echo htmlspecialchars(implode(', ', array_slice($status['languages'], 0, 10))); ?>
                                <?php if (count($status['languages']) > 10) echo '...'; ?>
                            </span>
                        </div>
                    <?php else: ?>

                        <div class="install-instructions">
                            <h3>Installation Instructions</h3>
                            <p><strong>Windows:</strong></p>
                            <ol>
                                <li>Download Tesseract from: <a href="https://github.com/UB-Mannheim/tesseract/wiki" target="_blank">https://github.com/UB-Mannheim/tesseract/wiki</a></li>
                                <li>Run the installer (choose the default installation path)</li>
                                <li>Add <code>C:\Program Files\Tesseract-OCR</code> to your system PATH</li>
                                <li>Restart Apache/XAMPP</li>
                            </ol>
                            <p><strong>Linux (Ubuntu/Debian):</strong></p>
                            <ol>
                                <li>Run: <code>sudo apt-get install tesseract-ocr</code></li>
                                <li>Install language packs: <code>sudo apt-get install tesseract-ocr-eng</code></li>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Queue Status -->
                <div class="status-card">
                    <h2><i class='bx bx-list-ul'></i> OCR Processing Queue</h2>

                    <div class="queue-stats">
                        <div class="queue-stat">
                            <div class="number"><?php echo $queue_status['pending'] ?? 0; ?></div>
                            <div class="label">Pending</div>
                        </div>
                        <div class="queue-stat">
                            <div class="number"><?php echo $queue_status['processing'] ?? 0; ?></div>
                            <div class="label">Processing</div>
                        </div>
                        <div class="queue-stat">
                            <div class="number"><?php echo $queue_status['completed'] ?? 0; ?></div>
                            <div class="label">Completed</div>
                        </div>
                        <div class="queue-stat">
                            <div class="number"><?php echo $queue_status['failed'] ?? 0; ?></div>
                            <div class="label">Failed</div>
                        </div>
                    </div>
                </div>

                <!-- Test OCR -->
                <?php if ($status['installed']): ?>
                    <div class="status-card">
                        <h2><i class='bx bx-test-tube'></i> Test OCR</h2>
                        <p>Upload an image to test OCR functionality:</p>

                        <form method="POST" enctype="multipart/form-data" class="test-form">
                            <input type="file" name="test_image" accept="image/*" required>
                            <br>
                            <button type="submit" name="test_ocr">
                                <i class='bx bx-play'></i> Run OCR Test
                            </button>
                        </form>

                        <?php if ($test_result): ?>
                            <div class="ocr-result">
                                <h3>
                                    <?php if ($test_result['success']): ?>
                                        <span class="status-ok"><i class='bx bx-check-circle'></i> OCR Successful</span>
                                    <?php else: ?>
                                        <span class="status-error"><i class='bx bx-x-circle'></i> OCR Failed</span>
                                    <?php endif; ?>
                                </h3>

                                <?php if ($test_result['success']): ?>
                                    <p>
                                        <strong>Confidence:</strong> <?php echo $test_result['confidence']; ?>%<br>
                                        <strong>Processing Time:</strong> <?php echo $test_result['processing_time']; ?> seconds
                                    </p>
                                    <p><strong>Extracted Text:</strong></p>
                                    <pre><?php echo htmlspecialchars($test_result['text'] ?: '(No text detected)'); ?></pre>
                                <?php else: ?>
                                    <p class="status-error"><?php echo htmlspecialchars($test_result['error']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- PHP Extensions -->
                <div class="status-card">
                    <h2><i class='bx bx-extension'></i> PHP Extensions</h2>

                    <div class="status-item">
                        <span class="status-label">GD Extension (Image Processing)</span>
                        <span class="status-value">
                            <?php if (extension_loaded('gd')): ?>
                                <span class="badge badge-success">Enabled</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Not Enabled (optional)</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="status-item">
                        <span class="status-label">exec() Function</span>
                        <span class="status-value">
                            <?php if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))): ?>
                                <span class="badge badge-success">Available</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Disabled (required!)</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="status-item">
                        <span class="status-label">Upload Max Filesize</span>
                        <span class="status-value"><?php echo ini_get('upload_max_filesize'); ?></span>
                    </div>

                    <div class="status-item">
                        <span class="status-label">Post Max Size</span>
                        <span class="status-value"><?php echo ini_get('post_max_size'); ?></span>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
</body>

</html>