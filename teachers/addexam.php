<?php
session_start();
include('../config.php');

//Below code to add exam details

if (isset($_POST["addexm"])) {
    $exname = mysqli_real_escape_string($conn, $_POST["exname"]);
    $nq = mysqli_real_escape_string($conn, $_POST["nq"]);
    $desp = mysqli_real_escape_string($conn, $_POST["desp"]);
    $subt = mysqli_real_escape_string($conn, $_POST["subt"]);
    $extime = mysqli_real_escape_string($conn, $_POST["extime"]);
    $subject = mysqli_real_escape_string($conn, $_POST["subject"]);
    $duration = mysqli_real_escape_string($conn, $_POST["duration"]);
    $school_id = mysqli_real_escape_string($conn, $_POST["school_id"]);
    $teacher_id = $_SESSION['user_id'];

    // Verify teacher is enrolled in this school
    $verify_sql = "SELECT * FROM teacher_schools 
                   WHERE teacher_id = ? 
                   AND school_id = ? 
                   AND enrollment_status = 'active'";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);

    if (mysqli_num_rows($verify_result) == 0) {
        echo "<script>alert('You are not enrolled in this school.');</script>";
        header("Location: exams.php");
        exit;
    }

    $sql = "INSERT INTO exm_list (exname, nq, desp, subt, extime, subject, duration, school_id) VALUES ('$exname', '$nq', '$desp', '$subt', '$extime', '$subject', '$duration', '$school_id')";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        // Get the ID of the newly created exam
        $exam_id = mysqli_insert_id($conn);

        // Log the action
        error_log("Created exam ID $exam_id for school ID $school_id");

        // Instead of redirecting, output a form that auto-submits to addqp.php
        echo "
        <form id='redirectForm' action='addqp.php' method='post'>
            <input type='hidden' name='exid' value='$exam_id'>
            <input type='hidden' name='nq' value='$nq'>
        </form>
        <script>
            document.getElementById('redirectForm').submit();
        </script>
        ";
        exit; // Stop execution to prevent additional headers
    } else {
        echo "<script>alert('Adding exam failed.');</script>";
        header("Location: exams.php");
    }
}

// ********************************************

//Below code to add question to database

if (isset($_POST["addqp"])) {
    $nq = mysqli_real_escape_string($conn, $_POST["nq"]);
    $exid = mysqli_real_escape_string($conn, $_POST["exid"]);

    // Track if all insertions were successful
    $all_successful = true;

    for ($i = 1; $i <= $nq; $i++) {
        $q = mysqli_real_escape_string($conn, $_POST['q' . $i]);
        $o1 = mysqli_real_escape_string($conn, $_POST['o1' . $i]);
        $o2 = mysqli_real_escape_string($conn, $_POST['o2' . $i]);
        $o3 = mysqli_real_escape_string($conn, $_POST['o3' . $i]);
        $o4 = mysqli_real_escape_string($conn, $_POST['o4' . $i]);
        $a = mysqli_real_escape_string($conn, $_POST['a' . $i]);
        $sql = "INSERT INTO qstn_list (exid, qstn, qstn_o1, qstn_o2, qstn_o3, qstn_o4, qstn_ans, sno) VALUES ('$exid', '$q', '$o1', '$o2', '$o3', '$o4', '$a', '$i')";
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            $all_successful = false;
        }
    }

    if ($all_successful) {
        // Get the exam details for mock exam generation
        $exam_sql = "SELECT * FROM exm_list WHERE exid = '$exid'";
        $exam_result = mysqli_query($conn, $exam_sql);

        if (mysqli_num_rows($exam_result) > 0) {
            $exam_row = mysqli_fetch_assoc($exam_result);
            $exname = $exam_row['exname'];
            $description = $exam_row['desp'];
            $subject = $exam_row['subject'];

            // Generate mock exams using our helper file
            include_once('mock_exam_helper.php');
            generateMockExamsHelper($exid, $exname, $description, $subject);

            // Log the action
            error_log("Added questions to exam ID $exid and triggered mock exam generation");
        }

        header("Location: exams.php");
    } else {
        echo "<script>alert('Updating questions failed.');</script>";
        header("Location: exams.php");
    }
}

// ********************************************
