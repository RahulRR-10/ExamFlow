<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

// Filters
$admin_filter = intval($_GET['admin'] ?? 0);
$action_filter = $_GET['action'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build query
$where_clause = "WHERE 1=1";

if ($admin_filter) {
    $where_clause .= " AND al.admin_id = $admin_filter";
}

if ($action_filter) {
    $action_escaped = mysqli_real_escape_string($conn, $action_filter);
    $where_clause .= " AND al.action_type = '$action_escaped'";
}

// Count
$count_sql = "SELECT COUNT(*) as cnt FROM admin_audit_log al $where_clause";
$total = mysqli_fetch_assoc(mysqli_query($conn, $count_sql))['cnt'];
$total_pages = ceil($total / $per_page);

// Get logs
$sql = "SELECT al.*, a.fname as admin_name, a.uname as admin_uname
        FROM admin_audit_log al
        JOIN admin a ON al.admin_id = a.id
        $where_clause
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset";
$logs = mysqli_query($conn, $sql);

// Get all admins for filter dropdown
$admins = mysqli_query($conn, "SELECT id, fname FROM admin ORDER BY fname");

// Get action types for filter
$actions_sql = "SELECT DISTINCT action_type FROM admin_audit_log ORDER BY action_type";
$action_types = mysqli_query($conn, $actions_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Audit Log | ExamFlow Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>📜 Audit Log</h1>
            <p class="subtitle">Track all admin activities and actions</p>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-body">
                <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <div>
                        <label style="display: block; font-size: 12px; color: #666; margin-bottom: 5px;">Admin</label>
                        <select name="admin" style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; min-width: 150px;">
                            <option value="">All Admins</option>
                            <?php while ($admin = mysqli_fetch_assoc($admins)): ?>
                                <option value="<?= $admin['id'] ?>" <?= $admin_filter == $admin['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($admin['fname']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-size: 12px; color: #666; margin-bottom: 5px;">Action Type</label>
                        <select name="action" style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; min-width: 150px;">
                            <option value="">All Actions</option>
                            <?php while ($at = mysqli_fetch_assoc($action_types)): ?>
                                <option value="<?= htmlspecialchars($at['action_type']) ?>" <?= $action_filter === $at['action_type'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($at['action_type']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="audit_log.php" class="btn btn-sm" style="margin-left: 10px; background: #f5f5f5; color: #666;">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs -->
        <div class="card">
            <div class="card-header">
                <h2>Activity Log</h2>
                <span class="result-count"><?= $total ?> entries</span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($logs) === 0): ?>
                    <div class="empty-state">
                        <span class="empty-icon">📋</span>
                        <h3>No log entries found</h3>
                        <p>Admin activities will appear here.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                            <tr>
                                <td>
                                    <small><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($log['admin_name']) ?></strong>
                                    <br><small style="color: #999;">@<?= htmlspecialchars($log['admin_uname']) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $action_class = 'info';
                                    if (strpos($log['action_type'], 'approve') !== false) $action_class = 'success';
                                    if (strpos($log['action_type'], 'reject') !== false) $action_class = 'warning';
                                    if ($log['action_type'] === 'login') $action_class = 'info';
                                    if ($log['action_type'] === 'logout') $action_class = 'pending';
                                    ?>
                                    <span class="badge badge-<?= $action_class ?>">
                                        <?= htmlspecialchars($log['action_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['target_table'] && $log['target_id']): ?>
                                        <?= htmlspecialchars($log['target_table']) ?> #<?= $log['target_id'] ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['action_details']): ?>
                                        <span title="<?= htmlspecialchars($log['action_details']) ?>">
                                            <?= htmlspecialchars(substr($log['action_details'], 0, 50)) ?>
                                            <?= strlen($log['action_details']) > 50 ? '...' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="color: #999;"><?= htmlspecialchars($log['ip_address']) ?></small>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?admin=<?= $admin_filter ?>&action=<?= urlencode($action_filter) ?>&page=<?= $page - 1 ?>" class="page-btn">← Previous</a>
                        <?php endif; ?>
                        
                        <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?admin=<?= $admin_filter ?>&action=<?= urlencode($action_filter) ?>&page=<?= $page + 1 ?>" class="page-btn">Next →</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
