<?php
session_start();
if (!isset($_SESSION["fname"])) {
  header("Location: ../login_teacher.php");
}
include '../config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$teacher_id = $_SESSION['user_id'];

// Get schools where the teacher has booked teaching slots
$sql = "SELECT DISTINCT 
          s.school_id,
          s.school_name,
          COUNT(DISTINCT ste.enrollment_id) as total_bookings,
          COUNT(DISTINCT CASE WHEN ste.enrollment_status = 'booked' THEN ste.enrollment_id END) as active_bookings
        FROM schools s
        INNER JOIN school_teaching_slots sts ON s.school_id = sts.school_id
        INNER JOIN slot_teacher_enrollments ste ON sts.slot_id = ste.slot_id
        WHERE ste.teacher_id = ?
        GROUP BY s.school_id, s.school_name
        ORDER BY s.school_name";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
  die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$schools_result = mysqli_stmt_get_result($stmt);
$booked_schools = [];
while ($school = mysqli_fetch_assoc($schools_result)) {
  $booked_schools[] = $school;
}

// Get students from schools where teacher has bookings
$students = [];
if (!empty($booked_schools)) {
  $school_ids = array_column($booked_schools, 'school_id');
  $placeholders = implode(',', array_fill(0, count($school_ids), '?'));
  
  $student_sql = "SELECT 
                    st.*,
                    sch.school_name,
                    COUNT(DISTINCT al.exid) as total_exams_taken,
                    AVG(al.ptg) as avg_marks
                  FROM student st
                  INNER JOIN schools sch ON st.school_id = sch.school_id
                  LEFT JOIN atmpt_list al ON st.uname = al.uname
                  WHERE st.school_id IN ($placeholders)
                  GROUP BY st.id
                  ORDER BY sch.school_name, st.fname";
  
  $student_stmt = mysqli_prepare($conn, $student_sql);
  if (!$student_stmt) {
    die("Prepare failed: " . mysqli_error($conn));
  }
  $types = str_repeat('i', count($school_ids));
  mysqli_stmt_bind_param($student_stmt, $types, ...$school_ids);
  mysqli_stmt_execute($student_stmt);
  $students_result = mysqli_stmt_get_result($student_stmt);
  
  while ($student = mysqli_fetch_assoc($students_result)) {
    $students[] = $student;
  }
}

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Student Records - Booked Schools</title>
  <link rel="stylesheet" href="css/dash.css">
  <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .school-filter {
      margin: 20px 0;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    .school-filter select {
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #ddd;
      font-size: 14px;
      min-width: 250px;
    }
    .stats-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
    }
    .stat-card h3 {
      margin: 0;
      font-size: 32px;
      font-weight: bold;
    }
    .stat-card p {
      margin: 5px 0 0 0;
      opacity: 0.9;
    }
    .info-message {
      background: #e3f2fd;
      border-left: 4px solid #2196f3;
      padding: 15px;
      margin: 20px 0;
      border-radius: 5px;
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
        <a href="upload_material.php">
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
        <span class="dashboard">Student Records - My Booked Schools</span>
      </div>
      <div class="profile-details">
        <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
        <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
      </div>
    </nav>

    <div class="home-content">
      <?php if (empty($booked_schools)): ?>
        <div class="info-message">
          <h3>No Teaching Slots Booked Yet</h3>
          <p>You haven't booked any teaching slots yet. Once you book slots at schools, you'll be able to view the students from those schools here.</p>
          <p><a href="browse_slots.php" style="color: #2196f3; text-decoration: underline;">Browse Available Teaching Slots</a></p>
        </div>
      <?php else: ?>
        
        <div class="stats-summary">
          <div class="stat-card">
            <h3><?php echo count($booked_schools); ?></h3>
            <p>Schools with Bookings</p>
          </div>
          <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <h3><?php echo count($students); ?></h3>
            <p>Total Students</p>
          </div>
          <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <h3><?php echo array_sum(array_column($booked_schools, 'active_bookings')); ?></h3>
            <p>Active Bookings</p>
          </div>
        </div>

        <div class="school-filter">
          <label for="schoolFilter"><strong>Filter by School:</strong></label>
          <select id="schoolFilter" onchange="filterBySchool()">
            <option value="">All Schools</option>
            <?php foreach ($booked_schools as $school): ?>
              <option value="<?php echo $school['school_id']; ?>">
                <?php echo htmlspecialchars($school['school_name']); ?> 
                (<?php echo $school['active_bookings']; ?> active bookings)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="stat-boxes">
          <div class="recent-stat box" style="padding: 0px 0px; width: 100%;">
            <div class="title">Students from My Booked Schools</div>
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Full Name</th>
                  <th>Username</th>
                  <th>School</th>
                  <th>Email</th>
                  <th>Gender</th>
                  <th>DOB</th>
                  <th>Exams Taken</th>
                  <th>Avg Score</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if (!empty($students)) {
                  foreach ($students as $student) {
                ?>
                    <tr data-school-id="<?php echo $student['school_id']; ?>">
                      <td><?php echo $student['id']; ?></td>
                      <td><?php echo htmlspecialchars($student['fname']); ?></td>
                      <td><?php echo htmlspecialchars($student['uname']); ?></td>
                      <td><?php echo htmlspecialchars($student['school_name']); ?></td>
                      <td><?php echo htmlspecialchars($student['email']); ?></td>
                      <td><?php echo $student['gender']; ?></td>
                      <td><?php echo $student['dob']; ?></td>
                      <td><?php echo $student['total_exams_taken']; ?></td>
                      <td><?php echo $student['avg_marks'] ? round($student['avg_marks'], 1) . '%' : 'N/A'; ?></td>
                    </tr>
                <?php
                  }
                } else {
                  echo '<tr><td colspan="9" style="text-align:center;">No students found</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <script src="../js/script.js"></script>
  <script>
    function filterBySchool() {
      const select = document.getElementById('schoolFilter');
      const selectedSchool = select.value;
      const rows = document.querySelectorAll('tbody tr[data-school-id]');
      
      rows.forEach(row => {
        if (selectedSchool === '' || row.getAttribute('data-school-id') === selectedSchool) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }
  </script>
</body>

</html>