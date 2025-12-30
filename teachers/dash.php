<?php
session_start();
if (!isset($_SESSION["user_id"])) {
  header("Location: ../login_teacher.php");
  exit;
}
include '../config.php';

$teacher_id = $_SESSION['user_id'];

// Teaching Slots Based Stats
$slot_stats = [
    'upcoming_slots' => 0,
    'completed_sessions' => 0,
    'pending_verification' => 0,
    'total_schools' => 0
];

// Check if teaching_sessions table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'teaching_sessions'");
$slots_enabled = mysqli_num_rows($table_check) > 0;

if ($slots_enabled) {
    // Upcoming booked slots
    $upcoming_sql = "SELECT COUNT(*) as cnt FROM slot_teacher_enrollments ste
                     JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                     WHERE ste.teacher_id = ? AND ste.enrollment_status = 'confirmed'
                     AND sts.slot_date >= CURDATE() AND sts.slot_status NOT IN ('completed', 'cancelled')";
    $stmt = mysqli_prepare($conn, $upcoming_sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $slot_stats['upcoming_slots'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

    // Completed sessions (approved)
    $completed_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions 
                      WHERE teacher_id = ? AND session_status = 'approved'";
    $stmt = mysqli_prepare($conn, $completed_sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $slot_stats['completed_sessions'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

    // Pending verification
    $pending_sql = "SELECT COUNT(*) as cnt FROM teaching_sessions 
                    WHERE teacher_id = ? AND session_status = 'photo_submitted'";
    $stmt = mysqli_prepare($conn, $pending_sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $slot_stats['pending_verification'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;

    // Total unique schools from bookings
    $schools_sql = "SELECT COUNT(DISTINCT sts.school_id) as cnt FROM slot_teacher_enrollments ste
                    JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                    WHERE ste.teacher_id = ?";
    $stmt = mysqli_prepare($conn, $schools_sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $slot_stats['total_schools'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
}

// Objective exam stats (teacher-owned, not school-based)
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
      <!-- Teaching Slots Stats -->
      <div class="overview-boxes">
        <div class="box" onclick="window.location.href='my_slots.php'" style="cursor: pointer;">
          <div class="right-side">
            <div class="box-topic">Upcoming Slots</div>
            <div class="number"><?php echo $slot_stats['upcoming_slots']; ?></div>
            <div class="brief">
              <span class="text">Booked teaching slots</span>
            </div>
          </div>
          <i class='bx bx-calendar-check ico'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Schools Visited</div>
            <div class="number"><?php echo $slot_stats['total_schools']; ?></div>
            <div class="brief">
              <span class="text">Unique schools</span>
            </div>
          </div>
          <i class='bx bx-building-house ico two'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Completed Sessions</div>
            <div class="number"><?php echo $slot_stats['completed_sessions']; ?></div>
            <div class="brief">
              <span class="text">Verified sessions</span>
            </div>
          </div>
          <i class='bx bx-check-circle ico three'></i>
        </div>
        <div class="box">
          <div class="right-side">
            <div class="box-topic">Pending Review</div>
            <div class="number"><?php echo $slot_stats['pending_verification']; ?></div>
            <div class="brief">
              <span class="text">Awaiting verification</span>
            </div>
          </div>
          <i class='bx bx-time-five ico four'></i>
        </div>
      </div>

      <!-- Exam Stats Row -->
      <div class="overview-boxes" style="margin-top: 15px;">
        <div class="box" onclick="window.location.href='objective_exams.php'" style="cursor: pointer;">
          <div class="right-side">
            <div class="box-topic">Objective Exams</div>
            <div class="number"><?php echo $obj_stats['total_exams']; ?></div>
            <div class="brief">
              <span class="text">Exams created</span>
            </div>
          </div>
          <i class='bx bx-edit ico'></i>
        </div>
        <?php if ($obj_stats['pending_grading'] > 0): ?>
        <div class="box" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-left: 4px solid #ff9800; cursor: pointer;" onclick="window.location.href='grade_objective.php'">
          <div class="right-side">
            <div class="box-topic" style="color: #e65100;">Pending Grading</div>
            <div class="number" style="color: #ff9800;"><?php echo $obj_stats['pending_grading']; ?></div>
            <div class="brief">
              <span class="text" style="color: #e65100;">Needs grading</span>
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
              <span class="text" style="color: #1565c0;">Being processed</span>
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
              <span class="text" style="color: #17684f;">Completed</span>
            </div>
          </div>
          <i class='bx bx-check-double' style="color: #17684f; font-size: 48px;"></i>
        </div>
        <?php endif; ?>
      </div>

      <div class="stat-boxes">
        <div class="recent-stat box">
          <div class="title">Upcoming Teaching Sessions</div>
          <table id="res">
            <thead>
              <tr>
                <th id="res" style="width:25%">Date</th>
                <th id="res" style="width:35%">School</th>
                <th id="res" style="width:20%">Time</th>
                <th id="res" style="width:20%">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if ($slots_enabled) {
                $upcoming_sessions_sql = "SELECT sts.slot_date, sts.start_time, sts.end_time, s.school_name, ts.session_status
                                          FROM slot_teacher_enrollments ste
                                          JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                                          JOIN schools s ON sts.school_id = s.school_id
                                          LEFT JOIN teaching_sessions ts ON ts.slot_id = sts.slot_id AND ts.teacher_id = ste.teacher_id
                                          WHERE ste.teacher_id = ? AND ste.enrollment_status = 'confirmed'
                                          AND sts.slot_date >= CURDATE()
                                          ORDER BY sts.slot_date ASC, sts.start_time ASC
                                          LIMIT 5";
                $stmt = mysqli_prepare($conn, $upcoming_sessions_sql);
                mysqli_stmt_bind_param($stmt, "i", $teacher_id);
                mysqli_stmt_execute($stmt);
                $upcoming_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($upcoming_result) > 0) {
                  while ($row = mysqli_fetch_assoc($upcoming_result)) {
                    $status = $row['session_status'] ?? 'not_started';
                    $status_badge = match($status) {
                      'photo_submitted' => '<span style="background:#fff3e0;color:#e65100;padding:3px 8px;border-radius:12px;font-size:11px;">Pending</span>',
                      'approved' => '<span style="background:#e8f5e9;color:#17684f;padding:3px 8px;border-radius:12px;font-size:11px;">Approved</span>',
                      'rejected' => '<span style="background:#ffebee;color:#c62828;padding:3px 8px;border-radius:12px;font-size:11px;">Rejected</span>',
                      default => '<span style="background:#e3f2fd;color:#1565c0;padding:3px 8px;border-radius:12px;font-size:11px;">Scheduled</span>'
                    };
              ?>
                  <tr>
                    <td id="res"><?php echo date("M d, Y", strtotime($row['slot_date'])); ?></td>
                    <td id="res"><?php echo htmlspecialchars($row['school_name']); ?></td>
                    <td id="res"><?php echo date("h:i A", strtotime($row['start_time'])); ?></td>
                    <td id="res"><?php echo $status_badge; ?></td>
                  </tr>
              <?php
                  }
                } else {
                  echo '<tr><td colspan="4" style="text-align:center;color:#666;">No upcoming sessions. <a href="browse_slots.php">Browse slots</a></td></tr>';
                }
              } else {
                echo '<tr><td colspan="4" style="text-align:center;color:#666;">Teaching slots not enabled.</td></tr>';
              }
              ?>
            </tbody>
          </table>
          <div class="button" style="margin-top: 15px;">
            <a href="my_slots.php">See All Bookings</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script src="../js/script.js"></script>


</body>

</html>