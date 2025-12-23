<?php
session_start();
if (!isset($_SESSION["uname"])) {
    header("Location: ../login_student.php");
}

include '../config.php';
require_once '../utils/message_utils.php';
error_reporting(0);

$uname = $_SESSION['uname'];
$school_id = $_SESSION['school_id'] ?? 1;

// Get the count of unread messages
$unread_count = getUnreadMessageCount($uname, $conn);

// This query gets all mock exams that are ready and not attempted by the current user (filtered by school)
$sql = "SELECT me.* FROM mock_exm_list me 
        LEFT JOIN mock_atmpt_list ma 
        ON me.mock_exid = ma.mock_exid AND ma.uname = '$uname' AND ma.status = 1
        WHERE me.status = 'ready' AND me.school_id = $school_id AND (ma.id IS NULL)";
$result = mysqli_query($conn, $sql);

// For debugging
error_log("Mock exams query for user $uname found " . mysqli_num_rows($result) . " results");

// Get completed mock exams (filtered by school)
$completed_sql = "SELECT ma.*, me.exname 
                 FROM mock_atmpt_list ma 
                 JOIN mock_exm_list me ON ma.mock_exid = me.mock_exid 
                 WHERE ma.uname = '$uname' AND ma.status = 1 AND me.school_id = $school_id
                 ORDER BY ma.subtime DESC";
$completed_result = mysqli_query($conn, $completed_sql);

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Mock Exams</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .loading-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0A2558;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .pending-message {
            font-size: 16px;
            color: #555;
            margin-bottom: 15px;
        }

        .exmbtn {
            background-color: #0A2558;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .exmbtn:hover {
            background-color: #153d8a;
        }

        /* Notification badge style */
        .notification-badge {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            position: absolute;
            top: -5px;
            right: 10px;
            min-width: 18px;
            height: 18px;
            background-color: #ff3e55;
            color: white;
            border-radius: 50%;
            font-size: 11px;
            font-weight: bold;
            padding: 0 4px;
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
                    <span class="links_name">Exams</span>
                </a>
            </li>
            <li>
                <a href="#" class="active">
                    <i class='bx bx-edit'></i>
                    <span class="links_name">Mock Exams</span>
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
                    <span class="links_name">Announcements</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
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
                <span class="dashboard">Student Dashboard</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="pro">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <?php
            // Display notification for retry operation
            if (isset($_GET['retry'])) {
                if ($_GET['retry'] == 'success') {
                    $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
                    echo '<div style="background-color: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px;">
                          <strong>Success!</strong> Retried generation for ' . $count . ' mock exam(s). Please check back in a few minutes.
                          </div>';
                } elseif ($_GET['retry'] == 'none') {
                    echo '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px;">
                          <strong>Notice:</strong> No pending mock exams found to retry.
                          </div>';
                }
            }

            // Check for any pending mock exams
            $pending_sql = "SELECT * FROM mock_exm_list WHERE status = 'pending'";
            $pending_result = mysqli_query($conn, $pending_sql);

            if (mysqli_num_rows($pending_result) > 0) {
                echo '<div class="loading-container">
                <div class="loading-spinner"></div>
                <div class="pending-message">Mock exams are being generated. Please check back in a few minutes.</div>
                <div style="margin-top: 10px;">
                    <form action="retry_mock_generation.php" method="post">
                        <button type="submit" name="retry" style="padding: 5px 10px; background-color: #0A2558; color: white; border: none; border-radius: 4px; cursor: pointer;">Retry Generation</button>
                    </form>
                </div>
              </div>';
            }
            ?>

            <div class="stat-boxes">
                <div class="recent-stat box" style="padding: 0px 0px; width:90%">
                    <table>
                        <thead>
                            <tr>
                                <th>Sl.no</th>
                                <th>Mock Exam Name</th>
                                <th>Description</th>
                                <th>Subject</th>
                                <th>No. of questions</th>
                                <th>Submission time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo '<tr>
                                    <td>' . $i . '</td>
                                    <td>' . $row['exname'] . '</td>
                                    <td>' . $row['desp'] . '</td>
                                    <td>' . $row['subject'] . '</td>
                                    <td>' . $row['nq'] . '</td>
                                    <td>' . $row['subt'] . '</td>
                                    <td>
                                        <form action="mockexamportal.php" method="post">
                                            <input type="hidden" name="mock_exid" value="' . $row['mock_exid'] . '">
                                            <input type="hidden" name="nq" value="' . $row['nq'] . '">
                                            <input type="hidden" name="subject" value="' . $row['exname'] . '">
                                            <input type="hidden" name="desp" value="' . $row['desp'] . '">
                                            <button type="submit" name="edit_btn" class ="exmbtn">Start</button>
                                        </form>
                                    </td>
                                </tr>';
                                    $i++;
                                }
                            } else {
                                echo '<tr><td colspan="7" style="text-align: center;">No mock exams available at the moment.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Completed Mock Exams Section -->
            <?php if (mysqli_num_rows($completed_result) > 0) { ?>
                <div class="stat-boxes" style="margin-top: 30px;">
                    <div class="recent-stat box" style="padding: 0px 0px; width:90%">
                        <table>
                            <thead>
                                <tr>
                                    <th>Sl.no</th>
                                    <th>Mock Exam Name</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Integrity Score</th>
                                    <th>Submission Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                while ($row = mysqli_fetch_assoc($completed_result)) {
                                    echo '<tr>
                                <td>' . $i . '</td>
                                <td>' . $row['exname'] . '</td>
                                <td>' . $row['cnq'] . '/' . $row['nq'] . '</td>
                                <td>' . $row['ptg'] . '%</td>
                                <td>' . $row['integrity_score'] . '/100</td>
                                <td>' . $row['subtime'] . '</td>
                            </tr>';
                                    $i++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>

    <script src="../js/script.js"></script>
</body>

</html>