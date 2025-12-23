<?php
session_start();
if (!isset($_SESSION["user_id"])) {
  header("Location: ../login_teacher.php");
  exit;
}
include '../config.php';
error_reporting(0);

// Get exam ID from POST request
$exid = mysqli_real_escape_string($conn, $_POST['exid']);
$teacher_id = $_SESSION['user_id'];

if (!$exid) {
  echo "<script>alert('No exam selected. Please try again.');</script>";
  header("Location: results.php");
  exit;
}

// Verify teacher has access to this exam's school
$verify_sql = "SELECT e.exid FROM exm_list e 
               INNER JOIN teacher_schools ts ON e.school_id = ts.school_id 
               WHERE e.exid = ? AND ts.teacher_id = ?";
$verify_stmt = mysqli_prepare($conn, $verify_sql);
mysqli_stmt_bind_param($verify_stmt, "ii", $exid, $teacher_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) == 0) {
  echo "<script>alert('You do not have access to view these analytics.');</script>";
  header("Location: results.php");
  exit;
}

// Get exam details
$exam_query = "SELECT exname, desp FROM exm_list WHERE exid = '$exid'";
$exam_result = mysqli_query($conn, $exam_query);
$exam_data = mysqli_fetch_assoc($exam_result);

// Get analytics data
$server_name = $_SERVER['SERVER_NAME'];
$server_port = $_SERVER['SERVER_PORT'] != '80' ? ':' . $_SERVER['SERVER_PORT'] : '';
$directory = dirname($_SERVER['PHP_SELF']);
$analytics_endpoint = "http://" . $server_name . $server_port . $directory . "/simple_analytics.php?exam_id=" . $exid;

// Create a log file for debugging
$debug_log = __DIR__ . '/view_analytics_debug.log';
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Fetching data from: " . $analytics_endpoint . "\n", FILE_APPEND);

// Try to get the data with error reporting
$context = stream_context_create([
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
  ],
  'http' => [
    'ignore_errors' => true,  // To get error messages from HTTP response
    'header' => 'Accept: application/json'
  ]
]);

// Get the data
try {
  $analytics_json = @file_get_contents($analytics_endpoint, false, $context);

  // Add error debugging
  if ($analytics_json === false) {
    $error_msg = error_get_last()['message'] ?? 'Unknown error';
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error fetching data: " . $error_msg . "\n", FILE_APPEND);
    $analytics_data = ['error' => 'Failed to load analytics data: ' . $error_msg];
  } else {
    // Check if we have any HTTP errors from response headers
    $response_headers = $http_response_header ?? [];
    $http_code = 0;
    foreach ($response_headers as $header) {
      if (preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#', $header, $matches)) {
        $http_code = intval($matches[1]);
      }
    }

    if ($http_code >= 400) {
      file_put_contents($debug_log, date('Y-m-d H:i:s') . " - HTTP error code: " . $http_code . "\n", FILE_APPEND);
      $analytics_data = ['error' => "HTTP error $http_code while loading analytics data."];
    } else {
      file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Raw data: " . substr($analytics_json, 0, 500) . "\n", FILE_APPEND);

      // Remove any leading or trailing whitespace or unexpected characters
      $analytics_json = trim($analytics_json);

      // Check if the response starts with a JSON object
      if (substr($analytics_json, 0, 1) !== '{' && substr($analytics_json, 0, 1) !== '[') {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Invalid JSON format - doesn't start with { or [\n", FILE_APPEND);
        // Try to find the start of the JSON
        $json_start = strpos($analytics_json, '{');
        if ($json_start !== false) {
          $analytics_json = substr($analytics_json, $json_start);
          file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Extracted JSON starting at position $json_start\n", FILE_APPEND);
        }
      }

      // Now try to decode the JSON
      $analytics_data = json_decode($analytics_json, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        $json_error = json_last_error_msg();
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - JSON decoding error: " . $json_error . "\n", FILE_APPEND);
        $analytics_data = ['error' => 'Invalid data format received: ' . $json_error];
      } else {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Successfully decoded JSON data\n", FILE_APPEND);
      }
    }
  }
} catch (Exception $e) {
  file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n", FILE_APPEND);
  $analytics_data = ['error' => 'An exception occurred: ' . $e->getMessage()];
}

