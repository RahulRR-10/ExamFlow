<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/rate_limiter.php';

$teacher_id = $_SESSION['user_id'];

// Get rate limit info
$rateLimiter = new RateLimiter($conn);
$uploadStats = $rateLimiter->getUploadStats($teacher_id);

// Get teacher's enrolled schools
$schools_sql = "SELECT s.school_id, s.school_name
                FROM schools s
                INNER JOIN teacher_schools ts ON s.school_id = ts.school_id
                WHERE ts.teacher_id = ? AND s.status = 'active'
                ORDER BY s.school_name ASC";
$stmt = mysqli_prepare($conn, $schools_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$schools = mysqli_stmt_get_result($stmt);
$school_list = [];
while ($s = mysqli_fetch_assoc($schools)) {
    $school_list[] = $s;
}

// Get submission history
$history_sql = "SELECT tas.*, s.school_name, a.fname as admin_name
                FROM teaching_activity_submissions tas
                JOIN schools s ON tas.school_id = s.school_id
                LEFT JOIN admin a ON tas.verified_by = a.id
                WHERE tas.teacher_id = ?
                ORDER BY tas.upload_date DESC
                LIMIT 50";
$stmt = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$history = mysqli_stmt_get_result($stmt);

// Get stats
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

$stats_sql = "SELECT verification_status, COUNT(*) as cnt 
              FROM teaching_activity_submissions 
              WHERE teacher_id = ? 
              GROUP BY verification_status";
$stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($stats_result)) {
    $stats[$row['verification_status']] = $row['cnt'];
    $stats['total'] += $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <title>Teaching Activity Upload | ExamFlow</title>
    <link rel="stylesheet" href="css/dash.css">
    <link rel="stylesheet" href="css/teaching_activity.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <a href="records.php">
                    <i class='bx bxs-user-circle'></i>
                    <span class="links_name">Records</span>
                </a>
            </li>
            <li>
                <a href="messages.php">
                    <i class='bx bx-message'></i>
                    <span class="links_name">Messages</span>
                </a>
            </li>
            <li>
                <a href="school_management.php">
                    <i class='bx bx-building-house'></i>
                    <span class="links_name">Schools</span>
                </a>
            </li>
            <li>
                <a href="teaching_activity.php" class="active">
                    <i class='bx bx-map-pin'></i>
                    <span class="links_name">Teaching Activity</span>
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
                <span class="dashboard">Teaching Activity Upload</span>
            </div>
            <div class="profile-details">
                <img src="<?php echo $_SESSION['img']; ?>" alt="profile">
                <span class="admin_name"><?php echo $_SESSION['fname']; ?></span>
            </div>
        </nav>

        <div class="home-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class='bx bx-map-pin'></i> Teaching Activity Verification</h1>
                <p>Upload geotagged photos to verify your teaching activity at enrolled schools</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-row">
                <div class="stat-box total">
                    <i class='bx bx-images'></i>
                    <div>
                        <span class="stat-number"><?= $stats['total'] ?></span>
                        <span class="stat-label">Total Uploads</span>
                    </div>
                </div>
                <div class="stat-box pending">
                    <i class='bx bx-time-five'></i>
                    <div>
                        <span class="stat-number"><?= $stats['pending'] ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
                <div class="stat-box approved">
                    <i class='bx bx-check-circle'></i>
                    <div>
                        <span class="stat-number"><?= $stats['approved'] ?></span>
                        <span class="stat-label">Approved</span>
                    </div>
                </div>
                <div class="stat-box rejected">
                    <i class='bx bx-x-circle'></i>
                    <div>
                        <span class="stat-number"><?= $stats['rejected'] ?></span>
                        <span class="stat-label">Rejected</span>
                    </div>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="content-card upload-section">
                <div class="card-header">
                    <h2><i class='bx bx-cloud-upload'></i> New Submission</h2>
                </div>
                <div class="card-body">
                    <?php if (count($school_list) === 0): ?>
                        <div class="alert alert-warning">
                            <i class='bx bx-info-circle'></i>
                            You are not enrolled in any schools. Please contact your administrator.
                        </div>
                    <?php else: ?>
                        <form id="activityUploadForm" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="school_id">
                                        <i class='bx bx-building-house'></i> Select School
                                    </label>
                                    <select name="school_id" id="school_id" required>
                                        <option value="">-- Select School --</option>
                                        <?php foreach ($school_list as $school): ?>
                                            <option value="<?= $school['school_id'] ?>">
                                                <?= htmlspecialchars($school['school_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="activity_date">
                                        <i class='bx bx-calendar'></i> Activity Date
                                    </label>
                                    <input type="date" name="activity_date" id="activity_date"
                                           max="<?= date('Y-m-d') ?>" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="photo">
                                    <i class='bx bx-camera'></i> Geotagged Photo
                                </label>
                                <div class="file-upload-wrapper">
                                    <input type="file" name="photo" id="photo"
                                           accept="image/jpeg,image/png,image/heic" required>
                                    <div class="file-upload-info">
                                        <i class='bx bx-image-add'></i>
                                        <span id="file-name">Click to select or drag & drop image</span>
                                    </div>
                                </div>
                                <small class="help-text">
                                    <i class='bx bx-info-circle'></i>
                                    Photo must contain GPS location data (taken with location services enabled).
                                    Max size: 10MB. Formats: JPG, PNG, HEIC
                                </small>
                            </div>

                            <div id="imagePreview" class="image-preview" style="display: none;">
                                <img id="previewImg" src="" alt="Preview">
                                <button type="button" id="removeImage" class="btn-remove">
                                    <i class='bx bx-x'></i> Remove
                                </button>
                            </div>

                            <button type="submit" class="btn btn-primary btn-upload" id="submitBtn">
                                <i class='bx bx-upload'></i> Upload Photo
                            </button>
                        </form>

                        <div id="uploadResult" class="result-message"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submission History -->
            <div class="content-card history-section">
                <div class="card-header">
                    <h2><i class='bx bx-history'></i> Submission History</h2>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($history) === 0): ?>
                        <div class="empty-state">
                            <i class='bx bx-image-alt'></i>
                            <p>No submissions yet. Upload your first teaching activity photo!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Activity Date</th>
                                        <th>School</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Uploaded</th>
                                        <th>Admin Remarks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($history)): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('M d, Y', strtotime($row['activity_date'])) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($row['school_name']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $row['verification_status'] ?>">
                                                <?php
                                                $icons = ['pending' => 'bx-time-five', 'approved' => 'bx-check', 'rejected' => 'bx-x'];
                                                ?>
                                                <i class='bx <?= $icons[$row['verification_status']] ?>'></i>
                                                <?= ucfirst($row['verification_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['location_match_status'] === 'matched'): ?>
                                                <span class="location-matched">
                                                    <i class='bx bx-check-circle'></i>
                                                    <?= $row['distance_from_school'] ? round($row['distance_from_school']) . 'm' : 'Matched' ?>
                                                </span>
                                            <?php elseif ($row['location_match_status'] === 'mismatched'): ?>
                                                <span class="location-mismatched">
                                                    <i class='bx bx-error-circle'></i>
                                                    <?= $row['distance_from_school'] ? round($row['distance_from_school']) . 'm away' : 'Mismatch' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="location-unknown">
                                                    <i class='bx bx-question-mark'></i> N/A
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('M d, Y H:i', strtotime($row['upload_date'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($row['admin_remarks']): ?>
                                                <span class="remarks" title="<?= htmlspecialchars($row['admin_remarks']) ?>">
                                                    <?= htmlspecialchars(substr($row['admin_remarks'], 0, 30)) ?>
                                                    <?= strlen($row['admin_remarks']) > 30 ? '...' : '' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-icon btn-view" onclick="viewSubmission(<?= $row['id'] ?>)" title="View Details">
                                                <i class='bx bx-show'></i>
                                            </button>
                                            <?php if ($row['verification_status'] === 'pending'): ?>
                                                <button class="btn-icon btn-delete" onclick="deleteSubmission(<?= $row['id'] ?>)" title="Delete">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- View Submission Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class='bx bx-image'></i> Submission Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                Loading...
            </div>
        </div>
    </div>

    <script>
    // File input handling
    const fileInput = document.getElementById('photo');
    const fileName = document.getElementById('file-name');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const removeBtn = document.getElementById('removeImage');

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                fileName.textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        removeBtn.addEventListener('click', function() {
            fileInput.value = '';
            fileName.textContent = 'Click to select or drag & drop image';
            imagePreview.style.display = 'none';
            previewImg.src = '';
        });
    }

    // Form submission
    const form = document.getElementById('activityUploadForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const resultDiv = document.getElementById('uploadResult');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Uploading...';
            resultDiv.innerHTML = '';
            resultDiv.className = 'result-message';

            const formData = new FormData(form);

            try {
                const response = await fetch('upload_activity.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class='bx bx-check-circle'></i>
                            <div>
                                <strong>${result.message}</strong>
                                <p>${result.location_status || ''}</p>
                            </div>
                        </div>
                    `;
                    form.reset();
                    fileName.textContent = 'Click to select or drag & drop image';
                    imagePreview.style.display = 'none';
                    
                    // Update remaining uploads counter
                    if (result.remaining_uploads !== undefined) {
                        document.getElementById('remainingUploads').textContent = result.remaining_uploads;
                    }
                    
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-error">
                            <i class='bx bx-error-circle'></i>
                            <div><strong>Error:</strong> ${result.error}</div>
                        </div>
                    `;
                }
            } catch (err) {
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <i class='bx bx-error-circle'></i>
                        <div><strong>Upload failed:</strong> ${err.message}</div>
                    </div>
                `;
            }

            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bx bx-upload"></i> Upload Photo';
        });
    }

    // View submission
    function viewSubmission(id) {
        const modal = document.getElementById('viewModal');
        const modalBody = document.getElementById('modalBody');
        
        modal.style.display = 'flex';
        modalBody.innerHTML = '<div class="loading"><i class="bx bx-loader-alt bx-spin"></i> Loading...</div>';

        fetch('get_activity_details.php?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    modalBody.innerHTML = '<div class="alert alert-error">' + data.error + '</div>';
                    return;
                }
                
                modalBody.innerHTML = `
                    <div class="submission-details">
                        <div class="detail-image">
                            <img src="../${data.image_path}" alt="Teaching Activity Photo">
                        </div>
                        <div class="detail-info">
                            <div class="info-row">
                                <label>School:</label>
                                <span>${data.school_name}</span>
                            </div>
                            <div class="info-row">
                                <label>Activity Date:</label>
                                <span>${data.activity_date}</span>
                            </div>
                            <div class="info-row">
                                <label>Photo Taken:</label>
                                <span>${data.photo_taken_at || 'Unknown'}</span>
                            </div>
                            <div class="info-row">
                                <label>GPS Coordinates:</label>
                                <span>${data.gps_latitude && data.gps_longitude 
                                    ? data.gps_latitude + ', ' + data.gps_longitude 
                                    : 'Not available'}</span>
                            </div>
                            <div class="info-row">
                                <label>Location Status:</label>
                                <span class="badge badge-${data.location_match_status}">${data.location_match_status}</span>
                            </div>
                            <div class="info-row">
                                <label>Distance from School:</label>
                                <span>${data.distance_from_school ? Math.round(data.distance_from_school) + ' meters' : 'N/A'}</span>
                            </div>
                            <div class="info-row">
                                <label>Verification Status:</label>
                                <span class="badge badge-${data.verification_status}">${data.verification_status}</span>
                            </div>
                            ${data.admin_remarks ? `
                            <div class="info-row">
                                <label>Admin Remarks:</label>
                                <span>${data.admin_remarks}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            })
            .catch(err => {
                modalBody.innerHTML = '<div class="alert alert-error">Failed to load details</div>';
            });
    }

    function closeModal() {
        document.getElementById('viewModal').style.display = 'none';
    }

    // Close modal on outside click
    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Delete submission
    function deleteSubmission(id) {
        if (!confirm('Are you sure you want to delete this submission? This cannot be undone.')) {
            return;
        }

        fetch('delete_activity.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Submission deleted successfully');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(err => {
            alert('Failed to delete: ' + err.message);
        });
    }

    // Sidebar toggle
    let sidebar = document.querySelector(".sidebar");
    let sidebarBtn = document.querySelector(".sidebarBtn");
    sidebarBtn.onclick = function() {
        sidebar.classList.toggle("active");
    };
    </script>
</body>
</html>
