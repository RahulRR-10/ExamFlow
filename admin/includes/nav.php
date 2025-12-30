<?php
/**
 * Admin Navigation Component
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
<nav class="admin-nav">
    <div class="nav-brand">
        <i class='bx bx-shield-quarter'></i>
        <span class="brand-text">Admin Panel</span>
    </div>
    
    <ul class="nav-menu">
        <li class="<?= $current_page === 'dash.php' ? 'active' : '' ?>">
            <a href="dash.php">
                <i class='bx bx-grid-alt'></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        <li class="<?= $current_page === 'manage_schools.php' ? 'active' : '' ?>">
            <a href="manage_schools.php">
                <i class='bx bx-building-house'></i>
                <span class="nav-text">Manage Schools</span>
            </a>
        </li>
        <li class="<?= $current_page === 'teaching_slots.php' || $current_page === 'view_slot.php' ? 'active' : '' ?>">
            <a href="teaching_slots.php">
                <i class='bx bx-calendar-check'></i>
                <span class="nav-text">Teaching Slots</span>
            </a>
        </li>
        <li class="<?= $current_page === 'pending_sessions.php' || $current_page === 'review_session.php' ? 'active' : '' ?>">
            <a href="pending_sessions.php">
                <i class='bx bx-camera'></i>
                <span class="nav-text">Session Reviews</span>
            </a>
        </li>
        <li class="<?= $current_page === 'teacher_stats.php' || $current_page === 'teacher_detail.php' ? 'active' : '' ?>">
            <a href="teacher_stats.php">
                <i class='bx bx-bar-chart-alt-2'></i>
                <span class="nav-text">Teacher Stats</span>
            </a>
        </li>
        <li class="<?= $current_page === 'force_unenroll.php' ? 'active' : '' ?>">
            <a href="force_unenroll.php">
                <i class='bx bx-user-minus'></i>
                <span class="nav-text">Force Unenroll</span>
            </a>
        </li>
        <li class="<?= $current_page === 'audit_log.php' ? 'active' : '' ?>">
            <a href="audit_log.php">
                <i class='bx bx-list-ul'></i>
                <span class="nav-text">Audit Log</span>
            </a>
        </li>
        <li class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">
            <a href="reports.php">
                <i class='bx bx-line-chart'></i>
                <span class="nav-text">Reports</span>
            </a>
        </li>
        <li class="<?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <a href="settings.php">
                <i class='bx bx-cog'></i>
                <span class="nav-text">Settings</span>
            </a>
        </li>
    </ul>
    
    <div class="nav-footer">
        <a href="logout.php" title="Logout">
            <i class='bx bx-log-out-circle'></i>
            <span>Log out</span>
        </a>
    </div>
</nav>
