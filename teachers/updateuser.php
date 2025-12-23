<?php
include('../config.php');
session_start();

if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}

if (isset($_POST['updatebtn'])) {
    $id = mysqli_real_escape_string($conn, $_POST["id"]);
    $fname = mysqli_real_escape_string($conn, $_POST["fname"]);
    $dob = mysqli_real_escape_string($conn, $_POST["dob"]);
    $gender = mysqli_real_escape_string($conn, $_POST["gender"]);
    $email = mysqli_real_escape_string($conn, $_POST["email"]);
    $school_id = intval($_POST["school_id"]);
    $teacher_id = $_SESSION['user_id'];

    // Verify teacher is enrolled in this school
    $check_school = mysqli_prepare($conn, "SELECT id FROM teacher_schools WHERE teacher_id = ? AND school_id = ?");
    mysqli_stmt_bind_param($check_school, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($check_school);
    $school_check_result = mysqli_stmt_get_result($check_school);

    if (mysqli_num_rows($school_check_result) == 0) {
        echo "<script>alert('You are not enrolled in this school.');</script>";
        header("Location: records.php");
        exit;
    }

    $sql = "UPDATE student SET fname='$fname', dob ='$dob', gender='$gender', email ='$email', school_id='$school_id' WHERE id='$id' ";
    $query_run = mysqli_query($conn, $sql);
    if ($query_run) {
        echo "<script>alert('Profile details updated!.');</script>";

        header("Location: records.php");
    } else {
        echo "<script>alert('Updation failed.');</script>";
        header("Location: records.php");
    }
}
