<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';
require_once '../utils/verification_alerts.php';

$submission_id = intval($_GET['id'] ?? 0);

if (!$submission_id) {
    header("Location: pending_verifications.php");
    exit;
}

// Get submission details with all related info
$sql = "SELECT tas.*,
               t.fname as teacher_name, t.email as teacher_email, t.uname as teacher_uname,
               s.school_name, s.school_code,
               sl.latitude as school_lat, sl.longitude as school_lng, 
               sl.validation_radius_meters, sl.address as school_address,
               a.fname as verified_by_name
        FROM teaching_activity_submissions tas
        JOIN teacher t ON tas.teacher_id = t.id
        JOIN schools s ON tas.school_id = s.school_id
        LEFT JOIN school_locations sl ON s.school_id = sl.school_id
        LEFT JOIN admin a ON tas.verified_by = a.id
        WHERE tas.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $submission_id);
mysqli_stmt_execute($stmt);
$submission = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$submission) {
    header("Location: pending_verifications.php?error=not_found");
    exit;
}

// Get teacher's other submissions for context
$history_sql = "SELECT COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM teaching_activity_submissions
                WHERE teacher_id = ?";
$stmt = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($stmt, "i", $submission['teacher_id']);
mysqli_stmt_execute($stmt);
$teacher_history = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Get alerts for this submission
$alertSystem = new VerificationAlerts($conn);
$alerts = $alertSystem->checkSubmissionAlerts($submission_id);

