<?php

error_reporting(0);

if (isset($_SESSION["fname"])) {
	header("Location: students/dash.php");
}

include 'config.php';
session_start();
if (isset($_POST["signin"])) {
	$uname = mysqli_real_escape_string($conn, $_POST["uname"]);
	$pword = mysqli_real_escape_string($conn, md5($_POST["pword"]));

	$check_user = mysqli_query($conn, "SELECT s.*, sch.school_name, sch.status as school_status 
	                                   FROM student s 
	                                   LEFT JOIN schools sch ON s.school_id = sch.school_id 
	                                   WHERE s.uname='$uname' AND s.pword='$pword'");

	if (mysqli_num_rows($check_user) > 0) {
		$row = mysqli_fetch_assoc($check_user);

		// Check if student's school is active
		if ($row['school_id'] && $row['school_status'] !== 'active') {
			echo "<script>alert('Your school is currently inactive. Please contact administrator.');</script>";
		} else {
			$_SESSION["user_id"] = $row['id'];
			$_SESSION["fname"] = $row['fname'];
			$_SESSION["email"] = $row['email'];
			$_SESSION["dob"] = $row['dob'];
			$_SESSION["gender"] = $row['gender'];
			$_SESSION["uname"] = $row['uname'];
			$_SESSION["school_id"] = $row['school_id'];
			$_SESSION["school_name"] = $row['school_name'] ?? 'Default School';
			if ($row['gender'] == 'M') {
				$_SESSION['img'] = "../img/mp.png";
			} else if ($row['gender'] == 'F') {
				$_SESSION['img'] = "../img/fp.png";
			}
			header("Location: students/dash.php");
		}
	} else {
		echo "<script>alert('Login details is incorrect. Please try again.');</script>";
	}
}

?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="students/css/style.css">
	<title>Login| Welcome</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<style>
	body {
		background-image: url('<?php echo $img; ?>');
		background-repeat: no-repeat;
		background-attachment: fixed;
		background-size: 100% 100%;
	}
</style>

<body>
	<h1><?php echo $greet; ?></h1><br>
	<div class="container" id="container">
		<div class="form-container log-in-container">
			<form action="#" method="post">
				<h1>Student login</h1>
				<br><br>
				<span>Enter credentials</span>
				<input type="text" name="uname" placeholder="Username" value="<?php echo $_POST['uname']; ?>" required />
				<input type="password" name="pword" placeholder="Password" required />
				<a href="#">Forgot your password?</a>
				<button type="submit" name="signin">Log In</button>
				<p style="margin-top: 15px; font-size: 13px;">Don't have an account? <a href="register_student.php" style="color: #0A2558; font-weight: bold;">Register here</a></p>
			</form>
		</div>
		<div class="overlay-container">
			<div class="overlay">
				<div class="overlay-panel overlay-right">
					<p>Login as teacher</p>
					<button style="background-color:#ffffff;border-color:black;"><a href="login_teacher.php">Continue</a></button>
					<p style="margin-top:15px;">Login as admin</p>
					<button style="background-color:#ffffff;border-color:black;"><a href="login_admin.php" style="color:#667eea;">Admin Portal</a></button>
				</div>
			</div>
		</div>
	</div>
</body>

</html>