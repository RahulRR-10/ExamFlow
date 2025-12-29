<?php

error_reporting(0);

include 'config.php';
session_start();

if (isset($_SESSION["admin_id"])) {
    header("Location: admin/dash.php");
    exit;
}

if (isset($_POST["signin"])) {
    $uname = mysqli_real_escape_string($conn, $_POST["uname"]);
    $pword = mysqli_real_escape_string($conn, md5($_POST["pword"]));

    $check_user = mysqli_query($conn, 
        "SELECT * FROM admin WHERE uname='$uname' AND pword='$pword' AND status='active'"
    );

    if (mysqli_num_rows($check_user) > 0) {
        $row = mysqli_fetch_assoc($check_user);
        $_SESSION["admin_id"] = $row['id'];
        $_SESSION["admin_fname"] = $row['fname'];
        $_SESSION["admin_email"] = $row['email'];
        $_SESSION["admin_uname"] = $row['uname'];
        $_SESSION["user_role"] = "admin";
        
        // Log the login action
        require_once 'utils/admin_auth.php';
        logAdminAction($conn, $row['id'], 'login', null, null, 'Admin login successful');
        
        header("Location: admin/dash.php");
        exit;
    } else {
        $error_message = "Invalid credentials or inactive account.";
    }
}

?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="teachers/css/style.css">
    <title>Admin Login | ExamFlow</title>
    <style>
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 20px;
            display: inline-block;
        }
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #dc2626;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .form-container h1 {
            color: #667eea;
        }
        .overlay-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        button[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        button[type="submit"]:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
    </style>
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
                <span class="admin-badge">🔐 Administrator Portal</span>
                <h1>Admin Login</h1>
                <br>
                <?php if (isset($error_message)): ?>
                    <div class="error-message"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <span>Enter admin credentials</span>
                <input type="text" name="uname" placeholder="Username" value="<?php echo htmlspecialchars($_POST['uname'] ?? ''); ?>" required />
                <input type="password" name="pword" placeholder="Password" required />
                <button type="submit" name="signin">Log In</button>
                <p style="margin-top: 20px; font-size: 12px; color: #666;">
                    Admin access is restricted to verification personnel only.
                </p>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-right">
                    <h2>Teaching Activity Verification</h2>
                    <p>Admin portal for reviewing and verifying geotagged teaching activities.</p>
                    <br>
                    <p>Not an admin?</p>
                    <button style="background-color:#ffffff;border-color:black;margin-top:10px;">
                        <a href="login_teacher.php" style="color:#667eea;">Teacher Login</a>
                    </button>
                    <button style="background-color:#ffffff;border-color:black;margin-top:10px;">
                        <a href="login_student.php" style="color:#667eea;">Student Login</a>
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
