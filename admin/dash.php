<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';
require_once '../utils/teaching_slots_compat.php';

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_fname'];

// Get statistics
$stats = [
    'pending' => 0,
    'today' => 0,
    'distance_issues' => 0,
    'my_today' => 0,
    'total_approved' => 0,
    'total_rejected' => 0,
    'total_slots' => 0,
    'upcoming_slots' => 0
];

// Check if teaching_sessions table exists
$table_exists = isTeachingSlotsEnabled($conn);

if ($table_exists) {
    // Pending session reviews (photo submitted but not verified)
    $pending_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions WHERE session_status = 'photo_submitted'";
    $result = mysqli_query($conn, $pending_sql);
    if ($result) $stats['pending'] = mysqli_fetch_assoc($result)['cnt'];

    // Today's submissions (check both start and end photo timestamps)
    $today_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions WHERE DATE(start_photo_uploaded_at) = CURDATE() OR DATE(end_photo_uploaded_at) = CURDATE()";
    $result = mysqli_query($conn, $today_sql);
    if ($result) $stats['today'] = mysqli_fetch_assoc($result)['cnt'];

    // Distance issues (>500m from school) - check both start and end distances
    $distance_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions 
                     WHERE session_status IN ('start_submitted', 'end_submitted', 'photo_submitted') 
                     AND (start_distance_from_school > 500 OR end_distance_from_school > 500)";
    $result = mysqli_query($conn, $distance_sql);
    if ($result) $stats['distance_issues'] = mysqli_fetch_assoc($result)['cnt'];

    // My verifications today
    $my_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions 
               WHERE verified_by = ? AND DATE(verified_at) = CURDATE()";
    $stmt = mysqli_prepare($conn, $my_sql);
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) $stats['my_today'] = mysqli_fetch_assoc($result)['cnt'];

    // Total approved
    $approved_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions WHERE session_status = 'approved'";
    $result = mysqli_query($conn, $approved_sql);
    if ($result) $stats['total_approved'] = mysqli_fetch_assoc($result)['cnt'];

    // Total rejected
    $rejected_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions WHERE session_status = 'rejected'";
    $result = mysqli_query($conn, $rejected_sql);
    if ($result) $stats['total_rejected'] = mysqli_fetch_assoc($result)['cnt'];

    // Total teaching slots
    $slots_sql = "SELECT COUNT(*) as cnt FROM school_teaching_slots";
    $result = mysqli_query($conn, $slots_sql);
    if ($result) $stats['total_slots'] = mysqli_fetch_assoc($result)['cnt'];

    // Upcoming slots
    $upcoming_sql = "SELECT COUNT(*) as cnt FROM school_teaching_slots WHERE slot_date >= CURDATE() AND slot_status NOT IN ('completed', 'cancelled')";
    $result = mysqli_query($conn, $upcoming_sql);
    if ($result) $stats['upcoming_slots'] = mysqli_fetch_assoc($result)['cnt'];
}

// Get recent audit log for this admin
if (function_exists('getAdminAuditLog')) {
    $audit_logs = getAdminAuditLog($conn, $admin_id, 10);
} else {
    $audit_logs = [];
}

