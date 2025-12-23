<?php
error_reporting(0);
include 'config.php';
session_start();

// If user wants to register, clear any existing session
if (isset($_SESSION["fname"]) && !isset($_GET['force'])) {
    // Show option to logout and register or go to dashboard
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Already Logged In</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background: linear-gradient(135deg, #17684f 0%, #0A2558 100%);
                margin: 0;
            }

            .box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }

            h2 {
                color: #17684f;
                margin-bottom: 20px;
            }

            p {
                color: #666;
                margin-bottom: 25px;
            }

            .btn {
                display: inline-block;
                padding: 12px 30px;
                margin: 5px;
                border-radius: 25px;
                text-decoration: none;
                font-weight: bold;
            }

            .btn-primary {
                background: #17684f;
                color: white;
            }

            .btn-secondary {
                background: #eee;
                color: #333;
            }
        </style>
    </head>

    <body>
        <div class="box">
            <h2>You're Already Logged In</h2>
            <p>You are logged in as <strong><?php echo htmlspecialchars($_SESSION['fname']); ?></strong></p>
            <a href="teachers/dash.php" class="btn btn-primary">Go to Dashboard</a>
            <a href="?force=1" class="btn btn-secondary">Logout & Register New Account</a>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Clear session if force parameter is set
if (isset($_GET['force'])) {
    session_destroy();
    session_start();
}

// Get active schools for dropdown
$schools_sql = "SELECT school_id, school_name FROM schools WHERE status = 'active' ORDER BY school_name";
$schools_result = mysqli_query($conn, $schools_sql);

$success_msg = '';
$error_msg = '';

