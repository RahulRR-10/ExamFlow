<?php
date_default_timezone_set('Asia/Kolkata');

$hostname = "localhost";
$username = "root";
$password = "";
$database = "db_eval";

// Dual Photo Verification Settings
define('DURATION_TOLERANCE_MINUTES', 15);       // Allowed variance from expected duration
define('MIN_DURATION_PERCENT', 80);              // Must complete at least 80% of slot time
define('AUTO_APPROVE_START_DISTANCE', 100);      // Auto-approve start photo if within 100 meters
define('MAX_TIME_BEFORE_SLOT_START', 60);        // Max minutes early for start photo
define('MAX_TIME_AFTER_SLOT_END', 120);          // Max minutes late for end photo
define('REQUIRE_GPS_FOR_APPROVAL', true);        // Require GPS data for approval

if(!$conn = mysqli_connect($hostname, $username, $password, $database)){

 die("Database connection failed");
}

$time = date("H");
    /* Set the $timezone variable to become the current timezone */
$timezone = date("e");
    /* If the time is less than 1200 hours, show good morning */
if ($time < "12") {
     $greet= "Good Morning";
     $img="img/mng.jpg";
} else
    /* If the time is grater than or equal to 1200 hours, but less than 1700 hours, so good afternoon */
 if ($time >= "12" && $time < "17") {
    $greet= "Good Afternoon";
    $img="img/aftn.jpg";
  } else
    /* Should the time be between or equal to 1700 and 1900 hours, show good evening */
 if ($time >= "17" && $time < "19") {
    $greet= "Good Evening";
    $img="img/evng.jpg";
} else
    /* Finally, show good Evening if the time is greater than or equal to 1900 hours */
 if ($time >= "19") {
    $greet= "Good Evening";
    $img="img/evng.jpg";
}

?>