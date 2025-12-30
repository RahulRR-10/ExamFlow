<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];

// Get filter
$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query based on filter
$where_clause = "WHERE tas.verification_status = 'pending'";
$filter_label = "All Pending";

switch ($filter) {
    case 'mismatched':
        $where_clause .= " AND tas.location_match_status = 'mismatched'";
        $filter_label = "Location Mismatches";
        break;
    case 'matched':
        $where_clause .= " AND tas.location_match_status = 'matched'";
        $filter_label = "Location Matched";
        break;
    case 'unknown':
        $where_clause .= " AND tas.location_match_status = 'unknown'";
        $filter_label = "Unknown Location";
        break;
    case 'today':
        $where_clause .= " AND DATE(tas.upload_date) = CURDATE()";
        $filter_label = "Submitted Today";
        break;
}

// Get total count
$count_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions tas $where_clause";
$total_count = mysqli_fetch_assoc(mysqli_query($conn, $count_sql))['cnt'];
$total_pages = ceil($total_count / $per_page);

// Get submissions
$sql = "SELECT tas.*, t.fname as teacher_name, t.email as teacher_email, s.school_name
        FROM teaching_activity_submissions tas
        JOIN teacher t ON tas.teacher_id = t.id
        JOIN schools s ON tas.school_id = s.school_id
        $where_clause
        ORDER BY 
            CASE WHEN tas.location_match_status = 'mismatched' THEN 0 ELSE 1 END,
            tas.upload_date DESC
        LIMIT $per_page OFFSET $offset";
$submissions = mysqli_query($conn, $sql);

// Get quick stats
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN location_match_status = 'mismatched' THEN 1 ELSE 0 END) as mismatched,
    SUM(CASE WHEN location_match_status = 'matched' THEN 1 ELSE 0 END) as matched,
    SUM(CASE WHEN location_match_status = 'unknown' THEN 1 ELSE 0 END) as unknown
    FROM teaching_activity_submissions WHERE verification_status = 'pending'";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_sql));

// Handle success/error messages
$success_msg = '';
$error_msg = '';
if (isset($_GET['success'])) {
    $success_msg = $_GET['success'] === 'approve' ? 'Submission approved successfully!' : 'Submission rejected successfully!';
}
if (isset($_GET['error'])) {
    $error_msg = 'An error occurred. Please try again.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pending Verifications | ExamFlow Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div class="header-left">
                <h1><i class='bx bx-list-check'></i> Pending Verifications</h1>
                <p class="subtitle">Review and verify teaching activity submissions</p>
            </div>
            <div class="header-stats">
                <span class="quick-stat total"><?= $stats['total'] ?> Total</span>
                <span class="quick-stat mismatched"><?= $stats['mismatched'] ?> Mismatched</span>
                <span class="quick-stat matched"><?= $stats['matched'] ?> Matched</span>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <span>✓</span> <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-error">
                <span>✕</span> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filter-bar">
            <span class="filter-label">Filter:</span>
            <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                All Pending (<?= $stats['total'] ?>)
            </a>
            <a href="?filter=mismatched" class="filter-btn warning <?= $filter === 'mismatched' ? 'active' : '' ?>">
                <i class='bx bx-error'></i> Mismatched (<?= $stats['mismatched'] ?>)
            </a>
            <a href="?filter=matched" class="filter-btn success <?= $filter === 'matched' ? 'active' : '' ?>">
                ✓ Matched (<?= $stats['matched'] ?>)
            </a>
            <a href="?filter=unknown" class="filter-btn info <?= $filter === 'unknown' ? 'active' : '' ?>">
                ? Unknown (<?= $stats['unknown'] ?>)
            </a>
            <a href="?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class='bx bx-calendar'></i> Today
            </a>
        </div>

        <!-- Results -->
        <div class="card">
            <div class="card-header">
                <h2><?= $filter_label ?></h2>
                <span class="result-count"><?= $total_count ?> submission(s)</span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($submissions) === 0): ?>
                    <div class="empty-state">
                        <span class="empty-icon"><i class='bx bx-party'></i></span>
                        <h3>No pending verifications</h3>
                        <p>All submissions have been reviewed. Great job!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>School</th>
                                <th>Activity Date</th>
                                <th>Location Status</th>
                                <th>Distance</th>
                                <th>Uploaded</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($submissions)): ?>
                            <tr class="row-<?= $row['location_match_status'] ?>">
                                <td>
                                    <div class="teacher-info">
                                        <strong><?= htmlspecialchars($row['teacher_name']) ?></strong>
                                        <small><?= htmlspecialchars($row['teacher_email']) ?></small>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['school_name']) ?></td>
                                <td>
                                    <strong><?= date('M d, Y', strtotime($row['activity_date'])) ?></strong>
                                </td>
                                <td>
                                    <?php if ($row['location_match_status'] === 'mismatched'): ?>
                                        <span class="badge badge-warning"><i class='bx bx-error'></i> Mismatch</span>
                                    <?php elseif ($row['location_match_status'] === 'matched'): ?>
                                        <span class="badge badge-success">✓ Matched</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">? Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['distance_from_school']): ?>
                                        <span class="distance <?= $row['location_match_status'] ?>">
                                            <?= round($row['distance_from_school']) ?>m
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= date('M d, H:i', strtotime($row['upload_date'])) ?></small>
                                </td>
                                <td>
                                    <a href="verify_submission.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">
                                        Review
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
                            <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="page-btn">← Previous</a>
                        <?php endif; ?>
                        
                        <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="page-btn">Next →</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
