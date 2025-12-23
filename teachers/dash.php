<?php
session_start();
if (!isset($_SESSION["user_id"])) {
  header("Location: ../login_teacher.php");
  exit;
}
include '../config.php';

$teacher_id = $_SESSION['user_id'];

// Get teacher's enrolled schools
$schools_sql = "SELECT s.school_id, s.school_name, ts.is_primary 
                FROM schools s 
                INNER JOIN teacher_schools ts ON s.school_id = ts.school_id 
                WHERE ts.teacher_id = ? AND s.status = 'active'
                ORDER BY ts.is_primary DESC, s.school_name ASC";
$stmt = mysqli_prepare($conn, $schools_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$enrolled_schools = mysqli_stmt_get_result($stmt);
$school_count = mysqli_num_rows($enrolled_schools);

// Get primary school name
$primary_school_name = "";
mysqli_data_seek($enrolled_schools, 0);
while ($school = mysqli_fetch_assoc($enrolled_schools)) {
  if ($school['is_primary']) {
    $primary_school_name = $school['school_name'];
    break;
  }
}
mysqli_data_seek($enrolled_schools, 0);

// Get stats filtered by teacher's enrolled schools
$student_count_sql = "SELECT COUNT(1) as cnt FROM student 
                      WHERE school_id IN (SELECT school_id FROM teacher_schools WHERE teacher_id = ?)";
$stmt = mysqli_prepare($conn, $student_count_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$student_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];

$exam_count_sql = "SELECT COUNT(1) as cnt FROM exm_list 
                   WHERE school_id IN (SELECT school_id FROM teacher_schools WHERE teacher_id = ?)";
$stmt = mysqli_prepare($conn, $exam_count_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$exam_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];

$result_count_sql = "SELECT COUNT(1) as cnt FROM atmpt_list a 
                     INNER JOIN exm_list e ON a.exid = e.exid 
                     WHERE e.school_id IN (SELECT school_id FROM teacher_schools WHERE teacher_id = ?)";
$stmt = mysqli_prepare($conn, $result_count_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$result_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];

$message_count_sql = "SELECT COUNT(1) as cnt FROM message";
$message_count = mysqli_fetch_assoc(mysqli_query($conn, $message_count_sql))['cnt'];

// Objective exam stats
$obj_stats = [
    'total_exams' => 0,
    'pending_grading' => 0,
    'graded' => 0,
    'processing' => 0
];

// Total objective exams
$obj_total_sql = "SELECT COUNT(*) as cnt FROM objective_exm_list WHERE teacher_id = ?";
$stmt = mysqli_prepare($conn, $obj_total_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$obj_stats['total_exams'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

// Pending grading (ocr_complete)
$obj_pending_sql = "SELECT COUNT(*) as cnt FROM objective_submissions os
                    JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                    WHERE oe.teacher_id = ? AND os.submission_status = 'ocr_complete'";
$stmt = mysqli_prepare($conn, $obj_pending_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$obj_stats['pending_grading'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

// Graded
$obj_graded_sql = "SELECT COUNT(*) as cnt FROM objective_submissions os
                   JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                   WHERE oe.teacher_id = ? AND os.submission_status = 'graded'";
$stmt = mysqli_prepare($conn, $obj_graded_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$obj_stats['graded'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

// Processing (pending, ocr_processing)
$obj_proc_sql = "SELECT COUNT(*) as cnt FROM objective_submissions os
                 JOIN objective_exm_list oe ON os.exam_id = oe.exam_id
                 WHERE oe.teacher_id = ? AND os.submission_status IN ('pending', 'ocr_processing', 'grading')";
$stmt = mysqli_prepare($conn, $obj_proc_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$obj_stats['processing'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
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
        <a href="school_management.php">
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
        <span class="dashboard">Teacher's Dashboard</span>
        <?php if ($primary_school_name): ?>
          <span style="font-size: 12px; color: #666; margin-left: 15px; padding: 4px 10px; background: #e3f2fd; border-radius: 12px;">
            <i class='bx bx-building-house' style="margin-right: 4px;"></i><?php echo htmlspecialchars($primary_school_name); ?>
            <?php if ($school_count > 1): ?>
              <span style="color: #1976d2; font-weight: 500;">(+<?php echo ($school_count - 1); ?> more)</span>
            <?php endif; ?>
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
            <div class="box-topic">Students</div>
            <div class="number"><?php echo $student_count; ?></div>
            <div class="brief">
              <span class="text">Students in your schools</span>
            </div>
          </div>
          <i class='bx bx-user ico'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">MCQ Exams</div>
            <div class="number"><?php echo $exam_count; ?></div>
            <div class="brief">
              <span class="text">MCQ exams created</span>
            </div>
          </div>
          <i class='bx bx-book-content ico two'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Objective Exams</div>
            <div class="number"><?php echo $obj_stats['total_exams']; ?></div>
            <div class="brief">
              <span class="text">Objective exams created</span>
            </div>
          </div>
          <i class='bx bx-edit ico three'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">MCQ Results</div>
            <div class="number"><?php echo $result_count; ?></div>
            <div class="brief">
              <span class="text">MCQ submissions</span>
            </div>
          </div>
          <i class='bx bx-bar-chart-alt-2 ico four'></i>
        </div>
      </div>

      <!-- Objective Exam Grading Status -->
      <?php if ($obj_stats['pending_grading'] > 0 || $obj_stats['processing'] > 0 || $obj_stats['graded'] > 0): ?>
      <div class="overview-boxes" style="margin-top: 15px;">
        <?php if ($obj_stats['pending_grading'] > 0): ?>
        <div class="box" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-left: 4px solid #ff9800; cursor: pointer;" onclick="window.location.href='grade_objective.php'">
          <div class="right-side">
            <div class="box-topic" style="color: #e65100;">Pending Grading</div>
            <div class="number" style="color: #ff9800;"><?php echo $obj_stats['pending_grading']; ?></div>
            <div class="brief">
              <span class="text" style="color: #e65100;">Objective exams need grading</span>
            </div>
          </div>
          <i class='bx bx-time-five' style="color: #ff9800; font-size: 48px;"></i>
        </div>
        <?php endif; ?>
        
        <?php if ($obj_stats['processing'] > 0): ?>
        <div class="box" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 4px solid #2196F3;">
          <div class="right-side">
            <div class="box-topic" style="color: #1565c0;">Processing</div>
            <div class="number" style="color: #2196F3;"><?php echo $obj_stats['processing']; ?></div>
            <div class="brief">
              <span class="text" style="color: #1565c0;">Submissions being processed</span>
            </div>
          </div>
          <i class='bx bx-loader-alt bx-spin' style="color: #2196F3; font-size: 48px;"></i>
        </div>
        <?php endif; ?>
        
        <?php if ($obj_stats['graded'] > 0): ?>
        <div class="box" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 4px solid #17684f; cursor: pointer;" onclick="window.location.href='view_objective_results.php'">
          <div class="right-side">
            <div class="box-topic" style="color: #17684f;">Graded</div>
            <div class="number" style="color: #17684f;"><?php echo $obj_stats['graded']; ?></div>
            <div class="brief">
              <span class="text" style="color: #17684f;">Completed objective exams</span>
            </div>
          </div>
          <i class='bx bx-check-double' style="color: #17684f; font-size: 48px;"></i>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="stat-boxes">
        <div class="recent-stat box">
          <div class="title">Recent MCQ Results</div>
          <table id="res">
            <thead>
              <tr>

                <th id="res" style="width:20%">Date</th>
                <th id="res" style="width:35%">Name</th>
                <th id="res" style="width:25%">Exam name</th>
                <th id="res" style="width:20%">Percentage</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sql = "SELECT * FROM atmpt_list ORDER BY subtime DESC LIMIT 8";
              $result = mysqli_query($conn, $sql);
              if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
              ?>
                  <tr>
                    <td id="res"><?php $dptime = $row['subtime'];
                                  $dptime = date("M d, Y", strtotime($dptime));
                                  echo $dptime; ?></td>
                    <td id="res"><?php $uname = $row['uname'];
                                  $sql_name = "SELECT * FROM student WHERE uname='$uname'";
                                  $result_name = mysqli_query($conn, $sql_name);
                                  $row_name = mysqli_fetch_assoc($result_name);
                                  echo $row_name['fname']; ?></td>
                    <td id="res"><?php $exid = $row['exid'];
                                  $sql_exname = "SELECT * FROM exm_list WHERE exid='$exid'";
                                  $result_exname = mysqli_query($conn, $sql_exname);
                                  $row_exname = mysqli_fetch_assoc($result_exname);
                                  echo $row_exname['exname']; ?></td>
                    <td id="res"><?php echo $row['ptg']; ?>%</td>
                  </tr>
              <?php
                }
              }
              ?>
            </tbody>
          </table>
          <div class="button" style="">
            <a href="results.php">See All</a>
          </div>
        </div>

      </div>
    </div>
  </section>

  <script src="../js/script.js"></script>


</body>

</html>