<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
error_reporting(0);

$teacher_id = $_SESSION['user_id'];

// Get schools the teacher is enrolled in
$enrolled_sql = "SELECT s.*, ts.enrolled_at, ts.is_primary 
                 FROM schools s 
                 INNER JOIN teacher_schools ts ON s.school_id = ts.school_id 
                 WHERE ts.teacher_id = ? AND s.status = 'active'
                 ORDER BY ts.is_primary DESC, ts.enrolled_at DESC";
$stmt = mysqli_prepare($conn, $enrolled_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$enrolled_result = mysqli_stmt_get_result($stmt);

// Get available schools (not enrolled)
$available_sql = "SELECT s.* FROM schools s 
                  WHERE s.status = 'active' 
                  AND s.school_id NOT IN (
                    SELECT school_id FROM teacher_schools WHERE teacher_id = ?
                  )
                  ORDER BY s.school_name ASC";
$stmt2 = mysqli_prepare($conn, $available_sql);
mysqli_stmt_bind_param($stmt2, "i", $teacher_id);
mysqli_stmt_execute($stmt2);
$available_result = mysqli_stmt_get_result($stmt2);

// Handle messages
$success_msg = isset($_GET['success']) ? $_GET['success'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>School Management</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .school-section {
            padding: 0 20px;
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #17684f;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #17684f;
        }

        .school-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .school-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .school-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .school-card.enrolled {
            border-left: 4px solid #17684f;
        }

        .school-card.available {
            border-left: 4px solid #3498db;
        }

        .school-card.primary {
            border-left: 4px solid #f39c12;
        }

        .school-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .school-code {
            font-size: 12px;
            color: #888;
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .school-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .school-info i {
            margin-right: 8px;
            color: #17684f;
        }

        .primary-badge {
            background: #f39c12;
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-enroll {
            background: #3498db;
            color: #fff;
        }

        .btn-enroll:hover {
            background: #2980b9;
        }

        .btn-leave {
            background: #e74c3c;
            color: #fff;
        }

        .btn-leave:hover {
            background: #c0392b;
        }

        .btn-primary-action {
            background: #f39c12;
            color: #fff;
            margin-right: 10px;
        }

        .btn-primary-action:hover {
            background: #d68910;
        }

        .btn-create {
            background: #17684f;
            color: #fff;
            margin-bottom: 20px;
        }

        .btn-create:hover {
            background: #11533e;
        }

        .btn-create i {
            margin-right: 8px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .card-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
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
            <li>
                <a href="dash.php">
                    <i class='bx bx-grid-alt'></i>
                    <span class="links_name">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="exams.php">
                    <i class='bx bx-book-content'></i>
                    <span class="links_name">MCQ Exams</span>
                </a>
            </li>
            <li>
                <a href="objective_exams.php">
                    <i class='bx bx-edit'></i>
                    <span class="links_name">Objective Exams</span>
                </a>
            </li>
            <li>
                <a href="results.php">
                    <i class='bx bxs-bar-chart-alt-2'></i>
                    <span class="links_name">Results</span>
                </a>
            </li>
            <li>
                <a href="records.php">
                    <i class='bx bxs-user-circle'></i>
                    <span class="links_name">Records</span>
                </a>
            </li>
            <li>
                <a href="messages.php">
                    <i class='bx bx-message'></i>
                    <span class="links_name">Messages</span>
                </a>
            </li>
            <li>
                <a href="#" class="active">
                    <i class='bx bx-building-house'></i>
                    <span class="links_name">Schools</span>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <i class='bx bx-cog'></i>
                    <span class="links_name">Settings</span>
                </a>
            </li>
            <li>
                <a href="help.php">
                    <i class='bx bx-help-circle'></i>
                    <span class="links_name">Help</span>
                </a>
            </li>
            <li class="log_out">
                <a href="../logout.php">
                    <i class='bx bx-log-out-circle'></i>
                    <span class="links_name">Log out</span>
                </a>
            </li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">School Management</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="school-section">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <!-- Enrolled Schools Section -->
                <h2 class="section-title"><i class='bx bx-check-circle'></i> My Schools</h2>

                <?php if (mysqli_num_rows($enrolled_result) > 0): ?>
                    <div class="school-grid">
                        <?php while ($school = mysqli_fetch_assoc($enrolled_result)): ?>
                            <div class="school-card <?php echo $school['is_primary'] ? 'primary' : 'enrolled'; ?>">
                                <?php if ($school['is_primary']): ?>
                                    <span class="primary-badge"><i class='bx bx-star'></i> Primary School</span>
                                <?php endif; ?>
                                <div class="school-name"><?php echo htmlspecialchars($school['school_name']); ?></div>
                                <span class="school-code"><?php echo htmlspecialchars($school['school_code']); ?></span>

                                <?php if ($school['address']): ?>
                                    <div class="school-info">
                                        <i class='bx bx-map'></i> <?php echo htmlspecialchars($school['address']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($school['contact_email']): ?>
                                    <div class="school-info">
                                        <i class='bx bx-envelope'></i> <?php echo htmlspecialchars($school['contact_email']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="school-info">
                                    <i class='bx bx-calendar'></i> Enrolled: <?php echo date('M d, Y', strtotime($school['enrolled_at'])); ?>
                                </div>

                                <div class="card-actions">
                                    <?php if (!$school['is_primary']): ?>
                                        <a href="enroll_school.php?action=set_primary&school_id=<?php echo $school['school_id']; ?>"
                                            class="btn btn-primary-action"
                                            onclick="return confirm('Set this as your primary school?');">
                                            <i class='bx bx-star'></i> Set Primary
                                        </a>
                                    <?php endif; ?>
                                    <a href="enroll_school.php?action=leave&school_id=<?php echo $school['school_id']; ?>"
                                        class="btn btn-leave"
                                        onclick="return confirm('Are you sure you want to leave this school?');">
                                        <i class='bx bx-exit'></i> Leave
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-building-house'></i>
                        <p>You are not enrolled in any schools yet.</p>
                        <p>Enroll in a school below or create a new one.</p>
                    </div>
                <?php endif; ?>

                <!-- Available Schools Section -->
                <h2 class="section-title"><i class='bx bx-search'></i> Available Schools</h2>

                <?php if (mysqli_num_rows($available_result) > 0): ?>
                    <div class="school-grid">
                        <?php while ($school = mysqli_fetch_assoc($available_result)): ?>
                            <div class="school-card available">
                                <div class="school-name"><?php echo htmlspecialchars($school['school_name']); ?></div>
                                <span class="school-code"><?php echo htmlspecialchars($school['school_code']); ?></span>

                                <?php if ($school['address']): ?>
                                    <div class="school-info">
                                        <i class='bx bx-map'></i> <?php echo htmlspecialchars($school['address']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($school['contact_email']): ?>
                                    <div class="school-info">
                                        <i class='bx bx-envelope'></i> <?php echo htmlspecialchars($school['contact_email']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="card-actions">
                                    <a href="enroll_school.php?action=enroll&school_id=<?php echo $school['school_id']; ?>"
                                        class="btn btn-enroll">
                                        <i class='bx bx-plus'></i> Enroll
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-check-double'></i>
                        <p>You are enrolled in all available schools.</p>
                    </div>
                <?php endif; ?>
            </div>
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