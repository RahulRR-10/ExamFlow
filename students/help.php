<?php
session_start();
if (!isset($_SESSION["fname"])) {
  header("Location: ../login_student.php");
}
include '../config.php';
require_once '../utils/message_utils.php';

// Get the unread message count
$uname = $_SESSION['uname'];
$unread_count = getUnreadMessageCount($uname, $conn);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>About&help</title>
  <link rel="stylesheet" href="css/dash.css">
  <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Notification badge style */
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
        <a href="settings.php">
          <i class='bx bx-cog'></i>
          <span class="links_name">Settings</span>
        </a>
      </li>
      <li>
        <a href="#" class="active">
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
        <span class="dashboard">Student Dashboard</span>
      </div>
      <div class="profile-details">
        <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
        <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
      </div>
    </nav>

    <div class="home-content">

      <div class="stat-boxes">
        <div class="recent-stat box" style="width:100%">
          <div class="title"><b>How to use</b></div>
          <br><br>
          <h4>Q1. How to logout ?</h4>
          <p>Ans: Click on the logout button at the left bottom on the navigation bar.</p><br>
          <h4>Q2. How to edit my profile details ?</h4>
          <p>Ans: Click on the settings option from the left naviation bar. After filling the required columns, click on update.</p><br>
          <h4>Q3. How to view the results ?</h4>
          <p>Ans: Go to the results option from the left navigation bar to view the results. </p><br>
          <h4>Q4. How to attempt exams ?</h4>
          <p>Ans: Navigate to the exams tab by clicking on the exams button from the left navigation bar. Tests can be attempted from here.</p><br>
          <h4>Q5. How to view announcements ?</h4>
          <p>Ans: Click on the Announcements option from the left navigation bar. A red notification badge will appear when there are new unread announcements.</p><br>

          <div class="credits">
            <span class="text" style="text-align: center;">Made <❤️> by Cerebro<br>Contact me on: Cerebro </span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script src="../js/script.js"></script>


</body>

</html>