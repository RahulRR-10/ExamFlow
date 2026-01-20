<?php
session_start();
if (!isset($_SESSION["uname"])) {
    header("Location: ../login_student.php");
    exit;
}

include '../config.php';
require_once '../utils/school_access_control.php';
error_reporting(0);

// Check if we have the required parameters
if (!isset($_GET['mock_exid']) || !isset($_GET['attempt_id'])) {
    header("Location: mock_exams.php");
    exit;
}

$mock_exid = mysqli_real_escape_string($conn, $_GET['mock_exid']);
$attempt_id = mysqli_real_escape_string($conn, $_GET['attempt_id']);
$uname = $_SESSION['uname'];
$school_id = $_SESSION['school_id'] ?? 0;

// Validate student has access to this mock exam's school
if (!validateStudentMockExamAccess($conn, $uname, $mock_exid)) {
    denyAccess("Access denied: This mock exam does not belong to your school.", "mock_exams.php");
}

// Get the exam attempt details
$sql = "SELECT ma.*, me.exname, me.subject 
        FROM mock_atmpt_list ma 
        JOIN mock_exm_list me ON ma.mock_exid = me.mock_exid 
        WHERE ma.id = ? AND ma.uname = ? AND me.school_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "isi", $attempt_id, $uname, $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    // No such attempt found for this user
    header("Location: mock_exams.php");
    exit;
}

$attempt = mysqli_fetch_assoc($result);

// Get exam questions and answers
$sql = "SELECT * FROM mock_qstn_list WHERE mock_exid = '$mock_exid' ORDER BY sno";
$questionsResult = mysqli_query($conn, $sql);

// Get user's answers
$user_answers = [];

// Check if the mock_qstn_ans table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'mock_qstn_ans'");
if (mysqli_num_rows($table_check) > 0) {
    // Table exists, proceed with query
    $sql = "SELECT * FROM mock_qstn_ans WHERE mock_exid='$mock_exid' AND uname='$uname' ORDER BY sno";
    $userAnswersResult = mysqli_query($conn, $sql);

    if ($userAnswersResult && mysqli_num_rows($userAnswersResult) > 0) {
        while ($row = mysqli_fetch_assoc($userAnswersResult)) {
            $user_answers[$row['sno']] = $row['ans'];
        }
    } else {
        // No user answers found, log this
        error_log("No user answers found for mock_exid=$mock_exid, uname=$uname");
    }
} else {
    // Table doesn't exist, log this
    error_log("Table mock_qstn_ans doesn't exist yet");
}

