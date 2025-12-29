<?php
session_start();
if (!isset($_SESSION["user_id"])) {
  header("Location: ../login_teacher.php");
  exit;
}
include '../config.php';
error_reporting(0);

$teacher_id = $_SESSION['user_id'];

// Filter exams by teacher's enrolled schools
$sql = "SELECT e.*, s.school_name 
        FROM exm_list e 
        LEFT JOIN schools s ON e.school_id = s.school_id 
        WHERE e.school_id IN (
            SELECT school_id FROM teacher_schools WHERE teacher_id = ?
        )
        ORDER BY e.datetime DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Select exam </title>
  <link rel="stylesheet" href="css/dash.css">
  <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <a href="#" class="active">
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
        <a href="school_management.php">
          <i class='bx bx-building-house'></i>
          <span class="links_name">Schools</span>
        </a>
      </li>
      <li>
        <a href="teaching_activity.php">
          <i class='bx bx-map-pin'></i>
          <span class="links_name">Teaching Activity</span>
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
        <span class="dashboard">Teacher's Dashboard</span>
      </div>
      <div class="profile-details">
        <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
        <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>

      </div>
    </nav>

    <div class="home-content">
      <div class="stat-boxes">
        <div class="recent-stat box" style="padding: 0px 0px;width:100%;">
          <table>
            <thead>
              <tr>
                <th>Exam no.</th>
                <th>Exam name</th>
                <th>School</th>
                <th>Description</th>
                <th>No. of questions</th>
                <th>Added on</th>
                <th>Actions</th>
                <th>Analytics</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $i = 1;
              if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                  $school_name = isset($row['school_name']) ? $row['school_name'] : 'N/A';
              ?>
                  <tr>
                    <td><?php echo $i; ?></td>
                    <td><?php echo htmlspecialchars($row['exname']); ?></td>
                    <td><span style="background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 12px; font-size: 12px;"><?php echo htmlspecialchars($school_name); ?></span></td>
                    <td><?php echo htmlspecialchars($row['desp']); ?></td>
                    <td><?php echo $row['nq']; ?></td>
                    <td><?php echo $row['datetime']; ?></td>
                    <td>
                      <form action="viewresults.php" method="post">
                        <input type="hidden" name="exid" value="<?php echo $row['exid']; ?>">
                        <button class="btnres" type="submit" name="vw_rslts"><i class='bx bx-search-alt'></i>View Result</button>
                      </form>
                    </td>
                    <td>
                      <form action="view_analytics.php" method="post">
                        <input type="hidden" name="exid" value="<?php echo $row['exid']; ?>">
                        <button class="btnres analytics-btn" type="submit" name="vw_analytics"><i class='bx bx-bar-chart-alt-2'></i>View Analytics</button>
                      </form>
                    </td>
                  </tr>
              <?php
                  $i++;
                }
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <script src="../js/script.js"></script>


</body>

</html>