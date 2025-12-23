<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
}
include '../config.php';
error_reporting(0);

// Get parameter
$attempt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$attempt_id) {
    echo "<script>alert('Invalid attempt ID provided.'); window.history.back();</script>";
    exit();
}

// Get attempt details
$sql = "SELECT * FROM atmpt_list WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$result = $stmt->get_result();

if (mysqli_num_rows($result) == 0) {
    echo "<script>alert('Attempt not found.'); window.history.back();</script>";
    exit();
}

$attempt_data = mysqli_fetch_assoc($result);
$exam_id = $attempt_data['exid'];
$student_username = $attempt_data['uname'];
$integrity_score = $attempt_data['integrity_score'] ?? 100;
$integrity_category = $attempt_data['integrity_category'] ?? 'Good';

// Get exam name
$sql = "SELECT exname FROM exm_list WHERE exid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam_result = $stmt->get_result();
$exam_data = mysqli_fetch_assoc($exam_result);
$exam_name = $exam_data['exname'];

// Get student name
$sql = "SELECT fname FROM student WHERE uname = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_username);
$stmt->execute();
$student_result = $stmt->get_result();
$student_data = mysqli_fetch_assoc($student_result);
$student_name = $student_data['fname'];

// Get all violations for this student in this exam
$sql = "SELECT * FROM cheat_violations 
        WHERE student_username = ? AND exam_id = ? 
        ORDER BY timestamp ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $student_username, $exam_id);
$stmt->execute();
$violations_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Integrity Violations</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .integrity-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .integrity-info {
            flex: 1;
        }

        .integrity-score {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 18px;
            margin-left: 20px;
        }

        .good {
            background-color: #d4edda;
            color: #155724;
        }

        .at-risk {
            background-color: #fff3cd;
            color: #856404;
        }

        .cheating {
            background-color: #f8d7da;
            color: #721c24;
        }

        .violations-table {
            width: 100%;
            border-collapse: collapse;
        }

        .violations-table th,
        .violations-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .violations-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .violations-table tr:hover {
            background-color: #f5f5f5;
        }

        .violation-type {
            font-weight: bold;
        }

        .tab-switch {
            color: #e67e22;
            background-color: rgba(230, 126, 34, 0.1);
            padding: 5px 8px;
            border-radius: 4px;
        }

        .window-blur {
            color: #3498db;
            background-color: rgba(52, 152, 219, 0.1);
            padding: 5px 8px;
            border-radius: 4px;
        }

        .app-switch {
            color: #9b59b6;
            background-color: rgba(155, 89, 182, 0.1);
            padding: 5px 8px;
            border-radius: 4px;
        }

        .combined {
            color: #e74c3c;
            background-color: rgba(231, 76, 60, 0.1);
            padding: 5px 8px;
            border-radius: 4px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #0A2558;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background-color: #0d3a80;
        }

        .back-btn i {
            margin-right: 5px;
        }

        .no-violations {
            text-align: center;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-top: 20px;
            color: #6c757d;
        }

        /* Detailed violation report styles */
        .violation-details {
            margin-top: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .violation-type {
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 15px;
            display: inline-block;
        }

        .tab-switch {
            background-color: #f8d7da;
            color: #721c24;
        }

        .window-blur {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .combined {
            background-color: #f5c6cb;
            color: #721c24;
            font-weight: 700;
            border: 1px solid #dc3545;
        }

        .app-switch {
            background-color: #fff3cd;
            color: #856404;
        }

        .violation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .violation-table th,
        .violation-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .violation-table th {
            background-color: #f0f0f0;
            font-weight: 600;
        }

        .violation-table tr:hover {
            background-color: #f5f5f5;
        }

        .integrity-score {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            display: inline-block;
        }

        .good {
            color: #28a745;
        }

        .at-risk {
            color: #ffc107;
        }

        .cheating {
            color: #dc3545;
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
                <a href="objective_exams.php">
                    <i class='bx bx-edit'></i>
                    <span class="links_name">Objective Exams</span>
                </a>
            </li>
            <li>
                <a href="results.php" class="active">
                    <i class='bx bxs-bar-chart-alt-2'></i>
                    <span class="links_name">Results</span>
                </a>
            </li>
            <li>
                <a href="records.php">
                    <i class='bx bxs-user-circle'></i>
                    <span class="links_name">Records</span>
                </a>
            </li>
            <li>
                <a href="messages.php">
                    <i class='bx bx-message'></i>
                    <span class="links_name">Messages</span>
                </a>
            </li>
            <li>
                <a href="school_management.php">
                    <i class='bx bx-building-house'></i>
                    <span class="links_name">Schools</span>
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
                <span class="dashboard">Teacher's Dashboard</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="stat-boxes">
                <div class="recent-stat box" style="padding: 20px;">

                    <a href="viewresults.php" class="back-btn">
                        <i class='bx bx-arrow-back'></i> Back to Results
                    </a>

                    <h2>Integrity Report</h2>

                    <div class="integrity-header">
                        <div class="integrity-info">
                            <h3><?php echo htmlspecialchars($student_name); ?> - <?php echo htmlspecialchars($exam_name); ?></h3>
                            <p>Exam Session: <?php echo date('F j, Y, g:i a', strtotime($attempt_data['subtime'])); ?></p>
                        </div>
                        <div class="integrity-score <?php echo strtolower($integrity_category); ?>">
                            <?php echo $integrity_score; ?>/100
                            <small>(<?php echo $integrity_category; ?>)</small>
                        </div>
                    </div>

                    <?php if (mysqli_num_rows($violations_result) > 0): ?>
                        <table class="violations-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Violation Type</th>
                                    <th>Occurrence</th>
                                    <th>Penalty</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                $total_penalty = 0;
                                while ($violation = mysqli_fetch_assoc($violations_result)):
                                    $total_penalty += $violation['penalty'];
                                    // Set CSS class and display text based on violation type
                                    switch ($violation['violation_type']) {
                                        case 'tab_switch':
                                            $violation_class = 'tab-switch';
                                            $violation_text = 'Tab Switching';
                                            $icon = '<i class="fas fa-exchange-alt"></i>';
                                            break;
                                        case 'window_blur':
                                            $violation_class = 'window-blur';
                                            $violation_text = 'Window Focus Loss';
                                            $icon = '<i class="fas fa-window-minimize"></i>';
                                            break;
                                        case 'combined':
                                            $violation_class = 'combined';
                                            $violation_text = 'Combined Violations';
                                            $icon = '<i class="fas fa-exclamation-triangle"></i>';
                                            break;
                                        default:
                                            $violation_class = '';
                                            $violation_text = $violation['violation_type'];
                                            $icon = '<i class="fas fa-question-circle"></i>';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td>
                                            <span class="violation-type <?php echo $violation_class; ?>">
                                                <?php echo $icon; ?>
                                                <?php echo $violation_text; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $violation['occurrence']; ?></td>
                                        <td>-<?php echo $violation['penalty']; ?> points</td>
                                        <td><?php echo date('g:i:s a', strtotime($violation['timestamp'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr>
                                    <td colspan="3"><strong>Total Penalty</strong></td>
                                    <td colspan="2"><strong>-<?php echo $total_penalty; ?> points</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-violations">
                            <h3><i class='bx bx-check-circle'></i> No Integrity Violations Detected</h3>
                            <p>This student completed the exam without any detected violations.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
</body>

</html>