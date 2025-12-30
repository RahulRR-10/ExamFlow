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

// Get schools the teacher is enrolled in for the dropdown
$enrolled_schools_sql = "SELECT s.school_id, s.school_name 
                         FROM schools s 
                         INNER JOIN teacher_schools ts ON s.school_id = ts.school_id 
                         WHERE ts.teacher_id = ? AND s.status = 'active'
                         ORDER BY ts.is_primary DESC, s.school_name ASC";
$stmt_schools = mysqli_prepare($conn, $enrolled_schools_sql);
mysqli_stmt_bind_param($stmt_schools, "i", $teacher_id);
mysqli_stmt_execute($stmt_schools);
$enrolled_schools = mysqli_stmt_get_result($stmt_schools);

// Get selected school filter (default to 'all')
$filter_school = isset($_GET['school_filter']) ? intval($_GET['school_filter']) : 0;

// Filter exams by teacher's enrolled schools
if ($filter_school > 0) {
  // Filter by specific school
  $sql = "SELECT e.*, s.school_name 
            FROM exm_list e 
            LEFT JOIN schools s ON e.school_id = s.school_id 
            WHERE e.school_id = ? 
            AND e.school_id IN (
                SELECT school_id FROM teacher_schools WHERE teacher_id = ?
            )
            ORDER BY e.extime DESC";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "ii", $filter_school, $teacher_id);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
} else {
  // Show all exams from enrolled schools
  $sql = "SELECT e.*, s.school_name 
            FROM exm_list e 
            LEFT JOIN schools s ON e.school_id = s.school_id 
            WHERE e.school_id IN (
                SELECT school_id FROM teacher_schools WHERE teacher_id = ?
            )
            ORDER BY e.extime DESC";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "i", $teacher_id);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
}

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Messages</title>
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
        <a href="#" class="active">
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
      <!-- School Filter Dropdown -->
      <div style="margin-bottom: 20px; padding: 15px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <form method="GET" action="exams.php" style="display: flex; align-items: center; gap: 15px;">
          <label for="school_filter" style="font-weight: 600; color: #17684f;">Filter by School:</label>
          <select name="school_filter" id="school_filter" style="padding: 8px 15px; border-radius: 6px; border: 1px solid #ddd; min-width: 200px;" onchange="this.form.submit()">
            <option value="0">All Schools</option>
            <?php
            // Reset the result pointer to reuse
            mysqli_data_seek($enrolled_schools, 0);
            while ($school = mysqli_fetch_assoc($enrolled_schools)) {
              $selected = ($filter_school == $school['school_id']) ? 'selected' : '';
              echo "<option value='{$school['school_id']}' $selected>{$school['school_name']}</option>";
            }
            ?>
          </select>
        </form>
      </div>

      <div class="stat-boxes">
        <div class="recent-stat box" style="padding: 0px 0px;width:75%;">
          <table>
            <thead>
              <tr>
                <th>Exam no.</th>
                <th>Exam name</th>
                <th>School</th>
                <th>No. of questions</th>
                <th>Exam time</th>
                <th>Submission time</th>
                <th>Duration (min)</th>
                <th>EDIT</th>
                <th>DELETE</th>
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
                    <td><?php echo $row['nq']; ?></td>
                    <td><?php echo $row['extime']; ?></td>
                    <td><?php echo $row['subt']; ?></td>
                    <td><?php echo $row['duration']; ?></td>
                    <td>
                      <form action="addqp.php" method="post">
                        <input type="hidden" name="nq" value="<?php echo $row['nq']; ?>">

                        <input type="hidden" name="exid" value="<?php $exid = $row['exid'];
                                                                echo $row['exid']; ?>">

                        <button type="submit" name="edit_btn" class="rounded-button-updt"><i class='bx bxs-edit'></i></button>
                      </form>
                    </td>
                    <td>
                      <form action="delexam.php" method="post">
                        <input type="hidden" name="delete_id" value="<?php echo $row['exid']; ?>">
                        <button type="submit" name="delete_btn" class="rounded-button-del"><i class='bx bx-x'></i></button>
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
        <div class="top-stat box">
          <div class="title">Add new exam</div>
          <br><br>
          <form action="addexam.php" method="post">
            <input type="hidden" name="subject" value="<?php echo $_SESSION['subject']; ?>">
            <label for="school_id">School *</label><br>
            <select class="inputbox" id="school_id" name="school_id" required style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 6px; border: 1px solid #ddd;">
              <option value="">-- Select School --</option>
              <?php
              // Reset the result pointer to reuse
              mysqli_data_seek($enrolled_schools, 0);
              while ($school = mysqli_fetch_assoc($enrolled_schools)) {
                echo "<option value='{$school['school_id']}'>{$school['school_name']}</option>";
              }
              ?>
            </select>
            <label for="exname">Exam name</label><br>
            <input class="inputbox" type="text" id="exname" name="exname" placeholder="Enter exam name" minlength="3" maxlength="30" required /></br>
            <label for="desp">Description</label><br>
            <input class="inputbox" type="text" id="desp" name="desp" placeholder="Enter exam description" minlength="5" maxlength="100" required /></br>
            <label for="extime">Exam time</label><br>
            <input class="inputbox" type="datetime-local" id="extime" name="extime" required /></br>
            <label for="subt">Submission time</label><br>
            <input class="inputbox" type="datetime-local" id="subt" name="subt" required /></br>
            <label for="duration">Test duration (minutes)</label><br>
            <input class="inputbox" type="number" id="duration" name="duration" min="1" max="240" value="60" required /></br>
            <label for="nq">No. of questions</label><br>
            <input class="inputbox" type="number" id="nq" name="nq" required /></br>
            <br><br>
            <button type="submit" name="addexm" class="btn"><i class='bx bx-plus'></i> Add</button>
          </form>
        </div>
      </div>

    </div>
  </section>

  <script src="../js/script.js"></script>


</body>

</html>