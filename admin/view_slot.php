<?php
/**
 * Admin - View Slot Details
 * Phase 4: Detailed view of a single teaching slot with enrolled teachers
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];
$message = '';
$error = '';

$slot_id = intval($_GET['id'] ?? 0);

if ($slot_id <= 0) {
    header("Location: teaching_slots.php");
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_no_show') {
        $enrollment_id = intval($_POST['enrollment_id']);
        
        $sql = "UPDATE slot_teacher_enrollments SET enrollment_status = 'no_show' WHERE enrollment_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $enrollment_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Teacher marked as no-show.";
            logAdminAction($conn, $admin_id, 'mark_no_show', "Marked enrollment #$enrollment_id as no-show", null, 'slot_teacher_enrollments', $enrollment_id);
        } else {
            $error = "Failed to update enrollment.";
        }
    } elseif ($action === 'mark_completed') {
        $enrollment_id = intval($_POST['enrollment_id']);
        
        $sql = "UPDATE slot_teacher_enrollments SET enrollment_status = 'completed' WHERE enrollment_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $enrollment_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Enrollment marked as completed.";
            logAdminAction($conn, $admin_id, 'mark_completed', "Marked enrollment #$enrollment_id as completed", null, 'slot_teacher_enrollments', $enrollment_id);
        } else {
            $error = "Failed to update enrollment.";
        }
    }
}

// Get slot details
$slot_sql = "SELECT sts.*, s.school_name, s.full_address, s.gps_latitude, s.gps_longitude,
             s.contact_person, s.contact_phone, a.fname as created_by_name
             FROM school_teaching_slots sts
             JOIN schools s ON sts.school_id = s.school_id
             LEFT JOIN admin a ON sts.created_by = a.id
             WHERE sts.slot_id = ?";
$stmt = mysqli_prepare($conn, $slot_sql);
mysqli_stmt_bind_param($stmt, "i", $slot_id);
mysqli_stmt_execute($stmt);
$slot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$slot) {
    header("Location: teaching_slots.php?error=Slot not found");
    exit;
}

// Get enrolled teachers with session info
$teachers_sql = "SELECT ste.*, t.fname as teacher_name, t.email as teacher_email, t.subject,
                 ts.session_id, ts.session_status, ts.photo_path, ts.photo_uploaded_at,
                 ts.gps_latitude as photo_lat, ts.gps_longitude as photo_lng,
                 ts.distance_from_school, ts.verified_by, ts.verified_at, ts.admin_remarks
                 FROM slot_teacher_enrollments ste
                 JOIN teacher t ON ste.teacher_id = t.id
                 LEFT JOIN teaching_sessions ts ON ste.enrollment_id = ts.enrollment_id
                 WHERE ste.slot_id = ?
                 ORDER BY ste.booked_at ASC";
$stmt = mysqli_prepare($conn, $teachers_sql);
mysqli_stmt_bind_param($stmt, "i", $slot_id);
mysqli_stmt_execute($stmt);
$teachers = mysqli_stmt_get_result($stmt);

// Calculate stats
$fill_pct = $slot['teachers_required'] > 0 ? 
            ($slot['teachers_enrolled'] / $slot['teachers_required']) * 100 : 0;
$is_past = strtotime($slot['slot_date']) < strtotime(date('Y-m-d'));
$is_today = $slot['slot_date'] === date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Slot #<?= $slot_id ?> | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .slot-header-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        .slot-header-card h1 {
            margin-bottom: 10px;
            font-size: 24px;
        }
        .slot-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .slot-meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
        }
        .slot-meta-item i {
            font-size: 20px;
            opacity: 0.9;
        }
        .capacity-section {
            background: rgba(255,255,255,0.15);
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .capacity-bar {
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
            height: 12px;
            overflow: hidden;
            margin: 10px 0;
        }
        .capacity-fill {
            height: 100%;
            background: white;
            transition: width 0.3s;
        }
        .capacity-text {
            font-size: 14px;
            opacity: 0.9;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        .teacher-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }
        .teacher-card.no-show {
            opacity: 0.6;
            border-color: var(--danger-color);
        }
        .teacher-card.completed {
            border-color: var(--success-color);
        }
        .teacher-info h4 {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .teacher-details {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .session-info {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
        }
        .session-info h5 {
            margin-bottom: 8px;
            font-size: 13px;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.photo_submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.booked { background: #dbeafe; color: #1e40af; }
        .status-badge.cancelled { background: #e5e7eb; color: #374151; }
        .status-badge.completed { background: #dcfce7; color: #166534; }
        .status-badge.no_show { background: #fee2e2; color: #991b1b; }
        .slot-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            background: rgba(255,255,255,0.2);
        }
        .teacher-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 130px;
        }
        #map {
            height: 250px;
            border-radius: 10px;
            margin-top: 15px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .back-link:hover {
            color: var(--primary-color);
        }
        .photo-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
        }
        .today-badge {
            background: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <a href="teaching_slots.php" class="back-link">← Back to Teaching Slots</a>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Slot Header -->
        <div class="slot-header-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1><i class='bx bx-building'></i> <?= htmlspecialchars($slot['school_name']) ?></h1>
                    <p style="opacity: 0.9;"><?= htmlspecialchars($slot['full_address'] ?: 'No address provided') ?></p>
                </div>
                <div style="text-align: right;">
                    <span class="slot-status">
                        <?= ucfirst(str_replace('_', ' ', $slot['slot_status'])) ?>
                    </span>
                    <?php if ($is_today): ?>
                    <span class="today-badge" style="margin-left: 10px;">TODAY</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="slot-meta">
                <div class="slot-meta-item">
                    <i class='bx bx-calendar'></i> <?= date('l, F j, Y', strtotime($slot['slot_date'])) ?>
                </div>
                <div class="slot-meta-item">
                    <i class='bx bx-time'></i> <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                </div>
                <?php if ($slot['contact_person']): ?>
                <div class="slot-meta-item">
                    <i class='bx bx-user'></i> <?= htmlspecialchars($slot['contact_person']) ?>
                    <?= $slot['contact_phone'] ? ' | <i class="bx bx-phone"></i> ' . htmlspecialchars($slot['contact_phone']) : '' ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="capacity-section">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong>Teacher Capacity</strong>
                    <span><?= $slot['teachers_enrolled'] ?> / <?= $slot['teachers_required'] ?> enrolled</span>
                </div>
                <div class="capacity-bar">
                    <div class="capacity-fill" style="width: <?= min(100, $fill_pct) ?>%"></div>
                </div>
                <div class="capacity-text">
                    <?php if ($slot['teachers_enrolled'] >= $slot['teachers_required']): ?>
                    <i class='bx bx-check-circle'></i> Slot is fully staffed
                    <?php else: ?>
                    <i class='bx bx-error'></i> <?= $slot['teachers_required'] - $slot['teachers_enrolled'] ?> more teacher(s) needed
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Enrolled Teachers -->
            <div class="card">
                <div class="card-header">
                    <h2><i class='bx bx-group'></i> Enrolled Teachers (<?= $slot['teachers_enrolled'] ?>)</h2>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($teachers) > 0): ?>
                        <?php while ($teacher = mysqli_fetch_assoc($teachers)): ?>
                        <div class="teacher-card <?= $teacher['enrollment_status'] ?>">
                            <div class="teacher-info" style="flex: 1;">
                                <h4>
                                    <?= htmlspecialchars($teacher['teacher_name']) ?>
                                    <span class="status-badge <?= $teacher['enrollment_status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $teacher['enrollment_status'])) ?>
                                    </span>
                                </h4>
                                <div class="teacher-details">
                                    <i class='bx bx-envelope'></i> <?= htmlspecialchars($teacher['teacher_email']) ?>
                                    <?php if ($teacher['subject']): ?>
                                    | <i class='bx bx-book'></i> <?= htmlspecialchars($teacher['subject']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="teacher-details">
                                    <i class='bx bx-calendar'></i> Booked: <?= date('M j, Y h:i A', strtotime($teacher['booked_at'])) ?>
                                    <?php if ($teacher['cancelled_at']): ?>
                                    <br><i class='bx bx-x-circle'></i> Cancelled: <?= date('M j, Y h:i A', strtotime($teacher['cancelled_at'])) ?>
                                    <?php if ($teacher['cancellation_reason']): ?>
                                    <br>Reason: <?= htmlspecialchars($teacher['cancellation_reason']) ?>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($teacher['session_id']): ?>
                                <div class="session-info">
                                    <h5><i class='bx bx-camera'></i> Session Status</h5>
                                    <span class="status-badge <?= $teacher['session_status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $teacher['session_status'])) ?>
                                    </span>
                                    
                                    <?php if ($teacher['photo_path']): ?>
                                    <div style="margin-top: 10px;">
                                        <img src="../<?= htmlspecialchars($teacher['photo_path']) ?>" 
                                             alt="Session Photo" class="photo-thumb"
                                             onclick="window.open('../<?= htmlspecialchars($teacher['photo_path']) ?>', '_blank')">
                                        <div style="margin-top: 5px; font-size: 12px; color: #666;">
                                            Uploaded: <?= date('M j, h:i A', strtotime($teacher['photo_uploaded_at'])) ?>
                                            <?php if ($teacher['distance_from_school'] !== null): ?>
                                            <br>Distance: <?= number_format($teacher['distance_from_school']) ?>m from school
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php elseif ($is_past || $is_today): ?>
                                    <div style="margin-top: 10px; color: var(--warning-color);">
                                        <i class='bx bx-error'></i> No photo uploaded
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($teacher['admin_remarks']): ?>
                                    <div style="margin-top: 10px; padding: 8px; background: #fff3cd; border-radius: 5px; font-size: 12px;">
                                        <i class='bx bx-message-square-detail'></i> <?= htmlspecialchars($teacher['admin_remarks']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($teacher['enrollment_status'] === 'booked' && ($is_past || $is_today)): ?>
                            <div class="teacher-actions">
                                <form method="post" onsubmit="return confirm('Mark this teacher as completed?')">
                                    <input type="hidden" name="action" value="mark_completed">
                                    <input type="hidden" name="enrollment_id" value="<?= $teacher['enrollment_id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm" style="width: 100%;">✓ Complete</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Mark this teacher as no-show?')">
                                    <input type="hidden" name="action" value="mark_no_show">
                                    <input type="hidden" name="enrollment_id" value="<?= $teacher['enrollment_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">✗ No-Show</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                    <p class="text-muted">No teachers enrolled yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Slot Details Sidebar -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2><i class='bx bx-map-pin'></i> Location</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($slot['gps_latitude'] && $slot['gps_longitude']): ?>
                        <div id="map"></div>
                        <p style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">
                            GPS: <?= number_format($slot['gps_latitude'], 6) ?>, <?= number_format($slot['gps_longitude'], 6) ?>
                        </p>
                        <?php else: ?>
                        <p class="text-muted">No GPS coordinates set for this school.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h2><i class='bx bx-info-circle'></i> Slot Info</h2>
                    </div>
                    <div class="card-body">
                        <table style="width: 100%; font-size: 14px;">
                            <tr>
                                <td style="padding: 8px 0; color: var(--text-muted);">Slot ID</td>
                                <td style="padding: 8px 0; text-align: right;">#<?= $slot['slot_id'] ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: var(--text-muted);">Created By</td>
                                <td style="padding: 8px 0; text-align: right;"><?= htmlspecialchars($slot['created_by_name'] ?? 'Unknown') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: var(--text-muted);">Created At</td>
                                <td style="padding: 8px 0; text-align: right;"><?= date('M j, Y', strtotime($slot['created_at'])) ?></td>
                            </tr>
                            <?php if ($slot['description']): ?>
                            <tr>
                                <td colspan="2" style="padding: 8px 0;">
                                    <strong>Description:</strong><br>
                                    <?= htmlspecialchars($slot['description']) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        
                        <?php if ($slot['slot_status'] !== 'completed' && $slot['slot_status'] !== 'cancelled'): ?>
                        <div style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                            <a href="teaching_slots.php?edit=<?= $slot['slot_id'] ?>" class="btn btn-secondary btn-sm" style="width: 100%;">
                                <i class='bx bx-edit'></i> Edit Slot
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($slot['gps_latitude'] && $slot['gps_longitude']): ?>
    <script>
        const lat = <?= $slot['gps_latitude'] ?>;
        const lng = <?= $slot['gps_longitude'] ?>;
        
        const map = L.map('map').setView([lat, lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        
        L.marker([lat, lng]).addTo(map)
            .bindPopup('<?= htmlspecialchars($slot['school_name']) ?>')
            .openPopup();
        
        // Add circle for allowed radius if available
        L.circle([lat, lng], {
            color: '#7C0A02',
            fillColor: '#7C0A02',
            fillOpacity: 0.1,
            radius: 200 // default radius
        }).addTo(map);
    </script>
    <?php endif; ?>
</body>
</html>
