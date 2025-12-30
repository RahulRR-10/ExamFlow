<?php
/**
 * Admin - Teacher Detail
 * Phase 4: Drill-down view for individual teacher statistics
 * 
 * Shows:
 * - Teacher profile info
 * - Complete session history
 * - Session-by-session breakdown with photos
 * - Duration compliance history
 * - Schools taught at
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';
require_once '../utils/duration_validator.php';

$admin_id = $_SESSION['admin_id'];
$teacher_id = intval($_GET['id'] ?? 0);

if ($teacher_id <= 0) {
    header("Location: teacher_stats.php");
    exit;
}

// Get teacher info
$teacher_sql = "SELECT * FROM teacher WHERE id = ?";
$stmt = mysqli_prepare($conn, $teacher_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$teacher = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$teacher) {
    header("Location: teacher_stats.php?error=Teacher not found");
    exit;
}

// Get teacher statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT ts.session_id) as total_sessions,
    SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN ts.session_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN ts.session_status IN ('pending', 'start_submitted', 'start_approved', 'end_submitted') THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN ts.session_status = 'approved' THEN COALESCE(ts.actual_duration_minutes, 0) ELSE 0 END) as total_minutes,
    AVG(CASE 
        WHEN ts.session_status = 'approved' AND ts.expected_duration_minutes > 0 
        THEN (ts.actual_duration_minutes * 100.0 / ts.expected_duration_minutes)
        ELSE NULL 
    END) as avg_compliance,
    COUNT(DISTINCT ts.school_id) as schools_count,
    MIN(sts.slot_date) as first_session,
    MAX(sts.slot_date) as last_session
    FROM teaching_sessions ts
    LEFT JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
    WHERE ts.teacher_id = ?";
$stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Get schools taught at
$schools_sql = "SELECT DISTINCT s.school_id, s.school_name, s.full_address,
                COUNT(DISTINCT ts.session_id) as sessions_count,
                SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as completed_count
                FROM teaching_sessions ts
                JOIN schools s ON ts.school_id = s.school_id
                WHERE ts.teacher_id = ?
                GROUP BY s.school_id, s.school_name, s.full_address
                ORDER BY sessions_count DESC";
$stmt = mysqli_prepare($conn, $schools_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$schools = mysqli_stmt_get_result($stmt);

// Get session history
$filter_status = $_GET['status'] ?? '';
$sessions_sql = "SELECT ts.*, 
                s.school_name, s.allowed_radius,
                sts.slot_date, sts.start_time, sts.end_time,
                a.fname as verified_by_name
                FROM teaching_sessions ts
                JOIN schools s ON ts.school_id = s.school_id
                JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                LEFT JOIN admin a ON ts.verified_by = a.id
                WHERE ts.teacher_id = ?";
if ($filter_status) {
    $sessions_sql .= " AND ts.session_status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
$sessions_sql .= " ORDER BY sts.slot_date DESC, sts.start_time DESC";

$stmt = mysqli_prepare($conn, $sessions_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$sessions = mysqli_stmt_get_result($stmt);

// Calculate completion rate
$completion_rate = $stats['total_sessions'] > 0 
    ? round(($stats['completed'] / $stats['total_sessions']) * 100, 1) 
    : 0;
$total_hours = round(($stats['total_minutes'] ?? 0) / 60, 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($teacher['fname']) ?> - Teacher Detail | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css?v=3.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .back-link:hover { color: #7C0A02; }
        
        /* Teacher Profile Card */
        .profile-section {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        @media (max-width: 900px) {
            .profile-section { grid-template-columns: 1fr; }
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-align: center;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #7C0A02, #a61b0d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: white;
            font-weight: 700;
        }
        .profile-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .profile-email {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .profile-subject {
            display: inline-block;
            padding: 4px 12px;
            background: #eff6ff;
            color: #1e40af;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .profile-meta {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: left;
        }
        .profile-meta-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
        }
        .profile-meta-item .label { color: #6b7280; }
        .profile-meta-item .value { font-weight: 500; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
        }
        .stat-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .stat-box .value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-box .label {
            font-size: 12px;
            color: #6b7280;
        }
        .stat-box.total .value { color: #374151; }
        .stat-box.completed .value { color: #22c55e; }
        .stat-box.rejected .value { color: #ef4444; }
        .stat-box.pending .value { color: #f59e0b; }
        .stat-box.hours .value { color: #8b5cf6; }
        .stat-box.rate .value { color: #3b82f6; }
        
        /* Schools Section */
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .schools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .school-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .school-card h4 {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .school-card p {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .school-stats {
            display: flex;
            gap: 15px;
            font-size: 12px;
        }
        .school-stats span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .school-stats .completed { color: #22c55e; }
        .school-stats .total { color: #6b7280; }
        
        /* Session History */
        .history-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .history-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .history-header h3 {
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
        }
        .filter-tab {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            text-decoration: none;
            color: #6b7280;
            background: #f3f4f6;
        }
        .filter-tab:hover { background: #e5e7eb; }
        .filter-tab.active { background: #7C0A02; color: white; }
        
        .session-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f3f4f6;
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 15px;
            align-items: center;
        }
        .session-item:last-child { border-bottom: none; }
        .session-item:hover { background: #fafafa; }
        
        .session-photos {
            display: flex;
            gap: 5px;
        }
        .session-photos .photo-thumb {
            width: 45px;
            height: 45px;
            border-radius: 6px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #e5e7eb;
        }
        .session-photos .photo-thumb.start { border-color: #22c55e; }
        .session-photos .photo-thumb.end { border-color: #8b5cf6; }
        .session-photos .no-photo {
            width: 45px;
            height: 45px;
            border-radius: 6px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #9ca3af;
        }
        
        .session-info h4 {
            font-size: 14px;
            margin-bottom: 4px;
        }
        .session-info .meta {
            font-size: 12px;
            color: #6b7280;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .session-info .meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .session-status {
            text-align: right;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.pending { background: #f3f4f6; color: #6b7280; }
        .status-badge.start_submitted { background: #fef3c7; color: #92400e; }
        .status-badge.start_approved { background: #dbeafe; color: #1e40af; }
        .status-badge.end_submitted { background: #ede9fe; color: #6d28d9; }
        
        .duration-info {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }
        .duration-info.good { color: #22c55e; }
        .duration-info.warning { color: #f59e0b; }
        .duration-info.bad { color: #ef4444; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        .empty-state i { font-size: 48px; margin-bottom: 10px; color: #d1d5db; }
        
        .btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        .btn-primary { background: #7C0A02; color: white; }
        .btn-primary:hover { background: #5c0801; }
        .btn-sm { padding: 4px 8px; font-size: 11px; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <a href="teacher_stats.php" class="back-link">
            <i class='bx bx-arrow-back'></i> Back to Teacher Statistics
        </a>
        
        <!-- Profile Section -->
        <div class="profile-section">
            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar">
                    <?= strtoupper(substr($teacher['fname'], 0, 1)) ?>
                </div>
                <div class="profile-name"><?= htmlspecialchars($teacher['fname']) ?></div>
                <div class="profile-email"><?= htmlspecialchars($teacher['email']) ?></div>
                <?php if ($teacher['subject']): ?>
                <div class="profile-subject"><?= htmlspecialchars($teacher['subject']) ?></div>
                <?php endif; ?>
                
                <div class="profile-meta">
                    <div class="profile-meta-item">
                        <span class="label">Schools Taught</span>
                        <span class="value"><?= $stats['schools_count'] ?? 0 ?></span>
                    </div>
                    <div class="profile-meta-item">
                        <span class="label">First Session</span>
                        <span class="value"><?= $stats['first_session'] ? date('M j, Y', strtotime($stats['first_session'])) : 'N/A' ?></span>
                    </div>
                    <div class="profile-meta-item">
                        <span class="label">Last Session</span>
                        <span class="value"><?= $stats['last_session'] ? date('M j, Y', strtotime($stats['last_session'])) : 'N/A' ?></span>
                    </div>
                    <div class="profile-meta-item">
                        <span class="label">Avg Duration Compliance</span>
                        <span class="value"><?= $stats['avg_compliance'] ? number_format($stats['avg_compliance'], 1) . '%' : 'N/A' ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-box total">
                    <div class="value"><?= $stats['total_sessions'] ?? 0 ?></div>
                    <div class="label">Total Sessions</div>
                </div>
                <div class="stat-box completed">
                    <div class="value"><?= $stats['completed'] ?? 0 ?></div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-box rejected">
                    <div class="value"><?= $stats['rejected'] ?? 0 ?></div>
                    <div class="label">Rejected</div>
                </div>
                <div class="stat-box pending">
                    <div class="value"><?= $stats['pending'] ?? 0 ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-box rate">
                    <div class="value"><?= $completion_rate ?>%</div>
                    <div class="label">Completion Rate</div>
                </div>
                <div class="stat-box hours">
                    <div class="value"><?= $total_hours ?>h</div>
                    <div class="label">Teaching Hours</div>
                </div>
            </div>
        </div>
        
        <!-- Schools Taught At -->
        <?php if (mysqli_num_rows($schools) > 0): ?>
        <h3 class="section-title"><i class='bx bx-building-house'></i> Schools Taught At</h3>
        <div class="schools-grid">
            <?php while ($school = mysqli_fetch_assoc($schools)): ?>
            <div class="school-card">
                <h4><?= htmlspecialchars($school['school_name']) ?></h4>
                <p><?= htmlspecialchars($school['full_address'] ?? 'No address') ?></p>
                <div class="school-stats">
                    <span class="completed"><i class='bx bx-check'></i> <?= $school['completed_count'] ?> completed</span>
                    <span class="total"><i class='bx bx-calendar'></i> <?= $school['sessions_count'] ?> total</span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <!-- Session History -->
        <div class="history-card">
            <div class="history-header">
                <h3><i class='bx bx-history'></i> Session History</h3>
                <div class="filter-tabs">
                    <a href="?id=<?= $teacher_id ?>" class="filter-tab <?= !$filter_status ? 'active' : '' ?>">All</a>
                    <a href="?id=<?= $teacher_id ?>&status=approved" class="filter-tab <?= $filter_status === 'approved' ? 'active' : '' ?>">Approved</a>
                    <a href="?id=<?= $teacher_id ?>&status=rejected" class="filter-tab <?= $filter_status === 'rejected' ? 'active' : '' ?>">Rejected</a>
                    <a href="?id=<?= $teacher_id ?>&status=pending" class="filter-tab <?= $filter_status === 'pending' ? 'active' : '' ?>">Pending</a>
                </div>
            </div>
            
            <?php if (mysqli_num_rows($sessions) > 0): ?>
                <?php mysqli_data_seek($sessions, 0); while ($session = mysqli_fetch_assoc($sessions)): 
                    // Calculate duration info
                    $duration_class = '';
                    $duration_text = '';
                    if ($session['actual_duration_minutes'] && $session['expected_duration_minutes']) {
                        $compliance = ($session['actual_duration_minutes'] / $session['expected_duration_minutes']) * 100;
                        $duration_text = DurationValidator::formatDuration($session['actual_duration_minutes']) . 
                                        ' / ' . DurationValidator::formatDuration($session['expected_duration_minutes']) .
                                        ' (' . number_format($compliance, 0) . '%)';
                        $duration_class = $compliance >= 90 ? 'good' : ($compliance >= 70 ? 'warning' : 'bad');
                    }
                ?>
                <div class="session-item">
                    <div class="session-photos">
                        <?php if ($session['start_photo_path']): ?>
                        <img src="../<?= htmlspecialchars($session['start_photo_path']) ?>" 
                             class="photo-thumb start" 
                             onclick="window.open('../<?= htmlspecialchars($session['start_photo_path']) ?>', '_blank')"
                             title="Start Photo">
                        <?php else: ?>
                        <div class="no-photo" title="No start photo"><i class='bx bx-camera'></i></div>
                        <?php endif; ?>
                        
                        <?php if ($session['end_photo_path']): ?>
                        <img src="../<?= htmlspecialchars($session['end_photo_path']) ?>" 
                             class="photo-thumb end" 
                             onclick="window.open('../<?= htmlspecialchars($session['end_photo_path']) ?>', '_blank')"
                             title="End Photo">
                        <?php else: ?>
                        <div class="no-photo" title="No end photo"><i class='bx bx-camera'></i></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="session-info">
                        <h4><?= htmlspecialchars($session['school_name']) ?></h4>
                        <div class="meta">
                            <span><i class='bx bx-calendar'></i> <?= date('M j, Y', strtotime($session['slot_date'])) ?></span>
                            <span><i class='bx bx-time'></i> <?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></span>
                            <?php if ($session['start_distance_from_school'] !== null): ?>
                            <span><i class='bx bx-map-pin'></i> <?= number_format($session['start_distance_from_school']) ?>m</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($duration_text): ?>
                        <div class="duration-info <?= $duration_class ?>">
                            <i class='bx bx-time-five'></i> <?= $duration_text ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="session-status">
                        <span class="status-badge <?= $session['session_status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?>
                        </span>
                        <?php if ($session['verified_by_name']): ?>
                        <div style="font-size: 10px; color: #9ca3af; margin-top: 4px;">
                            by <?= htmlspecialchars($session['verified_by_name']) ?>
                        </div>
                        <?php endif; ?>
                        <a href="review_session.php?id=<?= $session['session_id'] ?>" class="btn btn-primary btn-sm" style="margin-top: 8px;">
                            View
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-calendar-x'></i>
                <p>No sessions found matching your filter.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
