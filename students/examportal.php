<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["uname"])) {
  header("Location: ../login_student.php");
  exit;
}

include '../config.php';
require_once '../utils/school_access_control.php';
error_reporting(0);
$exid = mysqli_real_escape_string($conn, $_POST['exid']);
$school_id = $_SESSION['school_id'] ?? 0;
$uname = $_SESSION['uname'];

if (!isset($_POST["edit_btn"])) {
  header("Location: exams.php");
  exit;
}

// Validate student has access to this exam's school
if (!validateStudentExamAccess($conn, $uname, $exid)) {
  denyAccess("Access denied: This exam does not belong to your school.", "exams.php");
}

if (isset($_POST["edit_btn"])) {
  $sql = "SELECT * FROM exm_list WHERE exid='$exid' AND school_id='$school_id'";
  $result = mysqli_query($conn, $sql);
  $row = mysqli_fetch_assoc($result);
  $ogtime = $row['extime'];
  $subt = $row['subt'];
  $cmtime = date("Y-m-d H:i:s");

  $letters = array('-', ' ', ':');
  $ogtime = str_replace($letters, '', $ogtime);
  $cmtime = str_replace($letters, '', $cmtime);
  if ($ogtime > $cmtime) {
    header("Location: exams.php");
  }
  if ($cmtime > $subt) {
    echo "<script>st();</script>";
  }
}



$sql = "SELECT qid, qstn, qstn_o1, qstn_o2, qstn_o3, qstn_o4 FROM qstn_list WHERE exid='$exid'";
$result = mysqli_query($conn, $sql);

$details = "SELECT * FROM exm_list WHERE exid='$exid'";
$res = mysqli_query($conn, $details);
while ($rowd = mysqli_fetch_array($res)) {
  $nq = $rowd['nq'];
  $exname = $rowd['exname'];
  $desp = $rowd['desp'];
  $duration = $rowd['duration']; // Get the test duration
}


