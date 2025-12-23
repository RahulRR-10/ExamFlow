<?php

/**
 * View Objective Exam Results - Teacher Interface
 * 
 * Shows summary of all submissions for objective exams:
 * - Filter by exam, status, school
 * - View individual results
 * - Export functionality
 */

date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/message_utils.php';
error_reporting(0);

$teacher_id = $_SESSION['user_id'];
$fname = $_SESSION['fname'];

// Get unread message count
$unread_count = getUnreadMessageCount($fname, $conn);

// Get filters
$filter_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_school = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;

// Get list of exams for filter dropdown
$exams_sql = "SELECT exam_id, exam_name FROM objective_exm_list WHERE teacher_id = ? ORDER BY exam_name";
$exams_stmt = mysqli_prepare($conn, $exams_sql);
mysqli_stmt_bind_param($exams_stmt, "i", $teacher_id);
mysqli_stmt_execute($exams_stmt);
$exams_result = mysqli_stmt_get_result($exams_stmt);

// Get schools for filter
$schools_sql = "SELECT DISTINCT s.school_id, s.school_name 
                FROM schools s 
                INNER JOIN objective_exm_list oe ON s.school_id = oe.school_id 
                WHERE oe.teacher_id = ? 
                ORDER BY s.school_name";
$schools_stmt = mysqli_prepare($conn, $schools_sql);
mysqli_stmt_bind_param($schools_stmt, "i", $teacher_id);
mysqli_stmt_execute($schools_stmt);
$schools_result = mysqli_stmt_get_result($schools_stmt);

// Build query with filters
$where_conditions = ["oe.teacher_id = ?"];
$params = [$teacher_id];
$types = "i";

if ($filter_exam > 0) {
    $where_conditions[] = "oe.exam_id = ?";
    $params[] = $filter_exam;
    $types .= "i";
}