$error_msg = isset($_GET['error']) ? 'Failed to process verification. Please try again.' : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Submission #<?= $submission_id ?> | ExamFlow Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .verification-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 25px;
            margin-top: 20px;
        }
        
        @media (max-width: 1200px) {
            .verification-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .photo-container {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        
        .photo-container img {
            width: 100%;
            max-height: 600px;
            object-fit: contain;
            display: block;
        }
        
        .photo-meta {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 20px;
        }
        
        .photo-meta p {
            margin: 5px 0;
            font-size: 13px;
        }
        
        #map {
            height: 300px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .detail-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .detail-section h3 {
            font-size: 14px;
            text-transform: uppercase;
            color: #666;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
            font-size: 13px;
        }
        
        .detail-value {
            font-weight: 500;
            font-size: 14px;
            text-align: right;
        }
        
        .location-alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .location-alert.matched {
            background: #e8f5e9;
            border: 1px solid #66bb6a;
        }
        
        .location-alert.mismatched {
            background: #fff3e0;
            border: 1px solid #ffb74d;
        }
        
        .location-alert.unknown {
            background: #e3f2fd;
            border: 1px solid #64b5f6;
        }
        
        .action-form {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .action-form h3 {
            margin: 0 0 20px 0;
        }
        
        .action-form textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 15px;
        }
        
        .action-form textarea:focus {
            outline: none;
            border-color: #7C0A02;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .action-buttons .btn {
            flex: 1;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 600;
        }
        
        .btn-approve {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .btn-approve:hover {
            background: #059669;
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .btn-reject:hover {
            background: #dc2626;
        }
        
        .teacher-history {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .history-stat {
            text-align: center;
            padding: 10px 15px;
            background: #f5f5f5;
            border-radius: 8px;
            flex: 1;
        }
        
        .history-stat .number {
            font-size: 20px;
            font-weight: 700;
            display: block;
        }
        
        .history-stat .label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 15px 20px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .verified-badge.approved {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .verified-badge.rejected {
            background: #ffebee;
            color: #c62828;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .back-link:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <a href="pending_verifications.php" class="back-link">← Back to Pending Verifications</a>
        
        <div class="page-header">
            <h1>Verification Review #<?= $submission_id ?></h1>
            <span class="badge badge-<?= $submission['verification_status'] ?>" style="font-size: 14px; padding: 8px 16px;">
                <?= ucfirst($submission['verification_status']) ?>
            </span>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-error"><?= $error_msg ?></div>
        <?php endif; ?>

        <!-- System Alerts -->
        <?php if (!empty($alerts)): ?>
        <div class="system-alerts" style="margin-bottom: 20px;">
            <?php foreach ($alerts as $alert): ?>
            <div class="submission-alert <?= $alert['type'] ?>">
                <span class="alert-icon"><?= $alert['icon'] ?? '<i class="bx bx-error"></i>' ?></span>
                <div class="alert-content">
                    <strong><?= htmlspecialchars($alert['title']) ?></strong>
                    <p><?= htmlspecialchars($alert['message']) ?></p>
                    <?php if (!empty($alert['detail'])): ?>
                    <small><?= htmlspecialchars($alert['detail']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <style>
            .system-alerts { display: flex; flex-wrap: wrap; gap: 10px; }
            .submission-alert {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 12px 16px;
                border-radius: 10px;
                flex: 1 1 300px;
                min-width: 250px;
            }
            .submission-alert.warning { background: #fef3c7; border: 1px solid #f59e0b; }
            .submission-alert.danger { background: #fee2e2; border: 1px solid #ef4444; }
            .submission-alert.info { background: #e0e7ff; border: 1px solid #6366f1; }
            .submission-alert.success { background: #d1fae5; border: 1px solid #10b981; }
            .submission-alert .alert-icon { font-size: 20px; }
            .submission-alert .alert-content { flex: 1; }
            .submission-alert .alert-content strong { display: block; font-size: 14px; margin-bottom: 2px; }
            .submission-alert .alert-content p { margin: 0; font-size: 13px; color: #555; }
            .submission-alert .alert-content small { font-size: 11px; color: #888; }
        </style>
        <?php endif; ?>

        <div class="verification-layout">
            <!-- Left Column - Photo and Map -->
            <div class="left-column">
                <!-- Photo Preview -->
                <div class="photo-container">
                    <img src="../<?= htmlspecialchars($submission['image_path']) ?>" 
                         alt="Teaching Activity Photo"
                         onclick="window.open(this.src, '_blank')">
                    <div class="photo-meta">
                        <p><i class='bx bx-calendar'></i> Activity Date: <strong><?= date('F d, Y', strtotime($submission['activity_date'])) ?></strong></p>
                        <?php if ($submission['photo_taken_at']): ?>
                        <p><i class='bx bx-camera'></i> Photo Taken: <strong><?= date('F d, Y H:i:s', strtotime($submission['photo_taken_at'])) ?></strong></p>
                        <?php endif; ?>
                        <p><i class='bx bx-map-pin'></i> GPS: <strong><?= $submission['gps_latitude'] ?>, <?= $submission['gps_longitude'] ?></strong></p>
                    </div>
                </div>

                <!-- Map -->
                <?php if ($submission['gps_latitude'] && $submission['gps_longitude']): ?>
                <div class="detail-section">
                    <h3><i class='bx bx-map'></i> Location Map</h3>
                    <div id="map"></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Details and Actions -->
            <div class="right-column">
                <!-- Location Status Alert -->
                <div class="location-alert <?= $submission['location_match_status'] ?>">
                    <?php if ($submission['location_match_status'] === 'matched'): ?>
                        <strong>✓ Location Verified</strong>
                        <p>Photo was taken within <?= $submission['validation_radius_meters'] ?? 500 ?>m of school location.</p>
                    <?php elseif ($submission['location_match_status'] === 'mismatched'): ?>
                        <strong><i class='bx bx-error'></i> Location Mismatch</strong>
                        <p>Photo is <?= round($submission['distance_from_school']) ?>m from school (allowed: <?= $submission['validation_radius_meters'] ?? 500 ?>m)</p>
                    <?php else: ?>
                        <strong><i class='bx bx-question-mark'></i> Location Unknown</strong>
                        <p>School location not configured or GPS data missing.</p>
                    <?php endif; ?>
                </div>

                <!-- Teacher Info -->
                <div class="detail-section">
                    <h3><i class='bx bx-user'></i> Teacher Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Name</span>
                        <span class="detail-value"><?= htmlspecialchars($submission['teacher_name']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Username</span>
                        <span class="detail-value"><?= htmlspecialchars($submission['teacher_uname']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?= htmlspecialchars($submission['teacher_email']) ?></span>
                    </div>
                    
                    <div class="teacher-history">
                        <div class="history-stat">
                            <span class="number"><?= $teacher_history['total'] ?></span>
                            <span class="label">Total</span>
                        </div>
                        <div class="history-stat" style="background: #e8f5e9;">
                            <span class="number" style="color: #2e7d32;"><?= $teacher_history['approved'] ?></span>
                            <span class="label">Approved</span>
                        </div>
                        <div class="history-stat" style="background: #ffebee;">
                            <span class="number" style="color: #c62828;"><?= $teacher_history['rejected'] ?></span>
                            <span class="label">Rejected</span>
                        </div>
                    </div>
                </div>

                <!-- School Info -->
                <div class="detail-section">
                    <h3><i class='bx bx-building'></i> School Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">School Name</span>
                        <span class="detail-value"><?= htmlspecialchars($submission['school_name']) ?></span>
                    </div>
                    <?php if ($submission['school_code']): ?>
                    <div class="detail-row">
                        <span class="detail-label">School Code</span>
                        <span class="detail-value"><?= htmlspecialchars($submission['school_code']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($submission['school_address']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Address</span>
                        <span class="detail-value"><?= htmlspecialchars($submission['school_address']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($submission['school_lat'] && $submission['school_lng']): ?>
                    <div class="detail-row">
                        <span class="detail-label">School GPS</span>
                        <span class="detail-value"><?= $submission['school_lat'] ?>, <?= $submission['school_lng'] ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Validation Radius</span>
                        <span class="detail-value"><?= $submission['validation_radius_meters'] ?? 500 ?>m</span>
                    </div>
                    <?php else: ?>
                    <div class="detail-row">
                        <span class="detail-label">School Location</span>
                        <span class="detail-value text-warning">Not configured</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Submission Details -->
                <div class="detail-section">
                    <h3><i class='bx bx-file'></i> Submission Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Activity Date</span>
                        <span class="detail-value"><?= date('F d, Y', strtotime($submission['activity_date'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Uploaded</span>
                        <span class="detail-value"><?= date('M d, Y H:i', strtotime($submission['upload_date'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Distance</span>
                        <span class="detail-value">
                            <?= $submission['distance_from_school'] 
                                ? round($submission['distance_from_school']) . 'm' 
                                : 'N/A' ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">File Size</span>
                        <span class="detail-value"><?= round($submission['image_size'] / 1024) ?> KB</span>
                    </div>
                </div>

                <!-- Verification Action / Status -->
                <?php if ($submission['verification_status'] === 'pending'): ?>
                <div class="action-form">
                    <h3><i class='bx bx-check-circle'></i> Verification Action</h3>
                    <form action="process_verification.php" method="POST" id="verifyForm">
                        <input type="hidden" name="submission_id" value="<?= $submission['id'] ?>">
                        
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                            Admin Remarks (optional)
                        </label>
                        <textarea name="remarks" placeholder="Enter any notes or reasons for your decision..."></textarea>
                        
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approve" class="btn btn-approve"
                                    onclick="return confirm('Approve this submission?')">
                                ✓ Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-reject"
                                    onclick="return confirm('Reject this submission?')">
                                ✕ Reject
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="detail-section">
                    <h3><i class='bx bx-check-circle'></i> Verification Status</h3>
                    <div class="verified-badge <?= $submission['verification_status'] ?>">
                        <?= $submission['verification_status'] === 'approved' ? '✓ Approved' : '✕ Rejected' ?>
                    </div>
                    <?php if ($submission['verified_by_name']): ?>
                    <div class="detail-row" style="margin-top: 15px;">
                        <span class="detail-label">Verified By</span>
                        <span class="detail-value"><?= htmlspecialchars($submission['verified_by_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($submission['verified_at']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Verified At</span>
                        <span class="detail-value"><?= date('M d, Y H:i', strtotime($submission['verified_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($submission['admin_remarks']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Remarks</span>
                        <span class="detail-value"><?= htmlspecialchars($submission['admin_remarks']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($submission['gps_latitude'] && $submission['gps_longitude']): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        const photoLat = <?= $submission['gps_latitude'] ?>;
        const photoLng = <?= $submission['gps_longitude'] ?>;
        const schoolLat = <?= $submission['school_lat'] ?? 'null' ?>;
        const schoolLng = <?= $submission['school_lng'] ?? 'null' ?>;
        const radius = <?= $submission['validation_radius_meters'] ?? 500 ?>;
        const locationStatus = '<?= $submission['location_match_status'] ?>';

        const map = L.map('map').setView([photoLat, photoLng], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Photo location marker (red)
        const photoIcon = L.divIcon({
            html: '<div style="background:#ef4444;width:20px;height:20px;border-radius:50%;border:3px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);"></div>',
            className: 'photo-marker',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });
        
        L.marker([photoLat, photoLng], {icon: photoIcon})
            .addTo(map)
            .bindPopup('<strong><i class="bx bx-camera"></i> Photo Location</strong><br>' + photoLat.toFixed(6) + ', ' + photoLng.toFixed(6));

        // School location marker and radius
        if (schoolLat && schoolLng) {
            const schoolIcon = L.divIcon({
                html: '<div style="background:#7C0A02;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;font-size:12px;"><i class=\'bx bx-building-house\' style=\'color:white;font-size:12px;\'></i></div>',
                className: 'school-marker',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });
            
            L.marker([schoolLat, schoolLng], {icon: schoolIcon})
                .addTo(map)
                .bindPopup('<strong><i class="bx bx-building"></i> School Location</strong>');

            // Validation radius circle
            const circleColor = locationStatus === 'matched' ? '#10b981' : '#f59e0b';
            L.circle([schoolLat, schoolLng], {
                radius: radius,
                color: circleColor,
                fillColor: circleColor,
                fillOpacity: 0.15,
                weight: 2
            }).addTo(map);

            // Fit bounds to show both markers
            const bounds = L.latLngBounds([[photoLat, photoLng], [schoolLat, schoolLng]]);
            map.fitBounds(bounds, {padding: [50, 50]});
        }
    </script>
    <?php endif; ?>
</body>
</html>
