<?php
/**
 * Teacher - My Schools
 * Shows schools where teacher has teaching slot bookings
 */
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
error_reporting(0);

$teacher_id = $_SESSION['user_id'];

// Get schools where teacher has slot bookings (past or present)
$schools_sql = "SELECT 
    s.school_id,
    s.school_name,
    s.school_code,
    s.address,
    s.contact_email,
    s.contact_phone,
    s.contact_person,
    COUNT(DISTINCT ste.enrollment_id) as total_bookings,
    COUNT(DISTINCT CASE WHEN ste.enrollment_status = 'booked' AND sts.slot_date >= CURDATE() THEN ste.enrollment_id END) as upcoming_bookings,
    COUNT(DISTINCT CASE WHEN ste.enrollment_status = 'completed' THEN ste.enrollment_id END) as completed_sessions,
    COUNT(DISTINCT CASE WHEN ts.session_status = 'approved' THEN ts.session_id END) as approved_sessions,
    MIN(CASE WHEN ste.enrollment_status = 'booked' AND sts.slot_date >= CURDATE() THEN sts.slot_date END) as next_slot_date
FROM schools s
INNER JOIN school_teaching_slots sts ON s.school_id = sts.school_id
INNER JOIN slot_teacher_enrollments ste ON sts.slot_id = ste.slot_id
LEFT JOIN teaching_sessions ts ON ste.enrollment_id = ts.enrollment_id
WHERE ste.teacher_id = ?
GROUP BY s.school_id
ORDER BY upcoming_bookings DESC, s.school_name ASC";

