<?php
/**
 * Admin Analytics & Reports Dashboard (Combined)
 * Comprehensive overview of teaching slots, sessions, and enrollments
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];

// Get date filters
$view_period = $_GET['period'] ?? 'month';
$filter_school = intval($_GET['school'] ?? 0);

// Calculate date ranges
$today = date('Y-m-d');
switch ($view_period) {
    case 'today':
        $start_date = $today;
        $end_date = $today;
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'all':
        $start_date = '2000-01-01';
        $end_date = '2099-12-31';
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

// Get all schools for dropdown
$schools_sql = "SELECT school_id, school_name FROM schools WHERE status = 'active' ORDER BY school_name";
$schools = mysqli_query($conn, $schools_sql);
$schools_list = [];
while ($s = mysqli_fetch_assoc($schools)) {
    $schools_list[$s['school_id']] = $s['school_name'];
}

// Build filters
$stats_where = "WHERE sts.slot_date BETWEEN '$start_date' AND '$end_date'";
if ($filter_school > 0) {
    $stats_where .= " AND sts.school_id = $filter_school";
}

// === SLOT STATS ===
$slot_stats = [
    'total_slots' => 0,
    'open_slots' => 0,
    'filled_slots' => 0,
    'total_enrollments' => 0,
    'pending_sessions' => 0,
    'approved_sessions' => 0
];

$stats_sql = "SELECT 
    COUNT(*) as total_slots,
    SUM(CASE WHEN slot_status = 'open' THEN 1 ELSE 0 END) as open_slots,
    SUM(CASE WHEN slot_status = 'full' OR slot_status = 'partially_filled' THEN 1 ELSE 0 END) as filled_slots,
    SUM(teachers_enrolled) as total_enrollments
    FROM school_teaching_slots sts $stats_where";
$result = mysqli_query($conn, $stats_sql);
if ($row = mysqli_fetch_assoc($result)) {
    $slot_stats = array_merge($slot_stats, $row);
}

// Session stats
$session_sql = "SELECT 
    SUM(CASE WHEN ts.session_status = 'pending' OR ts.session_status = 'photo_submitted' THEN 1 ELSE 0 END) as pending_sessions,
    SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as approved_sessions
    FROM teaching_sessions ts
    JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
    $stats_where";
$result = mysqli_query($conn, $session_sql);
if ($row = mysqli_fetch_assoc($result)) {
    $slot_stats['pending_sessions'] = $row['pending_sessions'] ?? 0;
    $slot_stats['approved_sessions'] = $row['approved_sessions'] ?? 0;
}

// === UPCOMING SLOTS ===
$upcoming_where = "WHERE sts.slot_date >= '$today' AND sts.slot_status NOT IN ('completed', 'cancelled')";
if ($filter_school > 0) {
    $upcoming_where .= " AND sts.school_id = $filter_school";
}
$upcoming_sql = "SELECT sts.*, s.school_name
    FROM school_teaching_slots sts
    JOIN schools s ON sts.school_id = s.school_id
    $upcoming_where
    ORDER BY sts.slot_date ASC, sts.start_time ASC
    LIMIT 8";
$upcoming_slots = mysqli_query($conn, $upcoming_sql);

// === RECENT ENROLLMENTS ===
$recent_sql = "SELECT ste.*, t.fname as teacher_name, t.email as teacher_email,
    sts.slot_date, sts.start_time, s.school_name
    FROM slot_teacher_enrollments ste
    JOIN teacher t ON ste.teacher_id = t.id
    JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
    JOIN schools s ON sts.school_id = s.school_id
    ORDER BY ste.booked_at DESC
    LIMIT 8";
$recent_enrollments = mysqli_query($conn, $recent_sql);

// === SCHOOL BREAKDOWN ===
$school_stats_sql = "SELECT s.school_id, s.school_name,
    COUNT(sts.slot_id) as total_slots,
    SUM(CASE WHEN sts.slot_status IN ('open', 'partially_filled') THEN 1 ELSE 0 END) as open_slots,
    SUM(CASE WHEN sts.slot_status = 'full' THEN 1 ELSE 0 END) as full_slots,
    SUM(sts.teachers_enrolled) as enrolled_teachers,
    SUM(sts.teachers_required) as required_teachers
    FROM schools s
    LEFT JOIN school_teaching_slots sts ON s.school_id = sts.school_id 
        AND sts.slot_date BETWEEN '$start_date' AND '$end_date'
    WHERE s.status = 'active'
    GROUP BY s.school_id
    ORDER BY total_slots DESC";
$school_stats = mysqli_query($conn, $school_stats_sql);

// === SESSION SUBMISSION STATS ===
$submission_stats = ['total' => 0, 'approved' => 0, 'rejected' => 0, 'pending' => 0];
$sub_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN session_status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN session_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN session_status IN ('pending', 'photo_submitted') THEN 1 ELSE 0 END) as pending
    FROM teaching_sessions ts
    JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
    $stats_where";
$result = mysqli_query($conn, $sub_sql);
if ($row = mysqli_fetch_assoc($result)) {
    $submission_stats = $row;
}

// Top teachers by sessions
$topTeachersSql = "SELECT t.id, t.fname, t.email, 
    COUNT(*) as total_sessions,
    SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM teaching_sessions ts
    JOIN slot_teacher_enrollments ste ON ts.enrollment_id = ste.enrollment_id
    JOIN teacher t ON ste.teacher_id = t.id
    JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
    $stats_where
    GROUP BY t.id ORDER BY total_sessions DESC LIMIT 5";
$topTeachers = mysqli_query($conn, $topTeachersSql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Analytics & Reports | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stat-card h3 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        .stat-card h3.primary { color: var(--primary-color); }
        .stat-card h3.success { color: var(--success-color); }
        .stat-card h3.warning { color: var(--warning-color); }
        .stat-card h3.danger { color: var(--danger-color); }
        .stat-card h3.info { color: var(--info-color); }
        .stat-card p {
            color: var(--text-muted);
            font-size: 13px;
        }
        .filters-bar {
            background: var(--card-bg);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 12px;
            color: var(--text-muted);
        }
        .period-tabs {
            display: flex;
            gap: 5px;
            background: #f0f0f0;
            padding: 4px;
            border-radius: 8px;
        }
        .period-tab {
            padding: 8px 14px;
            border: none;
            background: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s;
        }
        .period-tab.active {
            background: var(--primary-color);
            color: white;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 1100px) {
            .content-grid { grid-template-columns: 1fr; }
        }
        .slot-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .slot-status.open { background: #dcfce7; color: #166534; }
        .slot-status.partially_filled { background: #fef3c7; color: #92400e; }
        .slot-status.full { background: #dbeafe; color: #1e40af; }
        .progress-bar {
            background: #e5e7eb;
            border-radius: 10px;
            height: 6px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-color));
        }
        .mini-table {
            width: 100%;
            font-size: 13px;
        }
        .mini-table th {
            text-align: left;
            padding: 8px 10px;
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
        }
        .mini-table td {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .enrollment-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
        }
        .enrollment-badge.booked { background: #dbeafe; color: #1e40af; }
        .enrollment-badge.cancelled { background: #fee2e2; color: #991b1b; }
        .school-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
        }
        .school-info h4 { margin-bottom: 5px; font-size: 14px; }
        .school-stats {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .school-stats strong { color: var(--text-color); }
        .chart-container {
            position: relative;
            height: 200px;
        }
        .export-btn {
            padding: 8px 16px;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .export-btn:hover { background: #047857; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1><i class='bx bx-bar-chart-alt-2'></i> Analytics & Reports</h1>
                <p class="subtitle">Teaching slots, sessions, and enrollment overview</p>
            </div>
            <a href="export_report.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&school_id=<?= $filter_school ?>" class="export-btn">
                <i class='bx bx-download'></i> Export CSV
            </a>
        </div>
        
        <!-- Filters -->
        <div class="filters-bar">
            <div class="filter-group">
                <label>Time Period</label>
                <div class="period-tabs">
                    <button class="period-tab <?= $view_period === 'today' ? 'active' : '' ?>" onclick="setPeriod('today')">Today</button>
                    <button class="period-tab <?= $view_period === 'week' ? 'active' : '' ?>" onclick="setPeriod('week')">Week</button>
                    <button class="period-tab <?= $view_period === 'month' ? 'active' : '' ?>" onclick="setPeriod('month')">Month</button>
                    <button class="period-tab <?= $view_period === 'all' ? 'active' : '' ?>" onclick="setPeriod('all')">All</button>
                </div>
            </div>
            <div class="filter-group">
                <label>School</label>
                <select id="filter-school" onchange="applyFilters()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd;">
                    <option value="">All Schools</option>
                    <?php foreach ($schools_list as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $filter_school == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Key Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3 class="primary"><?= $slot_stats['total_slots'] ?? 0 ?></h3>
                <p>Total Slots</p>
            </div>
            <div class="stat-card">
                <h3 class="success"><?= $slot_stats['open_slots'] ?? 0 ?></h3>
                <p>Open Slots</p>
            </div>
            <div class="stat-card">
                <h3 class="info"><?= $slot_stats['total_enrollments'] ?? 0 ?></h3>
                <p>Enrollments</p>
            </div>
            <div class="stat-card">
                <h3 class="warning"><?= $slot_stats['pending_sessions'] ?? 0 ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-card">
                <h3 class="success"><?= $slot_stats['approved_sessions'] ?? 0 ?></h3>
                <p>Approved</p>
            </div>
            <div class="stat-card">
                <h3 class="danger"><?= $submission_stats['rejected'] ?? 0 ?></h3>
                <p>Rejected</p>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="content-grid">
            <!-- Upcoming Slots -->
            <div class="card">
                <div class="card-header">
                    <h2><i class='bx bx-calendar'></i> Upcoming Slots</h2>
                    <a href="teaching_slots.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($upcoming_slots) > 0): ?>
                    <table class="mini-table">
                        <thead>
                            <tr><th>School</th><th>Date</th><th>Teachers</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($slot = mysqli_fetch_assoc($upcoming_slots)): 
                                $fill_pct = $slot['teachers_required'] > 0 ? ($slot['teachers_enrolled'] / $slot['teachers_required']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <a href="view_slot.php?id=<?= $slot['slot_id'] ?>" style="color: var(--primary-color); font-weight: 500;">
                                        <?= htmlspecialchars($slot['school_name']) ?>
                                    </a>
                                </td>
                                <td><?= date('M j', strtotime($slot['slot_date'])) ?><br><small><?= date('h:i A', strtotime($slot['start_time'])) ?></small></td>
                                <td>
                                    <?= $slot['teachers_enrolled'] ?>/<?= $slot['teachers_required'] ?>
                                    <div class="progress-bar"><div class="progress-fill" style="width: <?= min(100, $fill_pct) ?>%"></div></div>
                                </td>
                                <td><span class="slot-status <?= $slot['slot_status'] ?>"><?= ucfirst(str_replace('_', ' ', $slot['slot_status'])) ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted">No upcoming slots.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Enrollments -->
            <div class="card">
                <div class="card-header">
                    <h2><i class='bx bx-group'></i> Recent Enrollments</h2>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($recent_enrollments) > 0): ?>
                    <table class="mini-table">
                        <thead>
                            <tr><th>Teacher</th><th>School</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($enroll = mysqli_fetch_assoc($recent_enrollments)): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($enroll['teacher_name']) ?></strong></td>
                                <td><?= htmlspecialchars($enroll['school_name']) ?></td>
                                <td><?= date('M j', strtotime($enroll['slot_date'])) ?></td>
                                <td><span class="enrollment-badge <?= $enroll['enrollment_status'] ?>"><?= ucfirst($enroll['enrollment_status']) ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted">No recent enrollments.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="content-grid" style="margin-top: 20px;">
            <div class="card">
                <div class="card-header">
                    <h2><i class='bx bx-pie-chart-alt-2'></i> Session Status</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top Teachers -->
            <div class="card">
                <div class="card-header">
                    <h2><i class='bx bx-trophy'></i> Top Teachers</h2>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($topTeachers) > 0): ?>
                    <table class="mini-table">
                        <thead>
                            <tr><th>Teacher</th><th>Sessions</th><th>Approved</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($teacher = mysqli_fetch_assoc($topTeachers)): 
                                $rate = $teacher['total_sessions'] > 0 ? round(($teacher['approved'] / $teacher['total_sessions']) * 100) : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($teacher['fname']) ?></strong><br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($teacher['email']) ?></small>
                                </td>
                                <td><?= $teacher['total_sessions'] ?></td>
                                <td>
                                    <span style="color: var(--success-color);"><?= $teacher['approved'] ?></span>
                                    <span style="color: var(--text-muted); font-size: 11px;">(<?= $rate ?>%)</span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted">No data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- School Breakdown -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h2><i class='bx bx-building'></i> School Overview</h2>
            </div>
            <div class="card-body">
                <?php 
                mysqli_data_seek($school_stats, 0);
                if (mysqli_num_rows($school_stats) > 0): 
                ?>
                    <?php while ($school = mysqli_fetch_assoc($school_stats)): 
                        $coverage = ($school['required_teachers'] > 0) ? round(($school['enrolled_teachers'] / $school['required_teachers']) * 100) : 0;
                    ?>
                    <div class="school-card">
                        <div class="school-info">
                            <h4><?= htmlspecialchars($school['school_name']) ?></h4>
                            <div class="school-stats">
                                <span><strong><?= $school['total_slots'] ?? 0 ?></strong> Slots</span>
                                <span><strong><?= $school['open_slots'] ?? 0 ?></strong> Open</span>
                                <span><strong><?= $school['full_slots'] ?? 0 ?></strong> Full</span>
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 120px;">
                            <div style="font-size: 20px; font-weight: 600; color: <?= $coverage >= 80 ? 'var(--success-color)' : ($coverage >= 50 ? 'var(--warning-color)' : 'var(--danger-color)') ?>;">
                                <?= $coverage ?>%
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted);">Coverage</div>
                            <div class="progress-bar" style="margin-top: 5px;">
                                <div class="progress-fill" style="width: <?= min(100, $coverage) ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                <p class="text-muted">No schools found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function setPeriod(period) {
            const school = document.getElementById('filter-school').value;
            let url = `reports.php?period=${period}`;
            if (school) url += `&school=${school}`;
            window.location.href = url;
        }
        
        function applyFilters() {
            const school = document.getElementById('filter-school').value;
            const period = '<?= $view_period ?>';
            let url = `reports.php?period=${period}`;
            if (school) url += `&school=${school}`;
            window.location.href = url;
        }
        
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [<?= $submission_stats['approved'] ?? 0 ?>, <?= $submission_stats['pending'] ?? 0 ?>, <?= $submission_stats['rejected'] ?? 0 ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    </script>
</body>
</html>