?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Exams</title>
  <link rel="stylesheet" href="css/dash.css">
  <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Styles for question navigation */
    .navigation-buttons {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 20px 0;
      width: 100%;
    }

    .nav-btn {
      padding: 8px 15px;
      background-color: #0A2558;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .nav-btn:hover {
      background-color: #0d3a80;
    }

    .nav-btn:disabled {
      background-color: #cccccc;
      cursor: not-allowed;
    }

    #question-number {
      font-size: 16px;
      font-weight: bold;
    }

    .question {
      min-height: 250px;
      padding: 10px;
      border-radius: 5px;
    }

    /* Question Navigation Box */
    .question-nav-box {
      position: fixed;
      top: 100px;
      right: 20px;
      width: 200px;
      border: 1px solid #ddd;
      border-radius: 5px;
      padding: 15px;
      margin-bottom: 20px;
      background-color: #f8f9fa;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
      z-index: 100;
    }

    .question-nav-title {
      font-weight: bold;
      margin-bottom: 10px;
      font-size: 14px;
      text-align: center;
      border-bottom: 1px solid #ddd;
      padding-bottom: 8px;
    }

    .question-number-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
    }

    .question-number-btn {
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
      background-color: #f8d7da;
      /* Red for unattempted */
      color: #721c24;
      cursor: pointer;
      text-align: center;
      font-size: 14px;
      font-weight: bold;
      transition: all 0.2s ease;
    }

    .question-number-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .question-number-btn.current {
      border: 2px solid #0A2558;
    }

    .question-number-btn.attempted {
      background-color: #d4edda;
      /* Green for attempted */
      color: #155724;
    }

    /* Status indicators */
    .status-indicators {
      display: flex;
      justify-content: space-between;
      margin-top: 15px;
      font-size: 12px;
      border-top: 1px solid #ddd;
      padding-top: 8px;
    }

    .status-indicator {
      display: flex;
      align-items: center;
    }

    .status-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      margin-right: 5px;
    }

    .status-dot.unattempted {
      background-color: #f8d7da;
      border: 1px solid #721c24;
    }

    .status-dot.attempted {
      background-color: #d4edda;
      border: 1px solid #155724;
    }
  </style>
  <?php
  $td = $subt;
  ?>
  <script type="text/javascript">
    function st() {
      document.getElementById("form1").submit();
    }
    //set the date we are counting down to 
    var count_id = "<?php echo $td; ?>";
    var countDownDate = new Date(count_id).getTime();

    // Get the test duration in minutes and convert to milliseconds
    var testDuration = <?php echo $duration; ?> * 60 * 1000;
    var startTime = new Date().getTime();
    var endTime = startTime + testDuration;

    // Use the smaller of the two times: either submission deadline or test duration
    var effectiveEndTime = Math.min(countDownDate, endTime);

    // Function to auto-submit the test when time expires
    function autoSubmitTest() {
      // Simply submit the form with current answers without any alert
      document.getElementById("form1").submit();
    }

    //Update the count down every 1 second 
    var x = setInterval(function() {
      //Get today's date and time 
      var now = new Date().getTime();
      //Find the distance between now and the effective end time
      var distance = effectiveEndTime - now;

      if (distance <= 0) {
        // Clear the interval
        clearInterval(x);
        // Update timer display to show 0
        document.getElementById("time").innerHTML = "Timer: 0h 0m 0s";
        // Auto-submit the test
        autoSubmitTest();
        return;
      }

      //Time calculations for hours, minutes and seconds 
      var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      var seconds = Math.floor((distance % (1000 * 60)) / 1000);
      document.getElementById("time").innerHTML = "Timer: " + hours + "h " + minutes + "m " + seconds + "s";

      // If approaching end of time (last 60 seconds), flash the timer
      if (distance < 60000) {
        document.getElementById("time").style.color = distance % 1000 < 500 ? "#dc3545" : "#0A2558";
      }
    }, 1000);
  </script>

  <!-- Anti-cheat system: Phase 1 - Tab Switching Detection -->
  <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
      // Get exam ID from hidden field
      const examId = document.querySelector('input[name="exid"]').value;
      let warningShown = false;
      let examInitialized = false; // Add flag to track if exam is initialized
      let isFullScreen = false;
      let currentIntegrityScore = 100; // Initial integrity score
      let currentIntegrityCategory = 'Good'; // Initial category

      // Create integrity score display container
      const integrityContainer = document.createElement('div');
      integrityContainer.id = 'integrity-score-display';
      integrityContainer.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: #f8f9fa;
        padding: 10px 15px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        font-family: Arial, sans-serif;
        z-index: 1000;
        border-left: 4px solid #28a745;
      `;
      integrityContainer.innerHTML = `
        <div style="font-weight: bold; margin-bottom: 5px;">Integrity Monitor</div>
        <div>Score: <span id="integrity-score-value" style="font-weight: bold; color: #28a745;">100/100</span></div>
        <div>Status: <span id="integrity-category" style="font-weight: bold; color: #28a745;">Good</span></div>
      `;
      document.body.appendChild(integrityContainer);

      // Function to update integrity score display
      function updateIntegrityDisplay(score, category) {
        currentIntegrityScore = score;
        currentIntegrityCategory = category;

        const scoreElement = document.getElementById('integrity-score-value');
        const categoryElement = document.getElementById('integrity-category');

        scoreElement.textContent = `${score}/100`;
        categoryElement.textContent = category;

        // Update colors based on category
        let categoryColor, borderColor;
        if (category === 'Good') {
          categoryColor = '#28a745'; // green
          borderColor = '#28a745';
        } else if (category === 'At-Risk') {
          categoryColor = '#ffc107'; // yellow
          borderColor = '#ffc107';
        } else {
          categoryColor = '#dc3545'; // red
          borderColor = '#dc3545';
        }

        scoreElement.style.color = categoryColor;
        categoryElement.style.color = categoryColor;
        integrityContainer.style.borderLeft = `4px solid ${borderColor}`;
      }

      // Initialize anti-cheat warning container
      const warningContainer = document.createElement('div');
      warningContainer.id = 'anti-cheat-warning';
      warningContainer.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 5px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        z-index: 1000;
        max-width: 300px;
        display: none;
        font-weight: bold;
      `;
      document.body.appendChild(warningContainer);

      // Create full screen button and container
      const fullScreenContainer = document.createElement('div');
      fullScreenContainer.id = 'fullscreen-prompt';
      fullScreenContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.9);
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
      `;

      // Create test info modal that appears before full screen prompt
      const testInfoModal = document.createElement('div');
      testInfoModal.id = 'test-info-modal';
      testInfoModal.style.cssText = `
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background-color: rgba(0,0,0,0.9);
          display: flex;
          justify-content: center;
          align-items: center;
          z-index: 10000;
      `;

      const modalContent = document.createElement('div');
      modalContent.style.cssText = `
          background-color: white;
          border-radius: 8px;
          padding: 30px;
          max-width: 600px;
          width: 90%;
          box-shadow: 0 5px 15px rgba(0,0,0,0.3);
          text-align: center;
      `;

      modalContent.innerHTML = `
          <h2 style="color: #0A2558; margin-bottom: 20px; font-size: 24px;">Exam Information</h2>
          <div style="text-align: left; margin-bottom: 25px;">
              <p style="margin-bottom: 10px; font-size: 16px;"><strong>Exam Name:</strong> <?php echo $exname; ?></p>
              <p style="margin-bottom: 10px; font-size: 16px;"><strong>Subject:</strong> <?php echo $desp; ?></p>
              <p style="margin-bottom: 10px; font-size: 16px;"><strong>Number of Questions:</strong> <?php echo $nq; ?></p>
              <p style="margin-bottom: 10px; font-size: 16px;"><strong>Duration:</strong> <?php echo $duration; ?> minutes</p>
          </div>
          
          <h3 style="color: #dc3545; margin-bottom: 15px; font-size: 18px;">Important: Anti-Cheat System</h3>
          <div style="text-align: left; margin-bottom: 25px; background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;">
              <p style="margin-bottom: 10px; font-size: 15px;">This exam employs the following integrity monitoring features:</p>
              <ul style="margin-left: 20px; margin-bottom: 15px;">
                  <li style="margin-bottom: 8px; font-size: 15px;">Full-screen mode is required throughout the exam</li>
                  <li style="margin-bottom: 8px; font-size: 15px;">Tab switching detection</li>
                  <li style="margin-bottom: 8px; font-size: 15px;">Window focus/blur monitoring</li>
                  <li style="margin-bottom: 8px; font-size: 15px;">Real-time integrity score calculation</li>
              </ul>
              <p style="font-size: 15px; color: #dc3545; font-weight: bold;">Any attempt to exit full-screen mode, switch tabs, or use other applications during the exam will lower your integrity score and may result in automatic test submission.</p>
          </div>
          
          <button id="start-test-btn" style="
              background-color: #0A2558;
              color: white;
              border: none;
              padding: 12px 24px;
              font-size: 18px;
              border-radius: 5px;
              cursor: pointer;
              font-weight: bold;
              margin-top: 10px;
          ">I Understand & Start Exam</button>
      `;

      testInfoModal.appendChild(modalContent);
      document.body.appendChild(testInfoModal);

      // Start test button event handler
      document.getElementById('start-test-btn').addEventListener('click', function() {
        testInfoModal.style.display = 'none';
        // Enter full screen mode immediately instead of showing the prompt
        requestFullScreen();
      });

      // Make sure test info modal is displayed when page loads
      testInfoModal.style.display = 'flex';
      fullScreenContainer.style.display = 'none';

      const fullScreenMessage = document.createElement('div');
      fullScreenMessage.style.cssText = `
        color: white;
        font-size: 24px;
        margin-bottom: 20px;
        text-align: center;
        max-width: 600px;
      `;
      fullScreenMessage.innerHTML = '<strong>⚠️ EXAM SECURITY NOTICE</strong><br><br>This exam requires full screen mode to maintain integrity.<br>Please click the button below to enter full screen mode.';

      const fullScreenButton = document.createElement('button');
      fullScreenButton.textContent = 'Enter Full Screen Mode';
      fullScreenButton.style.cssText = `
        background-color: #0A2558;
        color: white;
        border: none;
        padding: 12px 24px;
        font-size: 18px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
      `;

      fullScreenContainer.appendChild(fullScreenMessage);
      fullScreenContainer.appendChild(fullScreenButton);
      document.body.appendChild(fullScreenContainer);

      // Function to request full screen
      function requestFullScreen() {
        const element = document.documentElement;

        if (element.requestFullscreen) {
          element.requestFullscreen();
        } else if (element.mozRequestFullScreen) { // Firefox
          element.mozRequestFullScreen();
        } else if (element.webkitRequestFullscreen) { // Chrome, Safari and Opera
          element.webkitRequestFullscreen();
        } else if (element.msRequestFullscreen) { // IE/Edge
          element.msRequestFullscreen();
        }

        isFullScreen = true;
        examInitialized = true; // Set exam as initialized when entering fullscreen
        fullScreenContainer.style.display = 'none';
        showWarning('Full screen mode activated. Do not exit full screen during the exam.');
      }

      // Function to exit full screen
      function exitFullScreen() {
        if (document.exitFullscreen) {
          document.exitFullscreen();
        } else if (document.mozCancelFullScreen) { // Firefox
          document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) { // Chrome, Safari and Opera
          document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) { // IE/Edge
          document.msExitFullscreen();
        }

        isFullScreen = false;
      }

      // Event listener for full screen button
      fullScreenButton.addEventListener('click', requestFullScreen);

      // Function to check if browser is in full screen mode
      function isInFullScreen() {
        return (
          document.fullscreenElement ||
          document.webkitFullscreenElement ||
          document.mozFullScreenElement ||
          document.msFullscreenElement
        );
      }

      // Listen for fullscreen change events
      document.addEventListener('fullscreenchange', handleFullScreenChange);
      document.addEventListener('webkitfullscreenchange', handleFullScreenChange);
      document.addEventListener('mozfullscreenchange', handleFullScreenChange);
      document.addEventListener('MSFullscreenChange', handleFullScreenChange);

      function handleFullScreenChange() {
        if (examInitialized && !isInFullScreen()) {
          showWarning('⚠️ WARNING: You exited full screen mode! This may be flagged as suspicious behavior.');
          fullScreenContainer.style.display = 'flex';
        } else if (isInFullScreen()) {
          fullScreenContainer.style.display = 'none';
        }
      }

      // Function to show warning message
      function showWarning(message) {
        warningContainer.innerHTML = message;
        warningContainer.style.display = 'block';
        warningShown = true;

        // Hide warning after 5 seconds
        setTimeout(() => {
          warningContainer.style.display = 'none';
          warningShown = false;
        }, 5000);
      }

      // Phase 3: Variables to track combined violations
      let lastViolationTime = 0;
      let lastViolationType = null;

      // Track visibility change events
      document.addEventListener('visibilitychange', function() {
        if (examInitialized && document.visibilityState === 'hidden') {
          // User switched tabs or minimized window
          checkCombinedViolation('tab_switch');
          logViolation('tab_switch');
        }
      });

      // Phase 2: Track window blur/focus events
      let blurTime = null;
      let isBlurred = false;

      window.addEventListener('blur', function() {
        if (examInitialized && !isBlurred) {
          isBlurred = true;
          blurTime = new Date().getTime();
          // Log window blur violation
          checkCombinedViolation('window_blur');
          logViolation('window_blur');
        }
      });

      window.addEventListener('focus', function() {
        if (examInitialized && isBlurred) {
          isBlurred = false;
          // Calculate the time spent outside the window
          const focusTime = new Date().getTime();
          const timeSpentOutside = focusTime - blurTime;

          // If time spent outside is very short (less than 1 second),
          // it could be a false positive, so we don't need to do anything

          // If time spent outside is significant (more than 5 seconds),
          // it could be considered as an app switching attempt (will be part of Phase 3)
          if (timeSpentOutside > 5000) {
            // This will be used in Phase 3 for app switching detection
            console.log('Potential app switching detected:', timeSpentOutside / 1000, 'seconds');
          }
        }
      });

      // Function to check for combined violations (Phase 3)
      function checkCombinedViolation(currentViolationType) {
        const now = new Date().getTime();

        // If we had a different violation type within the last 2 seconds
        if (lastViolationType &&
          lastViolationType !== currentViolationType &&
          now - lastViolationTime < 2000) {

          // Log a combined violation
          logViolation('combined');
          console.log('Combined violation detected: ' + lastViolationType + ' and ' + currentViolationType + ' within 2 seconds');
        }

        // Update the last violation info
        lastViolationTime = now;
        lastViolationType = currentViolationType;
      }

      // Function to log violations - updated to handle different violation types
      function logViolation(violationType) {
        fetch('log_violation.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              exam_id: examId,
              violation_type: violationType
            })
          })
          .then(response => response.json())
          .then(data => {
            if (data.status === 'success') {
              // Update the integrity score display
              updateIntegrityDisplay(data.integrity_score, data.integrity_category);

              // Force submit if integrity score falls below threshold
              if (data.integrity_score < 20) {
                showWarning(`
                  <strong>⚠️ CRITICAL INTEGRITY VIOLATION!</strong><br>
                  Your integrity score has fallen below the minimum threshold.<br>
                  Your exam will be automatically submitted in 1 second.
                `);

                setTimeout(() => {
                  alert("Your exam is being submitted due to critical integrity violations. Your final integrity score is: " + data.integrity_score);
                  document.getElementById("form1").submit();
                }, 1000);

                return; // Skip showing regular violation warning
              }

              let violationTypeText = '';
              switch (violationType) {
                case 'tab_switch':
                  violationTypeText = 'Tab switching';
                  break;
                case 'window_blur':
                  violationTypeText = 'Window focus loss';
                  break;
                case 'combined':
                  violationTypeText = 'Combined violations';
                  break;
                default:
                  violationTypeText = violationType;
              }

              let warningMessage = `
              <strong>⚠️ WARNING: ${violationTypeText} detected!</strong><br>
              Attempt #${data.occurrence}<br>
              Penalty: -${data.penalty} points<br>
              Current integrity score: ${data.integrity_score} (${data.integrity_category})
            `;
              showWarning(warningMessage);

              // If integrity score is in the "Cheating Suspicion" range, notify
              if (data.integrity_category === 'Cheating Suspicion' && !warningShown) {
                alert('WARNING: Your integrity score is critically low due to suspected cheating behavior. Your exam may be disqualified for review.');
              }

              // For combined violations, notify of mandatory review
              if (violationType === 'combined') {
                console.warn('NOTICE: Combined violations trigger mandatory manual review by instructors');
              }
            }
          })
          .catch(error => {
            console.error('Error logging violation:', error);
          });
      }

      // Display initial anti-cheat notification and show full screen prompt
      // Remove the alert message
      // alert('⚠️ ANTI-CHEAT SYSTEM ACTIVATED: Tab switching, window focus loss, and other suspicious activities will be detected and penalized. Combined violations will trigger mandatory review. Your integrity score starts at 100 points.');

      // Set a small delay before enabling the violation tracking
      setTimeout(() => {
        examInitialized = true;
      }, 2000); // 2 second delay to allow page to fully load and settle
    });
  </script>

</head>

<body>
  <div class="sidebar active">
    <div class="logo-details">
      <i class='bx bx-diamond'></i>
      <span class="logo_name">Welcome</span>
    </div>
    <ul class="nav-links">
      <li>
        <a>
          <i class='bx bx-grid-alt'></i>
          <span class="links_name">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="exams.php" class="active">
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
        <a>
          <i class='bx bx-message'></i>
          <span class="links_name">Announcements</span>
        </a>
      </li>
      <li>
        <a>
          <i class='bx bx-cog'></i>
          <span class="links_name">Settings</span>
        </a>
      </li>
      <li>
        <a>
          <i class='bx bx-help-circle'></i>
          <span class="links_name">Help</span>
        </a>
      </li>
      <li class="log_out">
        <a>
          <i class='bx bx-log-out-circle'></i>
          <span class="links_name">Log out</span>
        </a>
      </li>
    </ul>
  </div>

  <!-- Question Navigation Box - Outside the main container, fixed on the right -->
  <div class="question-nav-box">
    <div class="question-nav-title">Question Navigation</div>
    <div class="question-number-grid" id="question-nav-grid">
      <!-- Question number buttons will be generated by JavaScript -->
    </div>
    <div class="status-indicators">
      <div class="status-indicator">
        <div class="status-dot unattempted"></div>
        <span>Unattempted</span>
      </div>
      <div class="status-indicator">
        <div class="status-dot attempted"></div>
        <span>Attempted</span>
      </div>
    </div>
  </div>

  <section class="home-section">
    <nav>
      <div class="sidebar-button">
        <i class='bx bx-menu-alt-right sidebarBtn'></i>
        <span class="dashboard">Student Dashboard</span>
      </div>
    </nav>

    <div class="home-content">
      <div class="stat-boxes">
        <div class="recent-stat box">
          <div>
            <h3>Exam name: <?php echo $exname ?><?php echo '
          <p id="time"style="float:right"></p>'; ?></h3>
          </div>
          <span style="font-size: 17px;">Description: <?php echo $desp ?></span>
          <br><br><br>
          <form action="submit.php" id="form1" method="post">
            <div class="radio-container">
              <?php

              if (mysqli_num_rows($result) > 0) {
                $i = 1;
                $questions = array();
                while ($row = mysqli_fetch_assoc($result)) {
                  $questions[] = $row;
                  echo '<input type="hidden" name="qid' . $i . '" value="' . $row['qid'] . '">';
                  $i++;
                }
                $totalQuestions = count($questions);
              ?>

                <div id="question-container">
                  <!-- Questions will be displayed here by JavaScript -->
                </div>

                <div class="navigation-buttons">
                  <button type="button" id="prev-btn" class="nav-btn" disabled>Previous</button>
                  <span id="question-number">Question 1 of <?php echo $totalQuestions; ?></span>
                  <button type="button" id="next-btn" class="nav-btn">Next</button>
                </div>

              <?php
              }
              ?>
            </div>
            <input type="hidden" name="exid" value="<?php echo $exid ?>">
            <input type="hidden" name="nq" value="<?php echo $nq ?>">
            <button type="reset" id="reset-btn" class="rbtn">Reset current</button>
            <br><br>
            <input type="submit" name="ans_sub" value="Submit" class="btn" />
            <!-- <button type="submit" name="ans_sub" class="btn">Submit</button>     -->
          </form>
        </div>
      </div>
    </div>
  </section>

  <script>
    // Store questions data from PHP to JavaScript
    const questions = <?php echo json_encode($questions); ?>;
    const totalQuestions = <?php echo $totalQuestions; ?>;
    let currentQuestion = 0;

    // DOM elements
    const questionContainer = document.getElementById('question-container');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const questionNumber = document.getElementById('question-number');
    const resetBtn = document.getElementById('reset-btn');
    const questionNavGrid = document.getElementById('question-nav-grid');

    // Track attempted questions
    const attemptedQuestions = new Set();

    // Generate question navigation grid
    function generateQuestionNavGrid() {
      questionNavGrid.innerHTML = '';

      for (let i = 0; i < totalQuestions; i++) {
        const qNum = i + 1;
        const btn = document.createElement('div');
        btn.className = 'question-number-btn';
        if (attemptedQuestions.has(qNum)) {
          btn.classList.add('attempted');
        }
        if (currentQuestion === i) {
          btn.classList.add('current');
        }
        btn.textContent = qNum;
        btn.dataset.question = i;

        btn.addEventListener('click', function() {
          currentQuestion = parseInt(this.dataset.question);
          displayQuestion();
        });

        questionNavGrid.appendChild(btn);
      }
    }

    // Function to display current question
    function displayQuestion() {
      const q = questions[currentQuestion];
      const qNum = currentQuestion + 1;

      // Update question number display
      questionNumber.textContent = `Question ${qNum} of ${totalQuestions}`;

      // Enable/disable navigation buttons as needed
      prevBtn.disabled = currentQuestion === 0;
      nextBtn.disabled = currentQuestion === totalQuestions - 1;
      nextBtn.textContent = currentQuestion === totalQuestions - 1 ? "Review" : "Next";

      // Generate question HTML
      let questionHTML = `
        <div class="question" data-index="${qNum}">
          <span><b>Q${qNum}. ${q.qstn}</b></span><br><br>
          
          <input type="radio" id="o1${qNum}" name="o${qNum}" value="${q.qstn_o1}" />
          <label class="lbl" for="o1${qNum}">${q.qstn_o1}</label><br>
          
          <input type="radio" id="o2${qNum}" name="o${qNum}" value="${q.qstn_o2}" />
          <label class="lbl" for="o2${qNum}">${q.qstn_o2}</label><br>
          
          <input type="radio" id="o3${qNum}" name="o${qNum}" value="${q.qstn_o3}" />
          <label class="lbl" for="o3${qNum}">${q.qstn_o3}</label><br>
          
          <input type="radio" id="o4${qNum}" name="o${qNum}" value="${q.qstn_o4}" />
          <label class="lbl" for="o4${qNum}">${q.qstn_o4}</label><br>
        </div>
      `;

      questionContainer.innerHTML = questionHTML;

      // Check if this question was previously attempted
      if (attemptedQuestions.has(qNum)) {
        // Check if there's a hidden input field for this question
        const hiddenInput = document.querySelector(`input[name="stored_ans_${qNum}"]`);
        if (hiddenInput) {
          const savedValue = hiddenInput.value;
          const radioBtn = document.querySelector(`input[name="o${qNum}"][value="${savedValue}"]`);
          if (radioBtn) radioBtn.checked = true;
        }
      }

      // Add event listeners to save answers
      const radioButtons = document.querySelectorAll(`input[name="o${qNum}"]`);
      radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
          attemptedQuestions.add(qNum);

          // Store the selected answer in a hidden input field
          let hiddenInput = document.querySelector(`input[name="stored_ans_${qNum}"]`);
          if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = `stored_ans_${qNum}`;
            document.getElementById('form1').appendChild(hiddenInput);
          }
          hiddenInput.value = this.value;

          generateQuestionNavGrid(); // Update the navigation grid
        });
      });

      // Update question navigation grid
      generateQuestionNavGrid();
    }

    // Event listeners for navigation
    prevBtn.addEventListener('click', () => {
      if (currentQuestion > 0) {
        currentQuestion--;
        displayQuestion();
      }
    });

    nextBtn.addEventListener('click', () => {
      if (currentQuestion < totalQuestions - 1) {
        currentQuestion++;
        displayQuestion();
      } else {
        // If on last question, show a summary or allow submission
        alert("You've reached the last question. Review your answers before submitting.");
      }
    });

    // Reset button functionality
    resetBtn.addEventListener('click', function() {
      const qNum = currentQuestion + 1;
      const radioButtons = document.querySelectorAll(`input[name="o${qNum}"]`);
      radioButtons.forEach(radio => radio.checked = false);
      attemptedQuestions.delete(qNum);
      generateQuestionNavGrid(); // Update the navigation grid
    });

    // Initialize - show first question
    const exid = <?php echo $exid; ?>;
    displayQuestion();
    generateQuestionNavGrid();

    // Handle form submission
    document.getElementById('form1').addEventListener('submit', function(e) {
      // Before submitting, transfer all stored answers to the actual form inputs
      for (let i = 1; i <= totalQuestions; i++) {
        const storedAnswer = document.querySelector(`input[name="stored_ans_${i}"]`);
        if (storedAnswer) {
          // Create or update the actual form input that submit.php expects
          let formInput = document.querySelector(`input[name="o${i}"]`);
          if (!formInput) {
            formInput = document.createElement('input');
            formInput.type = 'hidden';
            formInput.name = `o${i}`;
            this.appendChild(formInput);
          }
          formInput.value = storedAnswer.value;
        }
      }
      // Now submit the form with all answers
    });

    // Original timer code
    var inputs = document.querySelectorAll("input[type=radio]:checked"),
      x = inputs.length;

    // Function to submit the form when timer expires
    function st() {
      document.getElementById("form1").submit();
    }
  </script>
</body>

</html>