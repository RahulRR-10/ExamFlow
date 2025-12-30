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
        <li class="<?= $current_page === 'manage_schools.php' ? 'active' : '' ?>">
            <a href="manage_schools.php">
                <span class="nav-icon">🏫</span>
                <span class="nav-text">Manage Schools</span>
            </a>
        </li>
        <li class="<?= $current_page === 'teaching_slots.php' || $current_page === 'view_slot.php' ? 'active' : '' ?>">
            <a href="teaching_slots.php">
                <span class="nav-icon">📅</span>
                <span class="nav-text">Teaching Slots</span>
            </a>
        </li>
        <li class="<?= $current_page === 'slot_dashboard.php' ? 'active' : '' ?>">
            <a href="slot_dashboard.php">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Slot Dashboard</span>
            </a>
        </li>
        <li class="<?= $current_page === 'pending_sessions.php' || $current_page === 'review_session.php' ? 'active' : '' ?>">
            <a href="pending_sessions.php">
                <span class="nav-icon">📷</span>
                <span class="nav-text">Session Reviews</span>
            </a>
        </li>
        <li class="<?= $current_page === 'force_unenroll.php' ? 'active' : '' ?>">
            <a href="force_unenroll.php">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Force Unenroll</span>
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
