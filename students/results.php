<?php
session_start();
if (!isset($_SESSION["uname"])) {
  header("Location: ../login_student.php");
  exit;
}
include '../config.php';
require_once '../utils/message_utils.php';
$uname = $_SESSION['uname'];
$school_id = $_SESSION['school_id'] ?? 0;

// Get the count of unread messages
$unread_count = getUnreadMessageCount($uname, $conn);

// Filter results by student's school - only show results for exams from their school
$sql = "SELECT a.*, e.exname 
        FROM atmpt_list a 
        INNER JOIN exm_list e ON a.exid = e.exid 
        WHERE a.uname = ? AND e.school_id = ?
        ORDER BY a.id DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $uname, $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Results</title>
  <link rel="stylesheet" href="css/dash.css">
  <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .cert-btn {
      display: inline-flex;
      align-items: center;
      padding: 6px 12px;
      background-color: #0A2558;
      color: white;
      border-radius: 4px;
      text-decoration: none;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .cert-btn:hover {
      background-color: #0d3a80;
    }

    .cert-btn i {
      margin-right: 5px;
    }

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
        <a href="#" class="active">
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
      </div>
      <div class="profile-details">
        <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
        <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
      </div>
    </nav>

    <div class="home-content">
      <div class="stat-boxes">
        <div class="recent-stat box" style="padding: 0px 0px; width:70%;">
          <table>
            <thead>
              <tr>
                <th>Exam name</th>
                <th>Total questions</th>
                <th>Correct answers</th>
                <th>Percentage</th>
                <th>Completed on</th>
                <th>Certificate</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
              ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['exname']); ?></td>
                    <td><?php echo $row['nq']; ?></td>
                    <td><?php echo $row['cnq']; ?></td>
                    <td><?php echo $row['ptg']; ?>%</td>
                    <td><?php echo $row['subtime']; ?></td>
                    <td>
                      <a href="generate_certificate.php?id=<?php echo $row['id']; ?>" class="cert-btn" target="_blank">
                        <i class='bx bx-download'></i> Certificate
                      </a>
                    </td>
                  </tr>
              <?php
                }
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
    </div>
  </section>

  <script src="../js/script.js"></script>

</body>

</html>