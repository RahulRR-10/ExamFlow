<?php
/**
 * Admin - Pending Sessions (Dual Photo Verification)
 * Phase 3: Updated for dual photo workflow with new status tabs
 * 
 * Shows sessions awaiting review with:
 * - Start photos pending review
 * - End photos pending review (final verification)
 * - Duration compliance indicators
 * - Bulk approval functionality
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';
require_once '../utils/duration_validator.php';

$admin_id = $_SESSION['admin_id'];
$message = $_GET['message'] ?? '';
$error = '';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $session_ids = $_POST['session_ids'] ?? [];
    
    if (!empty($session_ids) && is_array($session_ids)) {
        $ids = array_map('intval', $session_ids);
        $ids_str = implode(',', $ids);
        
        if ($action === 'bulk_approve_start') {
            // Bulk approve start photos
            $sql = "UPDATE teaching_sessions SET 
                    session_status = 'start_approved',
                    verified_by = ?,
                    verified_at = NOW()
                    WHERE session_id IN ($ids_str) AND session_status = 'start_submitted'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $admin_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_affected_rows($conn);
                $message = "$affected session(s) start photos approved.";
                logAdminAction($conn, $admin_id, 'bulk_approve_start', "Bulk approved start photos for $affected sessions", null, 'teaching_sessions', null);
            }
        } elseif ($action === 'bulk_approve') {
            // Full bulk approval
            $sql = "UPDATE teaching_sessions SET 
                    session_status = 'approved',
                    verified_by = ?,
                    verified_at = NOW()
                    WHERE session_id IN ($ids_str) AND session_status = 'end_submitted'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $admin_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_affected_rows($conn);
                $message = "$affected session(s) fully approved.";
                logAdminAction($conn, $admin_id, 'bulk_approve', "Bulk approved $affected sessions", null, 'teaching_sessions', null);
                
                // Update enrollment statuses
                $update_enrollment = "UPDATE slot_teacher_enrollments ste
                                     JOIN teaching_sessions ts ON ste.enrollment_id = ts.enrollment_id
                                     SET ste.enrollment_status = 'completed'
                                     WHERE ts.session_id IN ($ids_str) AND ts.session_status = 'approved'";
                mysqli_query($conn, $update_enrollment);
            }
        } elseif ($action === 'bulk_reject') {
            $reason = trim($_POST['reject_reason'] ?? 'Rejected by admin');
            $sql = "UPDATE teaching_sessions SET 
                    session_status = 'rejected',
                    verified_by = ?,
                    verified_at = NOW(),
                    admin_remarks = ?
                    WHERE session_id IN ($ids_str)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "is", $admin_id, $reason);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_affected_rows($conn);
                $message = "$affected session(s) rejected.";
                logAdminAction($conn, $admin_id, 'bulk_reject', "Bulk rejected $affected sessions: $reason", null, 'teaching_sessions', null);
            }
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'start_submitted';
$filter_school = intval($_GET['school_id'] ?? 0);
$filter_teacher = intval($_GET['teacher_id'] ?? 0);
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build main query for sessions with dual photo fields
$sql = "SELECT ts.*, 
        t.fname as teacher_name, t.email as teacher_email,
        s.school_name, s.allowed_radius,
        sts.slot_date, sts.start_time, sts.end_time
        FROM teaching_sessions ts
        JOIN teacher t ON ts.teacher_id = t.id
        JOIN schools s ON ts.school_id = s.school_id
        JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
        WHERE 1=1";

$params = [];
$types = "";

// Apply status filter
if ($filter_status === 'all_pending') {
    $sql .= " AND ts.session_status IN ('start_submitted', 'end_submitted')";
} elseif ($filter_status) {
    $sql .= " AND ts.session_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

// Apply school filter
if ($filter_school) {
    $sql .= " AND ts.school_id = ?";
    $params[] = $filter_school;
    $types .= "i";
}

// Apply teacher filter
if ($filter_teacher) {
    $sql .= " AND ts.teacher_id = ?";
    $params[] = $filter_teacher;
    $types .= "i";
}

// Apply date filters
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

$sql .= " ORDER BY ts.start_photo_uploaded_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$sessions = mysqli_stmt_get_result($stmt);

// Get counts for each status tab
$counts_sql = "SELECT 
    SUM(CASE WHEN session_status = 'start_submitted' THEN 1 ELSE 0 END) as start_pending,
    SUM(CASE WHEN session_status = 'end_submitted' THEN 1 ELSE 0 END) as end_pending,
    SUM(CASE WHEN session_status = 'start_approved' THEN 1 ELSE 0 END) as awaiting_end,
    SUM(CASE WHEN session_status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN session_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN session_status = 'pending' THEN 1 ELSE 0 END) as awaiting_start
    FROM teaching_sessions";
$counts_result = mysqli_query($conn, $counts_sql);
$counts = mysqli_fetch_assoc($counts_result);

// Get schools for filter dropdown
$schools = mysqli_query($conn, "SELECT school_id, school_name FROM schools ORDER BY school_name");

// Get teachers for filter dropdown
$teachers = mysqli_query($conn, "SELECT id, fname FROM teacher ORDER BY fname");

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    return match($status) {
        'approved' => 'success',
        'rejected' => 'danger',
        'start_submitted' => 'warning',
        'end_submitted' => 'purple',
        'start_approved' => 'info',
        'pending' => 'muted',
        'partial' => 'orange',
        default => 'muted'
    };
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Reviews | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css?v=3.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .page-header {
            margin-bottom: 20px;
        }
        .page-header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
        }
        
        /* Status Tabs */
        .status-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .status-tab {
            padding: 12px 20px;
            background: white;
            border-radius: 10px;
            text-decoration: none;
            color: #374151;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 120px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .status-tab:hover { border-color: #e5e7eb; transform: translateY(-2px); }
        .status-tab.active { border-color: #7C0A02; background: #fef7f6; }
        .status-tab .count {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .status-tab .label { font-size: 12px; color: #6b7280; }
        .status-tab.start_submitted .count { color: #f59e0b; }
        .status-tab.end_submitted .count { color: #8b5cf6; }
        .status-tab.awaiting_end .count { color: #3b82f6; }
        .status-tab.approved .count { color: #22c55e; }
        .status-tab.rejected .count { color: #ef4444; }
        .status-tab.pending .count { color: #9ca3af; }
        
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
        
        /* Bulk Actions Bar */
        .bulk-actions {
            background: #1f2937;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }
        .bulk-actions.active { display: flex; }
        .bulk-btns {
            display: flex;
            gap: 10px;
        }
        
        /* Session Grid */
        .session-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 15px;
        }
        
        .session-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .session-card:hover { border-color: #e5e7eb; }
        .session-card.selected { border-color: #7C0A02; background: #fef7f6; }
        
        .session-photos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            height: 120px;
            position: relative;
        }
        .session-photos .photo-half {
            position: relative;
            overflow: hidden;
        }
        .session-photos .photo-half img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .session-photos .photo-half .no-photo {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #9ca3af;
        }
        .session-photos .photo-half .no-photo i { font-size: 24px; margin-bottom: 4px; }
        .session-photos .photo-half.start { background: #f0fdf4; border-right: 1px solid white; }
        .session-photos .photo-half.end { background: #faf5ff; }
        .session-photos .photo-half.start .no-photo { color: #166534; }
        .session-photos .photo-half.end .no-photo { color: #7c3aed; }
        
        .photo-label {
            position: absolute;
            bottom: 4px;
            left: 4px;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            color: white;
        }
        .photo-half.start .photo-label { background: #22c55e; }
        .photo-half.end .photo-label { background: #8b5cf6; }
        
        .distance-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 2px;
        }
        .distance-badge.ok { background: #dcfce7; color: #166534; }
        .distance-badge.warn { background: #fef3c7; color: #92400e; }
        .distance-badge.bad { background: #fee2e2; color: #991b1b; }
        .distance-badge.unknown { background: #f3f4f6; color: #6b7280; }
        
        .select-checkbox {
            position: absolute;
            top: 8px;
            left: 8px;
            width: 20px;
            height: 20px;
            z-index: 10;
            cursor: pointer;
        }
        
        .session-body {
            padding: 12px 15px;
        }
        .session-body h4 {
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .session-meta {
            font-size: 12px;
            color: #6b7280;
        }
        .session-meta p {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 4px;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .status-badge.pending { background: #f3f4f6; color: #6b7280; }
        .status-badge.start_submitted { background: #fef3c7; color: #92400e; }
        .status-badge.end_submitted { background: #ede9fe; color: #6d28d9; }
        .status-badge.start_approved { background: #dbeafe; color: #1e40af; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        
        .duration-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
            padding: 6px 10px;
            background: #f9fafb;
            border-radius: 6px;
            font-size: 11px;
        }
        .duration-indicator.excellent { background: #dcfce7; color: #166534; }
        .duration-indicator.good { background: #dbeafe; color: #1e40af; }
        .duration-indicator.warning { background: #fef3c7; color: #92400e; }
        .duration-indicator.short { background: #fee2e2; color: #991b1b; }
        .duration-indicator.pending { background: #f3f4f6; color: #6b7280; }
        
        .session-actions {
            padding: 10px 15px;
            border-top: 1px solid #f3f4f6;
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-sm { padding: 6px 10px; font-size: 11px; }
        .btn-primary { background: #7C0A02; color: white; }
        .btn-primary:hover { background: #5c0801; }
        .btn-success { background: #22c55e; color: white; }
        .btn-success:hover { background: #16a34a; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 15px;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        .empty-state i { font-size: 64px; color: #d1d5db; margin-bottom: 15px; }
        .empty-state h3 { color: #374151; margin-bottom: 8px; }
        .empty-state p { color: #6b7280; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
        }
        .modal-content h3 { margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .modal-content textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            min-height: 80px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class='bx bx-list-check'></i> Session Reviews</h1>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class='bx bx-error'></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Status Tabs -->
        <div class="status-tabs">
            <a href="?status=start_submitted" class="status-tab start_submitted <?= $filter_status === 'start_submitted' ? 'active' : '' ?>">
                <span class="count"><?= $counts['start_pending'] ?? 0 ?></span>
                <span class="label">Start Photos Pending</span>
            </a>
            <a href="?status=end_submitted" class="status-tab end_submitted <?= $filter_status === 'end_submitted' ? 'active' : '' ?>">
                <span class="count"><?= $counts['end_pending'] ?? 0 ?></span>
                <span class="label">End Photos Pending</span>
            </a>
            <a href="?status=start_approved" class="status-tab awaiting_end <?= $filter_status === 'start_approved' ? 'active' : '' ?>">
                <span class="count"><?= $counts['awaiting_end'] ?? 0 ?></span>
                <span class="label">Awaiting End Photo</span>
            </a>
            <a href="?status=approved" class="status-tab approved <?= $filter_status === 'approved' ? 'active' : '' ?>">
                <span class="count"><?= $counts['approved'] ?? 0 ?></span>
                <span class="label">Approved</span>
            </a>
            <a href="?status=rejected" class="status-tab rejected <?= $filter_status === 'rejected' ? 'active' : '' ?>">
                <span class="count"><?= $counts['rejected'] ?? 0 ?></span>
                <span class="label">Rejected</span>
            </a>
            <a href="?status=pending" class="status-tab pending <?= $filter_status === 'pending' ? 'active' : '' ?>">
                <span class="count"><?= $counts['awaiting_start'] ?? 0 ?></span>
                <span class="label">Awaiting Start</span>
            </a>
        </div>
        
        <!-- Filters -->
        <form class="filters-bar" method="GET">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
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
                <label>Teacher</label>
                <select name="teacher_id" onchange="this.form.submit()">
                    <option value="">All Teachers</option>
                    <?php mysqli_data_seek($teachers, 0); while ($teacher = mysqli_fetch_assoc($teachers)): ?>
                    <option value="<?= $teacher['id'] ?>" <?= $filter_teacher == $teacher['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($teacher['fname']) ?>
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
            <a href="pending_sessions.php" class="btn btn-secondary">Clear</a>
        </form>
        
        <!-- Bulk Actions Bar -->
        <form id="bulkForm" method="POST">
            <div id="bulkActions" class="bulk-actions">
                <span><span id="selectedCount">0</span> session(s) selected</span>
                <div class="bulk-btns">
                    <?php if ($filter_status === 'start_submitted'): ?>
                    <button type="submit" name="action" value="bulk_approve_start" class="btn btn-success">
                        <i class='bx bx-check'></i> Approve Start Photos
                    </button>
                    <?php elseif ($filter_status === 'end_submitted'): ?>
                    <button type="submit" name="action" value="bulk_approve" class="btn btn-success">
                        <i class='bx bx-check-double'></i> Approve Sessions
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-danger" onclick="showRejectModal()">
                        <i class='bx bx-x'></i> Reject Selected
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                        Cancel
                    </button>
                </div>
            </div>
        
            <!-- Sessions Grid -->
            <?php if (mysqli_num_rows($sessions) > 0): ?>
            <div class="session-grid">
                <?php while ($session = mysqli_fetch_assoc($sessions)): 
                    // Calculate distances
                    $start_distance = $session['start_distance_from_school'];
                    $end_distance = $session['end_distance_from_school'];
                    $allowed = $session['allowed_radius'] ?? 500;
                    
                    $start_dist_class = 'unknown';
                    if ($start_distance !== null) {
                        if ($start_distance <= $allowed) $start_dist_class = 'ok';
                        elseif ($start_distance <= $allowed * 1.5) $start_dist_class = 'warn';
                        else $start_dist_class = 'bad';
                    }
                    
                    $end_dist_class = 'unknown';
                    if ($end_distance !== null) {
                        if ($end_distance <= $allowed) $end_dist_class = 'ok';
                        elseif ($end_distance <= $allowed * 1.5) $end_dist_class = 'warn';
                        else $end_dist_class = 'bad';
                    }
                    
                    // Calculate duration info
                    $duration_status = 'pending';
                    $duration_text = 'Awaiting photos';
                    if ($session['start_photo_taken_at'] && $session['end_photo_taken_at']) {
                        $expected_mins = DurationValidator::calculateExpectedDuration($session['start_time'], $session['end_time']);
                        $actual_mins = $session['actual_duration_minutes'] ?? DurationValidator::calculateDuration(
                            $session['start_photo_taken_at'], 
                            $session['end_photo_taken_at']
                        );
                        $duration_status = DurationValidator::getDurationStatus($actual_mins, $expected_mins);
                        $duration_text = DurationValidator::formatDuration($actual_mins) . ' / ' . DurationValidator::formatDuration($expected_mins);
                    } elseif ($session['start_photo_taken_at']) {
                        $duration_text = 'Awaiting end photo';
                    }
                    
                    $can_select = in_array($session['session_status'], ['start_submitted', 'end_submitted']);
                ?>
                <div class="session-card" data-session-id="<?= $session['session_id'] ?>">
                    <div class="session-photos">
                        <?php if ($can_select): ?>
                        <input type="checkbox" name="session_ids[]" value="<?= $session['session_id'] ?>" 
                               class="select-checkbox" onchange="updateBulkActions()">
                        <?php endif; ?>
                        
                        <!-- Start Photo -->
                        <div class="photo-half start">
                            <?php if ($session['start_photo_path']): ?>
                            <img src="../<?= htmlspecialchars($session['start_photo_path']) ?>" 
                                 alt="Start Photo"
                                 onclick="window.open('../<?= htmlspecialchars($session['start_photo_path']) ?>', '_blank')">
                            <span class="distance-badge <?= $start_dist_class ?>">
                                <i class='bx bx-map-pin'></i>
                                <?= $start_distance !== null ? number_format($start_distance) . 'm' : 'No GPS' ?>
                            </span>
                            <?php else: ?>
                            <div class="no-photo">
                                <i class='bx bx-camera'></i>
                                <span>No start</span>
                            </div>
                            <?php endif; ?>
                            <span class="photo-label">START</span>
                        </div>
                        
                        <!-- End Photo -->
                        <div class="photo-half end">
                            <?php if ($session['end_photo_path']): ?>
                            <img src="../<?= htmlspecialchars($session['end_photo_path']) ?>" 
                                 alt="End Photo"
                                 onclick="window.open('../<?= htmlspecialchars($session['end_photo_path']) ?>', '_blank')">
                            <span class="distance-badge <?= $end_dist_class ?>">
                                <i class='bx bx-map-pin'></i>
                                <?= $end_distance !== null ? number_format($end_distance) . 'm' : 'No GPS' ?>
                            </span>
                            <?php else: ?>
                            <div class="no-photo">
                                <i class='bx bx-camera'></i>
                                <span>No end</span>
                            </div>
                            <?php endif; ?>
                            <span class="photo-label">END</span>
                        </div>
                    </div>
                    
                    <div class="session-body">
                        <h4>
                            <?= htmlspecialchars($session['teacher_name']) ?>
                            <span class="status-badge <?= $session['session_status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?>
                            </span>
                        </h4>
                        <div class="session-meta">
                            <p><i class='bx bx-building'></i> <?= htmlspecialchars($session['school_name']) ?></p>
                            <p><i class='bx bx-calendar'></i> <?= date('M j, Y', strtotime($session['slot_date'])) ?> 
                               | <i class='bx bx-time'></i> <?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></p>
                        </div>
                        
                        <div class="duration-indicator <?= $duration_status ?>">
                            <i class='bx bx-time-five'></i>
                            <span><?= $duration_text ?></span>
                        </div>
                    </div>
                    
                    <div class="session-actions">
                        <a href="review_session.php?id=<?= $session['session_id'] ?>" class="btn btn-primary btn-sm">
                            <i class='bx bx-show'></i> Review
                        </a>
                        <?php if ($session['session_status'] === 'start_submitted'): ?>
                        <button type="button" class="btn btn-success btn-sm" onclick="quickApproveStart(<?= $session['session_id'] ?>)">
                            <i class='bx bx-check'></i> Approve Start
                        </button>
                        <?php elseif ($session['session_status'] === 'end_submitted'): ?>
                        <button type="button" class="btn btn-success btn-sm" onclick="quickApprove(<?= $session['session_id'] ?>)">
                            <i class='bx bx-check-double'></i> Approve
                        </button>
                        <?php endif; ?>
                        <?php if (in_array($session['session_status'], ['start_submitted', 'end_submitted'])): ?>
                        <button type="button" class="btn btn-danger btn-sm" onclick="quickReject(<?= $session['session_id'] ?>)">
                            <i class='bx bx-x'></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-inbox'></i>
                <h3>No Sessions Found</h3>
                <p>
                    <?php
                    $empty_messages = [
                        'start_submitted' => 'No start photos pending review. Check back later!',
                        'end_submitted' => 'No end photos pending final review.',
                        'start_approved' => 'No sessions awaiting end photos.',
                        'approved' => 'No approved sessions match your filters.',
                        'rejected' => 'No rejected sessions.',
                        'pending' => 'No sessions awaiting start photo upload.'
                    ];
                    echo $empty_messages[$filter_status] ?? 'No sessions match your filters.';
                    ?>
                </p>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3><i class='bx bx-x-circle'></i> Reject Sessions</h3>
            <p style="color: #666; margin-bottom: 15px;">Enter a reason for rejection:</p>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="bulk_reject">
                <div id="rejectSessionIds"></div>
                <textarea name="reject_reason" placeholder="Enter rejection reason..." required></textarea>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Quick Action Form -->
    <form id="quickActionForm" method="POST" action="review_session.php" style="display:none;">
        <input type="hidden" name="session_id" id="quickSessionId">
        <input type="hidden" name="action" id="quickAction">
        <input type="hidden" name="remarks" id="quickRemarks">
        <input type="hidden" name="redirect" value="pending_sessions.php?status=<?= htmlspecialchars($filter_status) ?>">
    </form>
    
    <script>
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.select-checkbox:checked');
            const bulkBar = document.getElementById('bulkActions');
            const countSpan = document.getElementById('selectedCount');
            
            countSpan.textContent = checkboxes.length;
            
            if (checkboxes.length > 0) {
                bulkBar.classList.add('active');
            } else {
                bulkBar.classList.remove('active');
            }
            
            // Update card selection state
            document.querySelectorAll('.session-card').forEach(card => {
                const checkbox = card.querySelector('.select-checkbox');
                if (checkbox && checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }
        
        function clearSelection() {
            document.querySelectorAll('.select-checkbox').forEach(cb => cb.checked = false);
            updateBulkActions();
        }
        
        function showRejectModal() {
            const checkboxes = document.querySelectorAll('.select-checkbox:checked');
            const idsContainer = document.getElementById('rejectSessionIds');
            idsContainer.innerHTML = '';
            
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'session_ids[]';
                input.value = cb.value;
                idsContainer.appendChild(input);
            });
            
            document.getElementById('rejectModal').classList.add('active');
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
        }
        
        function quickApproveStart(sessionId) {
            if (confirm('Approve start photo for this session?')) {
                document.getElementById('quickSessionId').value = sessionId;
                document.getElementById('quickAction').value = 'approve_start';
                document.getElementById('quickRemarks').value = '';
                document.getElementById('quickActionForm').submit();
            }
        }
        
        function quickApprove(sessionId) {
            if (confirm('Fully approve this session?')) {
                document.getElementById('quickSessionId').value = sessionId;
                document.getElementById('quickAction').value = 'approve';
                document.getElementById('quickRemarks').value = '';
                document.getElementById('quickActionForm').submit();
            }
        }
        
        function quickReject(sessionId) {
            const reason = prompt('Enter rejection reason:');
            if (reason !== null && reason.trim() !== '') {
                document.getElementById('quickSessionId').value = sessionId;
                document.getElementById('quickAction').value = 'reject';
                document.getElementById('quickRemarks').value = reason;
                document.getElementById('quickActionForm').submit();
            }
        }
        
        // Close modal on outside click
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
    </script>
</body>
</html>
