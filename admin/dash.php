<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_fname'];

// Get statistics
$stats = [
    'pending' => 0,
    'today' => 0,
    'mismatched' => 0,
    'my_today' => 0,
    'total_approved' => 0,
    'total_rejected' => 0
];

// Check if teaching_activity_submissions table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'teaching_activity_submissions'");
$table_exists = mysqli_num_rows($table_check) > 0;

if ($table_exists) {
    // Pending verifications
    $pending_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions WHERE verification_status = 'pending'";
    $result = mysqli_query($conn, $pending_sql);
    if ($result) $stats['pending'] = mysqli_fetch_assoc($result)['cnt'];

    // Today's submissions
    $today_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions WHERE DATE(upload_date) = CURDATE()";
    $result = mysqli_query($conn, $today_sql);
    if ($result) $stats['today'] = mysqli_fetch_assoc($result)['cnt'];

    // Location mismatches
    $mismatch_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions 
                     WHERE verification_status = 'pending' AND location_match_status = 'mismatched'";
    $result = mysqli_query($conn, $mismatch_sql);
    if ($result) $stats['mismatched'] = mysqli_fetch_assoc($result)['cnt'];

    // My verifications today
    $my_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions 
               WHERE verified_by = ? AND DATE(verified_at) = CURDATE()";
    $stmt = mysqli_prepare($conn, $my_sql);
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) $stats['my_today'] = mysqli_fetch_assoc($result)['cnt'];

    // Total approved
    $approved_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions WHERE verification_status = 'approved'";
    $result = mysqli_query($conn, $approved_sql);
    if ($result) $stats['total_approved'] = mysqli_fetch_assoc($result)['cnt'];

    // Total rejected
    $rejected_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions WHERE verification_status = 'rejected'";
    $result = mysqli_query($conn, $rejected_sql);
    if ($result) $stats['total_rejected'] = mysqli_fetch_assoc($result)['cnt'];
}

// Get recent audit log for this admin
$audit_logs = getAdminAuditLog($conn, $admin_id, 10);
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
            <h1>Welcome, <?= htmlspecialchars($admin_name) ?> 👋</h1>
            <p class="subtitle">Teaching Activity Verification Dashboard</p>
        </div>
        
        <?php if (!$table_exists): ?>
        <div class="alert alert-warning">
            <h3>⚠️ Setup Required</h3>
            <p>The teaching activity verification tables have not been created yet.</p>
            <p>Please run <code>db/migrate_teaching_verification.sql</code> to set up the required tables.</p>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-icon">📋</div>
                <div class="stat-info">
                    <h3><?= $stats['pending'] ?></h3>
                    <p>Pending Verifications</p>
                </div>
                <?php if ($table_exists): ?>
                <a href="pending_verifications.php" class="stat-link">View All →</a>
                <?php endif; ?>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <h3><?= $stats['mismatched'] ?></h3>
                    <p>Location Mismatches</p>
                </div>
                <?php if ($table_exists): ?>
                <a href="pending_verifications.php?filter=mismatched" class="stat-link">Review →</a>
                <?php endif; ?>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <h3><?= $stats['today'] ?></h3>
                    <p>Submitted Today</p>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3><?= $stats['my_today'] ?></h3>
                    <p>Verified by Me Today</p>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Summary Card -->
            <div class="card">
                <div class="card-header">
                    <h2>📊 Verification Summary</h2>
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
                    <h2>🕐 My Recent Activity</h2>
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
        <!-- Recent Pending Submissions -->
        <div class="card full-width">
            <div class="card-header">
                <h2>📷 Recent Pending Submissions</h2>
                <a href="pending_verifications.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php
                $recent_sql = "SELECT tas.*, t.fname as teacher_name, s.school_name
                               FROM teaching_activity_submissions tas
                               JOIN teacher t ON tas.teacher_id = t.id
                               JOIN schools s ON tas.school_id = s.school_id
                               WHERE tas.verification_status = 'pending'
                               ORDER BY tas.upload_date DESC
                               LIMIT 5";
                $recent = mysqli_query($conn, $recent_sql);
                
                if ($recent && mysqli_num_rows($recent) > 0):
                ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>School</th>
                            <th>Activity Date</th>
                            <th>Location</th>
                            <th>Uploaded</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recent)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                            <td><?= htmlspecialchars($row['school_name']) ?></td>
                            <td><?= date('M d, Y', strtotime($row['activity_date'])) ?></td>
                            <td>
                                <?php if ($row['location_match_status'] === 'mismatched'): ?>
                                    <span class="badge badge-warning">⚠️ Mismatch</span>
                                <?php elseif ($row['location_match_status'] === 'matched'): ?>
                                    <span class="badge badge-success">✓ Matched</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Unknown</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, H:i', strtotime($row['upload_date'])) ?></td>
                            <td>
                                <a href="verify_submission.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                    Review
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-muted text-center">No pending submissions to review.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
