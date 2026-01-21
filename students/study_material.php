<?php
session_start();
if (!isset($_SESSION["uname"])) {
    header("Location: ../login_student.php");
    exit;
}
include '../config.php';
require_once '../utils/message_utils.php';

$uname = $_SESSION['uname'];
$unread_count = getUnreadMessageCount($uname, $conn);

$drive_link = "https://drive.google.com/drive/folders/1SkyKmJ1s_pjwdKLFZzAr23KV7yFLVFRQ?usp=sharing";
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Study Material</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .notification-badge {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            position: absolute;
            top: -5px;
            right: 10px;
            min-width: 18px;
            height: 18px;
            background-color: #ff3e55;
            color: white;
            border-radius: 50%;
            font-size: 11px;
            font-weight: bold;
            padding: 0 4px;
        }

        .material-container {
            padding: 30px;
            padding-top: 110px;
            max-width: 800px;
            margin: 0 auto;
        }

        .material-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .material-card h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .material-card p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .material-icon {
            font-size: 80px;
            color: #4f457a;
            margin-bottom: 20px;
        }

        .btn-access {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #4f457a 0%, #a395d6 100%);
            color: white;
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(23, 104, 79, 0.3);
        }

        .btn-access:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(23, 104, 79, 0.4);
            background: linear-gradient(135deg, #1a7a5c 0%, #17684f 100%);
        }

        .btn-access i {
            font-size: 24px;
        }

        .info-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }

        .info-section h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .info-list {
            text-align: left;
            max-width: 400px;
            margin: 0 auto;
        }

        .info-list li {
            color: #666;
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-list li i {
            color: #17684f;
            font-size: 18px;
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
                    <i class='bx bx-edit-alt'></i>
                    <span class="links_name">Objective Exams</span>
                </a>
            </li>
            <li>
                <a href="mock_exams.php">
                    <i class='bx bx-test-tube'></i>
                    <span class="links_name">Mock Exams</span>
                </a>
            </li>
            <li>
                <a href="results.php">
                    <i class='bx bxs-bar-chart-alt-2'></i>
                    <span class="links_name">Results</span>
                </a>
            </li>
            <li>
                <a href="messages.php">
                    <i class='bx bx-message'></i>
                    <span class="links_name">Announcements</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="study_material.php" class="active">
                    <i class='bx bx-book-open'></i>
                    <span class="links_name">Study Material</span>
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
                <span class="dashboard">Study Material</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="material-container">
            <div class="material-card">
                <i class='bx bx-book-open material-icon'></i>
                <h1>📚 Study Material</h1>
                <p>Access all your study notes, resources, and learning materials in one place.
                    Our curated collection of study materials will help you prepare better for your exams.</p>

                <a href="<?php echo $drive_link; ?>" target="_blank" class="btn-access">
                    <i class='bx bx-folder-open'></i>
                    Access Study Materials
                </a>

                <div class="info-section">
                    <h3>What you'll find:</h3>
                    <ul class="info-list">
                        <li><i class='bx bx-check-circle'></i> Subject-wise notes and summaries</li>
                        <li><i class='bx bx-check-circle'></i> Previous year question papers</li>
                        <li><i class='bx bx-check-circle'></i> Reference materials and guides</li>
                        <li><i class='bx bx-check-circle'></i> Practice worksheets</li>
                    </ul>
                </div>
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