<?php
/**
 * Teacher Portal - Session Details & Dual Photo Upload
 * Phase 2: View session details and upload start/end geotagged photos
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/exif_extractor.php';
require_once '../utils/location_validator.php';
require_once '../utils/duration_validator.php';

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

// Determine which photo can be uploaded
$is_today = $session['slot_date'] === date('Y-m-d');
$is_past = strtotime($session['slot_date']) < strtotime(date('Y-m-d'));
$can_upload = ($is_today || $is_past);

// Determine upload type based on session status
$upload_type = null;
$upload_title = '';
$upload_instructions = '';

if ($can_upload) {
    if (in_array($session['session_status'], ['pending'])) {
        $upload_type = 'start';
        $upload_title = 'Upload Arrival Photo';
        $upload_instructions = 'Take a photo when you arrive at the school to start your teaching session.';
    } elseif (in_array($session['session_status'], ['start_submitted', 'start_approved'])) {
        $upload_type = 'end';
        $upload_title = 'Upload Completion Photo';
        $upload_instructions = 'Take a photo after you finish teaching to complete verification.';
    } elseif ($session['session_status'] === 'rejected') {
        // Allow re-upload of start photo if rejected
        $upload_type = 'start';
        $upload_title = 'Re-upload Arrival Photo';
        $upload_instructions = 'Your previous photo was rejected. Please upload a new arrival photo.';
    }
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['session_photo']) && $upload_type) {
    $file = $_FILES['session_photo'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error. Please try again.";
    } elseif ($file['size'] > 10 * 1024 * 1024) {
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
            $filename = 'session_' . $session_id . '_' . $upload_type . '_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $upload_dir . '/' . $filename;
            $relative_path = 'uploads/session_photos/' . date('Y/m') . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Extract EXIF data
                $gps_data = ExifExtractor::extractGPS($filepath);
                $timestamp = ExifExtractor::extractTimestamp($filepath);
                
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
                
                if ($upload_type === 'start') {
                    // Update start photo fields
                    $new_status = 'start_submitted';
                    $update_sql = "UPDATE teaching_sessions SET 
                                  start_photo_path = ?,
                                  start_photo_uploaded_at = NOW(),
                                  start_gps_latitude = ?,
                                  start_gps_longitude = ?,
                                  start_photo_taken_at = ?,
                                  start_distance_from_school = ?,
                                  session_status = ?
                                  WHERE session_id = ?";
                    $stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($stmt, "sddsdsi", 
                        $relative_path, $photo_lat, $photo_lng, $photo_taken_at, $distance, $new_status, $session_id);
                } else {
                    // End photo - calculate duration
                    $actual_duration = null;
                    $expected_duration = null;
                    $duration_verified = false;
                    
                    if ($session['start_photo_taken_at'] && $photo_taken_at) {
                        $actual_duration = DurationValidator::calculateDuration(
                            $session['start_photo_taken_at'], 
                            $photo_taken_at
                        );
                        $expected_duration = DurationValidator::calculateExpectedDuration(
                            $session['start_time'], 
                            $session['end_time']
                        );
                        $duration_verified = DurationValidator::verifyDuration($actual_duration, $expected_duration);
                        
                        // Add warning if duration is too short
                        if ($actual_duration < 0) {
                            $warnings[] = "End photo appears to be taken before start photo.";
                        } elseif (!DurationValidator::meetsMinimumDuration($actual_duration, $expected_duration)) {
                            $warnings[] = "Session duration (" . DurationValidator::formatDuration($actual_duration) . 
                                        ") is below expected (" . DurationValidator::formatDuration($expected_duration) . ")";
                        }
                    }
                    
                    $new_status = 'end_submitted';
                    $update_sql = "UPDATE teaching_sessions SET 
                                  end_photo_path = ?,
                                  end_photo_uploaded_at = NOW(),
                                  end_gps_latitude = ?,
                                  end_gps_longitude = ?,
                                  end_photo_taken_at = ?,
                                  end_distance_from_school = ?,
                                  actual_duration_minutes = ?,
                                  expected_duration_minutes = ?,
                                  duration_verified = ?,
                                  session_status = ?
                                  WHERE session_id = ?";
                    $stmt = mysqli_prepare($conn, $update_sql);
                    $duration_verified_int = $duration_verified ? 1 : 0;
                    mysqli_stmt_bind_param($stmt, "sddsdiiiis", 
                        $relative_path, $photo_lat, $photo_lng, $photo_taken_at, $distance,
                        $actual_duration, $expected_duration, $duration_verified_int, $new_status, $session_id);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = ucfirst($upload_type) . " photo uploaded successfully!";
                    if (!empty($warnings)) {
                        $message .= " However, there are some concerns that admin will review.";
                    }
                    // Refresh session data
                    $stmt = mysqli_prepare($conn, $session_sql);
                    mysqli_stmt_bind_param($stmt, "ii", $session_id, $teacher_id);
                    mysqli_stmt_execute($stmt);
                    $session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    
                    // Re-determine upload type after refresh
                    $upload_type = null;
                    if ($can_upload) {
                        if (in_array($session['session_status'], ['pending'])) {
                            $upload_type = 'start';
                        } elseif (in_array($session['session_status'], ['start_submitted', 'start_approved'])) {
                            $upload_type = 'end';
                        }
                    }
                } else {
                    $error = "Failed to save photo info: " . mysqli_error($conn);
                    unlink($filepath);
                }
            } else {
                $error = "Failed to upload file. Please try again.";
            }
        }
    }
}

// Calculate display values
$start_distance_ok = $session['start_distance_from_school'] !== null && 
                     $session['start_distance_from_school'] <= ($session['allowed_radius'] ?? 500);
$end_distance_ok = $session['end_distance_from_school'] !== null && 
                   $session['end_distance_from_school'] <= ($session['allowed_radius'] ?? 500);

// Get duration status if both photos exist
$duration_status = null;
if ($session['actual_duration_minutes'] !== null && $session['expected_duration_minutes'] !== null) {
    $duration_status = DurationValidator::getDurationStatus(
        $session['actual_duration_minutes'],
        $session['expected_duration_minutes']
    );
}
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
            max-width: 1200px;
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
        .session-header p { opacity: 0.9; margin-bottom: 15px; }
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
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.start_submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.start_approved { background: #d1fae5; color: #065f46; }
        .status-badge.end_submitted { background: #e0e7ff; color: #3730a3; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.partial { background: #fef3c7; color: #92400e; }
        
        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 25px 0;
            padding: 0 10px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 60%;
            width: 80%;
            height: 3px;
            background: #e5e7eb;
        }
        .step.completed:not(:last-child)::after { background: #10b981; }
        .step.active:not(:last-child)::after { background: linear-gradient(90deg, #10b981, #e5e7eb); }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            z-index: 1;
            color: #666;
        }
        .step.completed .step-circle { background: #10b981; color: white; }
        .step.active .step-circle { background: #f59e0b; color: white; animation: pulse 2s infinite; }
        .step.rejected .step-circle { background: #ef4444; color: white; }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
        }
        .step-label {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .step.completed .step-label { color: #10b981; font-weight: 500; }
        .step.active .step-label { color: #f59e0b; font-weight: 500; }
        
        /* Dual Photo Grid */
        .photo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        @media (max-width: 900px) {
            .photo-grid { grid-template-columns: 1fr; }
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
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
        .card-header.start { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .card-header.end { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .card-body { padding: 20px; }
        
        /* Photo Display */
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
        
        /* Empty Photo State */
        .empty-photo {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
        }
        .empty-photo i { font-size: 48px; color: #ccc; margin-bottom: 15px; }
        .empty-photo p { color: #888; margin-bottom: 10px; }
        .empty-photo small { color: #aaa; font-size: 12px; }
        
        /* Upload Section */
        .upload-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-top: 25px;
        }
        .upload-section h3 {
            font-size: 18px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .upload-section .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .requirements {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .requirements h4 { font-size: 14px; margin-bottom: 10px; color: #856404; }
        .requirements ul { margin: 0; padding-left: 20px; font-size: 13px; color: #856404; }
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
        .upload-area:hover { border-color: #667eea; background: #f8f0ff; }
        .upload-area.dragover { border-color: #667eea; background: #f0e6ff; }
        .upload-area i { font-size: 48px; color: #667eea; margin-bottom: 15px; }
        .upload-area p { color: #666; margin-bottom: 10px; }
        .upload-area small { color: #999; font-size: 12px; }
        #fileInput { display: none; }
        .preview-section { margin-top: 20px; display: none; }
        .preview-section.active { display: block; }
        .preview-img { max-width: 100%; max-height: 300px; border-radius: 10px; margin-bottom: 15px; }
        
        /* Duration Card */
        .duration-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .duration-card h3 {
            font-size: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .duration-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            text-align: center;
        }
        .duration-item label { font-size: 12px; color: #888; display: block; margin-bottom: 5px; }
        .duration-item .value { font-size: 24px; font-weight: 600; }
        .duration-item .value.success { color: #10b981; }
        .duration-item .value.warning { color: #f59e0b; }
        .duration-item .value.danger { color: #ef4444; }
        .duration-status {
            margin-top: 15px;
            padding: 12px 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }
        .duration-status.success { background: #dcfce7; color: #166534; }
        .duration-status.warning { background: #fef3c7; color: #92400e; }
        .duration-status.danger { background: #fee2e2; color: #991b1b; }
        .duration-status.info { background: #dbeafe; color: #1e40af; }
        
        /* Map */
        #map { height: 300px; border-radius: 10px; margin-top: 20px; }
        
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
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-secondary { background: #f0f0f0; color: #333; }
        .btn:hover { transform: translateY(-2px); }
        
        /* Info Rows */
        .info-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .info-section h3 {
            font-size: 16px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row i { color: #667eea; font-size: 18px; width: 24px; }
        .info-row .info-label { font-size: 12px; color: #999; }
        .info-row .info-value { font-weight: 500; color: #333; }
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
                    <?php foreach ($warnings as $w): ?>
                    <div class="warning-item">
                        <i class='bx bx-info-circle'></i>
                        <span><?= htmlspecialchars($w) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
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
            
            <!-- Progress Steps -->
            <div class="progress-steps">
                <?php
                $status = $session['session_status'];
                $step1_class = 'completed';
                $step2_class = in_array($status, ['start_submitted', 'start_approved', 'end_submitted', 'approved']) ? 'completed' : ($status === 'pending' && $can_upload ? 'active' : '');
                $step3_class = in_array($status, ['start_approved', 'end_submitted', 'approved']) ? 'completed' : ($status === 'start_submitted' ? 'active' : '');
                $step4_class = in_array($status, ['end_submitted', 'approved']) ? 'completed' : ($status === 'start_approved' ? 'active' : '');
                $step5_class = $status === 'approved' ? 'completed' : ($status === 'end_submitted' ? 'active' : '');
                if ($status === 'rejected') { $step2_class = 'rejected'; }
                ?>
                <div class="step <?= $step1_class ?>">
                    <div class="step-circle"><i class='bx bx-check'></i></div>
                    <div class="step-label">Slot Booked</div>
                </div>
                <div class="step <?= $step2_class ?>">
                    <div class="step-circle"><?= $step2_class === 'completed' ? "<i class='bx bx-check'></i>" : ($step2_class === 'rejected' ? "<i class='bx bx-x'></i>" : "2") ?></div>
                    <div class="step-label">Start Photo</div>
                </div>
                <div class="step <?= $step3_class ?>">
                    <div class="step-circle"><?= $step3_class === 'completed' ? "<i class='bx bx-check'></i>" : "3" ?></div>
                    <div class="step-label">Start Verified</div>
                </div>
                <div class="step <?= $step4_class ?>">
                    <div class="step-circle"><?= $step4_class === 'completed' ? "<i class='bx bx-check'></i>" : "4" ?></div>
                    <div class="step-label">End Photo</div>
                </div>
                <div class="step <?= $step5_class ?>">
                    <div class="step-circle"><?= $step5_class === 'completed' ? "<i class='bx bx-check'></i>" : "5" ?></div>
                    <div class="step-label">Approved</div>
                </div>
            </div>
            
            <!-- Dual Photo Grid -->
            <div class="photo-grid">
                <!-- Start Photo Card -->
                <div class="card">
                    <div class="card-header start">
                        <i class='bx bx-log-in-circle'></i> Arrival Photo (Start)
                    </div>
                    <div class="card-body">
                        <?php if ($session['start_photo_path']): ?>
                        <div class="photo-display">
                            <img src="../<?= htmlspecialchars($session['start_photo_path']) ?>" 
                                 alt="Start Photo" 
                                 onclick="window.open('../<?= htmlspecialchars($session['start_photo_path']) ?>', '_blank')">
                        </div>
                        <div class="photo-meta">
                            <div class="photo-meta-row">
                                <label>Uploaded</label>
                                <span><?= date('M j, h:i A', strtotime($session['start_photo_uploaded_at'])) ?></span>
                            </div>
                            <?php if ($session['start_photo_taken_at']): ?>
                            <div class="photo-meta-row">
                                <label>Photo Taken</label>
                                <span><?= date('M j, h:i A', strtotime($session['start_photo_taken_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($session['start_distance_from_school'] !== null): ?>
                            <div class="photo-meta-row">
                                <label>Distance</label>
                                <span class="<?= $start_distance_ok ? 'success' : 'danger' ?>">
                                    <?= number_format($session['start_distance_from_school']) ?>m
                                    <?= $start_distance_ok ? '✓' : '✗' ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-photo">
                            <i class='bx bx-camera'></i>
                            <p>No arrival photo uploaded</p>
                            <?php if ($upload_type === 'start'): ?>
                            <small>Upload your arrival photo below</small>
                            <?php elseif (!$can_upload): ?>
                            <small>Available on <?= date('M j', strtotime($session['slot_date'])) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- End Photo Card -->
                <div class="card">
                    <div class="card-header end">
                        <i class='bx bx-log-out-circle'></i> Completion Photo (End)
                    </div>
                    <div class="card-body">
                        <?php if ($session['end_photo_path']): ?>
                        <div class="photo-display">
                            <img src="../<?= htmlspecialchars($session['end_photo_path']) ?>" 
                                 alt="End Photo" 
                                 onclick="window.open('../<?= htmlspecialchars($session['end_photo_path']) ?>', '_blank')">
                        </div>
                        <div class="photo-meta">
                            <div class="photo-meta-row">
                                <label>Uploaded</label>
                                <span><?= date('M j, h:i A', strtotime($session['end_photo_uploaded_at'])) ?></span>
                            </div>
                            <?php if ($session['end_photo_taken_at']): ?>
                            <div class="photo-meta-row">
                                <label>Photo Taken</label>
                                <span><?= date('M j, h:i A', strtotime($session['end_photo_taken_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($session['end_distance_from_school'] !== null): ?>
                            <div class="photo-meta-row">
                                <label>Distance</label>
                                <span class="<?= $end_distance_ok ? 'success' : 'danger' ?>">
                                    <?= number_format($session['end_distance_from_school']) ?>m
                                    <?= $end_distance_ok ? '✓' : '✗' ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-photo">
                            <i class='bx bx-camera'></i>
                            <p>No completion photo uploaded</p>
                            <?php if ($upload_type === 'end'): ?>
                            <small>Upload your completion photo below</small>
                            <?php elseif (!$session['start_photo_path']): ?>
                            <small>Upload arrival photo first</small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Duration Card (if both photos exist) -->
            <?php if ($duration_status): ?>
            <div class="duration-card">
                <h3><i class='bx bx-time-five'></i> Duration Verification</h3>
                <div class="duration-grid">
                    <div class="duration-item">
                        <label>Expected Duration</label>
                        <div class="value"><?= $duration_status['formattedExpected'] ?></div>
                    </div>
                    <div class="duration-item">
                        <label>Actual Duration</label>
                        <div class="value <?= $duration_status['statusClass'] ?>"><?= $duration_status['formattedActual'] ?></div>
                    </div>
                    <div class="duration-item">
                        <label>Difference</label>
                        <div class="value <?= $duration_status['statusClass'] ?>"><?= $duration_status['formattedDifference'] ?></div>
                    </div>
                </div>
                <div class="duration-status <?= $duration_status['statusClass'] ?>">
                    <?= $duration_status['statusText'] ?> (<?= $duration_status['percentComplete'] ?>% of expected time)
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Upload Section -->
            <?php if ($upload_type): ?>
            <div class="upload-section">
                <h3>
                    <i class='bx bx-upload'></i> <?= $upload_title ?>
                </h3>
                <p class="subtitle"><?= $upload_instructions ?></p>
                
                <div class="requirements">
                    <h4><i class='bx bx-list-check'></i> Photo Requirements</h4>
                    <ul>
                        <li>Take the photo <strong>at the school location</strong></li>
                        <li>Enable <strong>location services</strong> on your camera/phone</li>
                        <li>Photo should be taken on <strong><?= date('F j, Y', strtotime($session['slot_date'])) ?></strong></li>
                        <?php if ($upload_type === 'start'): ?>
                        <li>Take this photo when you <strong>arrive</strong> at the school</li>
                        <?php else: ?>
                        <li>Take this photo when you <strong>finish</strong> teaching</li>
                        <?php endif; ?>
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
                        <h4>Preview</h4>
                        <img id="previewImg" class="preview-img" src="">
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-<?= $upload_type === 'start' ? 'success' : 'primary' ?>">
                                <i class='bx bx-upload'></i> Upload <?= ucfirst($upload_type) ?> Photo
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearPreview()">
                                <i class='bx bx-x'></i> Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php elseif ($session['session_status'] === 'approved'): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i>
                <div>
                    <strong>Session Approved!</strong>
                    <p style="margin-top: 5px;">Your teaching session has been verified and approved.</p>
                    <?php if ($session['admin_remarks']): ?>
                    <p style="margin-top: 10px; font-style: italic;">"<?= htmlspecialchars($session['admin_remarks']) ?>"</p>
                    <?php endif; ?>
                    <?php if ($session['verified_by_name']): ?>
                    <p style="margin-top: 5px; font-size: 13px; opacity: 0.8;">
                        — <?= htmlspecialchars($session['verified_by_name']) ?>, <?= date('M j, Y', strtotime($session['verified_at'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($session['session_status'] === 'end_submitted'): ?>
            <div class="alert alert-info">
                <i class='bx bx-hourglass'></i>
                <div>
                    <strong>Awaiting Final Review</strong>
                    <p style="margin-top: 5px;">Both photos have been submitted. An admin will review and approve your session soon.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Map Section -->
            <?php if (($session['start_gps_latitude'] && $session['start_gps_longitude']) || 
                      ($session['end_gps_latitude'] && $session['end_gps_longitude'])): ?>
            <div class="info-section">
                <h3><i class='bx bx-map'></i> Location Map</h3>
                <div id="map"></div>
            </div>
            <?php endif; ?>
            
            <!-- School Info -->
            <div class="info-section">
                <h3><i class='bx bx-building-house'></i> School Information</h3>
                <div class="info-row">
                    <i class='bx bx-map'></i>
                    <div>
                        <div class="info-label">Address</div>
                        <div class="info-value"><?= htmlspecialchars($session['full_address'] ?: 'Not provided') ?></div>
                    </div>
                </div>
                <?php if ($session['contact_person']): ?>
                <div class="info-row">
                    <i class='bx bx-user'></i>
                    <div>
                        <div class="info-label">Contact Person</div>
                        <div class="info-value"><?= htmlspecialchars($session['contact_person']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($session['contact_phone']): ?>
                <div class="info-row">
                    <i class='bx bx-phone'></i>
                    <div>
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?= htmlspecialchars($session['contact_phone']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <i class='bx bx-target-lock'></i>
                    <div>
                        <div class="info-label">Allowed Radius</div>
                        <div class="info-value"><?= $session['allowed_radius'] ?? 500 ?>m from school</div>
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
        <?php 
        $hasStartGPS = $session['start_gps_latitude'] && $session['start_gps_longitude'];
        $hasEndGPS = $session['end_gps_latitude'] && $session['end_gps_longitude'];
        $hasSchoolGPS = $session['school_lat'] && $session['school_lng'];
        
        if ($hasStartGPS || $hasEndGPS): 
        ?>
        document.addEventListener('DOMContentLoaded', function() {
            const schoolLat = <?= $session['school_lat'] ?? 0 ?>;
            const schoolLng = <?= $session['school_lng'] ?? 0 ?>;
            const allowedRadius = <?= $session['allowed_radius'] ?? 500 ?>;
            
            <?php if ($hasStartGPS): ?>
            const startLat = <?= $session['start_gps_latitude'] ?>;
            const startLng = <?= $session['start_gps_longitude'] ?>;
            <?php endif; ?>
            
            <?php if ($hasEndGPS): ?>
            const endLat = <?= $session['end_gps_latitude'] ?>;
            const endLng = <?= $session['end_gps_longitude'] ?>;
            <?php endif; ?>
            
            // Calculate bounds
            const points = [];
            <?php if ($hasSchoolGPS): ?>points.push([schoolLat, schoolLng]);<?php endif; ?>
            <?php if ($hasStartGPS): ?>points.push([startLat, startLng]);<?php endif; ?>
            <?php if ($hasEndGPS): ?>points.push([endLat, endLng]);<?php endif; ?>
            
            const map = L.map('map');
            if (points.length > 0) {
                map.fitBounds(points, { padding: [50, 50] });
            }
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            
            <?php if ($hasSchoolGPS): ?>
            // School marker
            L.marker([schoolLat, schoolLng], {
                icon: L.divIcon({
                    className: 'school-marker',
                    html: '<div style="background:#667eea;color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 5px rgba(0,0,0,0.3);">🏫</div>'
                })
            }).addTo(map).bindPopup('School Location');
            
            // Radius circle
            L.circle([schoolLat, schoolLng], {
                color: '#667eea',
                fillColor: '#667eea',
                fillOpacity: 0.1,
                radius: allowedRadius
            }).addTo(map);
            <?php endif; ?>
            
            <?php if ($hasStartGPS): ?>
            // Start photo marker
            L.marker([startLat, startLng], {
                icon: L.divIcon({
                    className: 'start-marker',
                    html: '<div style="background:#10b981;color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 5px rgba(0,0,0,0.3);">📸</div>'
                })
            }).addTo(map).bindPopup('Start Photo Location');
            <?php endif; ?>
            
            <?php if ($hasEndGPS): ?>
            // End photo marker
            L.marker([endLat, endLng], {
                icon: L.divIcon({
                    className: 'end-marker',
                    html: '<div style="background:#8b5cf6;color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 5px rgba(0,0,0,0.3);">🏁</div>'
                })
            }).addTo(map).bindPopup('End Photo Location');
            <?php endif; ?>
        });
        <?php endif; ?>
    </script>
</body>
</html>