if (!empty($filter_status)) {
    $where_conditions[] = "os.submission_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_school > 0) {
    $where_conditions[] = "oe.school_id = ?";
    $params[] = $filter_school;
    $types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Get submissions with details
$results_sql = "SELECT os.*, 
                       oe.exam_name, oe.total_marks, oe.passing_marks, oe.grading_mode,
                       st.fname as student_name, st.uname as student_uname, st.email as student_email,
                       s.school_name,
                       (SELECT COUNT(*) FROM objective_answer_grades WHERE submission_id = os.submission_id AND final_score IS NOT NULL) as graded_questions,
                       (SELECT COUNT(*) FROM objective_questions WHERE exam_id = oe.exam_id) as total_questions
                FROM objective_submissions os
                JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                JOIN student st ON os.student_id = st.id
                LEFT JOIN schools s ON oe.school_id = s.school_id
                WHERE $where_clause
                ORDER BY os.submitted_at DESC";

$results_stmt = mysqli_prepare($conn, $results_sql);
mysqli_stmt_bind_param($results_stmt, $types, ...$params);
mysqli_stmt_execute($results_stmt);
$results = mysqli_stmt_get_result($results_stmt);

// Get summary statistics
$stats_sql = "SELECT 
                COUNT(*) as total_submissions,
                SUM(CASE WHEN os.submission_status = 'graded' THEN 1 ELSE 0 END) as graded_count,
                SUM(CASE WHEN os.submission_status = 'ocr_complete' THEN 1 ELSE 0 END) as pending_grading,
                SUM(CASE WHEN os.submission_status IN ('pending', 'ocr_processing') THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN os.submission_status = 'error' THEN 1 ELSE 0 END) as errors,
                AVG(CASE WHEN os.submission_status = 'graded' THEN os.scored_marks ELSE NULL END) as avg_score,
                MAX(CASE WHEN os.submission_status = 'graded' THEN os.scored_marks ELSE NULL END) as max_score,
                MIN(CASE WHEN os.submission_status = 'graded' THEN os.scored_marks ELSE NULL END) as min_score
              FROM objective_submissions os
              JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
              WHERE $where_clause";

$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, $types, ...$params);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="objective_results_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, ['Student Name', 'Username', 'Email', 'Exam Name', 'School', 'Score', 'Total Marks', 'Percentage', 'Status', 'Submitted At', 'Grading Mode']);
    
    // Reset result pointer
    mysqli_data_seek($results, 0);
    
    while ($row = mysqli_fetch_assoc($results)) {
        $percentage = $row['total_marks'] > 0 ? round(($row['scored_marks'] / $row['total_marks']) * 100, 1) : 0;
        fputcsv($output, [
            $row['student_name'],
            $row['student_uname'],
            $row['student_email'],
            $row['exam_name'],
            $row['school_name'],
            $row['scored_marks'] ?? 'N/A',
            $row['total_marks'],
            $percentage . '%',
            ucfirst(str_replace('_', ' ', $row['submission_status'])),
            date('Y-m-d H:i', strtotime($row['submitted_at'])),
            strtoupper($row['grading_mode'])
        ]);
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Objective Exam Results</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .results-container {
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #17684f;
        }

        .stat-card .label {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .stat-card.pending .value { color: #ff9800; }
        .stat-card.error .value { color: #dc3545; }
        .stat-card.processing .value { color: #2196F3; }

        .filters-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filters-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 180px;
        }

        .filter-group label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-btn {
            padding: 10px 20px;
            background: #17684f;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .filter-btn:hover {
            background: #11533e;
        }

        .export-btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        .export-btn:hover {
            background: #218838;
        }

        .results-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .results-header {
            padding: 20px;
            background: #17684f;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        .results-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .results-table tr:hover {
            background: #f8f9fa;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-avatar {
            width: 36px;
            height: 36px;
            background: #17684f;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .student-details .name {
            font-weight: 600;
            color: #333;
        }

        .student-details .username {
            font-size: 12px;
            color: #666;
        }

        .score-display {
            font-weight: 600;
        }

        .score-pass { color: #28a745; }
        .score-fail { color: #dc3545; }

        .progress-bar {
            width: 100px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
        }

        .progress-fill.pass { background: #28a745; }
        .progress-fill.fail { background: #dc3545; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-ocr_processing { background: #cce5ff; color: #004085; }
        .status-ocr_complete { background: #d4edda; color: #155724; }
        .status-grading { background: #fff3cd; color: #856404; }
        .status-graded { background: #d1e7dd; color: #0f5132; }
        .status-error { background: #f8d7da; color: #721c24; }

        .grading-mode {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #666;
        }

        .action-btns {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 6px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-view { background: #17684f; color: white; }
        .btn-grade { background: #ff9800; color: white; }
        .btn-view:hover, .btn-grade:hover { opacity: 0.85; }

        .no-results {
            padding: 60px 20px;
            text-align: center;
            color: #666;
        }

        .no-results i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .filters-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .results-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-details">
            <i class='bx bxs-graduation'></i>
            <span class="logo_name">ExamPortal</span>
        </div>
        <ul class="nav-links">
            <li><a href="dash.php"><i class='bx bx-grid-alt'></i><span class="links_name">Dashboard</span></a></li>
            <li><a href="exams.php"><i class='bx bx-book'></i><span class="links_name">MCQ Exams</span></a></li>
            <li><a href="objective_exams.php"><i class='bx bx-file'></i><span class="links_name">Objective Exams</span></a></li>
            <li><a href="grade_objective.php"><i class='bx bx-check-circle'></i><span class="links_name">Grade Submissions</span></a></li>
            <li><a href="view_objective_results.php" class="active"><i class='bx bx-bar-chart'></i><span class="links_name">Objective Results</span></a></li>
            <li><a href="records.php"><i class='bx bx-user'></i><span class="links_name">Student Records</span></a></li>
            <li><a href="results.php"><i class='bx bx-bar-chart-alt-2'></i><span class="links_name">MCQ Results</span></a></li>
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu siderbar-btn'></i>
                <span class="dashboard">Objective Exam Results</span>
            </div>
            <div class="profile-details">
                <a href="messages.php" style="text-decoration: none; position: relative; margin-right: 15px;">
                    <i class='bx bx-bell' style="font-size: 24px; color: #17684f;"></i>
                    <?php if ($unread_count > 0): ?>
                        <span style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; 
                                     border-radius: 50%; width: 18px; height: 18px; font-size: 10px; 
                                     display: flex; align-items: center; justify-content: center;">
                            <?php echo $unread_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <span class="admin_name"><?php echo htmlspecialchars($fname); ?></span>
            </div>
        </nav>

        <div class="results-container">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="value"><?php echo $stats['total_submissions'] ?? 0; ?></div>
                    <div class="label">Total Submissions</div>
                </div>
                <div class="stat-card">
                    <div class="value"><?php echo $stats['graded_count'] ?? 0; ?></div>
                    <div class="label">Graded</div>
                </div>
                <div class="stat-card pending">
                    <div class="value"><?php echo $stats['pending_grading'] ?? 0; ?></div>
                    <div class="label">Pending Grading</div>
                </div>
                <div class="stat-card processing">
                    <div class="value"><?php echo $stats['processing'] ?? 0; ?></div>
                    <div class="label">Processing</div>
                </div>
                <div class="stat-card">
                    <div class="value"><?php echo $stats['avg_score'] ? number_format($stats['avg_score'], 1) : '-'; ?></div>
                    <div class="label">Average Score</div>
                </div>
                <div class="stat-card error">
                    <div class="value"><?php echo $stats['errors'] ?? 0; ?></div>
                    <div class="label">Errors</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label>Filter by Exam</label>
                            <select name="exam_id">
                                <option value="">All Exams</option>
                                <?php 
                                mysqli_data_seek($exams_result, 0);
                                while ($exam = mysqli_fetch_assoc($exams_result)): 
                                ?>
                                    <option value="<?php echo $exam['exam_id']; ?>" 
                                            <?php echo $filter_exam == $exam['exam_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($exam['exam_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Filter by Status</label>
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="ocr_processing" <?php echo $filter_status == 'ocr_processing' ? 'selected' : ''; ?>>OCR Processing</option>
                                <option value="ocr_complete" <?php echo $filter_status == 'ocr_complete' ? 'selected' : ''; ?>>Ready for Grading</option>
                                <option value="grading" <?php echo $filter_status == 'grading' ? 'selected' : ''; ?>>Grading</option>
                                <option value="graded" <?php echo $filter_status == 'graded' ? 'selected' : ''; ?>>Graded</option>
                                <option value="error" <?php echo $filter_status == 'error' ? 'selected' : ''; ?>>Error</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Filter by School</label>
                            <select name="school_id">
                                <option value="">All Schools</option>
                                <?php 
                                mysqli_data_seek($schools_result, 0);
                                while ($school = mysqli_fetch_assoc($schools_result)): 
                                ?>
                                    <option value="<?php echo $school['school_id']; ?>" 
                                            <?php echo $filter_school == $school['school_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($school['school_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="filter-btn">
                            <i class='bx bx-filter-alt'></i> Apply Filters
                        </button>
                        
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="export-btn">
                            <i class='bx bx-download'></i> Export CSV
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Table -->
            <div class="results-card">
                <div class="results-header">
                    <h2><i class='bx bx-table'></i> Submission Results</h2>
                    <span><?php echo mysqli_num_rows($results); ?> submissions</span>
                </div>
                
                <?php if (mysqli_num_rows($results) > 0): ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Exam</th>
                                <th>School</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Mode</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($results)): 
                                $percentage = $row['total_marks'] > 0 && $row['scored_marks'] !== null 
                                    ? round(($row['scored_marks'] / $row['total_marks']) * 100, 1) 
                                    : 0;
                                $passed = $row['scored_marks'] >= $row['passing_marks'];
                                $initial = strtoupper(substr($row['student_name'], 0, 1));
                            ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <div class="student-avatar"><?php echo $initial; ?></div>
                                            <div class="student-details">
                                                <div class="name"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                                <div class="username"><?php echo htmlspecialchars($row['student_uname']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['exam_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['school_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($row['submission_status'] == 'graded'): ?>
                                            <div class="score-display <?php echo $passed ? 'score-pass' : 'score-fail'; ?>">
                                                <?php echo number_format($row['scored_marks'], 1); ?>/<?php echo $row['total_marks']; ?>
                                                <small>(<?php echo $percentage; ?>%)</small>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill <?php echo $passed ? 'pass' : 'fail'; ?>" 
                                                     style="width: <?php echo min($percentage, 100); ?>%"></div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $row['submission_status']; ?>">
                                            <?php echo str_replace('_', ' ', $row['submission_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="grading-mode">
                                            <i class='bx bx-<?php echo $row['grading_mode'] == 'ai' ? 'brain' : 'user'; ?>'></i>
                                            <?php echo strtoupper($row['grading_mode']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px;"><?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></div>
                                        <div style="font-size: 11px; color: #666;"><?php echo date('h:i A', strtotime($row['submitted_at'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <?php if (in_array($row['submission_status'], ['ocr_complete', 'grading', 'graded'])): ?>
                                                <a href="grade_objective.php?id=<?php echo $row['submission_id']; ?>&exam_id=<?php echo $row['exam_id']; ?>" 
                                                   class="action-btn <?php echo $row['submission_status'] == 'graded' ? 'btn-view' : 'btn-grade'; ?>">
                                                    <i class='bx bx-<?php echo $row['submission_status'] == 'graded' ? 'show' : 'edit'; ?>'></i>
                                                    <?php echo $row['submission_status'] == 'graded' ? 'View' : 'Grade'; ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-results">
                        <i class='bx bx-file-find'></i>
                        <h3>No submissions found</h3>
                        <p>Try adjusting your filters or wait for students to submit their exams</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        // Sidebar toggle
        let sidebar = document.querySelector(".sidebar");
        let sidebarBtn = document.querySelector(".siderbar-btn");
        if (sidebarBtn) {
            sidebarBtn.onclick = function() {
                sidebar.classList.toggle("active");
            }
        }
    </script>
</body>

</html>