// Handle registration
if (isset($_POST["register"])) {
    $fname = mysqli_real_escape_string($conn, $_POST["fname"]);
    $email = mysqli_real_escape_string($conn, $_POST["email"]);
    $dob = mysqli_real_escape_string($conn, $_POST["dob"]);
    $gender = mysqli_real_escape_string($conn, $_POST["gender"]);
    $uname = mysqli_real_escape_string($conn, $_POST["uname"]);
    $pword = md5($_POST["pword"]);
    $subject = mysqli_real_escape_string($conn, $_POST["subject"]);
    $school_ids = isset($_POST["school_ids"]) ? $_POST["school_ids"] : [];

    // Validate at least one school selected
    if (empty($school_ids)) {
        $error_msg = "Please select at least one school to enroll in.";
    } else {
        // Check if username already exists
        $check_user = mysqli_query($conn, "SELECT uname FROM teacher WHERE uname = '$uname'");
        if (mysqli_num_rows($check_user) > 0) {
            $error_msg = "Username already exists. Please choose a different username.";
        } else {
            // Check if email already exists
            $check_email = mysqli_query($conn, "SELECT email FROM teacher WHERE email = '$email'");
            if (mysqli_num_rows($check_email) > 0) {
                $error_msg = "Email already registered. Please use a different email.";
            } else {
                // Insert new teacher
                $sql = "INSERT INTO teacher (fname, email, dob, gender, uname, pword, subject) 
                        VALUES ('$fname', '$email', '$dob', '$gender', '$uname', '$pword', '$subject')";

                if (mysqli_query($conn, $sql)) {
                    $teacher_id = mysqli_insert_id($conn);

                    // Enroll teacher in selected schools
                    $first = true;
                    $enrollment_success = true;

                    foreach ($school_ids as $school_id) {
                        $school_id = intval($school_id);

                        // Verify school exists and is active
                        $school_check = mysqli_query($conn, "SELECT school_id FROM schools WHERE school_id = $school_id AND status = 'active'");
                        if (mysqli_num_rows($school_check) > 0) {
                            $is_primary = $first ? 1 : 0;
                            $first = false;

                            $enroll_sql = "INSERT INTO teacher_schools (teacher_id, school_id, is_primary, enrollment_status) 
                                           VALUES ($teacher_id, $school_id, $is_primary, 'active')";

                            if (!mysqli_query($conn, $enroll_sql)) {
                                $enrollment_success = false;
                            }
                        }
                    }

                    if ($enrollment_success) {
                        $success_msg = "Registration successful! You can now login.";
                    } else {
                        $success_msg = "Registration successful but some school enrollments failed. You can manage schools after login.";
                    }
                } else {
                    $error_msg = "Registration failed. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="teachers/css/style.css">
    <title>Teacher Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<style>
    select {
        background-color: #eee;
        border: none;
        padding: 12px 15px;
        margin: 8px 0;
        width: 100%;
    }

    .form-row {
        display: flex;
        gap: 10px;
        width: 100%;
    }

    .form-row input,
    .form-row select {
        flex: 1;
    }

    .success-msg {
        background: #d4edda;
        color: #155724;
        padding: 10px 15px;
        border-radius: 5px;
        margin-bottom: 10px;
        font-size: 13px;
    }

    .error-msg {
        background: #f8d7da;
        color: #721c24;
        padding: 10px 15px;
        border-radius: 5px;
        margin-bottom: 10px;
        font-size: 13px;
    }

    .school-select-container {
        background: #eee;
        border-radius: 5px;
        padding: 10px;
        margin: 8px 0;
        max-height: 120px;
        overflow-y: auto;
        width: 100%;
    }

    .school-checkbox {
        display: flex;
        align-items: center;
        padding: 3px 0;
    }

    .school-checkbox input[type="checkbox"] {
        width: auto;
        margin-right: 10px;
    }

    .school-checkbox label {
        cursor: pointer;
        font-size: 13px;
    }
</style>

<body>
    <h1><?php echo $greet; ?></h1><br>
    <div class="container" id="container">
        <div class="form-container log-in-container">
            <form action="#" method="post">
                <h1>Teacher Registration</h1>

                <?php if ($success_msg): ?>
                    <div class="success-msg"><?php echo $success_msg; ?></div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="error-msg"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <span>Select School(s) to Enroll In</span>
                <div class="school-select-container">
                    <?php
                    mysqli_data_seek($schools_result, 0);
                    while ($school = mysqli_fetch_assoc($schools_result)):
                    ?>
                        <div class="school-checkbox">
                            <input type="checkbox" name="school_ids[]"
                                value="<?php echo $school['school_id']; ?>"
                                id="school_<?php echo $school['school_id']; ?>"
                                <?php echo (isset($_POST['school_ids']) && in_array($school['school_id'], $_POST['school_ids'])) ? 'checked' : ''; ?>>
                            <label for="school_<?php echo $school['school_id']; ?>">
                                <?php echo htmlspecialchars($school['school_name']); ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>

                <input type="text" name="fname" placeholder="Full Name"
                    value="<?php echo htmlspecialchars($_POST['fname'] ?? ''); ?>" required />

                <input type="email" name="email" placeholder="Email Address"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required />

                <div class="form-row">
                    <input type="date" name="dob" placeholder="Date of Birth"
                        value="<?php echo $_POST['dob'] ?? ''; ?>" required />
                    <select name="gender" required>
                        <option value="">Gender</option>
                        <option value="M" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'M') ? 'selected' : ''; ?>>Male</option>
                        <option value="F" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'F') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>

                <input type="text" name="subject" placeholder="Subject / Specialization"
                    value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required />

                <input type="text" name="uname" placeholder="Username"
                    value="<?php echo htmlspecialchars($_POST['uname'] ?? ''); ?>"
                    minlength="4" maxlength="20" required />

                <input type="password" name="pword" placeholder="Password"
                    minlength="6" required />

                <button type="submit" name="register">Register</button>

                <p style="margin-top: 15px; font-size: 13px;">Already have an account? <a href="login_teacher.php" style="color: #17684f; font-weight: bold;">Login here</a></p>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-right">
                    <p>Register as student</p>
                    <button style="background-color:#ffffff;border-color:black;"><a href="register_student.php">Continue</a></button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>