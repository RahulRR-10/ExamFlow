<?php
session_start();
if (!isset($_SESSION["fname"])) {
  header("Location: ../login_teacher.php");
}
include '../config.php';
error_reporting(0);

$teacher_id = $_SESSION['user_id'];

// Get teacher's enrolled schools
$schools_sql = "SELECT s.school_id, s.school_name 
                FROM schools s 
                INNER JOIN teacher_schools ts ON s.school_id = ts.school_id 
                WHERE ts.teacher_id = ? AND s.status = 'active'
                ORDER BY ts.is_primary DESC, s.school_name ASC";
$schools_stmt = mysqli_prepare($conn, $schools_sql);
mysqli_stmt_bind_param($schools_stmt, "i", $teacher_id);
mysqli_stmt_execute($schools_stmt);
$schools_result = mysqli_stmt_get_result($schools_stmt);
$teacher_schools = [];
while ($school = mysqli_fetch_assoc($schools_result)) {
  $teacher_schools[] = $school;
}

// Get students from teacher's enrolled schools only
$school_ids = array_column($teacher_schools, 'school_id');
if (!empty($school_ids)) {
  $school_ids_str = implode(',', $school_ids);
  $sql = "SELECT s.*, sch.school_name 
            FROM student s 
            LEFT JOIN schools sch ON s.school_id = sch.school_id 
            WHERE s.school_id IN ($school_ids_str) 
            ORDER BY sch.school_name, s.fname";
} else {
  $sql = "SELECT s.*, NULL as school_name FROM student s WHERE 1=0"; // No results if no schools
}
$result = mysqli_query($conn, $sql);

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
        <a href="#" class="active">
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
        <div class="recent-stat box" style="padding: 0px 0px;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Full name</th>
                <th>Username</th>
                <th>School</th>
                <th>Email</th>
                <th>Gender</th>
                <th>DOB</th>
                <th>EDIT</th>
                <th>DELETE</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
              ?>
                  <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['fname']; ?></td>
                    <td><?php echo $row['uname']; ?></td>
                    <td><?php echo htmlspecialchars($row['school_name'] ?? 'N/A'); ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td><?php echo $row['gender']; ?></td>
                    <td><?php echo $row['dob']; ?></td>
                    <td>
                      <form action="updateuserform.php" method="post">
                        <input type="hidden" name="edit_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="edit_btn" class="rounded-button-updt"><i class='bx bxs-edit'></i></button>
                      </form>
                    </td>
                    <td>
                      <form action="del.php" method="post">
                        <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="delete_btn" class="rounded-button-del"><i class='bx bx-x'></i></button>
                      </form>
                    </td>
                  </tr>
              <?php
                }
              }
              ?>
            </tbody>
          </table>
        </div>
        <div class="top-stat box">
          <div class="title">Add new student</div>
          <br><br>
          <img src="../img/anon.png" alt="pro" style=" display: block; margin-left: auto; margin-right: auto; width:30%; max-width:200px" ;>
          <form action="adduser.php" method="post">
            <label for="school_id">School *</label><br>
            <select class="inputbox" id="school_id" name="school_id" required style="width:100%; padding:8px; margin-bottom:10px;">
              <option value="">-- Select School --</option>
              <?php foreach ($teacher_schools as $school): ?>
                <option value="<?php echo $school['school_id']; ?>"><?php echo htmlspecialchars($school['school_name']); ?></option>
              <?php endforeach; ?>
            </select><br>
            <label for="fname">Full Name</label><br>
            <input class="inputbox" type="text" id="fname" name="fname" placeholder="Enter full name" minlength="4" maxlength="30" required /></br>
            <label for="uname">Username</label><br>
            <input class="inputbox" type="text" id="uname" name="uname" placeholder="Enter username" minlength="5" maxlength="15" required /></br>
            <label for="pword">Password</label><br>
            <input class="inputbox" type="password" id="pword" name="pword" placeholder="pass****" minlength="8" maxlength="16" required /></br>
            <label for="cpword">Confirm password</label><br>
            <input class="inputbox" type="password" id="cpword" name="cpword" placeholder="pass****" minlength="8" maxlength="16" required /></br>
            <label for="email">Email</label><br>
            <input class="inputbox" type="email" id="email" name="email" placeholder="Enter email" minlength="5" maxlength="50" required />
            <label for="dob">Date of Birth</label><br>
            <input class="inputbox" type="date" id="dob" name="dob" placeholder="Enter DOB" required /><br>
            <label for="gender">Gender</label><br>
            <input class="inputbox" type="text" id="gender" name="gender" placeholder="Enter gender (M or F)" minlength="1" maxlength="1" required /><br>
            <br><br>
            <button type="submit" name="adduser" class="btn">Add Student</button>
          </form>
        </div>
      </div>

    </div>
  </section>

  <script src="../js/script.js"></script>


</body>

</html>