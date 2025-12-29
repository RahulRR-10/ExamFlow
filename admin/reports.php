<?php
/**
 * Admin Reports & Analytics Dashboard
 * 
 * Comprehensive analytics for teaching activity verification
 */

session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

// Date range filter
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$schoolFilter = intval($_GET['school_id'] ?? 0);

// Get overall statistics
$stats = [];

// Total submissions in date range
$sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN location_match_status = 'matched' THEN 1 ELSE 0 END) as location_matched,
            SUM(CASE WHEN location_match_status = 'mismatched' THEN 1 ELSE 0 END) as location_mismatched,
            SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END) as suspicious
        FROM teaching_activity_submissions
        WHERE DATE(upload_date) BETWEEN ? AND ?";
$params = [$startDate, $endDate];
$types = "ss";

if ($schoolFilter > 0) {
    $sql .= " AND school_id = ?";
    $params[] = $schoolFilter;
    $types .= "i";
}

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Calculate rates
$stats['approval_rate'] = $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100, 1) : 0;
$stats['rejection_rate'] = $stats['total'] > 0 ? round(($stats['rejected'] / $stats['total']) * 100, 1) : 0;
$stats['location_match_rate'] = $stats['total'] > 0 ? round(($stats['location_matched'] / $stats['total']) * 100, 1) : 0;

// Daily submission trend (last 30 days)
$trendSql = "SELECT DATE(upload_date) as date, 
                    COUNT(*) as total,
                    SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
             FROM teaching_activity_submissions
             WHERE DATE(upload_date) BETWEEN ? AND ?";
if ($schoolFilter > 0) {
    $trendSql .= " AND school_id = ?";
}
$trendSql .= " GROUP BY DATE(upload_date) ORDER BY date ASC";

$stmt = mysqli_prepare($conn, $trendSql);
if ($schoolFilter > 0) {
    mysqli_stmt_bind_param($stmt, "ssi", $startDate, $endDate, $schoolFilter);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
}
mysqli_stmt_execute($stmt);
$trendResult = mysqli_stmt_get_result($stmt);
$trendData = [];
while ($row = mysqli_fetch_assoc($trendResult)) {
    $trendData[] = $row;
}
mysqli_stmt_close($stmt);

