<?php
session_start();
include('../config.php');

if (isset($_POST['delete_btn'])) {
    $id = mysqli_real_escape_string($conn, $_POST['delete_id']);
    $teacher_id = $_SESSION['user_id'];

    // Verify teacher is enrolled in the exam's school before deleting
    $verify_sql = "SELECT e.exid FROM exm_list e 
                   INNER JOIN teacher_schools ts ON e.school_id = ts.school_id 
                   WHERE e.exid = ? AND ts.teacher_id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "ii", $id, $teacher_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);

    if (mysqli_num_rows($verify_result) == 0) {
        echo "<script>alert('You do not have permission to delete this exam.');</script>";
        header('Location: exams.php');
        exit;
    }

    // Delete related mock exams first
    $query = "DELETE FROM mock_exm_list WHERE original_exid='$id'";
    mysqli_query($conn, $query);

    $query = "DELETE FROM exm_list WHERE exid='$id' ";
    $query_run = mysqli_query($conn, $query);

    $query = "DELETE FROM qstn_list WHERE exid='$id' ";
    $query_run = mysqli_query($conn, $query);

    $query = "DELETE FROM atmpt_list WHERE exid='$id' ";
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        header('Location: exams.php');
    } else {
        echo "<script>alert('Your Data is NOT DELETED');</script>";
        header('Location: exams.php');
    }
}
