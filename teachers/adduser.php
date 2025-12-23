<?php
session_start();
include('../config.php');

if (!isset($_SESSION["fname"])) {
  header("Location: ../login_teacher.php");
  exit;
}

if (isset($_POST["adduser"])) {
  $fname = mysqli_real_escape_string($conn, $_POST["fname"]);
  $uname = mysqli_real_escape_string($conn, $_POST["uname"]);
  $dob = mysqli_real_escape_string($conn, $_POST["dob"]);
  $gender = mysqli_real_escape_string($conn, $_POST["gender"]);
  $email = mysqli_real_escape_string($conn, $_POST["email"]);
  $pword = mysqli_real_escape_string($conn, md5($_POST["pword"]));
  $cpword = mysqli_real_escape_string($conn, md5($_POST["cpword"]));
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

  $check_user = mysqli_num_rows(mysqli_query($conn, "SELECT uname FROM student WHERE uname='$uname'"));

  if ($pword !== $cpword) {
    echo "<script>alert('Password did not match. Please try again');</script>";
    header("Location: records.php");
  } elseif ($check_user > 0) {
    echo "<script>alert('Username already exists in the database.');</script>";
    header("Location: records.php");
  } elseif ($school_id <= 0) {
    echo "<script>alert('Please select a school.');</script>";
    header("Location: records.php");
  } else {
    $sql = "INSERT INTO student (uname, pword, fname, dob, gender, email, school_id) VALUES ('$uname', '$pword', '$fname', '$dob', '$gender', '$email', '$school_id')";
    $result = mysqli_query($conn, $sql);
    if ($result) {
      header("Location: records.php");
    } else {
      echo "<script>alert('Student registration failed.');</script>";
      header("Location: records.php");
    }
  }
}
