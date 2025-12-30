<?php
/**
 * Admin - Pending Sessions Queue
 * Phase 6: Review and approve/reject teaching session photos
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];
$message = '';
$error = '';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $session_ids = $_POST['session_ids'] ?? [];
    
    if (!empty($session_ids) && is_array($session_ids)) {
        $session_ids = array_map('intval', $session_ids);
        $ids_str = implode(',', $session_ids);
        
        if ($action === 'bulk_approve') {
            $sql = "UPDATE teaching_sessions SET 
                    session_status = 'approved', 
                    verified_by = ?, 
                    verified_at = NOW(),
                    admin_remarks = 'Bulk approved'
                    WHERE session_id IN ($ids_str) AND session_status = 'photo_submitted'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $admin_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_affected_rows($conn);
                $message = "Successfully approved $affected session(s).";
                logAdminAction($conn, $admin_id, 'bulk_approve_sessions', "Bulk approved sessions: $ids_str");
            } else {
                $error = "Failed to approve sessions.";
            }
        } elseif ($action === 'bulk_reject') {
            $reason = trim($_POST['reject_reason'] ?? 'Rejected by admin');
            $sql = "UPDATE teaching_sessions SET 
                    session_status = 'rejected', 
                    verified_by = ?, 
                    verified_at = NOW(),
                    admin_remarks = ?
                    WHERE session_id IN ($ids_str) AND session_status = 'photo_submitted'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "is", $admin_id, $reason);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_affected_rows($conn);
                $message = "Rejected $affected session(s).";
                logAdminAction($conn, $admin_id, 'bulk_reject_sessions', "Bulk rejected sessions: $ids_str");
            } else {
                $error = "Failed to reject sessions.";
            }
        }
    }
}

// Filters
$filter_school = intval($_GET['school_id'] ?? 0);
$filter_status = $_GET['status'] ?? 'photo_submitted';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_teacher = intval($_GET['teacher_id'] ?? 0);

// Build query
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($filter_status) {
    $where_conditions[] = "ts.session_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_school > 0) {
    $where_conditions[] = "ts.school_id = ?";
    $params[] = $filter_school;
    $types .= "i";
}

if ($filter_teacher > 0) {
    $where_conditions[] = "ts.teacher_id = ?";
    $params[] = $filter_teacher;
    $types .= "i";
}

if ($filter_date_from) {
    $where_conditions[] = "sts.slot_date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if ($filter_date_to) {
    $where_conditions[] = "sts.slot_date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$where_sql = implode(' AND ', $where_conditions);

// Get sessions
$sessions_sql = "SELECT ts.*, t.fname as teacher_name, t.email as teacher_email,
                 s.school_name, s.gps_latitude as school_lat, s.gps_longitude as school_lng,
                 s.allowed_radius,
                 sts.slot_date, sts.start_time, sts.end_time,
                 a.fname as verified_by_name
                 FROM teaching_sessions ts
                 JOIN teacher t ON ts.teacher_id = t.id
                 JOIN schools s ON ts.school_id = s.school_id
                 JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                 LEFT JOIN admin a ON ts.verified_by = a.id
                 WHERE $where_sql
                 ORDER BY ts.photo_uploaded_at DESC";

$stmt = mysqli_prepare($conn, $sessions_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$sessions = mysqli_stmt_get_result($stmt);

// Get counts for tabs
$count_sql = "SELECT 
              SUM(CASE WHEN session_status = 'photo_submitted' THEN 1 ELSE 0 END) as pending,
              SUM(CASE WHEN session_status = 'approved' THEN 1 ELSE 0 END) as approved,
              SUM(CASE WHEN session_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
              SUM(CASE WHEN session_status = 'pending' THEN 1 ELSE 0 END) as awaiting_photo
              FROM teaching_sessions";
$counts = mysqli_fetch_assoc(mysqli_query($conn, $count_sql));

// Get schools for filter
$schools = mysqli_query($conn, "SELECT school_id, school_name FROM schools ORDER BY school_name");

// Get teachers for filter
$teachers = mysqli_query($conn, "SELECT id, fname, email FROM teacher ORDER BY fname");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Reviews | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .stat-card:hover {
            border-color: var(--primary-color);
        }
        .stat-card.active {
            border-color: var(--primary-color);
            background: #f0e6ff;
        }
        .stat-card h3 {
            font-size: 32px;
            margin-bottom: 5px;
        }
        .stat-card h3.warning { color: #f59e0b; }
        .stat-card h3.success { color: #10b981; }
        .stat-card h3.danger { color: #ef4444; }
        .stat-card h3.muted { color: #9ca3af; }
        .stat-card p {
            color: var(--text-muted);
            font-size: 14px;
        }
        .filters-bar {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
        }
        .session-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .session-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        .session-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .session-card.selected {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        .session-photo {
            position: relative;
            height: 200px;
            background: #f0f0f0;
            overflow: hidden;
        }
        .session-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
        }
        .session-photo .select-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .session-photo .distance-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .distance-badge.ok { background: #dcfce7; color: #166534; }
        .distance-badge.warning { background: #fef3c7; color: #92400e; }
        .distance-badge.danger { background: #fee2e2; color: #991b1b; }
        .distance-badge.unknown { background: #e5e7eb; color: #374151; }
        .session-body {
            padding: 15px;
        }
        .session-body h4 {
            font-size: 15px;
            margin-bottom: 8px;
        }
        .session-meta {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .session-meta p {
            margin-bottom: 4px;
        }
        .session-actions {
            display: flex;
            gap: 10px;
            padding: 15px;
            border-top: 1px solid var(--border-color);
        }
        .session-actions .btn {
            flex: 1;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
        }
        .status-badge.photo_submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .bulk-actions {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }
        .bulk-actions.active {
            display: flex;
        }
        .bulk-actions .selected-count {
            font-weight: 600;
        }
        .bulk-actions .bulk-btns {
            display: flex;
            gap: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 12px;
        }
        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            font-size: 14px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
        }
        .modal-content h3 { margin-bottom: 15px; }
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
            <h1>📋 Session Reviews</h1>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-row">
            <a href="?status=photo_submitted" class="stat-card <?= $filter_status === 'photo_submitted' ? 'active' : '' ?>">
                <h3 class="warning"><?= $counts['pending'] ?? 0 ?></h3>
                <p>Pending Review</p>
            </a>
            <a href="?status=approved" class="stat-card <?= $filter_status === 'approved' ? 'active' : '' ?>">
                <h3 class="success"><?= $counts['approved'] ?? 0 ?></h3>
                <p>Approved</p>
            </a>
            <a href="?status=rejected" class="stat-card <?= $filter_status === 'rejected' ? 'active' : '' ?>">
                <h3 class="danger"><?= $counts['rejected'] ?? 0 ?></h3>
                <p>Rejected</p>
            </a>
            <a href="?status=pending" class="stat-card <?= $filter_status === 'pending' ? 'active' : '' ?>">
                <h3 class="muted"><?= $counts['awaiting_photo'] ?? 0 ?></h3>
                <p>Awaiting Photo</p>
            </a>
        </div>
        
        <!-- Filters -->
        <form class="filters-bar" method="GET">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
            <div class="filter-group">
                <label>School</label>
                <select name="school_id" onchange="this.form.submit()">
                    <option value="">All Schools</option>
                    <?php while ($school = mysqli_fetch_assoc($schools)): ?>
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
                    <?php while ($teacher = mysqli_fetch_assoc($teachers)): ?>
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
                    <button type="submit" name="action" value="bulk_approve" class="btn btn-success">
                        ✓ Approve Selected
                    </button>
                    <button type="button" class="btn btn-danger" onclick="showRejectModal()">
                        ✗ Reject Selected
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
                    $distance = $session['distance_from_school'];
                    $allowed = $session['allowed_radius'] ?? 500;
                    $distance_class = 'unknown';
                    if ($distance !== null) {
                        if ($distance <= $allowed) $distance_class = 'ok';
                        elseif ($distance <= $allowed * 1.5) $distance_class = 'warning';
                        else $distance_class = 'danger';
                    }
                ?>
                <div class="session-card" data-session-id="<?= $session['session_id'] ?>">
                    <div class="session-photo">
                        <?php if ($session['photo_path']): ?>
                        <input type="checkbox" name="session_ids[]" value="<?= $session['session_id'] ?>" 
                               class="select-checkbox" onchange="updateBulkActions()">
                        <img src="../<?= htmlspecialchars($session['photo_path']) ?>" 
                             alt="Session Photo"
                             onclick="window.open('../<?= htmlspecialchars($session['photo_path']) ?>', '_blank')">
                        <span class="distance-badge <?= $distance_class ?>">
                            <?php if ($distance !== null): ?>
                            📍 <?= number_format($distance) ?>m
                            <?php else: ?>
                            📍 No GPS
                            <?php endif; ?>
                        </span>
                        <?php else: ?>
                        <div class="no-photo">📷 No photo uploaded</div>
                        <?php endif; ?>
                    </div>
                    <div class="session-body">
                        <h4><?= htmlspecialchars($session['teacher_name']) ?></h4>
                        <div class="session-meta">
                            <p>🏫 <?= htmlspecialchars($session['school_name']) ?></p>
                            <p>📅 <?= date('M j, Y', strtotime($session['slot_date'])) ?> 
                               | 🕐 <?= date('h:i A', strtotime($session['start_time'])) ?></p>
                            <?php if ($session['photo_uploaded_at']): ?>
                            <p>📤 Uploaded: <?= date('M j, h:i A', strtotime($session['photo_uploaded_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge <?= $session['session_status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?>
                        </span>
                    </div>
                    <div class="session-actions">
                        <a href="review_session.php?id=<?= $session['session_id'] ?>" class="btn btn-primary btn-sm">
                            👁️ Review
                        </a>
                        <?php if ($session['session_status'] === 'photo_submitted'): ?>
                        <button type="button" class="btn btn-success btn-sm" 
                                onclick="quickApprove(<?= $session['session_id'] ?>)">✓</button>
                        <button type="button" class="btn btn-danger btn-sm" 
                                onclick="quickReject(<?= $session['session_id'] ?>)">✗</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 64px; margin-bottom: 20px;">📭</div>
                <h3>No Sessions Found</h3>
                <p style="color: var(--text-muted);">
                    <?php if ($filter_status === 'photo_submitted'): ?>
                    No sessions pending review. Check back later!
                    <?php else: ?>
                    No sessions match your filters.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>❌ Reject Sessions</h3>
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
        <input type="hidden" name="redirect" value="pending_sessions.php">
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
        
        function quickApprove(sessionId) {
            if (confirm('Approve this session?')) {
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
