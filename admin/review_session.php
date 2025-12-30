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
            grid-template-columns: 1fr 380px;
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        @media (max-width: 1000px) {
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
        
        /* Photo Section */
        .photo-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .photo-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
        }
        .photo-wrapper {
            position: relative;
            background: #1a1a2e;
            padding: 20px;
        }
        .photo-wrapper img {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            cursor: zoom-in;
            display: block;
            border-radius: 8px;
        }
        .photo-overlay {
            position: absolute;
            top: 30px;
            left: 30px;
            right: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .status-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-pill.photo_submitted { background: #3b82f6; color: white; }
        .status-pill.approved { background: #10b981; color: white; }
        .status-pill.rejected { background: #ef4444; color: white; }
        .status-pill.pending { background: #f59e0b; color: white; }
        
        .quick-badges {
            display: flex;
            gap: 8px;
        }
        .quick-badge {
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(255,255,255,0.95);
        }
        .quick-badge.ok { color: #059669; }
        .quick-badge.warn { color: #d97706; }
        .quick-badge.bad { color: #dc2626; }
        
        .map-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            padding: 15px;
        }
        .map-card h4 {
            font-size: 13px;
            font-weight: 600;
            margin: 0 0 12px 0;
            color: var(--text-muted);
        }
        #map {
            height: 220px;
            border-radius: 8px;
        }
        
        .no-photo {
            padding: 80px 20px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        /* Side Panel */
        .side-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .panel-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
        }
        .panel-header {
            padding: 16px 20px;
            background: #7C0A02;
            color: white;
        }
        .panel-header h2 {
            font-size: 16px;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .panel-header p {
            font-size: 13px;
            opacity: 0.9;
            margin: 0;
        }
        .panel-body {
            padding: 16px 20px;
        }
        .info-grid {
            display: grid;
            gap: 10px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .info-item .label { color: var(--text-muted); }
        .info-item .value { font-weight: 500; text-align: right; }
        
        .divider {
            height: 1px;
            background: var(--border-color);
            margin: 12px 0;
        }
        
        .checklist {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
        }
        .checklist-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .check-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .check-row:last-child { margin-bottom: 0; }
        .check-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
        }
        .check-icon.pass { background: #dcfce7; color: #166534; }
        .check-icon.fail { background: #fee2e2; color: #991b1b; }
        .check-icon.warn { background: #fef3c7; color: #92400e; }
        .check-icon.unknown { background: #e5e7eb; color: #6b7280; }
        
        /* Action Form */
        .action-form textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            min-height: 70px;
            resize: vertical;
            font-family: inherit;
            font-size: 13px;
            margin-bottom: 12px;
        }
        .action-form label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .action-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .action-btns .btn {
            padding: 10px 16px;
            font-size: 13px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        
        .prev-decision {
            background: #eff6ff;
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
            margin-bottom: 12px;
        }
        .prev-decision strong { color: #1e40af; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <a href="pending_sessions.php" class="back-link">← Back to Session Reviews</a>
        
        <?php if ($message): ?>
        <div class="alert alert-success" style="margin-bottom: 15px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 15px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="review-container">
            <!-- Photo Section -->
            <div class="photo-section">
                <?php if ($session['photo_path']): ?>
                <div class="photo-card">
                    <div class="photo-wrapper">
                        <img src="../<?= htmlspecialchars($session['photo_path']) ?>" 
                             alt="Session Photo"
                             onclick="window.open('../<?= htmlspecialchars($session['photo_path']) ?>', '_blank')">
                        <div class="photo-overlay">
                            <span class="status-pill <?= $session['session_status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?>
                            </span>
                            <div class="quick-badges">
                                <?php if ($session['gps_latitude'] && $session['gps_longitude']): ?>
                                <span class="quick-badge <?= $distance_ok ? 'ok' : 'bad' ?>">
                                    <i class='bx bx-map-pin'></i> <?= number_format($distance) ?>m <?= $distance_ok ? '✓' : '✗' ?>
                                </span>
                                <?php endif; ?>
                                <span class="quick-badge <?= $date_match ? 'ok' : 'warn' ?>">
                                    <i class='bx bx-calendar'></i> <?= $date_match ? 'Date ✓' : 'Date ≠' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($session['gps_latitude'] && $session['gps_longitude'] && $session['school_lat'] && $session['school_lng']): ?>
                <div class="map-card">
                    <h4><i class='bx bx-map'></i> Location Verification</h4>
                    <div id="map"></div>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="no-photo">
                    <div style="font-size: 48px; margin-bottom: 15px;"><i class='bx bx-camera'></i></div>
                    <h3>No Photo Uploaded</h3>
                    <p style="color: var(--text-muted);">Teacher has not submitted a photo yet.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Side Panel -->
            <div class="side-panel">
                <!-- School Info -->
                <div class="panel-card">
                    <div class="panel-header">
                        <h2><i class='bx bx-building'></i> <?= htmlspecialchars($session['school_name']) ?></h2>
                        <p><?= htmlspecialchars($session['full_address'] ?: 'No address provided') ?></p>
                    </div>
                    <div class="panel-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="label">Name</span>
                                <span class="value"><?= htmlspecialchars($session['teacher_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Email</span>
                                <span class="value"><?= htmlspecialchars($session['teacher_email']) ?></span>
                            </div>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="label">Date</span>
                                <span class="value"><?= date('M j, Y', strtotime($session['slot_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Time</span>
                                <span class="value"><?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?></span>
                            </div>
                            <?php if ($session['contact_person']): ?>
                            <div class="info-item">
                                <span class="label">Contact</span>
                                <span class="value"><?= htmlspecialchars($session['contact_person']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <!-- Verification Checklist -->
                        <div class="checklist">
                            <div class="checklist-title"><i class='bx bx-check-circle'></i> Verification Checklist</div>
                            <div class="check-row">
                                <span class="check-icon <?= $session['photo_path'] ? 'pass' : 'fail' ?>">
                                    <?= $session['photo_path'] ? '✓' : '✗' ?>
                                </span>
                                <span>Photo uploaded</span>
                            </div>
                            <div class="check-row">
                                <span class="check-icon <?= ($session['gps_latitude'] && $session['gps_longitude']) ? ($distance_ok ? 'pass' : 'fail') : 'unknown' ?>">
                                    <?= ($session['gps_latitude'] && $session['gps_longitude']) ? ($distance_ok ? '✓' : '✗') : '?' ?>
                                </span>
                                <span>Location within <?= $allowed_radius ?>m of school</span>
                            </div>
                            <div class="check-row">
                                <span class="check-icon <?= $session['photo_taken_at'] ? ($date_match ? 'pass' : 'warn') : 'unknown' ?>">
                                    <?= $session['photo_taken_at'] ? ($date_match ? '✓' : '!') : '?' ?>
                                </span>
                                <span>Photo date matches session date</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Card -->
                <div class="panel-card">
                    <div class="panel-body">
                        <?php if ($session['verified_at']): ?>
                        <div class="prev-decision">
                            <strong><?= $session['session_status'] === 'approved' ? '<i class="bx bx-check-circle"></i> Approved' : '<i class="bx bx-x-circle"></i> Rejected' ?></strong>
                            by <?= htmlspecialchars($session['verified_by_name']) ?><br>
                            <small><?= date('M j, Y h:i A', strtotime($session['verified_at'])) ?></small>
                            <?php if ($session['admin_remarks']): ?>
                            <p style="margin-top: 6px; font-style: italic;">"<?= htmlspecialchars($session['admin_remarks']) ?>"</p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($session['session_status'] === 'photo_submitted'): ?>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="session_id" value="<?= $session_id ?>">
                            <label>Remarks (optional for approval, required for rejection)</label>
                            <textarea name="remarks" placeholder="Enter remarks..."></textarea>
                            <div class="action-btns">
                                <button type="submit" name="action" value="approve" class="btn btn-success">✓ Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger"
                                    onclick="if(!document.querySelector('textarea[name=remarks]').value.trim()){alert('Please enter a rejection reason');return false;}">
                                    ✗ Reject
                                </button>
                            </div>
                        </form>
                        <?php elseif ($session['session_status'] === 'approved'): ?>
                        <div class="alert alert-success" style="margin: 0;"><i class='bx bx-check-circle'></i> This session has been approved.</div>
                        <?php elseif ($session['session_status'] === 'rejected'): ?>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="session_id" value="<?= $session_id ?>">
                            <label>Change decision:</label>
                            <textarea name="remarks" placeholder="Enter remarks..."></textarea>
                            <button type="submit" name="action" value="approve" class="btn btn-success" style="width: 100%;">
                                ✓ Approve Instead
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
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
                html: '<div style="background:#7C0A02;color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);"><i class=\'bx bx-building-house\'></i></div>'
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
                html: '<div style="background:' + (distanceOk ? '#10b981' : '#ef4444') + ';color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);"><i class="bx bx-camera"></i></div>'
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
