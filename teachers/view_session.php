<?php
/**
 * Teacher Portal - Session Details & Photo Upload (Combined)
 * Phase 5: View session details and upload geotagged photo proof
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

// Support both ?id= and ?session= parameters
$session_id = intval($_GET['id'] ?? $_GET['session'] ?? 0);

if ($session_id <= 0) {
    header("Location: my_slots.php?error=Invalid session");
    exit;
}

// Get session details with school info
$session_sql = "SELECT ts.*, ste.teacher_id, ste.enrollment_status, ste.booked_at,
                sts.slot_date, sts.start_time, sts.end_time, sts.slot_status, sts.description as slot_desc,
                sts.teachers_required, sts.teachers_enrolled,
                s.school_id, s.school_name, s.full_address, s.gps_latitude as school_lat, 
                s.gps_longitude as school_lng, s.allowed_radius, s.contact_person, s.contact_phone,
                a.fname as verified_by_name
                FROM teaching_sessions ts
                JOIN slot_teacher_enrollments ste ON ts.enrollment_id = ste.enrollment_id
                JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                JOIN schools s ON sts.school_id = s.school_id
                LEFT JOIN admin a ON ts.verified_by = a.id
                WHERE ts.session_id = ? AND ste.teacher_id = ?";
$stmt = mysqli_prepare($conn, $session_sql);
mysqli_stmt_bind_param($stmt, "ii", $session_id, $teacher_id);
mysqli_stmt_execute($stmt);
$session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$session) {
    header("Location: my_slots.php?error=Session not found or access denied");
    exit;
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['session_photo'])) {
    // Check if already approved
    if ($session['session_status'] === 'approved') {
        $error = "Session already approved. Cannot change photo.";
    } else {
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
                        if (!empty($warnings)) {
                            $message .= " However, there are some concerns that admin will review.";
                        }
                        // Refresh session data
                        $stmt = mysqli_prepare($conn, $session_sql);
                        mysqli_stmt_bind_param($stmt, "ii", $session_id, $teacher_id);
                        mysqli_stmt_execute($stmt);
                        $session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
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
}

$is_today = $session['slot_date'] === date('Y-m-d');
$is_past = strtotime($session['slot_date']) < strtotime(date('Y-m-d'));
$can_upload = ($is_today || $is_past) && $session['session_status'] !== 'approved';
$distance_ok = $session['distance_from_school'] !== null && 
               $session['distance_from_school'] <= ($session['allowed_radius'] ?? 500);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Session #<?= $session_id ?> - <?= htmlspecialchars($session['school_name']) ?></title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .session-container {
            padding: 20px;
            max-width: 1100px;
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
        
        /* Session Header */
        .session-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        .session-header h1 {
            font-size: 24px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .session-header p {
            opacity: 0.9;
            margin-bottom: 15px;
        }
        .session-meta {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            font-size: 14px;
        }
        .session-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.photo_submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        
        /* Tab Navigation */
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .tab-btn:hover { background: #f5f5f5; color: #333; }
        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 25px;
        }
        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            padding: 18px 20px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-body {
            padding: 20px;
        }
        
        /* Photo Section */
        .photo-display img {
            width: 100%;
            border-radius: 10px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .photo-display img:hover { opacity: 0.95; }
        .photo-meta {
            margin-top: 15px;
            font-size: 14px;
        }
        .photo-meta-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .photo-meta-row:last-child { border-bottom: none; }
        .photo-meta-row label { color: #888; }
        .photo-meta-row span { font-weight: 500; }
        .photo-meta-row span.success { color: #10b981; }
        .photo-meta-row span.warning { color: #f59e0b; }
        .photo-meta-row span.danger { color: #ef4444; }
        
        /* Upload Section */
        .upload-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
        }
        .upload-section h3 {
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        .requirements li { margin-bottom: 5px; }
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
        .upload-area p { color: #666; margin-bottom: 10px; }
        .upload-area small { color: #999; font-size: 12px; }
        #fileInput { display: none; }
        .preview-section {
            margin-top: 20px;
            display: none;
        }
        .preview-section.active { display: block; }
        .preview-img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .empty-photo {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
        }
        .empty-photo i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
        .empty-photo p { color: #888; margin-bottom: 15px; }
        
        /* Map */
        #map {
            height: 220px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        /* Info Rows */
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row i {
            color: #667eea;
            font-size: 18px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }
        .info-row .info-content { flex: 1; }
        .info-row .info-label { font-size: 12px; color: #999; }
        .info-row .info-value { font-weight: 500; color: #333; }
        
        /* Timeline */
        .timeline { margin-top: 10px; }
        .timeline-item {
            display: flex;
            gap: 15px;
            padding-bottom: 15px;
            position: relative;
        }
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 28px;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }
        .timeline-dot.active { background: #10b981; color: white; }
        .timeline-dot.pending { background: #f59e0b; color: white; }
        .timeline-content h4 { font-size: 14px; margin-bottom: 3px; }
        .timeline-content p { font-size: 12px; color: #888; }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert i { font-size: 20px; flex-shrink: 0; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-warning { background: #fef3c7; color: #92400e; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-info { background: #dbeafe; color: #1e40af; }
        .warning-list { margin-top: 10px; }
        .warning-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.5);
            border-radius: 6px;
            margin-top: 8px;
            font-size: 13px;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
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
        .btn-secondary { background: #f0f0f0; color: #333; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-block { width: 100%; justify-content: center; }
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
            <li><a href="messages.php"><i class='bx bx-message'></i><span class="links_name">Messages</span></a></li>
            <li><a href="school_management.php"><i class='bx bx-building-house'></i><span class="links_name">Schools</span></a></li>
            <li><a href="browse_slots.php"><i class='bx bx-calendar-check'></i><span class="links_name">Teaching Slots</span></a></li>
            <li><a href="my_slots.php" class="active"><i class='bx bx-calendar'></i><span class="links_name">My Bookings</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>
    
    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Session Details</span>
            </div>
        </nav>
        
        <div class="session-container">
            <a href="my_slots.php" class="back-link">
                <i class='bx bx-arrow-back'></i> Back to My Bookings
            </a>
            
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle'></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($warnings)): ?>
            <div class="alert alert-warning">
                <i class='bx bx-error'></i>
                <div>
                    <strong>Photo uploaded with warnings:</strong>
                    <div class="warning-list">
                        <?php foreach ($warnings as $w): ?>
                        <div class="warning-item">
                            <i class='bx bx-info-circle'></i>
                            <span><?= htmlspecialchars($w) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['info'])): ?>
            <div class="alert alert-info">
                <i class='bx bx-info-circle'></i>
                <div><?= htmlspecialchars($_GET['info']) ?></div>
            </div>
            <?php endif; ?>
            
            <!-- Session Header -->
            <div class="session-header">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h1><i class='bx bx-building-house'></i> <?= htmlspecialchars($session['school_name']) ?></h1>
                        <p><?= htmlspecialchars($session['full_address'] ?: 'No address provided') ?></p>
                    </div>
                    <span class="status-badge <?= $session['session_status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?>
                    </span>
                </div>
                <div class="session-meta">
                    <span><i class='bx bx-calendar'></i> <?= date('l, F j, Y', strtotime($session['slot_date'])) ?></span>
                    <span><i class='bx bx-time'></i> <?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></span>
                    <span><i class='bx bx-hash'></i> Session #<?= $session_id ?></span>
                </div>
            </div>
            
            <!-- Tab Navigation -->
            <div class="tabs">
                <button class="tab-btn active" data-tab="photo">
                    <i class='bx bx-camera'></i> Photo Proof
                </button>
                <button class="tab-btn" data-tab="details">
                    <i class='bx bx-info-circle'></i> Slot Details
                </button>
                <button class="tab-btn" data-tab="timeline">
                    <i class='bx bx-list-check'></i> Timeline
                </button>
            </div>
            
            <!-- Photo Tab -->
            <div class="tab-content active" id="tab-photo">
                <div class="content-grid">
                    <div>
                        <?php if ($session['photo_path']): ?>
                        <!-- Uploaded Photo -->
                        <div class="card">
                            <div class="card-header">
                                <i class='bx bx-image'></i> Uploaded Photo
                            </div>
                            <div class="card-body">
                                <div class="photo-display">
                                    <img src="../<?= htmlspecialchars($session['photo_path']) ?>" 
                                         alt="Session Photo" 
                                         onclick="window.open('../<?= htmlspecialchars($session['photo_path']) ?>', '_blank')">
                                </div>
                                
                                <div class="photo-meta">
                                    <div class="photo-meta-row">
                                        <label>Uploaded</label>
                                        <span><?= date('M j, Y h:i A', strtotime($session['photo_uploaded_at'])) ?></span>
                                    </div>
                                    <?php if ($session['photo_taken_at']): ?>
                                    <div class="photo-meta-row">
                                        <label>Photo Taken</label>
                                        <span><?= date('M j, Y h:i A', strtotime($session['photo_taken_at'])) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($session['gps_latitude'] && $session['gps_longitude']): ?>
                                    <div class="photo-meta-row">
                                        <label>GPS Location</label>
                                        <span><?= number_format($session['gps_latitude'], 6) ?>, <?= number_format($session['gps_longitude'], 6) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($session['distance_from_school'] !== null): ?>
                                    <div class="photo-meta-row">
                                        <label>Distance from School</label>
                                        <span class="<?= $distance_ok ? 'success' : 'danger' ?>">
                                            <?= number_format($session['distance_from_school']) ?>m
                                            <?= $distance_ok ? '✓' : '✗' ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($session['gps_latitude'] && $session['gps_longitude']): ?>
                                <div id="map"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($session['session_status'] === 'rejected'): ?>
                        <div class="alert alert-error">
                            <i class='bx bx-x-circle'></i>
                            <div>
                                <strong>Photo Rejected</strong>
                                <?php if ($session['admin_remarks']): ?>
                                <p style="margin-top: 8px;"><?= htmlspecialchars($session['admin_remarks']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($can_upload && $session['session_status'] !== 'approved'): ?>
                        <!-- Replace Photo Section -->
                        <div class="card">
                            <div class="card-header">
                                <i class='bx bx-upload'></i> Replace Photo
                            </div>
                            <div class="card-body">
                                <div class="upload-section">
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
                                            <div style="display: flex; gap: 10px;">
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
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <!-- No Photo Yet -->
                        <div class="card">
                            <div class="card-header">
                                <i class='bx bx-camera'></i> Session Photo
                            </div>
                            <div class="card-body">
                                <?php if ($can_upload): ?>
                                <div class="upload-section">
                                    <h3><i class='bx bx-upload'></i> Upload Session Photo</h3>
                                    
                                    <div class="requirements">
                                        <h4><i class='bx bx-list-check'></i> Photo Requirements</h4>
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
                                            <div style="display: flex; gap: 10px;">
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
                                <?php else: ?>
                                <div class="empty-photo">
                                    <i class='bx bx-camera-off'></i>
                                    <p>No photo uploaded yet</p>
                                    <?php if (!$is_today && !$is_past): ?>
                                    <p style="font-size: 13px; color: #888;">
                                        Upload will be available on <?= date('M j, Y', strtotime($session['slot_date'])) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($session['admin_remarks'] && $session['session_status'] === 'approved'): ?>
                        <div class="card">
                            <div class="card-header"><i class='bx bx-message-dots'></i> Admin Feedback</div>
                            <div class="card-body">
                                <p><?= htmlspecialchars($session['admin_remarks']) ?></p>
                                <?php if ($session['verified_by_name'] && $session['verified_at']): ?>
                                <p style="font-size: 13px; color: #888; margin-top: 10px;">
                                    — <?= htmlspecialchars($session['verified_by_name']) ?>, 
                                    <?= date('M j, Y h:i A', strtotime($session['verified_at'])) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sidebar Info -->
                    <div>
                        <div class="card">
                            <div class="card-header"><i class='bx bx-info-circle'></i> Quick Info</div>
                            <div class="card-body">
                                <div class="info-row">
                                    <i class='bx bx-building-house'></i>
                                    <div class="info-content">
                                        <div class="info-label">School</div>
                                        <div class="info-value"><?= htmlspecialchars($session['school_name']) ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <i class='bx bx-calendar'></i>
                                    <div class="info-content">
                                        <div class="info-label">Date</div>
                                        <div class="info-value"><?= date('M j, Y', strtotime($session['slot_date'])) ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <i class='bx bx-time'></i>
                                    <div class="info-content">
                                        <div class="info-label">Time</div>
                                        <div class="info-value"><?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <i class='bx bx-check-circle'></i>
                                    <div class="info-content">
                                        <div class="info-label">Status</div>
                                        <div class="info-value"><?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Details Tab -->
            <div class="tab-content" id="tab-details">
                <div class="content-grid">
                    <div>
                        <div class="card">
                            <div class="card-header"><i class='bx bx-building-house'></i> School Information</div>
                            <div class="card-body">
                                <div class="info-row">
                                    <i class='bx bx-map'></i>
                                    <div class="info-content">
                                        <div class="info-label">Address</div>
                                        <div class="info-value"><?= htmlspecialchars($session['full_address'] ?: 'Not provided') ?></div>
                                    </div>
                                </div>
                                <?php if ($session['contact_person']): ?>
                                <div class="info-row">
                                    <i class='bx bx-user'></i>
                                    <div class="info-content">
                                        <div class="info-label">Contact Person</div>
                                        <div class="info-value"><?= htmlspecialchars($session['contact_person']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($session['contact_phone']): ?>
                                <div class="info-row">
                                    <i class='bx bx-phone'></i>
                                    <div class="info-content">
                                        <div class="info-label">Phone</div>
                                        <div class="info-value"><?= htmlspecialchars($session['contact_phone']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($session['allowed_radius']): ?>
                                <div class="info-row">
                                    <i class='bx bx-target-lock'></i>
                                    <div class="info-content">
                                        <div class="info-label">Allowed Radius</div>
                                        <div class="info-value"><?= $session['allowed_radius'] ?>m from school</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header"><i class='bx bx-calendar-check'></i> Slot Information</div>
                            <div class="card-body">
                                <div class="info-row">
                                    <i class='bx bx-calendar'></i>
                                    <div class="info-content">
                                        <div class="info-label">Date</div>
                                        <div class="info-value"><?= date('l, F j, Y', strtotime($session['slot_date'])) ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <i class='bx bx-time'></i>
                                    <div class="info-content">
                                        <div class="info-label">Time</div>
                                        <div class="info-value"><?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <i class='bx bx-group'></i>
                                    <div class="info-content">
                                        <div class="info-label">Teachers</div>
                                        <div class="info-value"><?= $session['teachers_enrolled'] ?>/<?= $session['teachers_required'] ?> enrolled</div>
                                    </div>
                                </div>
                                <?php if ($session['slot_desc']): ?>
                                <div class="info-row">
                                    <i class='bx bx-note'></i>
                                    <div class="info-content">
                                        <div class="info-label">Notes</div>
                                        <div class="info-value"><?= htmlspecialchars($session['slot_desc']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="card">
                            <div class="card-header"><i class='bx bx-bookmark'></i> Booking Details</div>
                            <div class="card-body">
                                <div class="info-row">
                                    <i class='bx bx-calendar-plus'></i>
                                    <div class="info-content">
                                        <div class="info-label">Booked At</div>
                                        <div class="info-value"><?= date('M j, Y h:i A', strtotime($session['booked_at'])) ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <i class='bx bx-check-shield'></i>
                                    <div class="info-content">
                                        <div class="info-label">Enrollment Status</div>
                                        <div class="info-value"><?= ucfirst($session['enrollment_status']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Timeline Tab -->
            <div class="tab-content" id="tab-timeline">
                <div class="card" style="max-width: 600px;">
                    <div class="card-header"><i class='bx bx-list-check'></i> Session Progress</div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-dot active">✓</div>
                                <div class="timeline-content">
                                    <h4>Slot Booked</h4>
                                    <p><?= date('M j, Y h:i A', strtotime($session['booked_at'])) ?></p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-dot <?= $session['photo_path'] ? 'active' : ($can_upload ? 'pending' : '') ?>">
                                    <?= $session['photo_path'] ? '✓' : '2' ?>
                                </div>
                                <div class="timeline-content">
                                    <h4>Photo Uploaded</h4>
                                    <p>
                                        <?php if ($session['photo_uploaded_at']): ?>
                                        <?= date('M j, Y h:i A', strtotime($session['photo_uploaded_at'])) ?>
                                        <?php elseif ($can_upload): ?>
                                        Awaiting upload
                                        <?php else: ?>
                                        Pending
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-dot <?= $session['session_status'] === 'approved' ? 'active' : ($session['session_status'] === 'photo_submitted' ? 'pending' : '') ?>">
                                    <?= $session['session_status'] === 'approved' ? '✓' : '3' ?>
                                </div>
                                <div class="timeline-content">
                                    <h4>Admin Verification</h4>
                                    <p>
                                        <?php if ($session['verified_at']): ?>
                                        <?= date('M j, Y h:i A', strtotime($session['verified_at'])) ?>
                                        <?php elseif ($session['session_status'] === 'photo_submitted'): ?>
                                        Under review
                                        <?php else: ?>
                                        Pending
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($session['session_status'] === 'approved'): ?>
                        <div class="alert alert-success" style="margin-top: 20px; margin-bottom: 0;">
                            <i class='bx bx-check-circle'></i>
                            <div>
                                <strong>Session Approved!</strong>
                                <?php if ($session['verified_by_name']): ?>
                                <p style="margin-top: 5px; font-size: 13px;">
                                    Verified by <?= htmlspecialchars($session['verified_by_name']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
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
        
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
            });
        });
        
        // File upload handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const previewSection = document.getElementById('previewSection');
        const previewImg = document.getElementById('previewImg');
        
        if (dropZone) {
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
        }
        
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    showPreview(this.files[0]);
                }
            });
        }
        
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
        
        // Map initialization
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
                    html: '<div style="background:#667eea;color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:0 2px 5px rgba(0,0,0,0.3);">🏫</div>'
                })
            }).addTo(map).bindPopup('School Location');
            
            // Radius circle
            L.circle([schoolLat, schoolLng], {
                color: '#667eea',
                fillColor: '#667eea',
                fillOpacity: 0.1,
                radius: allowedRadius
            }).addTo(map);
            
            // Photo marker
            L.marker([photoLat, photoLng], {
                icon: L.divIcon({
                    className: 'photo-marker',
                    html: '<div style="background:#10b981;color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:0 2px 5px rgba(0,0,0,0.3);">📷</div>'
                })
            }).addTo(map).bindPopup('Photo Location');
            
            // Line between
            L.polyline([[schoolLat, schoolLng], [photoLat, photoLng]], {
                color: '#f59e0b',
                dashArray: '5, 10',
                weight: 2
            }).addTo(map);
        });
        <?php endif; ?>
    </script>
</body>
</html>
