<?php
session_start();
if (!isset($_SESSION["uname"])) {
    header("Location: ../login_student.php");
    exit;
}

include '../config.php';
require_once '../utils/school_access_control.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mock_exid = mysqli_real_escape_string($conn, $_POST['mock_exid']);
    $nq = $_POST['nq'];
    $uname = $_SESSION['uname'];
    $school_id = $_SESSION['school_id'] ?? 0;
    $integrity_score = isset($_POST['integrity_score']) ? intval($_POST['integrity_score']) : 100;

    // Validate student has access to this mock exam's school before processing submission
    if (!validateStudentMockExamAccess($conn, $uname, $mock_exid)) {
        die("Unauthorized submission attempt: This mock exam does not belong to your school.");
    }

    // Create the mock_cheat_violations table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS mock_cheat_violations (
        id INT(11) NOT NULL AUTO_INCREMENT,
        student_username VARCHAR(50) NOT NULL,
        mock_exam_id INT(11) NOT NULL,
        violation_type VARCHAR(50) NOT NULL,
        occurrence INT(11) NOT NULL,
        penalty INT(11) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    )";
    mysqli_query($conn, $create_table_sql);

    // Calculate integrity score
    $integrity_score = 100; // Start with 100

    // Get total penalties from violations
    $violations_query = "SELECT SUM(penalty) as total_penalty FROM mock_cheat_violations 
                        WHERE student_username = ? AND mock_exam_id = ?";
    $violations_stmt = $conn->prepare($violations_query);
    $violations_stmt->bind_param("si", $uname, $mock_exid);
    $violations_stmt->execute();
    $violations_result = $violations_stmt->get_result();
    $total_penalty = $violations_result->fetch_assoc()['total_penalty'] ?? 0;

    // Subtract total penalties from 100
    $integrity_score = max(0, $integrity_score - $total_penalty);

    // Determine integrity category
    $integrity_category = 'Good';
    if ($integrity_score >= 90) {
        $integrity_category = 'Excellent';
    } else if ($integrity_score >= 80) {
        $integrity_category = 'Good';
    } else if ($integrity_score >= 70) {
        $integrity_category = 'Fair';
    } else if ($integrity_score >= 60) {
        $integrity_category = 'Poor';
    } else {
        $integrity_category = 'Very Poor';
    }

    // Count correct answers
    $cnq = 0;

    // Check if user has already submitted this mock exam
    $check_sql = "SELECT id FROM mock_atmpt_list WHERE mock_exid='$mock_exid' AND uname='$uname' AND status=1";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        // User has already submitted this exam, redirect to mock exams page
        header("Location: mock_exams.php?error=already_submitted");
        exit;
    }

    // Check if the mock_qstn_ans table exists, create it if not
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'mock_qstn_ans'");
    if (mysqli_num_rows($table_check) == 0) {
        // Table doesn't exist, create it
        $create_table_sql = "CREATE TABLE IF NOT EXISTS mock_qstn_ans (
            id INT(11) NOT NULL AUTO_INCREMENT,
            mock_exid INT(11) NOT NULL,
            uname VARCHAR(50) NOT NULL,
            sno INT(11) NOT NULL,
            ans VARCHAR(255) NOT NULL,
            datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        )";

        if (!mysqli_query($conn, $create_table_sql)) {
            error_log("Error creating mock_qstn_ans table: " . mysqli_error($conn));
            echo "<h2>Error Setting Up Database</h2>";
            echo "<p>We encountered an error while setting up the database. Please contact support.</p>";
            echo "<p><a href='mock_exams.php'>Return to Mock Exams</a></p>";
            exit;
        }
    }

    // Delete any existing answers for this user and exam
    $delete_sql = "DELETE FROM mock_qstn_ans WHERE mock_exid='$mock_exid' AND uname='$uname'";
    mysqli_query($conn, $delete_sql);

    // Fetch correct answers from database and map option values to option keys
    $sql = "SELECT * FROM mock_qstn_list WHERE mock_exid='$mock_exid' ORDER BY sno";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sno = $row['sno'];
            $correct_ans = $row['qstn_ans'];
            $user_ans_value = isset($_POST['a' . $sno]) ? $_POST['a' . $sno] : '';

            // Debug info
            error_log("Q$sno: User selected text: '$user_ans_value'");
            error_log("Q$sno: Correct answer: '$correct_ans'");

            // Sanitize inputs to prevent SQL injection
            $sno = mysqli_real_escape_string($conn, $sno);
            $user_ans_value = mysqli_real_escape_string($conn, $user_ans_value);

            // Save user's answer
            if (!empty($user_ans_value)) {
                $save_ans_sql = "INSERT INTO mock_qstn_ans (mock_exid, uname, sno, ans) 
                               VALUES ('$mock_exid', '$uname', '$sno', '$user_ans_value')";

                if (!mysqli_query($conn, $save_ans_sql)) {
                    error_log("Error saving answer: " . mysqli_error($conn));
                }

                // Check if the user's answer is correct (case-sensitive comparison)
                if ($user_ans_value === $correct_ans) {
                    $cnq++;
                    error_log("Q$sno: Answer is correct!");
                } else {
                    error_log("Q$sno: Answer is incorrect. Expected '$correct_ans', got '$user_ans_value'");
                }
            } else {
                error_log("Q$sno: No answer provided");
            }
        }
    }

    // Calculate percentage
    $ptg = ($cnq / $nq) * 100;

    // Insert result into mock_atmpt_list
    $sql = "INSERT INTO mock_atmpt_list (mock_exid, uname, nq, cnq, ptg, status, integrity_score, integrity_category) 
            VALUES ('$mock_exid', '$uname', '$nq', '$cnq', '$ptg', '1', '$integrity_score', '$integrity_category')";

    if (mysqli_query($conn, $sql)) {
        // Get the ID of the inserted attempt
        $attempt_id = mysqli_insert_id($conn);

        // Redirect to the new results page
        header("Location: mock_test_result.php?mock_exid=$mock_exid&attempt_id=$attempt_id");
        exit;
    } else {
        // Log the error and show a user-friendly message
        error_log("Database error: " . mysqli_error($conn));
        echo "<h2>Error Processing Your Test</h2>";
        echo "<p>We encountered an error while processing your test results. Please contact support.</p>";
        echo "<p><a href='mock_exams.php'>Return to Mock Exams</a></p>";
    }
} else {
    // If not POST request, redirect to mock exams page
    header("Location: mock_exams.php");
}
