<?php
/**
 * Admin - Review Session (Dual Photo Verification)
 * Phase 3: Updated for dual photo (start + end) verification workflow
 * 
 * Handles review of teaching sessions with:
 * - Start photo verification (arrival)
 * - End photo verification (completion)
 * - Duration verification
 * - Location verification for both photos
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';
require_once '../utils/duration_validator.php';
require_once '../utils/session_validator.php';

$admin_id = $_SESSION['admin_id'];
$message = '';
$error = '';

$session_id = intval($_GET['id'] ?? $_POST['session_id'] ?? 0);
$redirect = $_POST['redirect'] ?? '';

if ($session_id <= 0) {
    header("Location: pending_sessions.php");
    exit;
}

// Get session details first (needed for validation during POST handling)
$session_sql = "SELECT ts.*, 
                t.fname as teacher_name, t.email as teacher_email, t.subject,
                s.school_name, s.full_address, s.gps_latitude as school_lat, s.gps_longitude as school_lng,
                s.allowed_radius, s.contact_person, s.contact_phone,
                sts.slot_date, sts.start_time, sts.end_time, sts.description as slot_desc,
                sts.teachers_required, sts.teachers_enrolled,
                a.fname as verified_by_name
                FROM teaching_sessions ts
                JOIN teacher t ON ts.teacher_id = t.id
                JOIN schools s ON ts.school_id = s.school_id
                JOIN school_teaching_slots sts ON ts.slot_id = sts.slot_id
                LEFT JOIN admin a ON ts.verified_by = a.id
                WHERE ts.session_id = ?";
$stmt = mysqli_prepare($conn, $session_sql);
mysqli_stmt_bind_param($stmt, "i", $session_id);
mysqli_stmt_execute($stmt);
$session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$session) {
    header("Location: pending_sessions.php?error=Session not found");
    exit;
}

$allowed_radius = $session['allowed_radius'] ?? 500;

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Get current session status to determine proper transition
    $status_check = mysqli_prepare($conn, "SELECT session_status FROM teaching_sessions WHERE session_id = ?");
    mysqli_stmt_bind_param($status_check, "i", $session_id);
    mysqli_stmt_execute($status_check);
    $result = mysqli_stmt_get_result($status_check);
    $current_status = mysqli_fetch_assoc($result)['session_status'] ?? '';
    
    // Run validation for approve actions
    if ($action === 'approve_start' || $action === 'approve') {
        require_once '../utils/session_validator.php';
        $preValidator = new SessionValidator($conn);
        $slot_data = [
            'slot_date' => $session['slot_date'],
            'start_time' => $session['start_time'],
            'end_time' => $session['end_time']
        ];
        $school_data = [
            'school_lat' => $session['school_lat'],
            'school_lng' => $session['school_lng'],
            'allowed_radius' => $allowed_radius
        ];
        $pre_check = $preValidator->checkAutoReject($session, $slot_data, $school_data);
        
        if ($pre_check['reject']) {
            $error = "Cannot approve: " . $pre_check['reason'];
            // Continue to display page, but don't process the action
            $action = '';
        }
    }
    
    if ($action === 'approve_start') {
        // Approve start photo only - transition to start_approved
        $sql = "UPDATE teaching_sessions SET 
                session_status = 'start_approved',
                verified_by = ?,
                verified_at = NOW(),
                admin_remarks = ?
                WHERE session_id = ? AND session_status = 'start_submitted'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $admin_id, $remarks, $session_id);
        
        if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
            $message = "Start photo approved! Awaiting end photo submission from teacher.";
            logAdminAction($conn, $admin_id, 'approve_start_photo', "Approved start photo for session #$session_id", null, 'teaching_sessions', $session_id);
        } else {
            $error = "Failed to approve start photo. Session may have already been processed.";
        }
        
    } elseif ($action === 'approve') {
        // Full approval - for end_submitted status (both photos verified)
        $sql = "UPDATE teaching_sessions SET 
                session_status = 'approved',
                verified_by = ?,
                verified_at = NOW(),
                admin_remarks = ?
                WHERE session_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $admin_id, $remarks, $session_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Session fully approved! Both photos verified.";
            logAdminAction($conn, $admin_id, 'approve_session', "Approved session #$session_id (dual photo)", null, 'teaching_sessions', $session_id);
            
            // Update enrollment status to completed
            $update_enrollment = "UPDATE slot_teacher_enrollments ste
                                 JOIN teaching_sessions ts ON ste.enrollment_id = ts.enrollment_id
                                 SET ste.enrollment_status = 'completed'
                                 WHERE ts.session_id = ?";
            $stmt2 = mysqli_prepare($conn, $update_enrollment);
            mysqli_stmt_bind_param($stmt2, "i", $session_id);
            mysqli_stmt_execute($stmt2);
            
            if ($redirect) {
                header("Location: $redirect?approved=1");
                exit;
            }
        } else {
            $error = "Failed to approve session.";
        }
        
    } elseif ($action === 'reject') {
        if (empty($remarks)) {
            $error = "Rejection reason is required.";
        } else {
            $sql = "UPDATE teaching_sessions SET 
                    session_status = 'rejected',
                    verified_by = ?,
                    verified_at = NOW(),
                    admin_remarks = ?
                    WHERE session_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isi", $admin_id, $remarks, $session_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Session rejected.";
                logAdminAction($conn, $admin_id, 'reject_session', "Rejected session #$session_id: $remarks", null, 'teaching_sessions', $session_id);
                
                if ($redirect) {
                    header("Location: $redirect?rejected=1");
                    exit;
                }
            } else {
                $error = "Failed to reject session.";
            }
        }
        
    } elseif ($action === 'request_resubmit') {
        // Allow teacher to resubmit - reset to appropriate state
        $new_status = ($current_status === 'end_submitted') ? 'start_approved' : 'pending';
        $sql = "UPDATE teaching_sessions SET 
                session_status = ?,
                admin_remarks = ?
                WHERE session_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $new_status, $remarks, $session_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $msg_type = ($new_status === 'start_approved') ? 'end' : 'start';
            $message = "Resubmission requested. Teacher can now upload a new $msg_type photo.";
            logAdminAction($conn, $admin_id, 'request_resubmit', "Requested resubmit for session #$session_id", null, 'teaching_sessions', $session_id);
        } else {
            $error = "Failed to request resubmission.";
        }
    }
}

// Re-fetch session data (may have been modified by POST action)
$stmt = mysqli_prepare($conn, $session_sql);
mysqli_stmt_bind_param($stmt, "i", $session_id);
mysqli_stmt_execute($stmt);
$session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Verify distances for both photos
$start_distance = $session['start_distance_from_school'];
$end_distance = $session['end_distance_from_school'];
$start_distance_ok = $start_distance !== null && $start_distance <= $allowed_radius;
$end_distance_ok = $end_distance !== null && $end_distance <= $allowed_radius;

// Check photo dates vs session date
$start_date_match = true;
$end_date_match = true;
if ($session['start_photo_taken_at']) {
    $start_photo_date = date('Y-m-d', strtotime($session['start_photo_taken_at']));
    $start_date_match = ($start_photo_date === $session['slot_date']);
}
if ($session['end_photo_taken_at']) {
    $end_photo_date = date('Y-m-d', strtotime($session['end_photo_taken_at']));
    $end_date_match = ($end_photo_date === $session['slot_date']);
}

// Duration verification using the utility
$duration_info = null;
$duration_status = null;
if ($session['start_photo_taken_at'] && $session['end_photo_taken_at']) {
    $expected_mins = DurationValidator::calculateExpectedDuration($session['start_time'], $session['end_time']);
    $actual_mins = $session['actual_duration_minutes'] ?? DurationValidator::calculateDuration(
        $session['start_photo_taken_at'], 
        $session['end_photo_taken_at']
    );
    
    $duration_info = [
        'actual' => $actual_mins,
        'expected' => $expected_mins,
        'verified' => DurationValidator::verifyDuration($actual_mins, $expected_mins),
        'meets_minimum' => DurationValidator::meetsMinimumDuration($actual_mins, $expected_mins),
        'status_details' => DurationValidator::getDurationStatus($actual_mins, $expected_mins)
    ];
    // Extract string status from the status details array
    $duration_info['status'] = $duration_info['status_details']['status'] ?? 'unknown';
    $duration_status = $duration_info['status'];
}

// Comprehensive Session Validation using SessionValidator
$sessionValidator = new SessionValidator($conn);
$slot_data = [
    'slot_date' => $session['slot_date'],
    'start_time' => $session['start_time'],
    'end_time' => $session['end_time']
];
$school_data = [
    'school_lat' => $session['school_lat'],
    'school_lng' => $session['school_lng'],
    'allowed_radius' => $allowed_radius
];
$validation_result = $sessionValidator->validateSession($session, $slot_data, $school_data);

// Check auto-reject conditions
$auto_reject_check = $sessionValidator->checkAutoReject($session, $slot_data, $school_data);

// Check auto-approve conditions
$auto_approve_check = $sessionValidator->checkAutoApprove($session, $slot_data, $school_data);

// Helper function for status classes
function getStatusClass($status) {
    return match($status) {
        'approved' => 'success',
        'rejected' => 'danger',
        'start_submitted', 'end_submitted' => 'warning',
        'start_approved' => 'info',
        'partial' => 'warning',
        default => 'muted'
    };
}

// Determine what review actions are available
$can_approve_start = ($session['session_status'] === 'start_submitted');
$can_approve_full = in_array($session['session_status'], ['end_submitted', 'start_approved']);
$is_finalized = in_array($session['session_status'], ['approved', 'rejected']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Review Session #<?= $session_id ?> | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css?v=3.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .review-container {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        @media (max-width: 1100px) {
            .review-container { grid-template-columns: 1fr; }
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .back-link:hover { color: var(--primary-color); }
        
        /* Dual Photo Section */
        .photos-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .photo-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 700px) {
            .photo-comparison { grid-template-columns: 1fr; }
        }
        .photo-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 2px solid #e5e7eb;
        }
        .photo-card.start { border-color: #22c55e; }
        .photo-card.end { border-color: #8b5cf6; }
        .photo-card-header {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        .photo-card.start .photo-card-header { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; }
        .photo-card.end .photo-card-header { background: linear-gradient(135deg, #ede9fe, #ddd6fe); color: #5b21b6; }
        .photo-card-header .icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .photo-card.start .icon { background: #22c55e; color: white; }
        .photo-card.end .icon { background: #8b5cf6; color: white; }
        
        .photo-wrapper {
            position: relative;
            padding-top: 75%;
            background: #f3f4f6;
        }
        .photo-wrapper img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
        }
        .photo-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            padding: 30px 12px 12px;
        }
        .photo-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .photo-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .photo-badge.ok { background: #dcfce7; color: #166534; }
        .photo-badge.warn { background: #fef3c7; color: #92400e; }
        .photo-badge.bad { background: #fee2e2; color: #991b1b; }
        
        .photo-details {
            padding: 12px 16px;
        }
        .photo-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
            border-bottom: 1px solid #f3f4f6;
        }
        .photo-detail-row:last-child { border-bottom: none; }
        .photo-detail-label { color: #6b7280; }
        .photo-detail-value { font-weight: 500; }
        
        .no-photo {
            padding-top: 75%;
            position: relative;
            background: #f9fafb;
        }
        .no-photo-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #9ca3af;
        }
        .no-photo-content i { font-size: 48px; margin-bottom: 10px; }
        
        /* Duration Card */
        .duration-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .duration-card h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .duration-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            text-align: center;
        }
        .duration-item {
            padding: 15px;
            border-radius: 10px;
            background: #f9fafb;
        }
        .duration-item.actual { background: #eff6ff; }
        .duration-item.expected { background: #f0fdf4; }
        .duration-item.status { background: #faf5ff; }
        .duration-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .duration-item.actual .duration-value { color: #2563eb; }
        .duration-item.expected .duration-value { color: #16a34a; }
        .duration-label { font-size: 12px; color: #6b7280; }
        
        .duration-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            margin-top: 15px;
        }
        .duration-status.excellent { background: #dcfce7; color: #166534; }
        .duration-status.good { background: #dbeafe; color: #1e40af; }
        .duration-status.warning { background: #fef3c7; color: #92400e; }
        .duration-status.short { background: #fee2e2; color: #991b1b; }
        
        /* Map Card */
        .map-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .map-card h4 {
            padding: 12px 16px;
            margin: 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #map {
            height: 300px;
            width: 100%;
        }
        .map-legend {
            padding: 12px 16px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 12px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .legend-dot.school { background: #7C0A02; }
        .legend-dot.start { background: #22c55e; }
        .legend-dot.end { background: #8b5cf6; }
        
        /* Side Panel */
        .side-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .panel-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .panel-header {
            padding: 16px;
            background: linear-gradient(135deg, #7C0A02, #a61b0d);
            color: white;
        }
        .panel-header h2 {
            font-size: 16px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .panel-header p {
            font-size: 12px;
            opacity: 0.85;
            margin: 0;
        }
        .panel-body { padding: 16px; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-item { font-size: 13px; }
        .info-item .label { display: block; color: #6b7280; font-size: 11px; margin-bottom: 2px; }
        .info-item .value { font-weight: 500; }
        .divider { border-top: 1px solid #e5e7eb; margin: 12px 0; }
        
        /* Status Timeline */
        .status-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 20px 0;
        }
        .status-timeline::before {
            content: '';
            position: absolute;
            top: 14px;
            left: 20px;
            right: 20px;
            height: 2px;
            background: #e5e7eb;
        }
        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
        }
        .timeline-step .step-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #9ca3af;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .timeline-step.done .step-dot { background: #22c55e; color: white; }
        .timeline-step.active .step-dot { background: #f59e0b; color: white; animation: pulse 2s infinite; }
        .timeline-step.rejected .step-dot { background: #ef4444; color: white; }
        .timeline-step .step-label { font-size: 10px; color: #6b7280; text-align: center; max-width: 60px; }
        .timeline-step.done .step-label { color: #166534; font-weight: 500; }
        .timeline-step.active .step-label { color: #92400e; font-weight: 500; }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
        }
        
        /* Checklist */
        .checklist { margin-top: 12px; }
        .checklist-title { font-weight: 600; font-size: 13px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .check-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; }
        .check-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }
        .check-icon.pass { background: #dcfce7; color: #166534; }
        .check-icon.fail { background: #fee2e2; color: #991b1b; }
        .check-icon.warn { background: #fef3c7; color: #92400e; }
        .check-icon.unknown { background: #f3f4f6; color: #6b7280; }
        
        /* Action Form */
        .action-form label { font-size: 13px; font-weight: 500; margin-bottom: 6px; display: block; }
        .action-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            resize: vertical;
            min-height: 80px;
            font-size: 13px;
            margin-bottom: 12px;
        }
        .action-btns {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-success { background: #22c55e; color: white; }
        .btn-success:hover { background: #16a34a; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 15px;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .alert-info { background: #dbeafe; color: #1e40af; }
        
        .prev-decision {
            background: #eff6ff;
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
            margin-bottom: 12px;
        }
        .prev-decision strong { color: #1e40af; }
        
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pill.pending { background: #f3f4f6; color: #6b7280; }
        .status-pill.start_submitted { background: #fef3c7; color: #92400e; }
        .status-pill.start_approved { background: #dbeafe; color: #1e40af; }
        .status-pill.end_submitted { background: #fae8ff; color: #86198f; }
        .status-pill.approved { background: #dcfce7; color: #166534; }
        .status-pill.rejected { background: #fee2e2; color: #991b1b; }
        .status-pill.partial { background: #fed7aa; color: #9a3412; }
        
        /* Validation Details */
        .validation-details { margin-top: 8px; }
        .validation-group { margin-bottom: 12px; }
        .validation-group h4 {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .validation-group.errors h4 { color: #991b1b; }
        .validation-group.warnings h4 { color: #92400e; }
        .validation-group.info h4 { color: #1e40af; }
        .validation-group ul {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 12px;
        }
        .validation-group ul li {
            padding: 4px 0 4px 16px;
            position: relative;
        }
        .validation-group ul li::before {
            content: '•';
            position: absolute;
            left: 4px;
        }
        .validation-group.errors ul li { color: #dc2626; }
        .validation-group.warnings ul li { color: #d97706; }
        .validation-group.info ul li { color: #6b7280; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <a href="pending_sessions.php" class="back-link">
            <i class='bx bx-arrow-back'></i> Back to Session Reviews
        </a>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class='bx bx-error'></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="review-container">
            <!-- Photos Section -->
            <div class="photos-section">
                <!-- Dual Photo Comparison -->
                <div class="photo-comparison">
                    <!-- Start Photo Card -->
                    <div class="photo-card start">
                        <div class="photo-card-header">
                            <div class="icon"><i class='bx bx-log-in'></i></div>
                            <div>
                                <div>Start Photo (Arrival)</div>
                                <small style="font-weight: 400; opacity: 0.8;">
                                    <?= $session['start_photo_path'] ? 'Uploaded' : 'Not yet submitted' ?>
                                </small>
                            </div>
                        </div>
                        <?php if ($session['start_photo_path']): ?>
                        <div class="photo-wrapper">
                            <img src="../<?= htmlspecialchars($session['start_photo_path']) ?>" 
                                 alt="Start Photo"
                                 onclick="window.open('../<?= htmlspecialchars($session['start_photo_path']) ?>', '_blank')">
                            <div class="photo-overlay">
                                <div class="photo-badges">
                                    <?php if ($session['start_gps_latitude'] && $session['start_gps_longitude']): ?>
                                    <span class="photo-badge <?= $start_distance_ok ? 'ok' : 'bad' ?>">
                                        <i class='bx bx-map-pin'></i> <?= number_format($start_distance) ?>m
                                    </span>
                                    <?php endif; ?>
                                    <span class="photo-badge <?= $start_date_match ? 'ok' : 'warn' ?>">
                                        <i class='bx bx-calendar'></i> <?= $start_date_match ? 'Date ✓' : 'Date ≠' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="photo-details">
                            <div class="photo-detail-row">
                                <span class="photo-detail-label">Photo Time</span>
                                <span class="photo-detail-value">
                                    <?= $session['start_photo_taken_at'] ? date('h:i A', strtotime($session['start_photo_taken_at'])) : 'N/A' ?>
                                </span>
                            </div>
                            <div class="photo-detail-row">
                                <span class="photo-detail-label">Uploaded</span>
                                <span class="photo-detail-value">
                                    <?= $session['start_photo_uploaded_at'] ? date('M j, h:i A', strtotime($session['start_photo_uploaded_at'])) : 'N/A' ?>
                                </span>
                            </div>
                            <div class="photo-detail-row">
                                <span class="photo-detail-label">Distance</span>
                                <span class="photo-detail-value" style="color: <?= $start_distance_ok ? '#166534' : '#991b1b' ?>">
                                    <?= $start_distance !== null ? number_format($start_distance) . 'm' : 'No GPS' ?>
                                    <?= $start_distance_ok ? '✓' : ($start_distance !== null ? '✗' : '') ?>
                                </span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="no-photo">
                            <div class="no-photo-content">
                                <i class='bx bx-camera'></i>
                                <p>No start photo</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- End Photo Card -->
                    <div class="photo-card end">
                        <div class="photo-card-header">
                            <div class="icon"><i class='bx bx-log-out'></i></div>
                            <div>
                                <div>End Photo (Completion)</div>
                                <small style="font-weight: 400; opacity: 0.8;">
                                    <?= $session['end_photo_path'] ? 'Uploaded' : 'Not yet submitted' ?>
                                </small>
                            </div>
                        </div>
                        <?php if ($session['end_photo_path']): ?>
                        <div class="photo-wrapper">
                            <img src="../<?= htmlspecialchars($session['end_photo_path']) ?>" 
                                 alt="End Photo"
                                 onclick="window.open('../<?= htmlspecialchars($session['end_photo_path']) ?>', '_blank')">
                            <div class="photo-overlay">
                                <div class="photo-badges">
                                    <?php if ($session['end_gps_latitude'] && $session['end_gps_longitude']): ?>
                                    <span class="photo-badge <?= $end_distance_ok ? 'ok' : 'bad' ?>">
                                        <i class='bx bx-map-pin'></i> <?= number_format($end_distance) ?>m
                                    </span>
                                    <?php endif; ?>
                                    <span class="photo-badge <?= $end_date_match ? 'ok' : 'warn' ?>">
                                        <i class='bx bx-calendar'></i> <?= $end_date_match ? 'Date ✓' : 'Date ≠' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="photo-details">
                            <div class="photo-detail-row">
                                <span class="photo-detail-label">Photo Time</span>
                                <span class="photo-detail-value">
                                    <?= $session['end_photo_taken_at'] ? date('h:i A', strtotime($session['end_photo_taken_at'])) : 'N/A' ?>
                                </span>
                            </div>
                            <div class="photo-detail-row">
                                <span class="photo-detail-label">Uploaded</span>
                                <span class="photo-detail-value">
                                    <?= $session['end_photo_uploaded_at'] ? date('M j, h:i A', strtotime($session['end_photo_uploaded_at'])) : 'N/A' ?>
                                </span>
                            </div>
                            <div class="photo-detail-row">
                                <span class="photo-detail-label">Distance</span>
                                <span class="photo-detail-value" style="color: <?= $end_distance_ok ? '#166534' : '#991b1b' ?>">
                                    <?= $end_distance !== null ? number_format($end_distance) . 'm' : 'No GPS' ?>
                                    <?= $end_distance_ok ? '✓' : ($end_distance !== null ? '✗' : '') ?>
                                </span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="no-photo">
                            <div class="no-photo-content">
                                <i class='bx bx-camera'></i>
                                <p>Awaiting end photo</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Duration Verification Card -->
                <?php if ($duration_info): ?>
                <div class="duration-card">
                    <h3><i class='bx bx-time-five'></i> Duration Verification</h3>
                    <div class="duration-grid">
                        <div class="duration-item actual">
                            <div class="duration-value"><?= DurationValidator::formatDuration($duration_info['actual']) ?></div>
                            <div class="duration-label">Actual Duration</div>
                        </div>
                        <div class="duration-item expected">
                            <div class="duration-value"><?= DurationValidator::formatDuration($duration_info['expected']) ?></div>
                            <div class="duration-label">Expected Duration</div>
                        </div>
                        <div class="duration-item status">
                            <div class="duration-value">
                                <?php 
                                $diff = $duration_info['actual'] - $duration_info['expected'];
                                echo ($diff >= 0 ? '+' : '') . $diff . 'm';
                                ?>
                            </div>
                            <div class="duration-label">Difference</div>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <span class="duration-status <?= $duration_info['status'] ?>">
                            <?php 
                            $status_icons = [
                                'verified' => '✓ Verified',
                                'extended' => '✓ Extended',
                                'short' => '⚠ Slightly Short',
                                'warning' => '⚠ Warning',
                                'rejected' => '✗ Auto-Rejected',
                                'invalid' => '✗ Invalid'
                            ];
                            echo $status_icons[$duration_info['status']] ?? ucfirst($duration_info['status']);
                            ?>
                            - <?= $duration_info['verified'] ? 'Within tolerance' : 'Outside tolerance' ?>
                        </span>
                    </div>
                </div>
                <?php elseif ($session['start_photo_taken_at'] && !$session['end_photo_taken_at']): ?>
                <div class="duration-card">
                    <h3><i class='bx bx-time-five'></i> Duration Verification</h3>
                    <div class="alert alert-info" style="margin: 0;">
                        <i class='bx bx-info-circle'></i> 
                        Duration will be calculated once the end photo is submitted.
                        <br><small>Expected slot duration: <?= DurationValidator::formatDuration(DurationValidator::calculateExpectedDuration($session['start_time'], $session['end_time'])) ?></small>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Map Card -->
                <?php 
                $has_start_gps = $session['start_gps_latitude'] && $session['start_gps_longitude'];
                $has_end_gps = $session['end_gps_latitude'] && $session['end_gps_longitude'];
                $has_school_gps = $session['school_lat'] && $session['school_lng'];
                
                if ($has_school_gps && ($has_start_gps || $has_end_gps)): 
                ?>
                <div class="map-card">
                    <h4><i class='bx bx-map'></i> Location Verification</h4>
                    <div id="map"></div>
                    <div class="map-legend">
                        <div class="legend-item">
                            <span class="legend-dot school"></span>
                            <span>School (<?= $allowed_radius ?>m radius)</span>
                        </div>
                        <?php if ($has_start_gps): ?>
                        <div class="legend-item">
                            <span class="legend-dot start"></span>
                            <span>Start Photo (<?= number_format($start_distance) ?>m)</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($has_end_gps): ?>
                        <div class="legend-item">
                            <span class="legend-dot end"></span>
                            <span>End Photo (<?= number_format($end_distance) ?>m)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Side Panel -->
            <div class="side-panel">
                <!-- Session Info Card -->
                <div class="panel-card">
                    <div class="panel-header">
                        <h2><i class='bx bx-building'></i> <?= htmlspecialchars($session['school_name']) ?></h2>
                        <p><?= htmlspecialchars($session['full_address'] ?: 'No address provided') ?></p>
                    </div>
                    <div class="panel-body">
                        <!-- Status Timeline -->
                        <div class="status-timeline">
                            <?php
                            $status = $session['session_status'];
                            $steps = [
                                ['key' => 'booked', 'label' => 'Booked', 'icon' => '1'],
                                ['key' => 'start', 'label' => 'Start Photo', 'icon' => '2'],
                                ['key' => 'start_ok', 'label' => 'Start OK', 'icon' => '3'],
                                ['key' => 'end', 'label' => 'End Photo', 'icon' => '4'],
                                ['key' => 'final', 'label' => 'Verified', 'icon' => '5']
                            ];
                            
                            $status_progress = [
                                'pending' => 1,
                                'start_submitted' => 2,
                                'start_approved' => 3,
                                'end_submitted' => 4,
                                'approved' => 5,
                                'rejected' => 0,
                                'partial' => 3
                            ];
                            
                            $current_step = $status_progress[$status] ?? 1;
                            
                            foreach ($steps as $i => $step):
                                $step_num = $i + 1;
                                $class = '';
                                if ($status === 'rejected') {
                                    $class = ($step_num <= $current_step) ? 'rejected' : '';
                                } elseif ($step_num < $current_step) {
                                    $class = 'done';
                                } elseif ($step_num === $current_step) {
                                    $class = 'active';
                                }
                            ?>
                            <div class="timeline-step <?= $class ?>">
                                <div class="step-dot">
                                    <?= ($class === 'done') ? '✓' : (($class === 'rejected') ? '✗' : $step['icon']) ?>
                                </div>
                                <div class="step-label"><?= $step['label'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="label">Teacher</span>
                                <span class="value"><?= htmlspecialchars($session['teacher_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Subject</span>
                                <span class="value"><?= htmlspecialchars($session['subject'] ?? 'N/A') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Date</span>
                                <span class="value"><?= date('M j, Y', strtotime($session['slot_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Slot Time</span>
                                <span class="value"><?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></span>
                            </div>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <!-- Verification Checklist -->
                        <div class="checklist">
                            <div class="checklist-title"><i class='bx bx-check-circle'></i> Verification Checklist</div>
                            
                            <div class="check-row">
                                <span class="check-icon <?= $session['start_photo_path'] ? 'pass' : 'fail' ?>">
                                    <?= $session['start_photo_path'] ? '✓' : '✗' ?>
                                </span>
                                <span>Start photo uploaded</span>
                            </div>
                            
                            <div class="check-row">
                                <span class="check-icon <?= $has_start_gps ? ($start_distance_ok ? 'pass' : 'fail') : 'unknown' ?>">
                                    <?= $has_start_gps ? ($start_distance_ok ? '✓' : '✗') : '?' ?>
                                </span>
                                <span>Start location within <?= $allowed_radius ?>m</span>
                            </div>
                            
                            <div class="check-row">
                                <span class="check-icon <?= $session['end_photo_path'] ? 'pass' : ($session['start_photo_path'] ? 'warn' : 'unknown') ?>">
                                    <?= $session['end_photo_path'] ? '✓' : ($session['start_photo_path'] ? '!' : '?') ?>
                                </span>
                                <span>End photo uploaded</span>
                            </div>
                            
                            <div class="check-row">
                                <span class="check-icon <?= $has_end_gps ? ($end_distance_ok ? 'pass' : 'fail') : 'unknown' ?>">
                                    <?= $has_end_gps ? ($end_distance_ok ? '✓' : '✗') : '?' ?>
                                </span>
                                <span>End location within <?= $allowed_radius ?>m</span>
                            </div>
                            
                            <?php if ($duration_info): ?>
                            <div class="check-row">
                                <span class="check-icon <?= $duration_info['meets_minimum'] ? 'pass' : 'fail' ?>">
                                    <?= $duration_info['meets_minimum'] ? '✓' : '✗' ?>
                                </span>
                                <span>Duration meets minimum (<?= MIN_DURATION_PERCENT ?>%)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Validation Summary Card -->
                <div class="panel-card">
                    <div class="panel-header">
                        <h3><i class='bx bx-shield-quarter'></i> Validation Analysis</h3>
                    </div>
                    <div class="panel-body">
                        <?php 
                        // Show auto-reject warning if applicable
                        if ($auto_reject_check['reject']): 
                        ?>
                        <div class="alert alert-danger" style="margin-bottom: 12px;">
                            <i class='bx bx-error-circle'></i>
                            <strong>Auto-Reject Condition Met</strong><br>
                            <?= htmlspecialchars($auto_reject_check['reason']) ?>
                        </div>
                        <?php elseif ($auto_approve_check['approve']): ?>
                        <div class="alert alert-success" style="margin-bottom: 12px;">
                            <i class='bx bx-check-circle'></i>
                            <strong>Eligible for Auto-Approval</strong><br>
                            All validation criteria passed.
                        </div>
                        <?php elseif ($validation_result['requiresManualReview']): ?>
                        <div class="alert alert-info" style="margin-bottom: 12px;">
                            <i class='bx bx-user-check'></i>
                            <strong>Manual Review Required</strong><br>
                            Some validations require admin judgment.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Validation Details -->
                        <div class="validation-details">
                            <?php if (!empty($validation_result['errors'])): ?>
                            <div class="validation-group errors">
                                <h4><i class='bx bx-x-circle'></i> Errors</h4>
                                <ul>
                                    <?php foreach ($validation_result['errors'] as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($validation_result['warnings'])): ?>
                            <div class="validation-group warnings">
                                <h4><i class='bx bx-error'></i> Warnings</h4>
                                <ul>
                                    <?php foreach ($validation_result['warnings'] as $warn): ?>
                                    <li><?= htmlspecialchars($warn) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($validation_result['info'])): ?>
                            <div class="validation-group info">
                                <h4><i class='bx bx-info-circle'></i> Details</h4>
                                <ul>
                                    <?php foreach ($validation_result['info'] as $info): ?>
                                    <li><?= htmlspecialchars($info) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (empty($validation_result['errors']) && empty($validation_result['warnings']) && empty($validation_result['info'])): ?>
                            <p style="color: #6b7280; text-align: center;">No validation data available yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Action Card -->
                <div class="panel-card">
                    <div class="panel-body">
                        <?php if ($session['verified_at']): ?>
                        <div class="prev-decision">
                            <strong>
                                <?php if ($session['session_status'] === 'approved'): ?>
                                <i class='bx bx-check-circle'></i> Approved
                                <?php elseif ($session['session_status'] === 'rejected'): ?>
                                <i class='bx bx-x-circle'></i> Rejected
                                <?php elseif ($session['session_status'] === 'start_approved'): ?>
                                <i class='bx bx-check'></i> Start Photo Approved
                                <?php endif; ?>
                            </strong>
                            by <?= htmlspecialchars($session['verified_by_name'] ?? 'Admin') ?><br>
                            <small><?= date('M j, Y h:i A', strtotime($session['verified_at'])) ?></small>
                            <?php if ($session['admin_remarks']): ?>
                            <p style="margin-top: 6px; font-style: italic;">"<?= htmlspecialchars($session['admin_remarks']) ?>"</p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($can_approve_start): ?>
                        <!-- Review Start Photo -->
                        <div class="alert alert-info" style="margin-bottom: 12px;">
                            <i class='bx bx-info-circle'></i> 
                            <strong>Start photo submitted</strong> - Review and approve to allow teacher to submit end photo.
                        </div>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="session_id" value="<?= $session_id ?>">
                            <label>Remarks (optional)</label>
                            <textarea name="remarks" placeholder="Enter remarks..."></textarea>
                            <div class="action-btns">
                                <button type="submit" name="action" value="approve_start" class="btn btn-success">
                                    <i class='bx bx-check'></i> Approve Start
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger"
                                    onclick="if(!document.querySelector('textarea[name=remarks]').value.trim()){alert('Please enter a rejection reason');return false;}">
                                    <i class='bx bx-x'></i> Reject
                                </button>
                            </div>
                        </form>
                        
                        <?php elseif ($can_approve_full && $session['session_status'] === 'end_submitted'): ?>
                        <!-- Review Both Photos (Final Approval) -->
                        <div class="alert alert-info" style="margin-bottom: 12px;">
                            <i class='bx bx-info-circle'></i> 
                            <strong>Both photos submitted</strong> - Review duration and approve for final verification.
                        </div>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="session_id" value="<?= $session_id ?>">
                            <label>Remarks (optional)</label>
                            <textarea name="remarks" placeholder="Enter remarks..."></textarea>
                            <div class="action-btns">
                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                    <i class='bx bx-check-double'></i> Approve Session
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger"
                                    onclick="if(!document.querySelector('textarea[name=remarks]').value.trim()){alert('Please enter a rejection reason');return false;}">
                                    <i class='bx bx-x'></i> Reject
                                </button>
                            </div>
                            <button type="submit" name="action" value="request_resubmit" class="btn btn-warning" style="width: 100%; margin-top: 10px;"
                                onclick="if(!document.querySelector('textarea[name=remarks]').value.trim()){alert('Please explain what needs to be resubmitted');return false;}">
                                <i class='bx bx-refresh'></i> Request End Photo Resubmit
                            </button>
                        </form>
                        
                        <?php elseif ($session['session_status'] === 'start_approved'): ?>
                        <!-- Waiting for End Photo -->
                        <div class="alert alert-info" style="margin: 0;">
                            <i class='bx bx-time'></i> 
                            <strong>Waiting for End Photo</strong><br>
                            Start photo has been approved. Waiting for teacher to submit completion photo.
                        </div>
                        
                        <?php elseif ($session['session_status'] === 'approved'): ?>
                        <div class="alert alert-success" style="margin: 0;">
                            <i class='bx bx-check-circle'></i> This session has been fully verified and approved.
                        </div>
                        
                        <?php elseif ($session['session_status'] === 'rejected'): ?>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="session_id" value="<?= $session_id ?>">
                            <label>Change decision:</label>
                            <textarea name="remarks" placeholder="Enter remarks for reconsideration..."></textarea>
                            <button type="submit" name="action" value="approve" class="btn btn-success" style="width: 100%;">
                                <i class='bx bx-check'></i> Approve Instead
                            </button>
                        </form>
                        
                        <?php elseif ($session['session_status'] === 'pending'): ?>
                        <div class="alert" style="background: #f3f4f6; color: #374151; margin: 0;">
                            <i class='bx bx-hourglass'></i> 
                            <strong>Awaiting Start Photo</strong><br>
                            Teacher has not yet submitted arrival photo.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($has_school_gps && ($has_start_gps || $has_end_gps)): ?>
    <script>
        const schoolLat = <?= $session['school_lat'] ?>;
        const schoolLng = <?= $session['school_lng'] ?>;
        const allowedRadius = <?= $allowed_radius ?>;
        
        <?php if ($has_start_gps): ?>
        const startLat = <?= $session['start_gps_latitude'] ?>;
        const startLng = <?= $session['start_gps_longitude'] ?>;
        const startDistanceOk = <?= $start_distance_ok ? 'true' : 'false' ?>;
        <?php endif; ?>
        
        <?php if ($has_end_gps): ?>
        const endLat = <?= $session['end_gps_latitude'] ?>;
        const endLng = <?= $session['end_gps_longitude'] ?>;
        const endDistanceOk = <?= $end_distance_ok ? 'true' : 'false' ?>;
        <?php endif; ?>
        
        // Calculate bounds
        const lats = [schoolLat<?php if ($has_start_gps) echo ', startLat'; ?><?php if ($has_end_gps) echo ', endLat'; ?>];
        const lngs = [schoolLng<?php if ($has_start_gps) echo ', startLng'; ?><?php if ($has_end_gps) echo ', endLng'; ?>];
        
        const map = L.map('map').fitBounds([
            [Math.min(...lats) - 0.002, Math.min(...lngs) - 0.002],
            [Math.max(...lats) + 0.002, Math.max(...lngs) + 0.002]
        ]);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        
        // School marker
        L.marker([schoolLat, schoolLng], {
            icon: L.divIcon({
                className: 'school-marker',
                html: '<div style="background:#7C0A02;color:white;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);"><i class=\'bx bx-building-house\'></i></div>'
            })
        }).addTo(map).bindPopup('<b>School Location</b><br>Allowed radius: ' + allowedRadius + 'm');
        
        // Allowed radius circle
        L.circle([schoolLat, schoolLng], {
            color: '#7C0A02',
            fillColor: '#7C0A02',
            fillOpacity: 0.08,
            radius: allowedRadius,
            weight: 2
        }).addTo(map);
        
        <?php if ($has_start_gps): ?>
        // Start photo marker
        L.marker([startLat, startLng], {
            icon: L.divIcon({
                className: 'start-marker',
                html: '<div style="background:#22c55e;color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);"><i class="bx bx-log-in"></i></div>'
            })
        }).addTo(map).bindPopup('<b>Start Photo</b><br>Distance: <?= number_format($start_distance) ?>m ' + (startDistanceOk ? '✓' : '✗'));
        
        // Line from school to start
        L.polyline([[schoolLat, schoolLng], [startLat, startLng]], {
            color: startDistanceOk ? '#22c55e' : '#ef4444',
            dashArray: '8, 8',
            weight: 2
        }).addTo(map);
        <?php endif; ?>
        
        <?php if ($has_end_gps): ?>
        // End photo marker
        L.marker([endLat, endLng], {
            icon: L.divIcon({
                className: 'end-marker',
                html: '<div style="background:#8b5cf6;color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);"><i class="bx bx-log-out"></i></div>'
            })
        }).addTo(map).bindPopup('<b>End Photo</b><br>Distance: <?= number_format($end_distance) ?>m ' + (endDistanceOk ? '✓' : '✗'));
        
        // Line from school to end
        L.polyline([[schoolLat, schoolLng], [endLat, endLng]], {
            color: endDistanceOk ? '#8b5cf6' : '#ef4444',
            dashArray: '8, 8',
            weight: 2
        }).addTo(map);
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>
