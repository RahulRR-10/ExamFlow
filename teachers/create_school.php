<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION["fname"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
error_reporting(0);

$teacher_id = $_SESSION['user_id'];
$error_msg = '';
$success_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = trim($_POST['school_name'] ?? '');
    $school_code = strtoupper(trim($_POST['school_code'] ?? ''));
    $address = trim($_POST['address'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');

    // Validation
    if (empty($school_name)) {
        $error_msg = 'School name is required';
    } elseif (empty($school_code)) {
        $error_msg = 'School code is required';
    } elseif (strlen($school_code) < 3 || strlen($school_code) > 20) {
        $error_msg = 'School code must be between 3 and 20 characters';
    } elseif (!preg_match('/^[A-Z0-9_-]+$/', $school_code)) {
        $error_msg = 'School code can only contain letters, numbers, hyphens, and underscores';
    } elseif ($contact_email && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid email address';
    } else {
        // Check if school code already exists
        $check_stmt = mysqli_prepare($conn, "SELECT school_id FROM schools WHERE school_code = ?");
        mysqli_stmt_bind_param($check_stmt, "s", $school_code);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = 'A school with this code already exists. Please choose a different code.';
        } else {
            // Create the school
            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO schools (school_name, school_code, address, contact_email, contact_phone, status, created_at) 
         VALUES (?, ?, ?, ?, ?, 'active', NOW())"
            );
            mysqli_stmt_bind_param($insert_stmt, "sssss", $school_name, $school_code, $address, $contact_email, $contact_phone);

            if (mysqli_stmt_execute($insert_stmt)) {
                $new_school_id = mysqli_insert_id($conn);

                // Check if teacher has any schools (to set is_primary)
                $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM teacher_schools WHERE teacher_id = ?");
                mysqli_stmt_bind_param($count_stmt, "i", $teacher_id);
                mysqli_stmt_execute($count_stmt);
                $count_result = mysqli_stmt_get_result($count_stmt);
                $count_row = mysqli_fetch_assoc($count_result);
                $is_primary = ($count_row['cnt'] == 0) ? 1 : 0;

                // Auto-enroll the creator in the new school
                $enroll_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO teacher_schools (teacher_id, school_id, is_primary, enrolled_at) VALUES (?, ?, ?, NOW())"
                );
                mysqli_stmt_bind_param($enroll_stmt, "iii", $teacher_id, $new_school_id, $is_primary);
                mysqli_stmt_execute($enroll_stmt);

                header("Location: school_management.php?success=" . urlencode("School '$school_name' created successfully and you have been enrolled!"));
                exit;
            } else {
                $error_msg = 'Failed to create school. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Create School</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .form-container {
            padding: 20px;
            margin-top: 80px;
            max-width: 600px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #17684f;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .form-card {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #17684f;
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group .hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-submit {
            background: #17684f;
            color: #fff;
        }

        .btn-submit:hover {
            background: #11533e;
        }

        .btn-cancel {
            background: #6c757d;
            color: #fff;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-details">
            <i class='bx bx-diamond'></i>
            <span class="logo_name">Welcome</span>
        </div>
        <ul class="nav-links">
            <li>
                <a href="dash.php">
                    <i class='bx bx-grid-alt'></i>
                    <span class="links_name">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="exams.php">
                    <i class='bx bx-book-content'></i>
                    <span class="links_name">MCQ Exams</span>
                </a>
            </li>
            <li>
                <a href="objective_exams.php">
                    <i class='bx bx-edit'></i>
                    <span class="links_name">Objective Exams</span>
                </a>
            </li>
            <li>
                <a href="results.php">
                    <i class='bx bxs-bar-chart-alt-2'></i>
                    <span class="links_name">Results</span>
                </a>
            </li>
            <li>
                <a href="messages.php">
                    <i class='bx bx-message'></i>
                    <span class="links_name">Messages</span>
                </a>
            </li>
            <li>
                <a href="school_management.php" class="active">
                    <i class='bx bx-building-house'></i>
                    <span class="links_name">Schools</span>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <i class='bx bx-cog'></i>
                    <span class="links_name">Settings</span>
                </a>
            </li>
            <li>
                <a href="help.php">
                    <i class='bx bx-help-circle'></i>
                    <span class="links_name">Help</span>
                </a>
            </li>
            <li class="log_out">
                <a href="../logout.php">
                    <i class='bx bx-log-out-circle'></i>
                    <span class="links_name">Log out</span>
                </a>
            </li>
        </ul>
    </div>

    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Create New School</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="form-container">
            <h1 class="page-title"><i class='bx bx-building-house'></i> Create New School</h1>
            <p class="page-subtitle">Set up a new school for your organization. You will be automatically enrolled as a member.</p>

            <?php if ($error_msg): ?>
                <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="school_name">School Name <span class="required">*</span></label>
                        <input type="text" id="school_name" name="school_name"
                            value="<?php echo htmlspecialchars($_POST['school_name'] ?? ''); ?>"
                            placeholder="e.g., Lincoln High School" required>
                    </div>

                    <div class="form-group">
                        <label for="school_code">School Code <span class="required">*</span></label>
                        <input type="text" id="school_code" name="school_code"
                            value="<?php echo htmlspecialchars($_POST['school_code'] ?? ''); ?>"
                            placeholder="e.g., LHS-2024"
                            pattern="[A-Za-z0-9_-]+"
                            maxlength="20" required>
                        <div class="hint">3-20 characters. Letters, numbers, hyphens, and underscores only. Will be converted to uppercase.</div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address"
                            placeholder="Street address, city, state, zip code"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email"
                            value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>"
                            placeholder="admin@school.edu">
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone"
                            value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>"
                            placeholder="+1 (555) 123-4567">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-submit">
                            <i class='bx bx-check'></i> Create School
                        </button>
                        <a href="school_management.php" class="btn btn-cancel">
                            <i class='bx bx-x'></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        let sidebar = document.querySelector(".sidebar");
        let sidebarBtn = document.querySelector(".sidebarBtn");
        sidebarBtn.onclick = function() {
            sidebar.classList.toggle("active");
            if (sidebar.classList.contains("active")) {
                sidebarBtn.classList.replace("bx-menu", "bx-menu-alt-right");
            } else {
                sidebarBtn.classList.replace("bx-menu-alt-right", "bx-menu");
            }
        }

        // Auto-uppercase school code
        document.getElementById('school_code').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>

</html>