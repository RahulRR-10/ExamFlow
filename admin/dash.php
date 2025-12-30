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

    // Today's submissions
    $today_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions WHERE DATE(photo_uploaded_at) = CURDATE()";
    $result = mysqli_query($conn, $today_sql);
    if ($result) $stats['today'] = mysqli_fetch_assoc($result)['cnt'];

    // Distance issues (>500m from school)
    $distance_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions 
                     WHERE session_status = 'photo_submitted' AND distance_from_school > 500";
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css">
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
        
        <?php if ($table_exists): ?>
        <!-- Recent Pending Sessions -->
        <div class="card full-width">
            <div class="card-header">
                <h2>Recent Pending Sessions</h2>
                <a href="pending_sessions.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php
                $recent_sql = "SELECT ts.*, t.fname as teacher_name, s.school_name, sts.slot_date, sts.start_time, sts.end_time
                               FROM teaching_sessions ts
                               JOIN teachers t ON ts.teacher_id = t.id
                               JOIN schools s ON ts.school_id = s.school_id
                               JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                               WHERE ts.session_status = 'photo_submitted'
                               ORDER BY ts.photo_uploaded_at DESC
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
                                <?php if ($row['distance_from_school'] !== null): ?>
                                    <?php if ($row['distance_from_school'] > 500): ?>
                                        <span class="badge badge-warning"><i class='bx bx-error'></i> <?= number_format($row['distance_from_school']) ?>m</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">✓ <?= number_format($row['distance_from_school']) ?>m</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-info">No GPS</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, H:i', strtotime($row['photo_uploaded_at'])) ?></td>
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
        <?php endif; ?>
    </div>
</body>
</html>
