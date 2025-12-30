<?php
/**
 * Admin - Teacher Statistics
 * Phase 4: Dashboard showing all teachers with their teaching statistics
 * 
 * Features:
 * - List all teachers who have booked slots
 * - Show statistics: total slots, completed, rejected, pending, rate
 * - Total teaching hours
 * - Filtering by school, date range, subject
 * - Export to CSV
 * - Sorting by any column
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';
require_once '../utils/duration_validator.php';

$admin_id = $_SESSION['admin_id'];

// Get filter parameters
$filter_school = intval($_GET['school_id'] ?? 0);
$filter_subject = $_GET['subject'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'total_sessions';
$sort_dir = $_GET['dir'] ?? 'DESC';

// Validate sort direction
$sort_dir = strtoupper($sort_dir) === 'ASC' ? 'ASC' : 'DESC';

// Allowed sort columns
$allowed_sorts = ['teacher_name', 'subject', 'total_sessions', 'completed', 'rejected', 'pending', 'completion_rate', 'total_hours'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'total_sessions';
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportCSV($conn, $filter_school, $filter_subject, $filter_date_from, $filter_date_to);
    exit;
}

// Build the main query for teacher statistics
$sql = "SELECT 
        t.id as teacher_id,
        t.fname as teacher_name,
        t.email,
        t.subject,
        COUNT(DISTINCT ts.session_id) as total_sessions,
        SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN ts.session_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN ts.session_status IN ('pending', 'start_submitted', 'start_approved', 'end_submitted') THEN 1 ELSE 0 END) as pending,
        ROUND(
            SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) * 100.0 / 
            NULLIF(COUNT(DISTINCT ts.session_id), 0), 
            1
        ) as completion_rate,
        SUM(CASE WHEN ts.session_status = 'approved' THEN COALESCE(ts.actual_duration_minutes, 0) ELSE 0 END) as total_minutes,
        ROUND(
            SUM(CASE WHEN ts.session_status = 'approved' THEN COALESCE(ts.actual_duration_minutes, 0) ELSE 0 END) / 60.0,
            1
        ) as total_hours,
        AVG(CASE 
            WHEN ts.session_status = 'approved' AND ts.expected_duration_minutes > 0 
            THEN (ts.actual_duration_minutes * 100.0 / ts.expected_duration_minutes)
            ELSE NULL 
        END) as avg_duration_compliance,
        COUNT(DISTINCT ts.school_id) as schools_count,
        MAX(sts.slot_date) as last_session_date
        FROM teacher t
        LEFT JOIN teaching_sessions ts ON t.id = ts.teacher_id
        LEFT JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
        WHERE 1=1";

$params = [];
$types = "";

// Apply filters
if ($filter_school) {
    $sql .= " AND ts.school_id = ?";
    $params[] = $filter_school;
    $types .= "i";
}

if ($filter_subject) {
    $sql .= " AND t.subject = ?";
    $params[] = $filter_subject;
    $types .= "s";
}

if ($filter_date_from) {
    $sql .= " AND sts.slot_date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if ($filter_date_to) {
    $sql .= " AND sts.slot_date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$sql .= " GROUP BY t.id, t.fname, t.email, t.subject
          HAVING total_sessions > 0
          ORDER BY $sort_by $sort_dir";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$teachers = mysqli_stmt_get_result($stmt);

// Get summary statistics
$summary_sql = "SELECT 
    COUNT(DISTINCT t.id) as total_teachers,
    COUNT(DISTINCT ts.session_id) as total_sessions,
    SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as total_completed,
    SUM(CASE WHEN ts.session_status = 'approved' THEN COALESCE(ts.actual_duration_minutes, 0) ELSE 0 END) as total_minutes
    FROM teacher t
    JOIN teaching_sessions ts ON t.id = ts.teacher_id";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Get schools for filter dropdown
$schools = mysqli_query($conn, "SELECT school_id, school_name FROM schools ORDER BY school_name");

// Get unique subjects for filter dropdown
$subjects = mysqli_query($conn, "SELECT DISTINCT subject FROM teacher WHERE subject IS NOT NULL AND subject != '' ORDER BY subject");

// Helper function to generate sort link
function sortLink($column, $current_sort, $current_dir) {
    $new_dir = ($current_sort === $column && $current_dir === 'DESC') ? 'ASC' : 'DESC';
    $params = $_GET;
    $params['sort'] = $column;
    $params['dir'] = $new_dir;
    return '?' . http_build_query($params);
}

function sortIcon($column, $current_sort, $current_dir) {
    if ($current_sort !== $column) return '<i class="bx bx-sort-alt-2"></i>';
    return $current_dir === 'ASC' ? '<i class="bx bx-sort-up"></i>' : '<i class="bx bx-sort-down"></i>';
}

// CSV Export function
function exportCSV($conn, $school, $subject, $date_from, $date_to) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="teacher_statistics_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'Teacher Name', 'Email', 'Subject', 'Total Sessions', 'Completed', 
        'Rejected', 'Pending', 'Completion Rate %', 'Total Hours', 
        'Avg Duration Compliance %', 'Schools Taught', 'Last Session'
    ]);
    
    // Build query (same as main query)
    $sql = "SELECT 
            t.fname, t.email, t.subject,
            COUNT(DISTINCT ts.session_id) as total_sessions,
            SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN ts.session_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN ts.session_status IN ('pending', 'start_submitted', 'start_approved', 'end_submitted') THEN 1 ELSE 0 END) as pending,
            ROUND(SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(DISTINCT ts.session_id), 0), 1) as completion_rate,
            ROUND(SUM(CASE WHEN ts.session_status = 'approved' THEN COALESCE(ts.actual_duration_minutes, 0) ELSE 0 END) / 60.0, 1) as total_hours,
            ROUND(AVG(CASE WHEN ts.session_status = 'approved' AND ts.expected_duration_minutes > 0 THEN (ts.actual_duration_minutes * 100.0 / ts.expected_duration_minutes) ELSE NULL END), 1) as avg_compliance,
            COUNT(DISTINCT ts.school_id) as schools_count,
            MAX(sts.slot_date) as last_session
            FROM teacher t
            LEFT JOIN teaching_sessions ts ON t.id = ts.teacher_id
            LEFT JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($school) {
        $sql .= " AND ts.school_id = ?";
        $params[] = $school;
        $types .= "i";
    }
    if ($subject) {
        $sql .= " AND t.subject = ?";
        $params[] = $subject;
        $types .= "s";
    }
    if ($date_from) {
        $sql .= " AND sts.slot_date >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    if ($date_to) {
        $sql .= " AND sts.slot_date <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql .= " GROUP BY t.id HAVING total_sessions > 0 ORDER BY total_sessions DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['fname'],
            $row['email'],
            $row['subject'] ?? 'N/A',
            $row['total_sessions'],
            $row['completed'],
            $row['rejected'],
            $row['pending'],
            $row['completion_rate'] ?? 0,
            $row['total_hours'] ?? 0,
            $row['avg_compliance'] ?? 'N/A',
            $row['schools_count'],
            $row['last_session'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Statistics | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css?v=3.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
        }
        
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .summary-card .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .summary-card .label {
            font-size: 13px;
            color: #6b7280;
        }
        .summary-card.teachers .value { color: #7C0A02; }
        .summary-card.sessions .value { color: #3b82f6; }
        .summary-card.completed .value { color: #22c55e; }
        .summary-card.hours .value { color: #8b5cf6; }
        
        /* Filters Bar */
        .filters-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
        }
        .filter-group select, .filter-group input {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 13px;
            min-width: 150px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: #7C0A02; color: white; }
        .btn-primary:hover { background: #5c0801; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-success { background: #22c55e; color: white; }
        .btn-success:hover { background: #16a34a; }
        .btn-sm { padding: 6px 10px; font-size: 11px; }
        
        /* Data Table */
        .data-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .data-card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .data-card-header h2 {
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stats-table {
            width: 100%;
            border-collapse: collapse;
        }
        .stats-table th {
            background: #f9fafb;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .stats-table th a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .stats-table th a:hover { color: #7C0A02; }
        .stats-table td {
            padding: 12px 15px;
            font-size: 13px;
            border-bottom: 1px solid #f3f4f6;
        }
        .stats-table tbody tr:hover { background: #fafafa; }
        
        .teacher-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .teacher-name {
            font-weight: 600;
            color: #111827;
        }
        .teacher-email {
            font-size: 11px;
            color: #6b7280;
        }
        
        .stat-cell {
            text-align: center;
            font-weight: 500;
        }
        .stat-cell.total { color: #374151; font-weight: 600; }
        .stat-cell.completed { color: #22c55e; }
        .stat-cell.rejected { color: #ef4444; }
        .stat-cell.pending { color: #f59e0b; }
        
        .rate-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .rate-badge.excellent { background: #dcfce7; color: #166534; }
        .rate-badge.good { background: #dbeafe; color: #1e40af; }
        .rate-badge.average { background: #fef3c7; color: #92400e; }
        .rate-badge.poor { background: #fee2e2; color: #991b1b; }
        
        .hours-cell {
            font-weight: 500;
            color: #6d28d9;
        }
        
        .compliance-bar {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .compliance-bar .bar {
            width: 60px;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }
        .compliance-bar .bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s;
        }
        .compliance-bar .bar-fill.excellent { background: #22c55e; }
        .compliance-bar .bar-fill.good { background: #3b82f6; }
        .compliance-bar .bar-fill.average { background: #f59e0b; }
        .compliance-bar .bar-fill.poor { background: #ef4444; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i { font-size: 64px; color: #d1d5db; margin-bottom: 15px; }
        .empty-state h3 { color: #374151; margin-bottom: 8px; }
        .empty-state p { color: #6b7280; }
        
        @media (max-width: 1200px) {
            .stats-table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class='bx bx-bar-chart-alt-2'></i> Teacher Statistics</h1>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
                <i class='bx bx-download'></i> Export CSV
            </a>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card teachers">
                <div class="value"><?= $summary['total_teachers'] ?? 0 ?></div>
                <div class="label">Active Teachers</div>
            </div>
            <div class="summary-card sessions">
                <div class="value"><?= $summary['total_sessions'] ?? 0 ?></div>
                <div class="label">Total Sessions</div>
            </div>
            <div class="summary-card completed">
                <div class="value"><?= $summary['total_completed'] ?? 0 ?></div>
                <div class="label">Completed Sessions</div>
            </div>
            <div class="summary-card hours">
                <div class="value"><?= number_format(($summary['total_minutes'] ?? 0) / 60, 1) ?></div>
                <div class="label">Total Teaching Hours</div>
            </div>
        </div>
        
        <!-- Filters -->
        <form class="filters-bar" method="GET">
            <div class="filter-group">
                <label>School</label>
                <select name="school_id" onchange="this.form.submit()">
                    <option value="">All Schools</option>
                    <?php mysqli_data_seek($schools, 0); while ($school = mysqli_fetch_assoc($schools)): ?>
                    <option value="<?= $school['school_id'] ?>" <?= $filter_school == $school['school_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($school['school_name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Subject</label>
                <select name="subject" onchange="this.form.submit()">
                    <option value="">All Subjects</option>
                    <?php mysqli_data_seek($subjects, 0); while ($subj = mysqli_fetch_assoc($subjects)): ?>
                    <option value="<?= htmlspecialchars($subj['subject']) ?>" <?= $filter_subject === $subj['subject'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($subj['subject']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>" onchange="this.form.submit()">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>" onchange="this.form.submit()">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="teacher_stats.php" class="btn btn-secondary">Clear</a>
        </form>
        
        <!-- Data Table -->
        <div class="data-card">
            <div class="data-card-header">
                <h2><i class='bx bx-table'></i> Teacher Performance</h2>
                <span style="color: #6b7280; font-size: 13px;">
                    <?= mysqli_num_rows($teachers) ?> teachers found
                </span>
            </div>
            
            <?php if (mysqli_num_rows($teachers) > 0): ?>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>
                            <a href="<?= sortLink('teacher_name', $sort_by, $sort_dir) ?>">
                                Teacher <?= sortIcon('teacher_name', $sort_by, $sort_dir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= sortLink('subject', $sort_by, $sort_dir) ?>">
                                Subject <?= sortIcon('subject', $sort_by, $sort_dir) ?>
                            </a>
                        </th>
                        <th style="text-align: center;">
                            <a href="<?= sortLink('total_sessions', $sort_by, $sort_dir) ?>">
                                Total <?= sortIcon('total_sessions', $sort_by, $sort_dir) ?>
                            </a>
                        </th>
                        <th style="text-align: center;">
                            <a href="<?= sortLink('completed', $sort_by, $sort_dir) ?>">
                                Completed <?= sortIcon('completed', $sort_by, $sort_dir) ?>
                            </a>
                        </th>
                        <th style="text-align: center;">
                            <a href="<?= sortLink('rejected', $sort_by, $sort_dir) ?>">
                                Rejected <?= sortIcon('rejected', $sort_by, $sort_dir) ?>
                            </a>
                        </th>
                        <th style="text-align: center;">
                            <a href="<?= sortLink('pending', $sort_by, $sort_dir) ?>">
                                Pending <?= sortIcon('pending', $sort_by, $sort_dir) ?>
                            </a>
                        </th>
                        <th style="text-align: center;">
                            <a href="<?= sortLink('completion_rate', $sort_by, $sort_dir) ?>">
                                Rate <?= sortIcon('completion_rate', $sort_by, $sort_dir) ?>
                            </a>
                        </th>
                        <th style="text-align: center;">
                            <a href="<?= sortLink('total_hours', $sort_by, $sort_dir) ?>">
                                Hours <?= sortIcon('total_hours', $sort_by, $sort_dir) ?>
                            </a>
                        </th>
                        <th>Duration Compliance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($teacher = mysqli_fetch_assoc($teachers)): 
                        $rate = $teacher['completion_rate'] ?? 0;
                        $rate_class = $rate >= 90 ? 'excellent' : ($rate >= 75 ? 'good' : ($rate >= 50 ? 'average' : 'poor'));
                        
                        $compliance = $teacher['avg_duration_compliance'] ?? 0;
                        $compliance_class = $compliance >= 95 ? 'excellent' : ($compliance >= 85 ? 'good' : ($compliance >= 70 ? 'average' : 'poor'));
                    ?>
                    <tr>
                        <td>
                            <div class="teacher-info">
                                <span class="teacher-name"><?= htmlspecialchars($teacher['teacher_name']) ?></span>
                                <span class="teacher-email"><?= htmlspecialchars($teacher['email']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($teacher['subject'] ?? 'N/A') ?></td>
                        <td class="stat-cell total"><?= $teacher['total_sessions'] ?></td>
                        <td class="stat-cell completed"><?= $teacher['completed'] ?></td>
                        <td class="stat-cell rejected"><?= $teacher['rejected'] ?></td>
                        <td class="stat-cell pending"><?= $teacher['pending'] ?></td>
                        <td style="text-align: center;">
                            <span class="rate-badge <?= $rate_class ?>">
                                <?= number_format($rate, 1) ?>%
                            </span>
                        </td>
                        <td class="stat-cell hours-cell"><?= number_format($teacher['total_hours'] ?? 0, 1) ?>h</td>
                        <td>
                            <?php if ($compliance > 0): ?>
                            <div class="compliance-bar">
                                <div class="bar">
                                    <div class="bar-fill <?= $compliance_class ?>" style="width: <?= min(100, $compliance) ?>%"></div>
                                </div>
                                <span style="font-size: 12px;"><?= number_format($compliance, 0) ?>%</span>
                            </div>
                            <?php else: ?>
                            <span style="color: #9ca3af; font-size: 12px;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="teacher_detail.php?id=<?= $teacher['teacher_id'] ?>" class="btn btn-primary btn-sm">
                                <i class='bx bx-show'></i> Details
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-user-x'></i>
                <h3>No Teachers Found</h3>
                <p>No teachers have booked teaching slots matching your filters.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
