<?php
/**
 * Admin Navigation Component
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="admin-nav">
    <div class="nav-brand">
        <span class="brand-icon">🔐</span>
        <span class="brand-text">ExamFlow <span class="brand-badge">Admin</span></span>
    </div>
    
    <ul class="nav-menu">
        <li class="<?= $current_page === 'dash.php' ? 'active' : '' ?>">
            <a href="dash.php">
                <span class="nav-icon">🏠</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        <li class="<?= $current_page === 'pending_verifications.php' ? 'active' : '' ?>">
            <a href="pending_verifications.php">
                <span class="nav-icon">📋</span>
                <span class="nav-text">Pending Verifications</span>
            </a>
        </li>
        <li class="<?= $current_page === 'all_submissions.php' ? 'active' : '' ?>">
            <a href="all_submissions.php">
                <span class="nav-icon">📁</span>
                <span class="nav-text">All Submissions</span>
            </a>
        </li>
        <li class="<?= $current_page === 'audit_log.php' ? 'active' : '' ?>">
            <a href="audit_log.php">
                <span class="nav-icon">📜</span>
                <span class="nav-text">Audit Log</span>
            </a>
        </li>
        <li class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">
            <a href="reports.php">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Reports</span>
            </a>
        </li>
        <li class="<?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <a href="settings.php">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Settings</span>
            </a>
        </li>
    </ul>
    
    <div class="nav-footer">
        <div class="user-info">
            <span class="user-avatar">👤</span>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($_SESSION['admin_fname'] ?? 'Admin') ?></span>
                <span class="user-role">Administrator</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn" title="Logout">
            <span>🚪</span>
        </a>
    </div>
</nav>