// Debug information to help troubleshoot
error_log("User Answers Retrieved: " . json_encode($user_answers));
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Mock Test Results</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .results-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .score-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }

        .score-title {
            color: #0A2558;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .score-value {
            font-size: 48px;
            font-weight: bold;
            color: #0A2558;
            margin-bottom: 10px;
        }

        .score-details {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }

        .score-item {
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .question-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .question-text {
            font-size: 18px;
            color: #0A2558;
            margin-bottom: 15px;
        }

        .options-list {
            list-style-type: none;
            padding: 0;
        }

        .option-item {
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        .option-correct {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .option-incorrect {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .option-user-selected {
            border-left: 4px solid #0A2558;
        }

        .actions {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            background-color: #0A2558;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #0d3a80;
        }

        /* Integrity score styling */
        .integrity-score {
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .integrity-good,
        .integrity-excellent {
            color: #28a745;
            background-color: #e6f7ee;
        }

        .integrity-fair,
        .integrity-at-risk {
            color: #ffc107;
            background-color: #fff8e6;
        }

        .integrity-poor,
        .integrity-very-poor,
        .integrity-cheating-suspicion {
            color: #dc3545;
            background-color: #f8e6e8;
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
                <a href="mock_exams.php" class="active">
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
                <span class="dashboard">Mock Test Results</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <div class="results-container">
                <div class="score-card">
                    <div class="score-title"><?php echo $attempt['exname']; ?> Results</div>
                    <div class="score-value"><?php echo $attempt['ptg']; ?>%</div>
                    <div class="score-details">
                        <div class="score-item">
                            <strong>Score:</strong> <?php echo $attempt['cnq'] . '/' . $attempt['nq']; ?>
                        </div>
                        <div class="score-item">
                            <?php
                            // Determine the CSS class based on integrity category
                            $integrityClass = 'integrity-good';
                            if (isset($attempt['integrity_category'])) {
                                if ($attempt['integrity_category'] == 'Excellent' || $attempt['integrity_category'] == 'Good') {
                                    $integrityClass = 'integrity-good';
                                } else if ($attempt['integrity_category'] == 'Fair' || $attempt['integrity_category'] == 'At-Risk') {
                                    $integrityClass = 'integrity-fair';
                                } else if ($attempt['integrity_category'] == 'Poor' || $attempt['integrity_category'] == 'Very Poor' || $attempt['integrity_category'] == 'Cheating Suspicion') {
                                    $integrityClass = 'integrity-poor';
                                }
                            }
                            ?>
                            <strong>Integrity:</strong>
                            <span class="integrity-score <?php echo $integrityClass; ?>">
                                <?php echo $attempt['integrity_score']; ?>/100
                                <?php if (isset($attempt['integrity_category'])) {
                                    echo '(' . $attempt['integrity_category'] . ')';
                                } ?>
                            </span>
                        </div>
                        <div class="score-item">
                            <strong>Completed:</strong> <?php echo date('M d, Y h:i A', strtotime($attempt['subtime'])); ?>
                        </div>
                    </div>

                    <div class="performance-summary" style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px; text-align: left;">
                        <h3 style="margin-top: 0; color: #0A2558;">Performance Summary</h3>
                        <?php
                        // Calculate performance metrics
                        $scorePercentage = $attempt['ptg'];

                        if ($scorePercentage >= 90) {
                            $performanceLevel = "Excellent";
                            $performanceColor = "#28a745";
                            $feedback = "Outstanding performance! You have a strong understanding of the material.";
                        } elseif ($scorePercentage >= 70) {
                            $performanceLevel = "Good";
                            $performanceColor = "#17a2b8";
                            $feedback = "Good job! You've shown a solid grasp of most concepts.";
                        } elseif ($scorePercentage >= 50) {
                            $performanceLevel = "Average";
                            $performanceColor = "#ffc107";
                            $feedback = "You're on the right track, but there's room for improvement.";
                        } else {
                            $performanceLevel = "Needs Improvement";
                            $performanceColor = "#dc3545";
                            $feedback = "You should review the material again and focus on the areas where you made mistakes.";
                        }
                        ?>

                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="font-weight: bold; color: <?php echo $performanceColor; ?>; font-size: 18px; margin-right: 10px;">
                                <?php echo $performanceLevel; ?>
                            </span>
                            <div style="flex-grow: 1; height: 8px; background-color: #e9ecef; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?php echo $scorePercentage; ?>%; height: 100%; background-color: <?php echo $performanceColor; ?>;"></div>
                            </div>
                            <span style="margin-left: 10px; font-weight: bold;"><?php echo $scorePercentage; ?>%</span>
                        </div>

                        <p style="margin-bottom: 0;"><?php echo $feedback; ?></p>
                    </div>
                </div>

                <h2>Questions and Answers</h2>

                <?php
                $question_number = 1;
                while ($question = mysqli_fetch_assoc($questionsResult)) {
                    $sno = $question['sno'];
                    $user_answer = isset($user_answers[$sno]) ? $user_answers[$sno] : null;

                    echo '<div class="question-card">';
                    echo '<div class="question-text">Question ' . $question_number . ': ' . $question['qstn'] . '</div>';
                    echo '<ul class="options-list">';

                    // Get the option names and values
                    $options = [
                        'option1' => $question['qstn_o1'],
                        'option2' => $question['qstn_o2'],
                        'option3' => $question['qstn_o3'],
                        'option4' => $question['qstn_o4']
                    ];

                    // Debug logging for this question
                    error_log("Question $sno - Correct Answer: {$question['qstn_ans']}, User Answer: " . (isset($user_answers[$sno]) ? $user_answers[$sno] : 'Not Found'));

                    foreach ($options as $option_key => $option_text) {
                        $is_correct = ($question['qstn_ans'] == $option_key);
                        $is_user_selected = (isset($user_answers[$sno]) && $user_answers[$sno] == $option_key);

                        echo '<li class="option-item">';
                        echo htmlspecialchars($option_text);
                        echo '</li>';
                    }

                    // Display correct and marked answers
                    echo '<div style="margin-top: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 4px;">';

                    // Get the correct answer text directly from the database
                    $correct_answer_text = $question['qstn_ans'];

                    // Get the user's marked answer text
                    $marked_answer_text = isset($user_answers[$sno]) ? $user_answers[$sno] : 'Not answered';

                    // Determine if the answer is correct
                    $is_correct = ($marked_answer_text === $correct_answer_text);

                    if ($marked_answer_text === 'Not answered') {
                        echo '<div><strong>Your Answer:</strong> <span style="color: #666;">Not answered</span></div>';
                        echo '<div><strong>Correct Answer:</strong> <span style="color: #28a745;">' . htmlspecialchars($correct_answer_text) . '</span></div>';
                    } else {
                        if ($is_correct) {
                            echo '<div><strong>Your Answer:</strong> <span style="color: #28a745;">' . htmlspecialchars($marked_answer_text) . ' ✓</span></div>';
                        } else {
                            echo '<div><strong>Your Answer:</strong> <span style="color: #dc3545;">' . htmlspecialchars($marked_answer_text) . ' ✗</span></div>';
                            echo '<div><strong>Correct Answer:</strong> <span style="color: #28a745;">' . htmlspecialchars($correct_answer_text) . '</span></div>';
                        }
                    }
                    echo '</div>';

                    echo '</ul>';
                    echo '</div>';

                    $question_number++;
                }
                ?>

                <div class="actions">
                    <a href="mock_exams.php" class="btn">Return to Mock Exams</a>
                </div>
            </div>
        </div>
    </section>

    <script src="../js/script.js"></script>
</body>

</html>