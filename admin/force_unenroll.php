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
    <title>Force Unenroll Teacher | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css?v=2.0">
    <style>
        .warning-box {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }
        .warning-box i { font-size: 24px; color: #dc2626; }
        .warning-box p { color: #991b1b; }
        
        .enrollment-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }
        .enrollment-card.has-obligations { border-left-color: var(--danger-color); }
        
        .enrollment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .enrollment-header h3 { font-size: 16px; margin-bottom: 5px; }
        .enrollment-header .school-name { color: var(--text-muted); font-size: 14px; }
        
        .obligation-badges { display: flex; gap: 8px; flex-wrap: wrap; margin: 10px 0; }
        .obligation-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .obligation-badge.warning { background: #fef3c7; color: #92400e; }
        .obligation-badge.danger { background: #fee2e2; color: #991b1b; }
        .obligation-badge.info { background: #dbeafe; color: #1e40af; }
        
        .unenroll-form { margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color); }
        .unenroll-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        .unenroll-form .form-actions { display: flex; gap: 10px; margin-top: 10px; }
        
        .tab-container { margin-top: 25px; }
        .tab-buttons { display: flex; gap: 5px; margin-bottom: 0; }
        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: #f0f0f0;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s;
        }
        .tab-btn.active { color: var(--primary-color); background: var(--card-bg); }
        .tab-content { display: none; background: var(--card-bg); padding: 20px; border-radius: 0 8px 8px 8px; }
        .tab-content.active { display: block; }
        
        .audit-entry {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .audit-entry:last-child { border-bottom: none; }
        .audit-entry .time { font-size: 12px; color: var(--text-muted); }
        .audit-entry .action { font-weight: 500; margin: 5px 0; }
        .audit-entry .details { font-size: 13px; color: #666; }
        
        .filters-bar {
            background: var(--card-bg);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filters-bar select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class='bx bx-user-x'></i> Force Unenroll Teacher</h1>
            <p class="subtitle">Remove teachers from schools even when they have pending obligations</p>
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
            <div class="filters-bar">
                <select id="filter-school" onchange="filterEnrollments()">
                    <option value="">All Schools</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filter-obligations" onchange="filterEnrollments()">
                    <option value="">All Teachers</option>
                    <option value="with">With Obligations</option>
                    <option value="without">No Obligations</option>
                </select>
            </div>

            <?php if (count($enrollments) > 0): ?>
                <div id="enrollments-list">
                    <?php foreach ($enrollments as $enrollment): ?>
                    <div class="enrollment-card <?= $enrollment['has_obligations'] ? 'has-obligations' : '' ?>" 
                         data-school="<?= $enrollment['school_id'] ?>" 
                         data-has-obligations="<?= $enrollment['has_obligations'] ? '1' : '0' ?>">
                        <div class="enrollment-header">
                            <div>
                                <h3><?= htmlspecialchars($enrollment['teacher_name']) ?></h3>
                                <p class="school-name"><?= htmlspecialchars($enrollment['teacher_email']) ?></p>
                            </div>
                            <div style="text-align: right;">
                                <strong><?= htmlspecialchars($enrollment['school_name']) ?></strong>
                                <p class="school-name">Since <?= date('M d, Y', strtotime($enrollment['enrolled_at'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="obligation-badges">
                            <?php if ($enrollment['has_obligations']): ?>
                                <?php if ($enrollment['upcoming_slots'] > 0): ?>
                                    <span class="obligation-badge info"><i class='bx bx-calendar'></i> <?= $enrollment['upcoming_slots'] ?> upcoming slots</span>
                                <?php endif; ?>
                                <?php if ($enrollment['pending_photos'] > 0): ?>
                                    <span class="obligation-badge warning"><i class='bx bx-camera'></i> <?= $enrollment['pending_photos'] ?> pending photos</span>
                                <?php endif; ?>
                                <?php if ($enrollment['pending_reviews'] > 0): ?>
                                    <span class="obligation-badge danger"><i class='bx bx-time'></i> <?= $enrollment['pending_reviews'] ?> pending reviews</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="obligation-badge" style="background: #d1fae5; color: #065f46;"><i class='bx bx-check'></i> No obligations</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="unenroll-form">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to force unenroll this teacher?');">
                                <input type="hidden" name="action" value="force_unenroll">
                                <input type="hidden" name="teacher_id" value="<?= $enrollment['teacher_id'] ?>">
                                <input type="hidden" name="school_id" value="<?= $enrollment['school_id'] ?>">
                                <textarea name="reason" placeholder="Reason for force unenrollment (required)" required></textarea>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-danger"><i class='bx bx-user-x'></i> Force Unenroll</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-user-check'></i>
                    <h3>No Enrollments</h3>
                    <p>No teacher-school enrollments found.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="audit-tab" class="tab-content">
            <?php if (count($audit_entries) > 0): ?>
                <?php foreach ($audit_entries as $entry): 
                    $details = json_decode($entry['details'], true);
                ?>
                <div class="audit-entry">
                    <p class="time"><i class='bx bx-time'></i> <?= date('M d, Y h:i A', strtotime($entry['created_at'])) ?></p>
                    <p class="action">
                        <strong><?= htmlspecialchars($entry['admin_name'] ?? 'Admin') ?></strong> unenrolled 
                        <strong><?= htmlspecialchars($entry['teacher_name'] ?? 'Teacher') ?></strong> from 
                        <strong><?= htmlspecialchars($entry['school_name'] ?? 'School') ?></strong>
                    </p>
                    <?php if (!empty($details['reason'])): ?>
                    <p class="details">"<?= htmlspecialchars($details['reason']) ?>"</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-history'></i>
                    <h3>No History</h3>
                    <p>No force unenrollments have been recorded.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector(`[onclick="showTab('${tab}')"]`).classList.add('active');
            document.getElementById(tab + '-tab').classList.add('active');
        }
        
        function filterEnrollments() {
            const school = document.getElementById('filter-school').value;
            const obligations = document.getElementById('filter-obligations').value;
            document.querySelectorAll('.enrollment-card').forEach(card => {
                let show = true;
                if (school && card.dataset.school !== school) show = false;
                if (obligations === 'with' && card.dataset.hasObligations !== '1') show = false;
                if (obligations === 'without' && card.dataset.hasObligations !== '0') show = false;
                card.style.display = show ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>

</html>
