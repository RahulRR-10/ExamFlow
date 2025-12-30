<?php
/**
 * Admin - Review Session
 * Phase 6: Detailed session review with approve/reject functionality
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];
$message = '';
$error = '';

$session_id = intval($_GET['id'] ?? $_POST['session_id'] ?? 0);
$redirect = $_POST['redirect'] ?? '';

if ($session_id <= 0) {
    header("Location: pending_sessions.php");
    exit;
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($action === 'approve') {
        $sql = "UPDATE teaching_sessions SET 
                session_status = 'approved',
                verified_by = ?,
                verified_at = NOW(),
                admin_remarks = ?
                WHERE session_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $admin_id, $remarks, $session_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Session approved successfully!";
            logAdminAction($conn, $admin_id, 'approve_session', "Approved session #$session_id", null, 'teaching_sessions', $session_id);
            
            // Also update enrollment status to completed
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
                $message = "Session rejected. Teacher can resubmit a new photo.";
                logAdminAction($conn, $admin_id, 'reject_session', "Rejected session #$session_id: $remarks", null, 'teaching_sessions', $session_id);
                
                if ($redirect) {
                    header("Location: $redirect?rejected=1");
                    exit;
                }
            } else {
                $error = "Failed to reject session.";
            }
        }
    }
}

// Get session details
$session_sql = "SELECT ts.*, t.fname as teacher_name, t.email as teacher_email, t.subject,
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

$distance = $session['distance_from_school'];
$allowed_radius = $session['allowed_radius'] ?? 500;
$distance_ok = $distance !== null && $distance <= $allowed_radius;

// Check photo date vs session date
$date_match = true;
$date_diff_hours = null;
if ($session['photo_taken_at']) {
    $photo_date = date('Y-m-d', strtotime($session['photo_taken_at']));
    $date_match = ($photo_date === $session['slot_date']);
    if (!$date_match) {
        $date_diff_hours = abs(strtotime($session['photo_taken_at']) - strtotime($session['slot_date'])) / 3600;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Review Session #<?= $session_id ?> | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .review-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 25px;
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
            margin-bottom: 20px;
        }
        .back-link:hover { color: var(--primary-color); }
        .photo-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
        }
        .photo-main {
            position: relative;
            background: #000;
        }
        .photo-main img {
            width: 100%;
            max-height: 500px;
            object-fit: contain;
            cursor: zoom-in;
        }
        .photo-status-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 15px;
            background: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .status-badge.photo_submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .validation-badges {
            display: flex;
            gap: 10px;
        }
        .validation-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .validation-badge.success { background: #dcfce7; color: #166534; }
        .validation-badge.warning { background: #fef3c7; color: #92400e; }
        .validation-badge.danger { background: #fee2e2; color: #991b1b; }
        .validation-badge.unknown { background: #e5e7eb; color: #374151; }
        .photo-meta {
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .meta-item {
            font-size: 14px;
        }
        .meta-item label {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 3px;
        }
        .meta-item span {
            font-weight: 500;
        }
        #map {
            height: 250px;
            border-radius: 10px;
            margin: 0 20px 20px;
        }
        .action-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
        }
        .action-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .action-header h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .action-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .action-body {
            padding: 20px;
        }
        .info-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .info-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-section h3 {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .info-row:last-child { margin-bottom: 0; }
        .info-row .label { color: var(--text-muted); }
        .info-row .value { font-weight: 500; }
        .action-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            min-height: 80px;
            resize: vertical;
            margin-bottom: 15px;
            font-family: inherit;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-buttons .btn {
            flex: 1;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .alert-warning { background: #fef3c7; color: #92400e; }
        .verification-checklist {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .verification-checklist h4 {
            font-size: 14px;
            margin-bottom: 12px;
        }
        .check-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .check-item:last-child { margin-bottom: 0; }
        .check-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .check-icon.pass { background: #dcfce7; color: #166534; }
        .check-icon.fail { background: #fee2e2; color: #991b1b; }
        .check-icon.warn { background: #fef3c7; color: #92400e; }
        .check-icon.unknown { background: #e5e7eb; color: #374151; }
        .no-photo {
            padding: 60px 20px;
            text-align: center;
            background: #f8f9fa;
        }
        .no-photo i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
        .previous-action {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .previous-action h4 {
            font-size: 14px;
            color: #0369a1;
            margin-bottom: 8px;
        }
        .previous-action p {
            font-size: 13px;
            color: #0c4a6e;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <a href="pending_sessions.php" class="back-link">
            ← Back to Session Reviews
        </a>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="review-container">
            <!-- Photo Section -->
            <div class="photo-card">
                <?php if ($session['photo_path']): ?>
                <div class="photo-main">
                    <img src="../<?= htmlspecialchars($session['photo_path']) ?>" 
                         alt="Session Photo"
                         onclick="window.open('../<?= htmlspecialchars($session['photo_path']) ?>', '_blank')">
                    <div class="photo-status-bar">
                        <span class="status-badge <?= $session['session_status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?>
                        </span>
                        <div class="validation-badges">
                            <?php if ($session['gps_latitude'] && $session['gps_longitude']): ?>
                                <?php if ($distance_ok): ?>
                                <span class="validation-badge success">📍 <?= number_format($distance) ?>m ✓</span>
                                <?php else: ?>
                                <span class="validation-badge danger">📍 <?= number_format($distance) ?>m ✗</span>
                                <?php endif; ?>
                            <?php else: ?>
                            <span class="validation-badge unknown">📍 No GPS</span>
                            <?php endif; ?>
                            
                            <?php if ($date_match): ?>
                            <span class="validation-badge success">📅 Date ✓</span>
                            <?php elseif ($session['photo_taken_at']): ?>
                            <span class="validation-badge warning">📅 Date ≠</span>
                            <?php else: ?>
                            <span class="validation-badge unknown">📅 Unknown</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="photo-meta">
                    <div class="meta-item">
                        <label>Uploaded At</label>
                        <span><?= date('M j, Y h:i A', strtotime($session['photo_uploaded_at'])) ?></span>
                    </div>
                    <?php if ($session['photo_taken_at']): ?>
                    <div class="meta-item">
                        <label>Photo Taken</label>
                        <span><?= date('M j, Y h:i A', strtotime($session['photo_taken_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($session['gps_latitude'] && $session['gps_longitude']): ?>
                    <div class="meta-item">
                        <label>Photo GPS</label>
                        <span><?= number_format($session['gps_latitude'], 6) ?>, <?= number_format($session['gps_longitude'], 6) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <label>Distance from School</label>
                        <span style="color: <?= $distance_ok ? '#10b981' : ($distance !== null ? '#ef4444' : '#9ca3af') ?>">
                            <?= $distance !== null ? number_format($distance) . 'm' : 'Unknown' ?>
                            (max: <?= $allowed_radius ?>m)
                        </span>
                    </div>
                </div>
                
                <?php if ($session['gps_latitude'] && $session['gps_longitude'] && $session['school_lat'] && $session['school_lng']): ?>
                <div id="map"></div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="no-photo">
                    <div style="font-size: 48px; color: #ccc; margin-bottom: 15px;">📷</div>
                    <h3>No Photo Uploaded</h3>
                    <p style="color: var(--text-muted);">Teacher has not submitted a photo for this session yet.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Action Panel -->
            <div class="action-card">
                <div class="action-header">
                    <h2>🏫 <?= htmlspecialchars($session['school_name']) ?></h2>
                    <p><?= htmlspecialchars($session['full_address'] ?: 'No address') ?></p>
                </div>
                <div class="action-body">
                    <!-- Session Info -->
                    <div class="info-section">
                        <h3>👤 Teacher</h3>
                        <div class="info-row">
                            <span class="label">Name</span>
                            <span class="value"><?= htmlspecialchars($session['teacher_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Email</span>
                            <span class="value"><?= htmlspecialchars($session['teacher_email']) ?></span>
                        </div>
                        <?php if ($session['subject']): ?>
                        <div class="info-row">
                            <span class="label">Subject</span>
                            <span class="value"><?= htmlspecialchars($session['subject']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-section">
                        <h3>📅 Slot Details</h3>
                        <div class="info-row">
                            <span class="label">Date</span>
                            <span class="value"><?= date('M j, Y', strtotime($session['slot_date'])) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Time</span>
                            <span class="value"><?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></span>
                        </div>
                        <?php if ($session['contact_person']): ?>
                        <div class="info-row">
                            <span class="label">Contact</span>
                            <span class="value"><?= htmlspecialchars($session['contact_person']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Verification Checklist -->
                    <div class="verification-checklist">
                        <h4>✅ Verification Checklist</h4>
                        <div class="check-item">
                            <span class="check-icon <?= $session['photo_path'] ? 'pass' : 'fail' ?>">
                                <?= $session['photo_path'] ? '✓' : '✗' ?>
                            </span>
                            <span>Photo uploaded</span>
                        </div>
                        <div class="check-item">
                            <span class="check-icon <?= ($session['gps_latitude'] && $session['gps_longitude']) ? ($distance_ok ? 'pass' : 'fail') : 'unknown' ?>">
                                <?= ($session['gps_latitude'] && $session['gps_longitude']) ? ($distance_ok ? '✓' : '✗') : '?' ?>
                            </span>
                            <span>Location within <?= $allowed_radius ?>m of school</span>
                        </div>
                        <div class="check-item">
                            <span class="check-icon <?= $session['photo_taken_at'] ? ($date_match ? 'pass' : 'warn') : 'unknown' ?>">
                                <?= $session['photo_taken_at'] ? ($date_match ? '✓' : '!' ) : '?' ?>
                            </span>
                            <span>Photo date matches session date</span>
                        </div>
                    </div>
                    
                    <?php if ($session['verified_at']): ?>
                    <div class="previous-action">
                        <h4><?= $session['session_status'] === 'approved' ? '✅ Previously Approved' : '❌ Previously Rejected' ?></h4>
                        <p>By <?= htmlspecialchars($session['verified_by_name']) ?> on <?= date('M j, Y h:i A', strtotime($session['verified_at'])) ?></p>
                        <?php if ($session['admin_remarks']): ?>
                        <p style="margin-top: 8px;"><em>"<?= htmlspecialchars($session['admin_remarks']) ?>"</em></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Form -->
                    <?php if ($session['session_status'] === 'photo_submitted'): ?>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="session_id" value="<?= $session_id ?>">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Remarks (optional for approval, required for rejection)</label>
                        <textarea name="remarks" placeholder="Enter any remarks or feedback..."></textarea>
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approve" class="btn btn-success">
                                ✓ Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger"
                                    onclick="if(!document.querySelector('textarea[name=remarks]').value.trim()){alert('Please enter a rejection reason');return false;}">
                                ✗ Reject
                            </button>
                        </div>
                    </form>
                    <?php elseif ($session['session_status'] === 'approved'): ?>
                    <div class="alert alert-success" style="margin-bottom: 0;">
                        ✅ This session has been approved.
                    </div>
                    <?php elseif ($session['session_status'] === 'rejected'): ?>
                    <div class="alert alert-danger" style="margin-bottom: 15px;">
                        ❌ This session was rejected. Teacher can resubmit.
                    </div>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="session_id" value="<?= $session_id ?>">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Change decision:</label>
                        <textarea name="remarks" placeholder="Enter remarks..."></textarea>
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-block">
                            ✓ Approve Instead
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($session['gps_latitude'] && $session['gps_longitude'] && $session['school_lat'] && $session['school_lng']): ?>
    <script>
        const photoLat = <?= $session['gps_latitude'] ?>;
        const photoLng = <?= $session['gps_longitude'] ?>;
        const schoolLat = <?= $session['school_lat'] ?>;
        const schoolLng = <?= $session['school_lng'] ?>;
        const allowedRadius = <?= $allowed_radius ?>;
        const distanceOk = <?= $distance_ok ? 'true' : 'false' ?>;
        
        const map = L.map('map').fitBounds([
            [Math.min(photoLat, schoolLat) - 0.003, Math.min(photoLng, schoolLng) - 0.003],
            [Math.max(photoLat, schoolLat) + 0.003, Math.max(photoLng, schoolLng) + 0.003]
        ]);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        
        // School marker
        L.marker([schoolLat, schoolLng], {
            icon: L.divIcon({
                className: 'school-marker',
                html: '<div style="background:#667eea;color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);">🏫</div>'
            })
        }).addTo(map).bindPopup('<b>School Location</b>');
        
        // Allowed radius circle
        L.circle([schoolLat, schoolLng], {
            color: distanceOk ? '#10b981' : '#ef4444',
            fillColor: distanceOk ? '#10b981' : '#ef4444',
            fillOpacity: 0.1,
            radius: allowedRadius
        }).addTo(map);
        
        // Photo location marker
        L.marker([photoLat, photoLng], {
            icon: L.divIcon({
                className: 'photo-marker',
                html: '<div style="background:' + (distanceOk ? '#10b981' : '#ef4444') + ';color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);">📷</div>'
            })
        }).addTo(map).bindPopup('<b>Photo Location</b><br>Distance: <?= number_format($distance) ?>m');
        
        // Line between points
        L.polyline([[schoolLat, schoolLng], [photoLat, photoLng]], {
            color: distanceOk ? '#10b981' : '#ef4444',
            dashArray: '8, 8',
            weight: 2
        }).addTo(map);
    </script>
    <?php endif; ?>
</body>
</html>