// Get top teachers this month (for widget)
$top_teachers = [];
if ($table_exists) {
    $top_sql = "SELECT t.id, t.fname, t.subject,
                COUNT(DISTINCT ts.session_id) as total_sessions,
                SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN ts.session_status = 'approved' THEN COALESCE(ts.actual_duration_minutes, 0) ELSE 0 END) / 60 as hours_taught
                FROM teaching_sessions ts
                JOIN teacher t ON ts.teacher_id = t.id
                JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                WHERE sts.slot_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY t.id, t.fname, t.subject
                HAVING completed > 0
                ORDER BY completed DESC, hours_taught DESC
                LIMIT 5";
    $result = mysqli_query($conn, $top_sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $top_teachers[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Welcome, <?= htmlspecialchars($admin_name) ?></h1>
            <p class="subtitle">Teaching Slots & Session Verification Dashboard</p>
        </div>
        
        <?php if (!$table_exists): ?>
        <div class="alert alert-warning">
            <h3>Setup Required</h3>
            <p>The teaching slots tables have not been created yet.</p>
            <p>Please run <code>db/migrate_teaching_slots.sql</code> to set up the required tables.</p>
            <p style="margin-top: 10px;">
                <a href="db_health_check.php" class="btn btn-primary btn-sm">Run Health Check</a>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-icon"><i class='bx bx-clipboard'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['pending'] ?></h3>
                    <p>Pending Reviews</p>
                </div>
                <?php if ($table_exists): ?>
                <a href="pending_sessions.php" class="stat-link">View All →</a>
                <?php endif; ?>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon"><i class='bx bx-error'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['distance_issues'] ?></h3>
                    <p>Distance Issues</p>
                </div>
                <?php if ($table_exists): ?>
                <a href="pending_sessions.php?filter=distance" class="stat-link">Review →</a>
                <?php endif; ?>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon"><i class='bx bx-calendar'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['today'] ?></h3>
                    <p>Submitted Today</p>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon"><i class='bx bx-check-circle'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['my_today'] ?></h3>
                    <p>Verified by Me Today</p>
                </div>
            </div>
        </div>
        
        <!-- Slot Stats Row -->
        <div class="stats-grid" style="margin-top: 20px;">
            <div class="stat-card">
                <div class="stat-icon"><i class='bx bx-calendar-check'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['total_slots'] ?></h3>
                    <p>Total Slots</p>
                </div>
                <a href="teaching_slots.php" class="stat-link">Manage →</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class='bx bx-time'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['upcoming_slots'] ?></h3>
                    <p>Upcoming Slots</p>
                </div>
                <a href="slot_dashboard.php" class="stat-link">View →</a>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✓</div>
                <div class="stat-info">
                    <h3><?= $stats['total_approved'] ?></h3>
                    <p>Sessions Approved</p>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">✗</div>
                <div class="stat-info">
                    <h3><?= $stats['total_rejected'] ?></h3>
                    <p>Sessions Rejected</p>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Summary Card -->
            <div class="card">
                <div class="card-header">
                    <h2>Verification Summary</h2>
                </div>
                <div class="card-body">
                    <div class="summary-stats">
                        <div class="summary-item">
                            <span class="summary-label">Total Approved</span>
                            <span class="summary-value text-success"><?= $stats['total_approved'] ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Total Rejected</span>
                            <span class="summary-value text-danger"><?= $stats['total_rejected'] ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Pending Review</span>
                            <span class="summary-value text-warning"><?= $stats['pending'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h2>My Recent Activity</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($audit_logs)): ?>
                        <p class="text-muted">No recent activity.</p>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($audit_logs as $log): ?>
                            <li class="activity-item">
                                <span class="activity-action"><?= htmlspecialchars($log['action_type']) ?></span>
                                <span class="activity-time"><?= date('M d, H:i', strtotime($log['created_at'])) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Teachers This Month -->
        <?php if (!empty($top_teachers)): ?>
        <div class="card full-width" style="margin-top: 20px;">
            <div class="card-header">
                <h2><i class='bx bx-trophy' style="color: #f59e0b;"></i> Top Teachers (Last 30 Days)</h2>
                <a href="teacher_stats.php" class="btn btn-primary btn-sm">View All Stats</a>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Completed Sessions</th>
                            <th>Hours Taught</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_teachers as $idx => $teacher): ?>
                        <tr>
                            <td>
                                <?php if ($idx === 0): ?>
                                    <span style="font-size: 20px;">🥇</span>
                                <?php elseif ($idx === 1): ?>
                                    <span style="font-size: 20px;">🥈</span>
                                <?php elseif ($idx === 2): ?>
                                    <span style="font-size: 20px;">🥉</span>
                                <?php else: ?>
                                    <?= $idx + 1 ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($teacher['fname']) ?></strong></td>
                            <td><?= htmlspecialchars($teacher['subject'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge badge-success"><?= $teacher['completed'] ?></span>
                            </td>
                            <td><?= number_format($teacher['hours_taught'], 1) ?>h</td>
                            <td>
                                <a href="teacher_detail.php?id=<?= $teacher['id'] ?>" class="btn btn-sm btn-outline">
                                    View Detail
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($table_exists): ?>
        <!-- Recent Pending Sessions -->
        <div class="card full-width">
            <div class="card-header">
                <h2>Recent Pending Sessions</h2>
                <a href="pending_sessions.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php
                $recent_sql = "SELECT ts.*, t.fname as teacher_name, s.school_name, sts.slot_date, sts.start_time, sts.end_time,
                               COALESCE(ts.end_photo_uploaded_at, ts.start_photo_uploaded_at) as latest_upload
                               FROM teaching_sessions ts
                               JOIN teacher t ON ts.teacher_id = t.id
                               JOIN schools s ON ts.school_id = s.school_id
                               JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                               WHERE ts.session_status IN ('start_submitted', 'end_submitted', 'photo_submitted')
                               ORDER BY latest_upload DESC
                               LIMIT 5";
                $recent = mysqli_query($conn, $recent_sql);
                
                if ($recent && mysqli_num_rows($recent) > 0):
                ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>School</th>
                            <th>Session Date</th>
                            <th>Distance</th>
                            <th>Uploaded</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recent)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                            <td><?= htmlspecialchars($row['school_name']) ?></td>
                            <td><?= date('M d, Y', strtotime($row['slot_date'])) ?></td>
                            <td>
                                <?php 
                                $distance = $row['start_distance_from_school'] ?? $row['end_distance_from_school'] ?? null;
                                if ($distance !== null): ?>
                                    <?php if ($distance > 500): ?>
                                        <span class="badge badge-warning"><i class='bx bx-error'></i> <?= number_format($distance) ?>m</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">✓ <?= number_format($distance) ?>m</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-info">No GPS</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['latest_upload'] ? date('M d, H:i', strtotime($row['latest_upload'])) : 'N/A' ?></td>
                            <td>
                                <a href="review_session.php?id=<?= $row['session_id'] ?>" class="btn btn-sm btn-primary">
                                    Review
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-muted text-center">No pending sessions to review. <i class='bx bx-party'></i></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Auto-Rejected Sessions -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Auto-Rejected Sessions</h2>
            </div>
            <div class="card-body">
                <?php
                $rejected_sql = "SELECT ts.*, t.fname as teacher_name, s.school_name, sts.slot_date, sts.start_time, sts.end_time,
                               COALESCE(ts.end_photo_uploaded_at, ts.start_photo_uploaded_at) as latest_upload
                               FROM teaching_sessions ts
                               JOIN teacher t ON ts.teacher_id = t.id
                               JOIN schools s ON ts.school_id = s.school_id
                               JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                               WHERE ts.session_status = 'rejected' AND ts.admin_remarks LIKE 'Auto-rejected:%'
                               ORDER BY ts.verified_at DESC
                               LIMIT 5";
                $rejected = mysqli_query($conn, $rejected_sql);
                
                if ($rejected && mysqli_num_rows($rejected) > 0):
                ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>School</th>
                            <th>Session Date</th>
                            <th>Reason</th>
                            <th>Rejected At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($rejected)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                            <td><?= htmlspecialchars($row['school_name']) ?></td>
                            <td><?= date('M d, Y', strtotime($row['slot_date'])) ?></td>
                            <td>
                                <span class="badge badge-danger" title="<?= htmlspecialchars($row['admin_remarks']) ?>">
                                    <?= htmlspecialchars(substr($row['admin_remarks'], 0, 50)) ?><?= strlen($row['admin_remarks']) > 50 ? '...' : '' ?>
                                </span>
                            </td>
                            <td><?= $row['verified_at'] ? date('M d, H:i', strtotime($row['verified_at'])) : 'N/A' ?></td>
                            <td>
                                <a href="review_session.php?id=<?= $row['session_id'] ?>" class="btn btn-sm btn-secondary">
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-muted text-center">No auto-rejected sessions. <i class='bx bx-check-circle'></i></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
