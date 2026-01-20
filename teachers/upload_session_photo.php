<?php
/**
 * Teacher Portal - Upload Session Photo
 * Phase 5: Geotagged photo proof for teaching sessions
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/exif_extractor.php';
require_once '../utils/location_validator.php';

$teacher_id = $_SESSION['user_id'];
$message = '';
$error = '';
$warnings = [];
$severe_issues = []; // Track issues that would trigger auto-rejection

$session_id = intval($_GET['session'] ?? 0);

if ($session_id <= 0) {
    header("Location: my_slots.php?error=Invalid session");
    exit;
}

// Get session details with school info
$session_sql = "SELECT ts.*, ste.teacher_id, ste.enrollment_status,
                sts.slot_date, sts.start_time, sts.end_time, sts.slot_status,
                s.school_id, s.school_name, s.full_address, s.gps_latitude as school_lat, 
                s.gps_longitude as school_lng, s.allowed_radius
                FROM teaching_sessions ts
                JOIN slot_teacher_enrollments ste ON ts.enrollment_id = ste.enrollment_id
                JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                JOIN schools s ON sts.school_id = s.school_id
                WHERE ts.session_id = ? AND ste.teacher_id = ?";
$stmt = mysqli_prepare($conn, $session_sql);
mysqli_stmt_bind_param($stmt, "ii", $session_id, $teacher_id);
mysqli_stmt_execute($stmt);
$session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$session) {
    header("Location: my_slots.php?error=Session not found or access denied");
    exit;
}

// Check if already approved
if ($session['session_status'] === 'approved') {
    header("Location: view_session.php?id=" . $session_id . "&info=Session already approved");
    exit;
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['session_photo'])) {
    $file = $_FILES['session_photo'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error. Please try again.";
    } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        $error = "File size too large. Maximum 10MB allowed.";
    } else {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $error = "Invalid file type. Only JPEG and PNG allowed.";
        } else {
            // Create upload directory
            $upload_dir = '../uploads/session_photos/' . date('Y/m');
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'session_' . $session_id . '_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $upload_dir . '/' . $filename;
            $relative_path = 'uploads/session_photos/' . date('Y/m') . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Extract EXIF data
                $gps_data = ExifExtractor::extractGPS($filepath);
                $timestamp = ExifExtractor::extractTimestamp($filepath);
                $device_info = ExifExtractor::extractDeviceInfo($filepath);
                
                $photo_lat = null;
                $photo_lng = null;
                $distance = null;
                
                // Process GPS data
                if (isset($gps_data['latitude']) && isset($gps_data['longitude'])) {
                    $photo_lat = $gps_data['latitude'];
                    $photo_lng = $gps_data['longitude'];
                    
                    // Calculate distance from school
                    if ($session['school_lat'] && $session['school_lng']) {
                        $distance = LocationValidator::calculateDistance(
                            $photo_lat, $photo_lng,
                            $session['school_lat'], $session['school_lng']
                        );
                        
                        // Check if within allowed radius (default 500m)
                        $allowed_radius = $session['allowed_radius'] ?? 500;
                        if ($distance > $allowed_radius) {
                            $warnings[] = "Photo location is " . round($distance) . "m from school (allowed: {$allowed_radius}m)";
                            // Auto-reject if distance exceeds 2x allowed radius
                            if ($distance > ($allowed_radius * 2)) {
                                $severe_issues[] = "Location too far from school (auto-rejected)";
                            }
                        }
                    }
                } else {
                    $warnings[] = "No GPS data found in photo. Location cannot be verified.";
                }
                
                // Check photo date
                $photo_taken_at = null;
                if ($timestamp) {
                    $photo_taken_at = $timestamp->format('Y-m-d H:i:s');
                    $photo_date = $timestamp->format('Y-m-d');
                    
                    if ($photo_date !== $session['slot_date']) {
                        $warnings[] = "Photo date ({$photo_date}) doesn't match session date ({$session['slot_date']})";
                    }
                } else {
                    $warnings[] = "Could not determine when photo was taken.";
                }
                
                // Update session with photo data
                $update_sql = "UPDATE teaching_sessions SET 
                              photo_path = ?,
                              photo_uploaded_at = NOW(),
                              gps_latitude = ?,
                              gps_longitude = ?,
                              photo_taken_at = ?,
                              distance_from_school = ?,
                              session_status = 'photo_submitted'
                              WHERE session_id = ?";
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, "sddsdi", 
                    $relative_path, $photo_lat, $photo_lng, $photo_taken_at, $distance, $session_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Photo uploaded successfully!";
                    if (!empty($severe_issues)) {
                        // Auto-rejection will occur - show clear message
                        $message = "Photo uploaded, but session will be auto-rejected due to: " . implode("; ", $severe_issues);
                    } elseif (!empty($warnings)) {
                        $message .= " However, there are some concerns that admin will review.";
                    }
                    // Refresh session data
                    $stmt = mysqli_prepare($conn, $session_sql);
                    mysqli_stmt_bind_param($stmt, "ii", $session_id, $teacher_id);
                    mysqli_stmt_execute($stmt);
                    $session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    
                    // Update the fields for display
                    $session['photo_path'] = $relative_path;
                    $session['gps_latitude'] = $photo_lat;
                    $session['gps_longitude'] = $photo_lng;
                    $session['distance_from_school'] = $distance;
                    $session['session_status'] = 'photo_submitted';
                } else {
                    $error = "Failed to save photo info: " . mysqli_error($conn);
                    unlink($filepath); // Clean up uploaded file
                }
            } else {
                $error = "Failed to upload file. Please try again.";
            }
        }
    }
}

$is_today = $session['slot_date'] === date('Y-m-d');
$is_past = strtotime($session['slot_date']) < strtotime(date('Y-m-d'));
$can_upload = ($is_today || $is_past) && $session['session_status'] !== 'approved';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Upload Session Photo</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .upload-container {
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .back-link:hover { color: #667eea; }
        .session-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .session-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
        }
        .session-header h1 {
            font-size: 22px;
            margin-bottom: 5px;
        }
        .session-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
            font-size: 14px;
            opacity: 0.95;
        }
        .session-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .session-body {
            padding: 25px;
        }
        .upload-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .upload-section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #667eea;
            background: #f8f0ff;
        }
        .upload-area.dragover {
            border-color: #667eea;
            background: #f0e6ff;
        }
        .upload-area i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        .upload-area p {
            color: #666;
            margin-bottom: 10px;
        }
        .upload-area small {
            color: #999;
            font-size: 12px;
        }
        #fileInput {
            display: none;
        }
        .preview-section {
            margin-top: 20px;
            display: none;
        }
        .preview-section.active {
            display: block;
        }
        .preview-img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-warning { background: #fef3c7; color: #92400e; }
        .alert-info { background: #dbeafe; color: #1e40af; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.photo_submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .current-photo {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .current-photo h3 {
            font-size: 16px;
            margin-bottom: 15px;
        }
        .current-photo img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 10px;
        }
        .photo-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .photo-meta-item {
            font-size: 14px;
        }
        .photo-meta-item label {
            color: #999;
            display: block;
            font-size: 12px;
            margin-bottom: 3px;
        }
        #map {
            height: 250px;
            border-radius: 10px;
            margin-top: 15px;
        }
        .requirements {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .requirements h4 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #856404;
        }
        .requirements ul {
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
            color: #856404;
        }
        .requirements li {
            margin-bottom: 5px;
        }
        .warning-list {
            margin-top: 15px;
        }
        .warning-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px;
            background: #fffbeb;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .warning-item i {
            color: #f59e0b;
            font-size: 18px;
            flex-shrink: 0;
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
            <li><a href="dash.php"><i class='bx bx-grid-alt'></i><span class="links_name">Dashboard</span></a></li>
            <li><a href="exams.php"><i class='bx bx-book-content'></i><span class="links_name">MCQ Exams</span></a></li>
            <li><a href="objective_exams.php"><i class='bx bx-edit'></i><span class="links_name">Objective Exams</span></a></li>
            <li><a href="results.php"><i class='bx bxs-bar-chart-alt-2'></i><span class="links_name">Results</span></a></li>
            <li><a href="records.php"><i class='bx bxs-user-circle'></i><span class="links_name">Records</span></a></li>
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="school_management.php"><i class='bx bx-building-house'></i><span class="links_name">Schools</span></a></li>
            <li><a href="browse_slots.php"><i class='bx bx-calendar-check'></i><span class="links_name">Teaching Slots</span></a></li>
            <li><a href="my_slots.php" class="active"><i class='bx bx-calendar'></i><span class="links_name">My Bookings</span></a></li>
            <li><a href="upload_material.php"><i class='bx bx-cloud-upload'></i><span class="links_name">Upload Material</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>
    
    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Upload Session Photo</span>
            </div>
        </nav>
        
        <div class="upload-container">
            <a href="my_slots.php" class="back-link">
                <i class='bx bx-arrow-back'></i> Back to My Bookings
            </a>
            
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i> <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($warnings)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Photo uploaded with warnings:</strong>
                <div class="warning-list">
                    <?php foreach ($warnings as $w): ?>
                    <div class="warning-item">
                        <i class='bx bx-error'></i>
                        <span><?= htmlspecialchars($w) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Session Info Card -->
            <div class="session-card">
                <div class="session-header">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h1>🏫 <?= htmlspecialchars($session['school_name']) ?></h1>
                            <p style="opacity: 0.9;"><?= htmlspecialchars($session['full_address'] ?: 'No address') ?></p>
                        </div>
                        <span class="status-badge <?= $session['session_status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?>
                        </span>
                    </div>
                    <div class="session-meta">
                        <span>📅 <?= date('l, F j, Y', strtotime($session['slot_date'])) ?></span>
                        <span>🕐 <?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></span>
                        <span>🎫 Session #<?= $session_id ?></span>
                    </div>
                </div>
                
                <div class="session-body">
                    <?php if ($session['photo_path']): ?>
                    <!-- Current Photo -->
                    <div class="current-photo">
                        <h3>📷 Uploaded Photo</h3>
                        <img src="../<?= htmlspecialchars($session['photo_path']) ?>" alt="Session Photo">
                        
                        <div class="photo-meta">
                            <div class="photo-meta-item">
                                <label>Uploaded At</label>
                                <?= date('M j, Y h:i A', strtotime($session['photo_uploaded_at'])) ?>
                            </div>
                            <?php if ($session['photo_taken_at']): ?>
                            <div class="photo-meta-item">
                                <label>Photo Taken</label>
                                <?= date('M j, Y h:i A', strtotime($session['photo_taken_at'])) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($session['gps_latitude'] && $session['gps_longitude']): ?>
                            <div class="photo-meta-item">
                                <label>GPS Location</label>
                                <?= number_format($session['gps_latitude'], 6) ?>, <?= number_format($session['gps_longitude'], 6) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($session['distance_from_school'] !== null): ?>
                            <div class="photo-meta-item">
                                <label>Distance from School</label>
                                <?= number_format($session['distance_from_school']) ?>m
                                <?php if ($session['distance_from_school'] <= ($session['allowed_radius'] ?? 500)): ?>
                                <span style="color: #10b981;">✓</span>
                                <?php else: ?>
                                <span style="color: #ef4444;">✗</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($session['gps_latitude'] && $session['gps_longitude'] && $session['school_lat'] && $session['school_lng']): ?>
                        <div id="map"></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($session['session_status'] === 'rejected'): ?>
                    <div class="alert alert-error">
                        <strong>❌ Photo Rejected</strong>
                        <?php if ($session['admin_remarks']): ?>
                        <p style="margin-top: 8px;"><?= htmlspecialchars($session['admin_remarks']) ?></p>
                        <?php endif; ?>
                        <p style="margin-top: 10px;">Please upload a new photo that meets the requirements.</p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($can_upload && $session['session_status'] !== 'approved'): ?>
                    <!-- Upload Section -->
                    <div class="upload-section">
                        <h2>
                            <?= $session['photo_path'] ? '📤 Replace Photo' : '📤 Upload Session Photo' ?>
                        </h2>
                        
                        <div class="requirements">
                            <h4>📋 Photo Requirements</h4>
                            <ul>
                                <li>Take the photo <strong>at the school location</strong> during your teaching session</li>
                                <li>Enable <strong>location services</strong> on your camera/phone</li>
                                <li>Photo should be taken on <strong><?= date('F j, Y', strtotime($session['slot_date'])) ?></strong></li>
                                <li>Maximum file size: 10MB (JPEG or PNG)</li>
                            </ul>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-area" id="dropZone" onclick="document.getElementById('fileInput').click()">
                                <i class='bx bx-cloud-upload'></i>
                                <p><strong>Click to select or drag & drop</strong></p>
                                <small>JPEG or PNG, max 10MB</small>
                            </div>
                            <input type="file" name="session_photo" id="fileInput" accept="image/jpeg,image/png">
                            
                            <div class="preview-section" id="previewSection">
                                <h3>Preview</h3>
                                <img id="previewImg" class="preview-img" src="">
                                <div style="margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class='bx bx-upload'></i> Upload Photo
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="clearPreview()">
                                        <i class='bx bx-x'></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php elseif (!$can_upload && !$is_today && !$is_past): ?>
                    <div class="alert alert-info">
                        <i class='bx bx-info-circle'></i> 
                        Photo upload will be available on <strong><?= date('F j, Y', strtotime($session['slot_date'])) ?></strong> (session date).
                    </div>
                    <?php elseif ($session['session_status'] === 'approved'): ?>
                    <div class="alert alert-success">
                        <i class='bx bx-check-circle'></i> 
                        This session has been <strong>approved</strong>. No further action needed.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <script>
        // Sidebar toggle
        let sidebar = document.querySelector(".sidebar");
        let sidebarBtn = document.querySelector(".sidebarBtn");
        sidebarBtn.onclick = function() {
            sidebar.classList.toggle("active");
        };
        
        // File upload handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const previewSection = document.getElementById('previewSection');
        const previewImg = document.getElementById('previewImg');
        
        // Drag & drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'));
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'));
        });
        
        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                showPreview(files[0]);
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                showPreview(this.files[0]);
            }
        });
        
        function showPreview(file) {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewSection.classList.add('active');
                    dropZone.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }
        
        function clearPreview() {
            previewSection.classList.remove('active');
            dropZone.style.display = 'block';
            fileInput.value = '';
            previewImg.src = '';
        }
        
        // Map
        <?php if ($session['gps_latitude'] && $session['gps_longitude'] && $session['school_lat'] && $session['school_lng']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const photoLat = <?= $session['gps_latitude'] ?>;
            const photoLng = <?= $session['gps_longitude'] ?>;
            const schoolLat = <?= $session['school_lat'] ?>;
            const schoolLng = <?= $session['school_lng'] ?>;
            const allowedRadius = <?= $session['allowed_radius'] ?? 500 ?>;
            
            const map = L.map('map').fitBounds([
                [Math.min(photoLat, schoolLat) - 0.002, Math.min(photoLng, schoolLng) - 0.002],
                [Math.max(photoLat, schoolLat) + 0.002, Math.max(photoLng, schoolLng) + 0.002]
            ]);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            
            // School marker
            L.marker([schoolLat, schoolLng], {
                icon: L.divIcon({
                    className: 'school-marker',
                    html: '<div style="background:#667eea;color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;">🏫</div>'
                })
            }).addTo(map).bindPopup('School Location');
            
            // Allowed radius circle
            L.circle([schoolLat, schoolLng], {
                color: '#667eea',
                fillColor: '#667eea',
                fillOpacity: 0.1,
                radius: allowedRadius
            }).addTo(map);
            
            // Photo location marker
            L.marker([photoLat, photoLng], {
                icon: L.divIcon({
                    className: 'photo-marker',
                    html: '<div style="background:#10b981;color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;">📷</div>'
                })
            }).addTo(map).bindPopup('Photo Location');
            
            // Line between them
            L.polyline([[schoolLat, schoolLng], [photoLat, photoLng]], {
                color: '#f59e0b',
                dashArray: '5, 10'
            }).addTo(map);
        });
        <?php endif; ?>
    </script>
</body>
</html>