// Top teachers by submissions
$topTeachersSql = "SELECT t.id, t.fname, t.email, 
                          COUNT(*) as total_submissions,
                          SUM(CASE WHEN tas.verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
                          SUM(CASE WHEN tas.verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
                   FROM teaching_activity_submissions tas
                   JOIN teacher t ON tas.teacher_id = t.id
                   WHERE DATE(tas.upload_date) BETWEEN ? AND ?";
if ($schoolFilter > 0) {
    $topTeachersSql .= " AND tas.school_id = ?";
}
$topTeachersSql .= " GROUP BY t.id ORDER BY total_submissions DESC LIMIT 10";

$stmt = mysqli_prepare($conn, $topTeachersSql);
if ($schoolFilter > 0) {
    mysqli_stmt_bind_param($stmt, "ssi", $startDate, $endDate, $schoolFilter);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
}
mysqli_stmt_execute($stmt);
$topTeachers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Schools by submissions
$schoolStatsSql = "SELECT s.school_id, s.school_name,
                          COUNT(*) as total_submissions,
                          SUM(CASE WHEN tas.verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
                          SUM(CASE WHEN tas.verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                          SUM(CASE WHEN tas.location_match_status = 'matched' THEN 1 ELSE 0 END) as location_matched
                   FROM teaching_activity_submissions tas
                   JOIN schools s ON tas.school_id = s.school_id
                   WHERE DATE(tas.upload_date) BETWEEN ? AND ?
                   GROUP BY s.school_id
                   ORDER BY total_submissions DESC";

$stmt = mysqli_prepare($conn, $schoolStatsSql);
mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($stmt);
$schoolStats = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Admin verification activity
$adminStatsSql = "SELECT a.id, a.fname,
                         COUNT(*) as verifications,
                         SUM(CASE WHEN tas.verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
                         SUM(CASE WHEN tas.verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
                  FROM teaching_activity_submissions tas
                  JOIN admin a ON tas.verified_by = a.id
                  WHERE DATE(tas.verified_at) BETWEEN ? AND ?
                  GROUP BY a.id
                  ORDER BY verifications DESC";

$stmt = mysqli_prepare($conn, $adminStatsSql);
mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($stmt);
$adminStats = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Get all schools for filter dropdown
$allSchools = [];
$schoolsResult = mysqli_query($conn, "SELECT school_id, school_name FROM schools WHERE status = 'active' ORDER BY school_name");
while ($row = mysqli_fetch_assoc($schoolsResult)) {
    $allSchools[] = $row;
}

// Hourly distribution
$hourlyDistSql = "SELECT HOUR(upload_date) as hour, COUNT(*) as count
                  FROM teaching_activity_submissions
                  WHERE DATE(upload_date) BETWEEN ? AND ?";
if ($schoolFilter > 0) {
    $hourlyDistSql .= " AND school_id = ?";
}
$hourlyDistSql .= " GROUP BY HOUR(upload_date) ORDER BY hour";

$stmt = mysqli_prepare($conn, $hourlyDistSql);
if ($schoolFilter > 0) {
    mysqli_stmt_bind_param($stmt, "ssi", $startDate, $endDate, $schoolFilter);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
}
mysqli_stmt_execute($stmt);
$hourlyDist = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Convert to full 24-hour array
$hourlyData = array_fill(0, 24, 0);
foreach ($hourlyDist as $h) {
    $hourlyData[$h['hour']] = intval($h['count']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports & Analytics | Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }
        .filter-group input, .filter-group select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-btn {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .filter-btn:hover { background: var(--primary-dark); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-card .label {
            font-size: 13px;
            color: #666;
        }
        .stat-card.total .number { color: #4f46e5; }
        .stat-card.approved .number { color: #10b981; }
        .stat-card.rejected .number { color: #ef4444; }
        .stat-card.pending .number { color: #f59e0b; }
        .stat-card.matched .number { color: #06b6d4; }
        .stat-card .rate {
            font-size: 14px;
            font-weight: 600;
            margin-top: 5px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        @media (max-width: 1200px) {
            .charts-grid { grid-template-columns: 1fr; }
        }

        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .chart-card h3 {
            margin: 0 0 20px 0;
            font-size: 16px;
            font-weight: 600;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }

        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        .table-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table-card h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mini-table {
            width: 100%;
            border-collapse: collapse;
        }
        .mini-table th, .mini-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        .mini-table th {
            font-weight: 600;
            color: #666;
        }
        .mini-table tr:last-child td { border-bottom: none; }

        .progress-bar {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-bar .fill {
            height: 100%;
            border-radius: 3px;
        }
        .progress-bar .fill.approved { background: #10b981; }
        .progress-bar .fill.rejected { background: #ef4444; }

        .export-btn {
            padding: 10px 20px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .export-btn:hover { background: #047857; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>📊 Reports & Analytics</h1>
                <p class="subtitle">Teaching Activity Verification Statistics</p>
            </div>
            <a href="export_report.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&school_id=<?= $schoolFilter ?>" class="export-btn">
                📥 Export CSV
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= $startDate ?>">
            </div>
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= $endDate ?>">
            </div>
            <div class="filter-group">
                <label>School</label>
                <select name="school_id">
                    <option value="0">All Schools</option>
                    <?php foreach ($allSchools as $school): ?>
                    <option value="<?= $school['school_id'] ?>" <?= $schoolFilter == $school['school_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($school['school_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="filter-btn">Apply Filter</button>
        </form>

        <!-- Key Stats -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="number"><?= number_format($stats['total']) ?></div>
                <div class="label">Total Submissions</div>
            </div>
            <div class="stat-card approved">
                <div class="number"><?= number_format($stats['approved']) ?></div>
                <div class="label">Approved</div>
                <div class="rate"><?= $stats['approval_rate'] ?>%</div>
            </div>
            <div class="stat-card rejected">
                <div class="number"><?= number_format($stats['rejected']) ?></div>
                <div class="label">Rejected</div>
                <div class="rate"><?= $stats['rejection_rate'] ?>%</div>
            </div>
            <div class="stat-card pending">
                <div class="number"><?= number_format($stats['pending']) ?></div>
                <div class="label">Pending Review</div>
            </div>
            <div class="stat-card matched">
                <div class="number"><?= $stats['location_match_rate'] ?>%</div>
                <div class="label">Location Match Rate</div>
            </div>
            <div class="stat-card" style="background: #fef3c7;">
                <div class="number" style="color: #d97706;"><?= number_format($stats['suspicious']) ?></div>
                <div class="label">Flagged Suspicious</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>📈 Submission Trend</h3>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>🕐 Upload Time Distribution</h3>
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
        </div>

        <div class="charts-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="chart-card">
                <h3>📊 Verification Status</h3>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>📍 Location Match Status</h3>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="locationChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <div class="tables-grid">
            <!-- Top Teachers -->
            <div class="table-card">
                <h3>👨‍🏫 Top Teachers by Submissions</h3>
                <?php if (empty($topTeachers)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No data available</p>
                <?php else: ?>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Total</th>
                            <th>Approved</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topTeachers as $teacher): 
                            $rate = $teacher['total_submissions'] > 0 
                                ? round(($teacher['approved'] / $teacher['total_submissions']) * 100) 
                                : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($teacher['fname']) ?></strong>
                                <br><small style="color: #666;"><?= htmlspecialchars($teacher['email']) ?></small>
                            </td>
                            <td><?= $teacher['total_submissions'] ?></td>
                            <td style="color: #10b981;"><?= $teacher['approved'] ?></td>
                            <td>
                                <?= $rate ?>%
                                <div class="progress-bar">
                                    <div class="fill approved" style="width: <?= $rate ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- School Stats -->
            <div class="table-card">
                <h3>🏫 Submissions by School</h3>
                <?php if (empty($schoolStats)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No data available</p>
                <?php else: ?>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Total</th>
                            <th>Approved</th>
                            <th>Location ✓</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schoolStats as $school): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($school['school_name']) ?></strong></td>
                            <td><?= $school['total_submissions'] ?></td>
                            <td style="color: #10b981;"><?= $school['approved'] ?></td>
                            <td style="color: #06b6d4;"><?= $school['location_matched'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Admin Stats -->
            <div class="table-card">
                <h3>👤 Admin Verification Activity</h3>
                <?php if (empty($adminStats)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No verifications in this period</p>
                <?php else: ?>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Verified</th>
                            <th>Approved</th>
                            <th>Rejected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminStats as $admin): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($admin['fname']) ?></strong></td>
                            <td><?= $admin['verifications'] ?></td>
                            <td style="color: #10b981;"><?= $admin['approved'] ?></td>
                            <td style="color: #ef4444;"><?= $admin['rejected'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($trendData, 'date')) ?>,
                datasets: [{
                    label: 'Total',
                    data: <?= json_encode(array_map('intval', array_column($trendData, 'total'))) ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Approved',
                    data: <?= json_encode(array_map('intval', array_column($trendData, 'approved'))) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    tension: 0.4
                }, {
                    label: 'Rejected',
                    data: <?= json_encode(array_map('intval', array_column($trendData, 'rejected'))) ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Hourly Distribution Chart
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Uploads',
                    data: <?= json_encode($hourlyData) ?>,
                    backgroundColor: 'rgba(79, 70, 229, 0.7)',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Status Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Rejected', 'Pending'],
                datasets: [{
                    data: [<?= $stats['approved'] ?>, <?= $stats['rejected'] ?>, <?= $stats['pending'] ?>],
                    backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Location Pie Chart
        const locationCtx = document.getElementById('locationChart').getContext('2d');
        new Chart(locationCtx, {
            type: 'doughnut',
            data: {
                labels: ['Matched', 'Mismatched', 'Unknown'],
                datasets: [{
                    data: [
                        <?= $stats['location_matched'] ?>, 
                        <?= $stats['location_mismatched'] ?>, 
                        <?= $stats['total'] - $stats['location_matched'] - $stats['location_mismatched'] ?>
                    ],
                    backgroundColor: ['#06b6d4', '#f59e0b', '#9ca3af'],
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