$stmt = mysqli_prepare($conn, $schools_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$schools_result = mysqli_stmt_get_result($stmt);

$my_schools = [];
while ($school = mysqli_fetch_assoc($schools_result)) {
    $my_schools[] = $school;
}

// Get total stats
$total_upcoming = array_sum(array_column($my_schools, 'upcoming_bookings'));
$total_completed = array_sum(array_column($my_schools, 'completed_sessions'));
$total_approved = array_sum(array_column($my_schools, 'approved_sessions'));
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>My Schools | Teacher Portal</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .page-header {
            margin-bottom: 25px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .page-subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 5px 0;
        }
        .stat-card p {
            color: #666;
            font-size: 13px;
            margin: 0;
        }
        .stat-card.schools h3 { color: #667eea; }
        .stat-card.upcoming h3 { color: #f59e0b; }
        .stat-card.completed h3 { color: #10b981; }
        .stat-card.approved h3 { color: #3b82f6; }
        
        .school-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .school-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid #667eea;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .school-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .school-card.has-upcoming {
            border-left-color: #f59e0b;
        }
        .school-name {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 5px;
        }
        .school-code {
            display: inline-block;
            background: #f3f4f6;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
            margin-bottom: 12px;
        }
        .school-info {
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .school-info i {
            color: #667eea;
            width: 16px;
        }
        .school-stats {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        .mini-stat {
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .mini-stat.upcoming {
            background: #fef3c7;
            color: #92400e;
        }
        .mini-stat.completed {
            background: #d1fae5;
            color: #065f46;
        }
        .mini-stat.approved {
            background: #dbeafe;
            color: #1e40af;
        }
        .next-slot {
            background: #fef3c7;
            color: #92400e;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 12px;
        }
        .school-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-sm {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            font-size: 20px;
            color: #374151;
            margin-bottom: 10px;
        }
        .empty-state p {
            color: #6b7280;
            margin-bottom: 20px;
        }
        .btn-large {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #667eea;
            color: white;
        }
        .btn-large:hover {
            background: #5a67d8;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-details">
            <i class='bx bx-diamond'></i>
            <span class="logo_name">Welcome</span>
        </div>
        <ul class="nav-links">
            <li><a href="dash.php"><i class='bx bx-grid-alt'></i><span class="links_name">Dashboard</span></a></li>
            <li><a href="exams.php"><i class='bx bx-book-content'></i><span class="links_name">MCQ Exams</span></a></li>
            <li><a href="objective_exams.php"><i class='bx bx-edit'></i><span class="links_name">Objective Exams</span></a></li>
            <li><a href="results.php"><i class='bx bxs-bar-chart-alt-2'></i><span class="links_name">Results</span></a></li>
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="#" class="active"><i class='bx bx-building-house'></i><span class="links_name">Schools</span></a></li>
            <li><a href="browse_slots.php"><i class='bx bx-calendar-check'></i><span class="links_name">Teaching Slots</span></a></li>
            <li><a href="my_slots.php"><i class='bx bx-calendar'></i><span class="links_name">My Bookings</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">My Schools</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content" style="padding: 20px;">
            <div class="page-header">
                <h1 class="page-title">🏫 My Schools</h1>
                <p class="page-subtitle">Schools where you have booked teaching slots</p>
            </div>
            
            <?php if (!empty($my_schools)): ?>
            <!-- Stats Overview -->
            <div class="stats-row">
                <div class="stat-card schools">
                    <h3><?= count($my_schools) ?></h3>
                    <p>Schools</p>
                </div>
                <div class="stat-card upcoming">
                    <h3><?= $total_upcoming ?></h3>
                    <p>Upcoming Slots</p>
                </div>
                <div class="stat-card completed">
                    <h3><?= $total_completed ?></h3>
                    <p>Completed Sessions</p>
                </div>
                <div class="stat-card approved">
                    <h3><?= $total_approved ?></h3>
                    <p>Approved Sessions</p>
                </div>
            </div>
            
            <!-- School Cards -->
            <div class="school-grid">
                <?php foreach ($my_schools as $school): ?>
                <div class="school-card <?= $school['upcoming_bookings'] > 0 ? 'has-upcoming' : '' ?>">
                    <div class="school-name"><?= htmlspecialchars($school['school_name']) ?></div>
                    <span class="school-code"><?= htmlspecialchars($school['school_code']) ?></span>
                    
                    <?php if ($school['address']): ?>
                    <div class="school-info">
                        <i class='bx bx-map'></i>
                        <span><?= htmlspecialchars($school['address']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($school['contact_person']): ?>
                    <div class="school-info">
                        <i class='bx bx-user'></i>
                        <span><?= htmlspecialchars($school['contact_person']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($school['next_slot_date']): ?>
                    <div class="next-slot">
                        <i class='bx bx-calendar'></i> Next slot: <?= date('M j, Y', strtotime($school['next_slot_date'])) ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="school-stats">
                        <?php if ($school['upcoming_bookings'] > 0): ?>
                        <span class="mini-stat upcoming">
                            <i class='bx bx-calendar-check'></i> <?= $school['upcoming_bookings'] ?> upcoming
                        </span>
                        <?php endif; ?>
                        <?php if ($school['completed_sessions'] > 0): ?>
                        <span class="mini-stat completed">
                            <i class='bx bx-check'></i> <?= $school['completed_sessions'] ?> completed
                        </span>
                        <?php endif; ?>
                        <?php if ($school['approved_sessions'] > 0): ?>
                        <span class="mini-stat approved">
                            <i class='bx bx-badge-check'></i> <?= $school['approved_sessions'] ?> approved
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="school-actions">
                        <a href="my_slots.php" class="btn-sm btn-primary">
                            <i class='bx bx-calendar'></i> My Bookings
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Browse More -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="browse_slots.php" class="btn-large">
                    <i class='bx bx-search'></i> Browse More Teaching Slots
                </a>
            </div>
            
            <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class='bx bx-building-house'></i>
                <h3>No Schools Yet</h3>
                <p>You haven't booked any teaching slots yet.<br>Browse available slots to start teaching at schools.</p>
                <a href="browse_slots.php" class="btn-large">
                    <i class='bx bx-calendar-check'></i> Browse Teaching Slots
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        let sidebar = document.querySelector(".sidebar");
        let sidebarBtn = document.querySelector(".sidebarBtn");
        sidebarBtn.onclick = function() {
            sidebar.classList.toggle("active");
            if (sidebar.classList.contains("active")) {
                sidebarBtn.classList.replace("bx-menu", "bx-menu-alt-right");
            } else {
                sidebarBtn.classList.replace("bx-menu-alt-right", "bx-menu");
            }
        }
    </script>
</body>
</html>
