<?php
session_start();
if (!isset($_SESSION["uname"])) {
  header("Location: ../login_student.php");
}
include '../config.php';
require_once '../utils/message_utils.php';
$uname = $_SESSION['uname'];
$student_id = $_SESSION['user_id'] ?? 0;
$school_id = $_SESSION['school_id'] ?? 1;

// Get the count of unread messages
$unread_count = getUnreadMessageCount($uname, $conn);

// Get objective exam stats
$objective_stats = [
    'available' => 0,
    'submitted' => 0,
    'graded' => 0,
    'pending_results' => 0
];

// Available objective exams
$obj_avail_sql = "SELECT COUNT(*) as cnt FROM objective_exm_list 
                  WHERE school_id = ? AND status = 'active' 
                  AND exam_id NOT IN (SELECT exam_id FROM objective_submissions WHERE student_id = ?)";
$stmt = mysqli_prepare($conn, $obj_avail_sql);
mysqli_stmt_bind_param($stmt, "ii", $school_id, $student_id);
mysqli_stmt_execute($stmt);
$objective_stats['available'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

// Submitted objective exams
$obj_sub_sql = "SELECT COUNT(*) as cnt FROM objective_submissions WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $obj_sub_sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$objective_stats['submitted'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

// Graded objective exams
$obj_graded_sql = "SELECT COUNT(*) as cnt FROM objective_submissions WHERE student_id = ? AND submission_status = 'graded'";
$stmt = mysqli_prepare($conn, $obj_graded_sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$objective_stats['graded'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

// Pending results (submitted but not graded)
$objective_stats['pending_results'] = $objective_stats['submitted'] - $objective_stats['graded'];

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
        <a href="study_material.php">
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
            <div class="box-topic">MCQ Exams</div>
            <div class="number"><?php
                                $sql = "SELECT COUNT(1) FROM exm_list WHERE school_id = $school_id";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_array($result);
                                echo $row['0'] ?></div>
            <div class="brief">
              <span class="text">Total MCQ exams available</span>
            </div>
          </div>
          <i class='bx bx-book-content ico'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Objective Exams</div>
            <div class="number"><?php echo $objective_stats['available']; ?></div>
            <div class="brief">
              <span class="text">Pending objective exams</span>
            </div>
          </div>
          <i class='bx bx-edit-alt ico two'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Attempts</div>
            <div class="number"><?php $sql = "SELECT COUNT(1) FROM atmpt_list WHERE uname='$uname'";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_array($result);
                                echo $row['0'] ?></div>
            <div class="brief">
              <span class="text">MCQ exams attempted</span>
            </div>
          </div>
          <i class='bx bx-check-circle ico three'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Announcements</div>
            <div class="number"><?php $sql = "SELECT COUNT(1) FROM message";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_array($result);
                                echo $row['0'] ?></div>
            <div class="brief">
              <span class="text">Total announcements</span>
            </div>
          </div>
          <i class='bx bx-paper-plane ico four'></i>
        </div>
      </div>

      <!-- Objective Exam Quick Status -->
      <?php if ($objective_stats['pending_results'] > 0 || $objective_stats['graded'] > 0): ?>
      <div class="overview-boxes" style="margin-top: 15px;">
        <?php if ($objective_stats['pending_results'] > 0): ?>
        <div class="box" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-left: 4px solid #ff9800;">
          <div class="right-side">
            <div class="box-topic" style="color: #e65100;">Pending Results</div>
            <div class="number" style="color: #ff9800;"><?php echo $objective_stats['pending_results']; ?></div>
            <div class="brief">
              <a href="objective_exams.php" style="color: #e65100; text-decoration: underline;">Objective exams being graded</a>
            </div>
          </div>
          <i class='bx bx-time-five' style="color: #ff9800;"></i>
        </div>
        <?php endif; ?>
        
        <?php if ($objective_stats['graded'] > 0): ?>
        <div class="box" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 4px solid #17684f;">
          <div class="right-side">
            <div class="box-topic" style="color: #17684f;">Graded Results</div>
            <div class="number" style="color: #17684f;"><?php echo $objective_stats['graded']; ?></div>
            <div class="brief">
              <a href="objective_exams.php" style="color: #17684f; text-decoration: underline;">View your objective exam results</a>
            </div>
          </div>
          <i class='bx bx-check-double' style="color: #17684f;"></i>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

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