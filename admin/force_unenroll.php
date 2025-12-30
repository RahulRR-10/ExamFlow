<?php
/**
 * Admin Force Unenroll - Phase 7: Controlled Unenrollment
 * Allows admins to force-unenroll teachers from schools even when they have obligations
 * Phase 8: Added compatibility layer and proper audit logging
 */

session_start();
require_once '../config.php';
require_once '../utils/enrollment_utils.php';
require_once '../utils/teaching_slots_compat.php';

// Admin authentication check
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login_teacher.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$success_message = '';
$error_message = '';

// Check if feature is available
if (!isTeachingSlotsEnabled($conn)) {
    $error_message = 'Teaching slots feature is not available. Database tables may be missing.';
}

// Get admin info - check both admin table and teachers table with is_admin flag
$admin = null;
$admin_query = mysqli_query($conn, "SELECT * FROM admin WHERE id = $admin_id");
if ($admin_query && mysqli_num_rows($admin_query) > 0) {
    $admin = mysqli_fetch_assoc($admin_query);
} else {
    // Fallback to teacher table
    $admin_query = mysqli_query($conn, "SELECT * FROM teacher WHERE id = $admin_id AND is_admin = 1");
    if ($admin_query && mysqli_num_rows($admin_query) > 0) {
        $admin = mysqli_fetch_assoc($admin_query);
    }
}

if (!$admin) {
    header('Location: ../login_teacher.php');
    exit;
}

// Handle force unenroll action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'force_unenroll') {
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $school_id = intval($_POST['school_id'] ?? 0);
    $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
    
    if ($teacher_id <= 0 || $school_id <= 0) {
        $error_message = 'Invalid teacher or school selection.';
    } elseif (empty($reason)) {
        $error_message = 'Please provide a reason for force unenrollment.';
    } else {
        $result = forceUnenrollTeacher($conn, $teacher_id, $school_id, $admin_id, $reason);
        
        if ($result['success']) {
            // Log to audit using compatibility layer
            logAdminAction($conn, $admin_id, 'force_unenroll', 'teacher_schools', $teacher_id, [
                'teacher_id' => $teacher_id,
                'school_id' => $school_id,
                'reason' => $reason,
                'cancelled_slots' => $result['cancelled_slots'] ?? 0
            ]);
            
            $success_message = $result['message'];
        } else {
            $error_message = $result['message'];
        }
    }
}

// Get all schools - handle different column names
$schools = [];
$schools_query = mysqli_query($conn, "SELECT school_id as id, school_name as name FROM schools WHERE status = 'active' ORDER BY school_name");
if ($schools_query) {
    while ($school = mysqli_fetch_assoc($schools_query)) {
        $schools[] = $school;
    }
}

