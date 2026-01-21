<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';

$drive_link = "https://drive.google.com/drive/folders/1SkyKmJ1s_pjwdKLFZzAr23KV7yFLVFRQ?usp=sharing";
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Upload Material</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
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

        .btn-upload {
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

        .btn-upload:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(23, 104, 79, 0.4);
            background: linear-gradient(135deg, #1a7a5c 0%, #17684f 100%);
        }

        .btn-upload i {
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
            max-width: 450px;
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

        .tip-box {
            background: #f0f9f6;
            border: 1px solid #17684f;
            border-radius: 10px;
            padding: 15px 20px;
            margin-top: 25px;
            text-align: left;
        }

        .tip-box h4 {
            color: #17684f;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tip-box p {
            color: #555;
            font-size: 14px;
            margin: 0;
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
                <a href="messages.php">
                    <i class='bx bx-message'></i>
                    <span class="links_name">Messages</span>
                </a>
            </li>
            <li>
                <a href="school_management.php">
                    <i class='bx bx-building-house'></i>
                    <span class="links_name">Schools</span>
                </a>
            </li>
            <li>
                <a href="browse_slots.php">
                    <i class='bx bx-calendar-check'></i>
                    <span class="links_name">Teaching Slots</span>
                </a>
            </li>
            <li>
                <a href="my_slots.php">
                    <i class='bx bx-calendar'></i>
                    <span class="links_name">My Bookings</span>
                </a>
            </li>
            <li>
                <a href="upload_material.php" class="active">
                    <i class='bx bx-cloud-upload'></i>
                    <span class="links_name">Upload Material</span>
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
                <span class="dashboard">Upload Material</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="material-container">
            <div class="material-card">
                <i class='bx bx-cloud-upload material-icon'></i>
                <h1>📤 Upload Study Material</h1>
                <p>Share study resources, notes, and learning materials with your students. 
                   Upload files to our shared Google Drive folder where students can easily access them.</p>
                
                <a href="<?php echo $drive_link; ?>" target="_blank" class="btn-upload">
                    <i class='bx bx-folder-plus'></i>
                    Open Drive Folder to Upload
                </a>

                <div class="info-section">
                    <h3>What you can upload:</h3>
                    <ul class="info-list">
                        <li><i class='bx bx-file'></i> Lecture notes and presentations (PDF, PPT)</li>
                        <li><i class='bx bx-spreadsheet'></i> Practice worksheets and problem sets</li>
                        <li><i class='bx bx-book'></i> Reference materials and study guides</li>
                        <li><i class='bx bx-video'></i> Video tutorials and recorded lectures</li>
                        <li><i class='bx bx-question-mark'></i> Previous year question papers</li>
                    </ul>
                </div>

                <div class="tip-box">
                    <h4><i class='bx bx-bulb'></i> Tip</h4>
                    <p>Organize your files in subject-wise folders for easy navigation. Use clear, descriptive file names 
                       so students can quickly find what they need.</p>
                </div>
            </div>
        </div>
    </section>

    <script>
        let sidebar = document.querySelector(".sidebar");
        let sidebarBtn = document.querySelector(".sidebarBtn");
        sidebarBtn.onclick = function () {
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
