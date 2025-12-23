<?php 
include('../config.php');
session_start();

if (isset($_POST["addmsg"])) {
    $feedback = mysqli_real_escape_string($conn, $_POST["feedback"]);
    $fname = mysqli_real_escape_string($conn, $_POST["fname"]);
    $teacher_id = $_SESSION['user_id'];
    
    // Get teacher's primary school
    $school_query = mysqli_query($conn, "SELECT school_id FROM teacher_schools WHERE teacher_id = $teacher_id AND is_primary = 1 LIMIT 1");
    
    if (mysqli_num_rows($school_query) > 0) {
        $school_row = mysqli_fetch_assoc($school_query);
        $school_id = $school_row['school_id'];
        
        $sql = "INSERT INTO message (fname, feedback, school_id) VALUES ('$fname', '$feedback', $school_id)";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            echo "<script>alert('Message sent successfully to your primary school!');</script>";
            header("Location: messages.php");
        } else {
            echo "<script>alert('Message sending failed.');</script>";
            header("Location: messages.php");
        }
    } else {
        echo "<script>alert('You must have a primary school set to send announcements. Go to School Management.');</script>";
        header("Location: messages.php");
    }
}
?>