// Check if teaching slots tables exist before querying
$enrollments = [];
if (isTeachingSlotsEnabled($conn)) {
    // Get teacher-school enrollments with obligations
    $enrollments_query = mysqli_query($conn, "
        SELECT 
            ts.id as enrollment_id,
            ts.teacher_id,
            ts.school_id,
            ts.enrolled_at,
            t.fname as teacher_name,
            t.email as teacher_email,
            s.school_name as school_name,
            (SELECT COUNT(*) FROM slot_teacher_enrollments ste2 
             JOIN school_teaching_slots sts ON ste2.slot_id = sts.slot_id 
             WHERE ste2.teacher_id = ts.teacher_id 
             AND sts.school_id = ts.school_id 
             AND sts.slot_date >= CURDATE()
             AND ste2.enrollment_status = 'booked') as upcoming_slots,
            (SELECT COUNT(*) FROM teaching_sessions tse
             JOIN slot_teacher_enrollments ste3 ON tse.enrollment_id = ste3.enrollment_id
             JOIN school_teaching_slots sts2 ON ste3.slot_id = sts2.slot_id
             WHERE ste3.teacher_id = ts.teacher_id
             AND sts2.school_id = ts.school_id
             AND tse.photo_path IS NULL
             AND sts2.slot_date < CURDATE()) as pending_photos,
            (SELECT COUNT(*) FROM teaching_sessions tse2
             JOIN slot_teacher_enrollments ste4 ON tse2.enrollment_id = ste4.enrollment_id
             JOIN school_teaching_slots sts3 ON ste4.slot_id = sts3.slot_id
             WHERE ste4.teacher_id = ts.teacher_id
             AND sts3.school_id = ts.school_id
             AND tse2.photo_path IS NOT NULL
             AND tse2.session_status = 'photo_submitted') as pending_reviews
        FROM teacher_schools ts
        JOIN teacher t ON ts.teacher_id = t.id
        JOIN schools s ON ts.school_id = s.school_id
        ORDER BY s.school_name, t.fname
    ");

    if ($enrollments_query) {
        while ($row = mysqli_fetch_assoc($enrollments_query)) {
            $row['has_obligations'] = ($row['upcoming_slots'] > 0 || $row['pending_photos'] > 0 || $row['pending_reviews'] > 0);
            $enrollments[] = $row;
        }
    }
}

// Get recent force unenrollments from audit log
$audit_entries = [];
$audit_table = getAuditLogTable($conn);
if ($audit_table === 'admin_audit_log') {
    $audit_query = mysqli_query($conn, "
        SELECT 
            al.created_at,
            al.action_details as details,
            t.fname as teacher_name,
            s.school_name as school_name,
            adm.fname as admin_name
        FROM admin_audit_log al
LEFT JOIN teacher t ON JSON_UNQUOTE(JSON_EXTRACT(al.action_details, '$.teacher_id')) = t.id
        LEFT JOIN schools s ON JSON_UNQUOTE(JSON_EXTRACT(al.action_details, '\$.school_id')) = s.school_id
        LEFT JOIN admin adm ON al.admin_id = adm.id
        WHERE al.action_type = 'force_unenroll'
        ORDER BY al.created_at DESC
        LIMIT 20
    ");
    if ($audit_query) {
        while ($row = mysqli_fetch_assoc($audit_query)) {
            $audit_entries[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Force Unenroll Teacher - Admin</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 260px;
            background: linear-gradient(180deg, #1a2a6c, #2d4a7c);
            padding: 15px;
            z-index: 100;
        }

        .sidebar .logo-details {
            display: flex;
            align-items: center;
            padding: 15px 10px;
            margin-bottom: 20px;
        }

        .sidebar .logo-details i {
            font-size: 28px;
            color: #ffc107;
            margin-right: 10px;
        }

        .sidebar .logo-details .logo_name {
            color: #fff;
            font-size: 20px;
            font-weight: 600;
        }

        .sidebar .nav-links {
            list-style: none;
        }

        .sidebar .nav-links li {
            margin-bottom: 5px;
        }

        .sidebar .nav-links li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-links li a:hover,
        .sidebar .nav-links li a.active {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .sidebar .nav-links li a i {
            min-width: 35px;
            font-size: 20px;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
        }

        .page-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .warning-box i {
            font-size: 24px;
            color: #856404;
        }

        .warning-box p {
            color: #856404;
            font-size: 14px;
            line-height: 1.5;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 {
            font-size: 18px;
            color: #333;
        }

        .card-header i {
            font-size: 22px;
            color: #dc3545;
        }

        .card-body {
            padding: 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #dc3545;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-danger {
            background: #dc3545;
            color: #fff;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .enrollment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .enrollment-table th,
        .enrollment-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .enrollment-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }

        .enrollment-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-warning {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-success {
            background: #d4edda;
            color: #28a745;
        }

        .badge-info {
            background: #e3f2fd;
            color: #1565c0;
        }

        .obligations {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .obligation-badge {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .obligation-badge.slots {
            background: #e3f2fd;
            color: #1565c0;
        }

        .obligation-badge.photos {
            background: #fff3e0;
            color: #e65100;
        }

        .obligation-badge.reviews {
            background: #fce4ec;
            color: #c2185b;
        }

        .action-btn {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        .audit-list {
            list-style: none;
        }

        .audit-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .audit-item:last-child {
            border-bottom: none;
        }

        .audit-icon {
            width: 40px;
            height: 40px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .audit-icon i {
            color: #dc3545;
            font-size: 18px;
        }

        .audit-content {
            flex: 1;
        }

        .audit-content strong {
            color: #333;
        }

        .audit-content .timestamp {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 18px;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .teacher-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .teacher-info p {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .teacher-info strong {
            color: #333;
        }

        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-bar select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: #dc3545;
            background: #fee2e2;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-details">
            <i class='bx bx-shield-quarter'></i>
            <span class="logo_name">Admin Panel</span>
        </div>
        <ul class="nav-links">
            <?php include 'includes/nav.php'; ?>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class='bx bx-user-x'></i> Force Unenroll Teacher</h1>
            <p>Remove teachers from schools even when they have pending obligations</p>
        </div>

        <div class="warning-box">
            <i class='bx bx-error'></i>
            <div>
                <p><strong>Warning:</strong> Force unenrollment should only be used in exceptional circumstances. This action will:</p>
                <ul style="margin-top: 8px; margin-left: 20px; font-size: 13px;">
                    <li>Cancel all upcoming slot enrollments for the teacher at this school</li>
                    <li>Mark pending sessions as cancelled</li>
                    <li>Create an audit log entry for accountability</li>
                </ul>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle'></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('enrollments')">
                <i class='bx bx-list-ul'></i> Active Enrollments
            </button>
            <button class="tab-btn" onclick="showTab('audit')">
                <i class='bx bx-history'></i> Audit Log
            </button>
        </div>

        <div id="enrollments-tab" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <i class='bx bx-user-check'></i>
                    <h2>Teacher-School Enrollments</h2>
                </div>
                <div class="card-body">
                    <div class="filter-bar">
                        <select id="filter-school" onchange="filterEnrollments()">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filter-obligations" onchange="filterEnrollments()">
                            <option value="">All Teachers</option>
                            <option value="with">With Obligations Only</option>
                            <option value="without">Without Obligations</option>
                        </select>
                    </div>

                    <?php if (count($enrollments) > 0): ?>
                        <table class="enrollment-table">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>School</th>
                                    <th>Enrolled Since</th>
                                    <th>Obligations</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="enrollments-body">
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <tr class="enrollment-row" 
                                        data-school="<?= $enrollment['school_id'] ?>" 
                                        data-has-obligations="<?= $enrollment['has_obligations'] ? '1' : '0' ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($enrollment['teacher_name']) ?></strong>
                                            <br><small style="color: #888;"><?= htmlspecialchars($enrollment['teacher_email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($enrollment['school_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($enrollment['enrolled_at'])) ?></td>
                                        <td>
                                            <?php if ($enrollment['has_obligations']): ?>
                                                <div class="obligations">
                                                    <?php if ($enrollment['upcoming_slots'] > 0): ?>
                                                        <span class="obligation-badge slots">
                                                            <i class='bx bx-calendar'></i>
                                                            <?= $enrollment['upcoming_slots'] ?> upcoming
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($enrollment['pending_photos'] > 0): ?>
                                                        <span class="obligation-badge photos">
                                                            <i class='bx bx-camera'></i>
                                                            <?= $enrollment['pending_photos'] ?> pending photos
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($enrollment['pending_reviews'] > 0): ?>
                                                        <span class="obligation-badge reviews">
                                                            <i class='bx bx-check-double'></i>
                                                            <?= $enrollment['pending_reviews'] ?> pending review
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge badge-success">
                                                    <i class='bx bx-check'></i> No obligations
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-danger action-btn" 
                                                    onclick="openUnenrollModal(<?= $enrollment['teacher_id'] ?>, '<?= htmlspecialchars(addslashes($enrollment['teacher_name'])) ?>', '<?= htmlspecialchars($enrollment['teacher_email']) ?>', <?= $enrollment['school_id'] ?>, '<?= htmlspecialchars(addslashes($enrollment['school_name'])) ?>', <?= $enrollment['upcoming_slots'] ?>, <?= $enrollment['pending_photos'] ?>, <?= $enrollment['pending_reviews'] ?>)">
                                                <i class='bx bx-user-x'></i> Force Unenroll
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-user-x'></i>
                            <p>No active teacher enrollments found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="audit-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <i class='bx bx-history'></i>
                    <h2>Force Unenrollment History</h2>
                </div>
                <div class="card-body">
                    <?php if (count($audit_entries) > 0): ?>
                        <ul class="audit-list">
                            <?php foreach ($audit_entries as $entry): 
                                $details = json_decode($entry['details'], true);
                            ?>
                                <li class="audit-item">
                                    <div class="audit-icon">
                                        <i class='bx bx-user-x'></i>
                                    </div>
                                    <div class="audit-content">
                                        <p>
                                            <strong><?= htmlspecialchars($entry['admin_name'] ?? 'Admin') ?></strong> 
                                            force-unenrolled 
                                            <strong><?= htmlspecialchars($entry['teacher_name'] ?? 'Teacher') ?></strong>
                                            from 
                                            <strong><?= htmlspecialchars($entry['school_name'] ?? 'School') ?></strong>
                                        </p>
                                        <?php if (!empty($details['reason'])): ?>
                                            <p style="color: #666; font-size: 13px; margin-top: 5px;">
                                                <i class='bx bx-message-detail'></i> 
                                                "<?= htmlspecialchars($details['reason']) ?>"
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($details['cancelled_enrollments']) || !empty($details['cancelled_sessions'])): ?>
                                            <p style="font-size: 12px; color: #888; margin-top: 5px;">
                                                Cancelled: <?= $details['cancelled_enrollments'] ?? 0 ?> enrollments, 
                                                <?= $details['cancelled_sessions'] ?? 0 ?> sessions
                                            </p>
                                        <?php endif; ?>
                                        <p class="timestamp">
                                            <i class='bx bx-time'></i>
                                            <?= date('M d, Y \a\t h:i A', strtotime($entry['created_at'])) ?>
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-history'></i>
                            <p>No force unenrollment records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Force Unenroll Modal -->
    <div id="unenroll-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class='bx bx-user-x' style="color: #dc3545;"></i> Confirm Force Unenrollment</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="force_unenroll">
                    <input type="hidden" name="teacher_id" id="modal-teacher-id">
                    <input type="hidden" name="school_id" id="modal-school-id">

                    <div class="teacher-info">
                        <p><strong>Teacher:</strong> <span id="modal-teacher-name"></span></p>
                        <p><strong>Email:</strong> <span id="modal-teacher-email"></span></p>
                        <p><strong>School:</strong> <span id="modal-school-name"></span></p>
                    </div>

                    <div id="modal-obligations" style="margin-bottom: 20px;"></div>

                    <div class="form-group">
                        <label for="reason">Reason for Force Unenrollment <span class="required">*</span></label>
                        <textarea name="reason" id="reason" class="form-control" 
                                  placeholder="Explain why this teacher needs to be force-unenrolled..." required></textarea>
                        <small style="color: #888; font-size: 12px;">This will be recorded in the audit log.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class='bx bx-user-x'></i> Force Unenroll
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        function filterEnrollments() {
            const schoolFilter = document.getElementById('filter-school').value;
            const obligationsFilter = document.getElementById('filter-obligations').value;
            
            document.querySelectorAll('.enrollment-row').forEach(row => {
                let show = true;
                
                if (schoolFilter && row.dataset.school !== schoolFilter) {
                    show = false;
                }
                
                if (obligationsFilter === 'with' && row.dataset.hasObligations !== '1') {
                    show = false;
                } else if (obligationsFilter === 'without' && row.dataset.hasObligations !== '0') {
                    show = false;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }

        function openUnenrollModal(teacherId, teacherName, teacherEmail, schoolId, schoolName, upcomingSlots, pendingPhotos, pendingReviews) {
            document.getElementById('modal-teacher-id').value = teacherId;
            document.getElementById('modal-school-id').value = schoolId;
            document.getElementById('modal-teacher-name').textContent = teacherName;
            document.getElementById('modal-teacher-email').textContent = teacherEmail;
            document.getElementById('modal-school-name').textContent = schoolName;
            
            let obligationsHtml = '';
            if (upcomingSlots > 0 || pendingPhotos > 0 || pendingReviews > 0) {
                obligationsHtml = '<div class="warning-box" style="margin: 0;"><i class="bx bx-error"></i><div>';
                obligationsHtml += '<p><strong>This teacher has pending obligations:</strong></p>';
                obligationsHtml += '<ul style="margin-top: 8px; margin-left: 20px; font-size: 13px;">';
                if (upcomingSlots > 0) {
                    obligationsHtml += '<li>' + upcomingSlots + ' upcoming slot(s) will be cancelled</li>';
                }
                if (pendingPhotos > 0) {
                    obligationsHtml += '<li>' + pendingPhotos + ' session(s) awaiting photo upload</li>';
                }
                if (pendingReviews > 0) {
                    obligationsHtml += '<li>' + pendingReviews + ' session(s) pending admin review</li>';
                }
                obligationsHtml += '</ul></div></div>';
            }
            document.getElementById('modal-obligations').innerHTML = obligationsHtml;
            
            document.getElementById('reason').value = '';
            document.getElementById('unenroll-modal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('unenroll-modal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('unenroll-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>

</html>
