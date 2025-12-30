<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];

// Filters
$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query
$where_clause = "WHERE 1=1";
$filter_label = "All Submissions";

switch ($filter) {
    case 'approved':
        $where_clause .= " AND tas.verification_status = 'approved'";
        $filter_label = "Approved";
        break;
    case 'rejected':
        $where_clause .= " AND tas.verification_status = 'rejected'";
        $filter_label = "Rejected";
        break;
    case 'pending':
        $where_clause .= " AND tas.verification_status = 'pending'";
        $filter_label = "Pending";
        break;
}

// Search
$search = trim($_GET['search'] ?? '');
if ($search) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clause .= " AND (t.fname LIKE '%$search_escaped%' OR s.school_name LIKE '%$search_escaped%')";
}

// Count
$count_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions tas
              JOIN teacher t ON tas.teacher_id = t.id
              JOIN schools s ON tas.school_id = s.school_id
              $where_clause";
$total = mysqli_fetch_assoc(mysqli_query($conn, $count_sql))['cnt'];
$total_pages = ceil($total / $per_page);

// Get submissions
$sql = "SELECT tas.*, t.fname as teacher_name, s.school_name, a.fname as admin_name
        FROM teaching_activity_submissions tas
        JOIN teacher t ON tas.teacher_id = t.id
        JOIN schools s ON tas.school_id = s.school_id
        LEFT JOIN admin a ON tas.verified_by = a.id
        $where_clause
        ORDER BY tas.upload_date DESC
        LIMIT $per_page OFFSET $offset";
$submissions = mysqli_query($conn, $sql);

// Stats
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM teaching_activity_submissions";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_sql));
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Submissions | ExamFlow Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class='bx bx-folder-open'></i> All Submissions</h1>
            <p class="subtitle">Complete history of teaching activity submissions</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom: 25px;">
            <div class="stat-card info">
                <div class="stat-icon"><i class='bx bx-bar-chart-alt-2'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Submissions</p>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon"><i class='bx bx-check-circle'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['approved'] ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon"><i class='bx bx-time-five'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['pending'] ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon"><i class='bx bx-x-circle'></i></div>
                <div class="stat-info">
                    <h3><?= $stats['rejected'] ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div class="filter-bar" style="margin: 0;">
                    <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                    <a href="?filter=approved" class="filter-btn success <?= $filter === 'approved' ? 'active' : '' ?>">Approved</a>
                    <a href="?filter=pending" class="filter-btn warning <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
                    <a href="?filter=rejected" class="filter-btn <?= $filter === 'rejected' ? 'active' : '' ?>">Rejected</a>
                </div>
                
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search teacher or school..." 
                           style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; width: 250px;">
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                </form>
            </div>
        </div>

        <!-- Results -->
        <div class="card">
            <div class="card-header">
                <h2><?= $filter_label ?></h2>
                <span class="result-count"><?= $total ?> submission(s)</span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($submissions) === 0): ?>
                    <div class="empty-state">
                        <span class="empty-icon"><i class='bx bx-inbox'></i></span>
                        <h3>No submissions found</h3>
                        <p>Try adjusting your filters or search terms.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Teacher</th>
                                <th>School</th>
                                <th>Activity Date</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($submissions)): ?>
                            <tr>
                                <td>#<?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                                <td><?= htmlspecialchars($row['school_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($row['activity_date'])) ?></td>
                                <td>
                                    <?php if ($row['location_match_status'] === 'matched'): ?>
                                        <span class="badge badge-success">✓ Matched</span>
                                    <?php elseif ($row['location_match_status'] === 'mismatched'): ?>
                                        <span class="badge badge-warning"><i class='bx bx-error'></i> Mismatch</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $row['verification_status'] ?>">
                                        <?= ucfirst($row['verification_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $row['admin_name'] ? htmlspecialchars($row['admin_name']) : '-' ?>
                                </td>
                                <td>
                                    <a href="verify_submission.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="page-btn">← Previous</a>
                        <?php endif; ?>
                        
                        <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="page-btn">Next →</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
