<?php
/**
 * Teacher Portal - View Session Details
 * Phase 5: View teaching session with photo proof status
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';

$teacher_id = $_SESSION['user_id'];
$session_id = intval($_GET['id'] ?? 0);

if ($session_id <= 0) {
    header("Location: my_slots.php?error=Invalid session");
    exit;
}

// Get session details
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
    <title>Session Details #<?= $session_id ?></title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .session-container {
            padding: 20px;
            max-width: 1000px;
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
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 25px;
        }
        @media (max-width: 900px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
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
        }
        .card-body {
            padding: 20px;
        }
        .photo-section img {
            width: 100%;
            border-radius: 10px;
            cursor: pointer;
        }
        .photo-section img:hover {
            opacity: 0.95;
        }
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
        .photo-meta-row:last-child {
            border-bottom: none;
        }
        .photo-meta-row label {
            color: #888;
        }
        .photo-meta-row span {
            font-weight: 500;
        }
        .photo-meta-row span.success { color: #10b981; }
        .photo-meta-row span.warning { color: #f59e0b; }
        .photo-meta-row span.danger { color: #ef4444; }
        #map {
            height: 200px;
            border-radius: 10px;
            margin-top: 15px;
        }
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-row i {
            color: #667eea;
            font-size: 18px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }
        .info-row .info-content {
            flex: 1;
        }
        .info-row .info-label {
            font-size: 12px;
            color: #999;
        }
        .info-row .info-value {
            font-weight: 500;
            color: #333;
        }
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
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-warning { background: #fef3c7; color: #92400e; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-info { background: #dbeafe; color: #1e40af; }
        .timeline {
            margin-top: 15px;
        }
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
        .timeline-dot.active {
            background: #10b981;
            color: white;
        }
        .timeline-dot.pending {
            background: #f59e0b;
            color: white;
        }
        .timeline-content h4 {
            font-size: 14px;
            margin-bottom: 3px;
        }
        .timeline-content p {
            font-size: 12px;
            color: #888;
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
        .empty-photo p {
            color: #888;
            margin-bottom: 15px;
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
            
            <?php if (isset($_GET['info'])): ?>
            <div class="alert alert-info">
                <?= htmlspecialchars($_GET['info']) ?>
            </div>
            <?php endif; ?>
            
            <!-- Session Header -->
            <div class="session-header">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h1>🏫 <?= htmlspecialchars($session['school_name']) ?></h1>
                        <p><?= htmlspecialchars($session['full_address'] ?: 'No address provided') ?></p>
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
            
            <div class="content-grid">
                <!-- Main Content -->
                <div>
                    <!-- Photo Section -->
                    <div class="card" style="margin-bottom: 20px;">
                        <div class="card-header">📷 Session Photo</div>
                        <div class="card-body">
                            <?php if ($session['photo_path']): ?>
                            <div class="photo-section">
                                <img src="../<?= htmlspecialchars($session['photo_path']) ?>" 
                                     alt="Session Photo" 
                                     onclick="window.open('../<?= htmlspecialchars($session['photo_path']) ?>', '_blank')">
                                
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
                            
                            <?php if ($session['session_status'] === 'rejected' && $can_upload): ?>
                            <div class="alert alert-error" style="margin-top: 15px;">
                                <strong>❌ Photo Rejected</strong>
                                <?php if ($session['admin_remarks']): ?>
                                <p style="margin-top: 5px;"><?= htmlspecialchars($session['admin_remarks']) ?></p>
                                <?php endif; ?>
                            </div>
                            <a href="upload_session_photo.php?session=<?= $session_id ?>" class="btn btn-warning btn-block" style="margin-top: 15px;">
                                <i class='bx bx-upload'></i> Upload New Photo
                            </a>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <div class="empty-photo">
                                <i class='bx bx-camera-off'></i>
                                <p>No photo uploaded yet</p>
                                <?php if ($can_upload): ?>
                                <a href="upload_session_photo.php?session=<?= $session_id ?>" class="btn btn-primary">
                                    <i class='bx bx-upload'></i> Upload Photo Now
                                </a>
                                <?php elseif (!$is_today && !$is_past): ?>
                                <p style="font-size: 13px; color: #888;">
                                    Upload will be available on <?= date('M j, Y', strtotime($session['slot_date'])) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($session['admin_remarks'] && $session['session_status'] === 'approved'): ?>
                    <div class="card" style="margin-bottom: 20px;">
                        <div class="card-header">💬 Admin Feedback</div>
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
                
                <!-- Sidebar -->
                <div>
                    <!-- Session Timeline -->
                    <div class="card" style="margin-bottom: 20px;">
                        <div class="card-header">📊 Session Timeline</div>
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
                        </div>
                    </div>
                    
                    <!-- Slot Info -->
                    <div class="card">
                        <div class="card-header">ℹ️ Slot Information</div>
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
                            <?php if ($session['contact_person']): ?>
                            <div class="info-row">
                                <i class='bx bx-user'></i>
                                <div class="info-content">
                                    <div class="info-label">Contact</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars($session['contact_person']) ?>
                                        <?php if ($session['contact_phone']): ?>
                                        <br><small><?= htmlspecialchars($session['contact_phone']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($session['slot_desc']): ?>
                            <div class="info-row">
                                <i class='bx bx-info-circle'></i>
                                <div class="info-content">
                                    <div class="info-label">Notes</div>
                                    <div class="info-value"><?= htmlspecialchars($session['slot_desc']) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
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
        
        <?php if ($session['gps_latitude'] && $session['gps_longitude'] && $session['school_lat'] && $session['school_lng']): ?>
        // Map initialization
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
                    html: '<div style="background:#667eea;color:white;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;">🏫</div>'
                })
            }).addTo(map).bindPopup('School');
            
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
                    html: '<div style="background:#10b981;color:white;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;">📷</div>'
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