?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Exam Analytics</title>
  <link rel="stylesheet" href="css/dash.css">
  <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .analytics-container {
      padding: 20px;
      background-color: #fff;
      border-radius: 8px;
      box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }

    .section-title {
      color: #0A2558;
      font-size: 1.2rem;
      margin-bottom: 15px;
      font-weight: 600;
      border-bottom: 1px solid #eee;
      padding-bottom: 8px;
    }

    .insight-item {
      margin-bottom: 15px;
      padding: 10px;
      background-color: #f9f9f9;
      border-left: 3px solid #0A2558;
      border-radius: 4px;
    }

    .chart-container {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 20px;
    }

    .chart-item {
      flex: 1 1 45%;
      min-width: 300px;
      background-color: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      padding: 15px;
      margin-bottom: 20px;
    }

    .question-summary {
      margin-bottom: 20px;
      padding: 15px;
      background-color: #f5f8ff;
      border-radius: 6px;
    }

    .correct-percentage {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-weight: bold;
    }

    .high {
      background-color: #d4edda;
      color: #155724;
    }

    .medium {
      background-color: #fff3cd;
      color: #856404;
    }

    .low {
      background-color: #f8d7da;
      color: #721c24;
    }

    .option-bar {
      height: 30px;
      margin: 5px 0;
      position: relative;
    }

    .option-percentage {
      display: block;
      height: 100%;
      border-radius: 4px;
      background-color: #cfe2ff;
      text-align: right;
      padding-right: 10px;
      line-height: 30px;
      font-size: 12px;
      color: #0A2558;
    }

    .option-percentage.correct {
      background-color: #9fc5e8;
    }

    .option-label {
      display: block;
      margin-bottom: 3px;
      font-size: 14px;
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
        <a href="results.php" class="active">
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
      </div>
      <div class="profile-details">
        <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
        <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
      </div>
    </nav>

    <div class="home-content">
      <div class="stat-boxes">
        <div class="recent-stat box" style="padding: 20px;width:100%;">
          <h2><?php echo $exam_data['exname']; ?> - Analytics</h2>
          <p><?php echo $exam_data['desp']; ?></p>

          <?php if (isset($analytics_data['error']) && strpos($analytics_data['error'], 'table does not exist') !== false): ?>
            <div class="analytics-container">
              <h3 class="section-title">Database Setup Required</h3>
              <p>Error: <?php echo $analytics_data['error']; ?></p>
              <p>The analytics tables need to be created in the database before you can view analytics.</p>
              <a href="../install_analytics_tables.php" class="btnres" style="display: inline-block; margin-top: 10px;">Install Analytics Tables</a>
            </div>
          <?php elseif (isset($analytics_data['error'])): ?>
            <div class="analytics-container">
              <p>Error: <?php echo $analytics_data['error']; ?></p>
              <p>Note: Analytics will be available once students have taken the exam and answer data has been collected.</p>
            </div>
          <?php else: ?>

            <div class="analytics-container">
              <h3 class="section-title">Question Analysis</h3>
              <?php foreach ($analytics_data['questions'] as $question): ?>
                <div class="question-summary">
                  <h4><?php echo $question['question_text']; ?></h4>

                  <?php
                  $percentage = $question['correct_percentage'];
                  $class = '';
                  if ($percentage >= 70) {
                    $class = 'high';
                  } elseif ($percentage >= 40) {
                    $class = 'medium';
                  } else {
                    $class = 'low';
                  }
                  ?>

                  <p>Correct responses: <span class="correct-percentage <?php echo $class; ?>"><?php echo $percentage; ?>%</span></p>

                  <div class="option-bars">
                    <?php foreach ($question['options'] as $index => $option): ?>
                      <div class="option-bar-container">
                        <span class="option-label">
                          Option <?php echo $index + 1; ?>: <?php echo $option['text']; ?>
                          <?php if ($option['text'] == $question['correct_option']): ?>
                            <strong>(Correct)</strong>
                          <?php endif; ?>
                        </span>
                        <div class="option-bar">
                          <span
                            class="option-percentage <?php echo ($option['text'] == $question['correct_option']) ? 'correct' : ''; ?>"
                            style="width: <?php echo $option['percentage']; ?>%">
                            <?php echo $option['percentage']; ?>%
                          </span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="analytics-container">
              <h3 class="section-title">Data Visualization</h3>
              <div class="chart-container">
                <div class="chart-item">
                  <h4>Overall Performance</h4>
                  <canvas id="performanceChart"></canvas>
                </div>
                <div class="chart-item">
                  <h4>Question Difficulty</h4>
                  <canvas id="difficultyChart"></canvas>
                </div>
              </div>
            </div>

            <script>
              document.addEventListener('DOMContentLoaded', function() {
                console.log("DOM Content Loaded");

                <?php if (!isset($analytics_data['error']) && isset($analytics_data['questions']) && count($analytics_data['questions']) > 0): ?>
                  try {
                    // Chart 1: Overall Performance
                    var performanceCtx = document.getElementById('performanceChart');
                    if (!performanceCtx) {
                      console.error("performanceChart canvas element not found");
                      return;
                    }

                    console.log("Chart data available");

                    var performanceData = {
                      labels: [<?php
                                $labels = array_map(function ($q) {
                                  return '"Q' . $q['question_id'] . '"';
                                }, $analytics_data['questions']);
                                echo implode(", ", $labels);
                                ?>],
                      datasets: [{
                        label: 'Correct Answers (%)',
                        data: [<?php
                                $percentages = array_map(function ($q) {
                                  return $q['correct_percentage'];
                                }, $analytics_data['questions']);
                                echo implode(", ", $percentages);
                                ?>],
                        backgroundColor: '#0A2558',
                        borderColor: '#0A2558',
                        borderWidth: 1
                      }]
                    };

                    console.log("Performance data prepared");

                    new Chart(performanceCtx.getContext('2d'), {
                      type: 'bar',
                      data: performanceData,
                      options: {
                        scales: {
                          y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                              display: true,
                              text: 'Percentage of Correct Answers'
                            }
                          },
                          x: {
                            title: {
                              display: true,
                              text: 'Questions'
                            }
                          }
                        }
                      }
                    });

                    // Chart 2: Question Difficulty
                    var difficultyCtx = document.getElementById('difficultyChart');
                    if (!difficultyCtx) {
                      console.error("difficultyChart canvas element not found");
                      return;
                    }

                    // Categorize questions by difficulty
                    var easy = 0;
                    var medium = 0;
                    var hard = 0;

                    <?php foreach ($analytics_data['questions'] as $question): ?>
                      var percentage = <?php echo $question['correct_percentage']; ?>;
                      if (percentage >= 70) {
                        easy++;
                      } else if (percentage >= 40) {
                        medium++;
                      } else {
                        hard++;
                      }
                    <?php endforeach; ?>

                    var difficultyData = {
                      labels: ['Easy (>70%)', 'Medium (40-70%)', 'Hard (<40%)'],
                      datasets: [{
                        data: [easy, medium, hard],
                        backgroundColor: ['#42A5F5', '#FFA726', '#EF5350'],
                        hoverOffset: 4
                      }]
                    };

                    console.log("Difficulty data prepared");

                    new Chart(difficultyCtx.getContext('2d'), {
                      type: 'doughnut',
                      data: difficultyData,
                      options: {
                        plugins: {
                          legend: {
                            position: 'bottom'
                          }
                        }
                      }
                    });
                  } catch (error) {
                    console.error("Error in chart initialization:", error);
                  }
                <?php else: ?>
                  console.log("No chart data available, skipping chart rendering");
                <?php endif; ?>
              });
            </script>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <script src="../js/script.js"></script>
</body>

</html>