<?php
session_start();
if (!isset($_SESSION["uname"])) {
  header("Location: ../login_student.php");
}
include '../config.php';
require_once '../utils/message_utils.php';
$uname = $_SESSION['uname'];

// Get the count of unread messages
$unread_count = getUnreadMessageCount($uname, $conn);

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
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
        <a href="#" class="active">
          <i class='bx bx-grid-alt'></i>
          <span class="links_name">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="exams.php">
          <i class='bx bx-book-content'></i>
          <span class="links_name">Exams</span>
        </a>
      </li>
      <li>
        <a href="mock_exams.php">
          <i class='bx bx-edit'></i>
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
        <span class="dashboard">Student Dashboard</span>
        <?php if (isset($_SESSION['school_name'])): ?>
          <span style="font-size: 12px; color: #666; margin-left: 15px; padding: 4px 10px; background: #e8f5e9; border-radius: 12px;">
            <i class='bx bx-building-house' style="margin-right: 4px;"></i><?php echo htmlspecialchars($_SESSION['school_name']); ?>
          </span>
        <?php endif; ?>
      </div>
      <div class="profile-details">
        <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
        <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
      </div>
    </nav>

    <div class="home-content">
      <div class="overview-boxes">
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Exams</div>
            <div class="number"><?php
                                $school_id = $_SESSION['school_id'] ?? 1;
                                $sql = "SELECT COUNT(1) FROM exm_list WHERE school_id = $school_id";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_array($result);
                                echo $row['0'] ?></div>
            <div class="brief">
              <span class="text">Total number of exams</span>
            </div>
          </div>
          <i class='bx bx-user ico'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Attempts</div>
            <div class="number"><?php $sql = "SELECT COUNT(1) FROM atmpt_list WHERE uname='$uname'";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_array($result);
                                echo $row['0'] ?></div>
            <div class="brief">
              <span class="text">Total number of attempted exams</span>
            </div>
          </div>
          <i class='bx bx-book ico two'></i>
        </div>
        <!-- <div class="box">
          <div class="right-side">
            <div class="box-topic">Results</div>
            <div class="number"><?php $sql = "SELECT COUNT(1) FROM atmpt_list";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_array($result);
                                echo $row['0'] ?></div>
            <div class="brief">
              <span class="text">Number of available results</span>
            </div>
          </div>
          <i class='bx bx-line-chart ico three' ></i>
        </div> -->
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Announcements</div>
            <div class="number"><?php $sql = "SELECT COUNT(1) FROM message";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_array($result);
                                echo $row['0'] ?></div>
            <div class="brief">
              <span class="text">Total number of announcements received</span>
            </div>
          </div>
          <i class='bx bx-paper-plane ico four'></i>
        </div>
      </div>

      <div class="stat-boxes">
        <div class="recent-stat box" style="width:100%;">
          <div class="title" style="text-align:center;">:: General Instructions ::</div><br><br>
          <div class="stat-details">
            <ul class="details">
              <li><strong>Exam Timing:</strong> You are only allowed to start the test at the scheduled time. The timer begins immediately and will expire at the designated end time regardless of when you start.</li><br>
              <li><strong>Full Screen Mode:</strong> All examinations must be completed in full screen mode. Exiting full screen will be flagged as a potential integrity violation.</li><br>
              <li><strong>Integrity Monitoring:</strong> The system includes an advanced anti-cheat system that tracks:
                <ul style="margin-left: 30px; margin-top: 10px;">
                  <li>Tab switching or browser minimizing</li>
                  <li>Window focus loss (switching to other applications)</li>
                  <li>Suspicious patterns of combined behaviors</li>
                </ul>
              </li><br>
              <li><strong>Integrity Score:</strong> You begin each exam with a perfect 100-point integrity score. Violations result in score deductions:
                <ul style="margin-left: 30px; margin-top: 10px;">
                  <li>75-100: Good standing</li>
                  <li>50-74: At-Risk (requires review)</li>
                  <li>0-49: Cheating Suspicion (may result in disqualification)</li>
                </ul>
              </li><br>
              <li><strong>Auto-Submission:</strong> If your integrity score falls below the critical threshold, your exam will be automatically submitted.</li><br>
              <li><strong>Answer Selection:</strong> Click an option to select it. Locked answers will appear in green. Use the navigation panel to track your progress.</li><br>
              <li><strong>Mock Exams:</strong> Practice tests are available in the Mock Exams section to help you prepare.</li><br>
              <li><strong>Results:</strong> View your scores, performance analytics, and integrity reports in the Results section after completion.</li><br>
              <li><strong>Certificates:</strong> Blockchain-verified certificates can be generated for successfully completed exams with satisfactory integrity scores.</li><br>
            </ul>
          </div>
        </div>

  </section>

  <script src="../js/script.js"></script>


</body>

</html>