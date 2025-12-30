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
