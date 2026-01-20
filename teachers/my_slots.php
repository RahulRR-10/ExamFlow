<?php
/**
 * Teacher Portal - My Slot Bookings
 * Phase 3: View and manage booked teaching slots
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';

$teacher_id = $_SESSION['user_id'];

// Get all bookings - upcoming and past
$bookings_sql = "SELECT ste.*, sts.slot_date, sts.start_time, sts.end_time, sts.slot_status,
                 sts.description as slot_description, sts.teachers_required, sts.teachers_enrolled,
                 s.school_id, s.school_name, s.full_address, s.gps_latitude, s.gps_longitude,
                 ts.session_id, ts.session_status, ts.start_photo_path, ts.start_photo_uploaded_at,
                 ts.end_photo_path, ts.end_photo_uploaded_at, ts.actual_duration_minutes,
                 ts.verified_by, ts.verified_at, ts.admin_remarks
                 FROM slot_teacher_enrollments ste
                 JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                 JOIN schools s ON sts.school_id = s.school_id
                 LEFT JOIN teaching_sessions ts ON ste.enrollment_id = ts.enrollment_id
                 WHERE ste.teacher_id = ?
                 ORDER BY sts.slot_date DESC, sts.start_time ASC";
$stmt = mysqli_prepare($conn, $bookings_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$all_bookings = mysqli_stmt_get_result($stmt);

// Separate into upcoming and past
$upcoming = [];
$past = [];
$today = date('Y-m-d');

while ($booking = mysqli_fetch_assoc($all_bookings)) {
    if ($booking['slot_date'] >= $today && $booking['enrollment_status'] === 'booked') {
        $upcoming[] = $booking;
    } else {
        $past[] = $booking;
    }
}

// Stats
$stats = [
    'upcoming' => count($upcoming),
    'completed' => 0,
    'cancelled' => 0,
    'pending_upload' => 0
];

foreach ($past as $b) {
    if ($b['enrollment_status'] === 'completed') $stats['completed']++;
    if ($b['enrollment_status'] === 'cancelled') $stats['cancelled']++;
}

foreach ($upcoming as $b) {
    if ($b['slot_date'] === $today) {
        // Count sessions needing photo upload (no start photo or start approved but no end photo)
        if (empty($b['start_photo_path']) || 
            (in_array($b['session_status'], ['start_submitted', 'start_approved']) && empty($b['end_photo_path']))) {
            $stats['pending_upload']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>My Bookings</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .bookings-container {
            padding: 20px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-header h1 {
            font-size: 24px;
            color: #333;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stat-card h3 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        .stat-card h3.primary { color: #667eea; }
        .stat-card h3.success { color: #10b981; }
        .stat-card h3.warning { color: #f59e0b; }
        .stat-card h3.danger { color: #ef4444; }
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        .section-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .booking-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            overflow: hidden;
        }
        .booking-card.today {
            border-left: 4px solid #f59e0b;
        }
        .booking-header {
            padding: 15px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .booking-header h3 {
            font-size: 16px;
            margin: 0;
        }
        .booking-date {
            font-size: 13px;
            opacity: 0.9;
        }
        .booking-body {
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .booking-info p {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
        }
        .booking-info i {
            color: #667eea;
            font-size: 16px;
            width: 20px;
        }
        .session-status {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
        }
        .session-status h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.start_submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.start_approved { background: #d1fae5; color: #065f46; }
        .status-badge.end_submitted { background: #e0e7ff; color: #3730a3; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.partial { background: #fef3c7; color: #92400e; }
        .status-badge.booked { background: #dbeafe; color: #1e40af; }
        .status-badge.cancelled { background: #e5e7eb; color: #374151; }
        .status-badge.completed { background: #dcfce7; color: #166534; }
        .status-badge.no_show { background: #fee2e2; color: #991b1b; }
        .booking-actions {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 15px;
        }
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }
        .empty-state p {
            color: #999;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .photo-preview {
            max-width: 100px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .today-badge {
            background: #f59e0b;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 450px;
            width: 90%;
        }
        .modal-content h3 {
            margin-bottom: 15px;
        }
        .modal-content p {
            color: #666;
            margin-bottom: 20px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            background: #f0f0f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
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
            <li><a href="upload_material.php"><i class='bx bx-cloud-upload'></i><span class="links_name">Upload Material</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>
    
    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">My Bookings</span>
            </div>
        </nav>
        
        <div class="bookings-container">
            <?php if (isset($_GET['cancelled'])): ?>
            <div class="alert alert-success">
                ✅ Booking cancelled successfully.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($_GET['error']) ?>
            </div>
            <?php endif; ?>
            
            <div class="page-header">
                <h1>📅 My Teaching Bookings</h1>
                <a href="browse_slots.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Book New Slot
                </a>
            </div>
            
            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <h3 class="primary"><?= $stats['upcoming'] ?></h3>
                    <p>Upcoming Slots</p>
                </div>
                <div class="stat-card">
                    <h3 class="warning"><?= $stats['pending_upload'] ?></h3>
                    <p>Need Photo Upload</p>
                </div>
                <div class="stat-card">
                    <h3 class="success"><?= $stats['completed'] ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card">
                    <h3 class="danger"><?= $stats['cancelled'] ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('upcoming')">Upcoming (<?= count($upcoming) ?>)</div>
                <div class="tab" onclick="showTab('past')">Past Bookings (<?= count($past) ?>)</div>
            </div>
            
            <!-- Upcoming Bookings -->
            <div id="upcoming-tab" class="tab-content active">
                <h2 class="section-title">📅 Upcoming Bookings</h2>
                
                <?php if (empty($upcoming)): ?>
                <div class="empty-state">
                    <i class='bx bx-calendar-x'></i>
                    <h3>No Upcoming Bookings</h3>
                    <p>You haven't booked any teaching slots yet.</p>
                    <a href="browse_slots.php" class="btn btn-primary" style="margin-top: 15px;">Browse Available Slots</a>
                </div>
                <?php else: ?>
                    <?php foreach ($upcoming as $booking): 
                        $is_today = $booking['slot_date'] === $today;
                        $can_cancel = strtotime($booking['slot_date']) > strtotime($today);
                        $needs_photo = $is_today && empty($booking['photo_path']);
                    ?>
                    <div class="booking-card <?= $is_today ? 'today' : '' ?>">
                        <div class="booking-header">
                            <div>
                                <h3><?= htmlspecialchars($booking['school_name']) ?></h3>
                                <div class="booking-date">
                                    📅 <?= date('l, F j, Y', strtotime($booking['slot_date'])) ?>
                                    <?php if ($is_today): ?>
                                    <span class="today-badge">TODAY</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="status-badge <?= $booking['enrollment_status'] ?>">
                                <?= ucfirst($booking['enrollment_status']) ?>
                            </span>
                        </div>
                        <div class="booking-body">
                            <div class="booking-info">
                                <p><i class='bx bx-time'></i> <?= date('h:i A', strtotime($booking['start_time'])) ?> - <?= date('h:i A', strtotime($booking['end_time'])) ?></p>
                                <?php if ($booking['full_address']): ?>
                                <p><i class='bx bx-map'></i> <?= htmlspecialchars($booking['full_address']) ?></p>
                                <?php endif; ?>
                                <?php if ($booking['slot_description']): ?>
                                <p><i class='bx bx-info-circle'></i> <?= htmlspecialchars($booking['slot_description']) ?></p>
                                <?php endif; ?>
                                <p><i class='bx bx-group'></i> <?= $booking['teachers_enrolled'] ?>/<?= $booking['teachers_required'] ?> teachers enrolled</p>
                            </div>
                            <div class="session-status">
                                <h4>📷 Session Status</h4>
                                <?php if ($booking['session_id']): ?>
                                <span class="status-badge <?= $booking['session_status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $booking['session_status'])) ?>
                                </span>
                                <?php 
                                $has_start = !empty($booking['start_photo_path']);
                                $has_end = !empty($booking['end_photo_path']);
                                $needs_start = !$has_start && $is_today;
                                $needs_end = $has_start && !$has_end && in_array($booking['session_status'], ['start_submitted', 'start_approved']);
                                ?>
                                <?php if ($has_start && $has_end): ?>
                                <p style="margin-top: 10px; font-size: 13px; color: #10b981;">
                                    <i class='bx bx-check-circle'></i> Both photos uploaded
                                </p>
                                <?php elseif ($has_start): ?>
                                <p style="margin-top: 10px; font-size: 13px; color: #10b981;">
                                    <i class='bx bx-check-circle'></i> Start photo uploaded
                                </p>
                                <?php if ($needs_end): ?>
                                <p style="margin-top: 5px; font-size: 13px; color: #f59e0b;">
                                    <i class='bx bx-camera'></i> End photo required
                                </p>
                                <?php endif; ?>
                                <?php elseif ($needs_start): ?>
                                <p style="margin-top: 10px; font-size: 13px; color: #f59e0b;">
                                    <i class='bx bx-camera'></i> Start photo required today
                                </p>
                                <?php else: ?>
                                <p style="margin-top: 10px; font-size: 13px; color: #999;">
                                    Photo upload available on <?= date('M j', strtotime($booking['slot_date'])) ?>
                                </p>
                                <?php endif; ?>
                                <?php else: ?>
                                <p style="font-size: 13px; color: #999;">Session will be created automatically</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="booking-actions">
                            <?php if ($booking['session_id']): 
                                $btn_class = ($needs_start || $needs_end) ? 'btn-warning' : 'btn-secondary';
                                $btn_icon = ($needs_start || $needs_end) ? 'bx-camera' : 'bx-show';
                                $btn_text = $needs_start ? 'Upload Start Photo' : ($needs_end ? 'Upload End Photo' : 'View Session');
                            ?>
                            <a href="view_session.php?id=<?= $booking['session_id'] ?>" class="btn <?= $btn_class ?> btn-sm">
                                <i class='bx <?= $btn_icon ?>'></i> <?= $btn_text ?>
                            </a>
                            <?php endif; ?>
                            <?php if ($can_cancel): ?>
                            <button class="btn btn-danger btn-sm" onclick="confirmCancel(<?= $booking['enrollment_id'] ?>, '<?= htmlspecialchars($booking['school_name']) ?>', '<?= date('M j, Y', strtotime($booking['slot_date'])) ?>')">
                                <i class='bx bx-x'></i> Cancel Booking
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Past Bookings -->
            <div id="past-tab" class="tab-content">
                <h2 class="section-title">📜 Past Bookings</h2>
                
                <?php if (empty($past)): ?>
                <div class="empty-state">
                    <i class='bx bx-history'></i>
                    <h3>No Past Bookings</h3>
                    <p>Your completed bookings will appear here.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($past as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-header" style="background: linear-gradient(135deg, #6b7280, #4b5563);">
                            <div>
                                <h3><?= htmlspecialchars($booking['school_name']) ?></h3>
                                <div class="booking-date">
                                    📅 <?= date('l, F j, Y', strtotime($booking['slot_date'])) ?>
                                </div>
                            </div>
                            <span class="status-badge <?= $booking['enrollment_status'] ?>">
                                <?= ucfirst($booking['enrollment_status']) ?>
                            </span>
                        </div>
                        <div class="booking-body">
                            <div class="booking-info">
                                <p><i class='bx bx-time'></i> <?= date('h:i A', strtotime($booking['start_time'])) ?> - <?= date('h:i A', strtotime($booking['end_time'])) ?></p>
                                <?php if ($booking['full_address']): ?>
                                <p><i class='bx bx-map'></i> <?= htmlspecialchars($booking['full_address']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="session-status">
                                <h4>📷 Session Result</h4>
                                <?php if ($booking['session_id']): ?>
                                <span class="status-badge <?= $booking['session_status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $booking['session_status'])) ?>
                                </span>
                                <?php 
                                $past_has_start = !empty($booking['start_photo_path']);
                                $past_has_end = !empty($booking['end_photo_path']);
                                ?>
                                <?php if ($past_has_start && $past_has_end): ?>
                                <p style="margin-top: 10px; font-size: 13px; color: #10b981;">
                                    <i class='bx bx-check-circle'></i> Both photos submitted
                                </p>
                                <?php elseif ($past_has_start): ?>
                                <p style="margin-top: 10px; font-size: 13px; color: #f59e0b;">
                                    <i class='bx bx-camera'></i> Only start photo submitted
                                </p>
                                <?php endif; ?>
                                <?php if ($booking['actual_duration_minutes']): ?>
                                <p style="margin-top: 5px; font-size: 13px; color: #666;">
                                    <i class='bx bx-time'></i> Duration: <?= floor($booking['actual_duration_minutes']/60) ?>h <?= $booking['actual_duration_minutes']%60 ?>m
                                </p>
                                <?php endif; ?>
                                <?php if ($booking['admin_remarks']): ?>
                                <p style="margin-top: 10px; font-size: 13px; color: #666;">
                                    💬 <?= htmlspecialchars($booking['admin_remarks']) ?>
                                </p>
                                <?php endif; ?>
                                <?php else: ?>
                                <p style="font-size: 13px; color: #999;">No session data</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($booking['session_id']): ?>
                        <div class="booking-actions">
                            <a href="view_session.php?id=<?= $booking['session_id'] ?>" class="btn btn-secondary btn-sm">
                                <i class='bx bx-show'></i> View Session Details
                            </a>
                            <?php if (in_array($booking['session_status'], ['approved', 'end_submitted', 'end_approved'])): ?>
                            <a href="generate_certificate.php?id=<?= $booking['session_id'] ?>" class="btn btn-success btn-sm" style="background: linear-gradient(135deg, #166534, #22c55e);">
                                <i class='bx bx-certification'></i> Generate Certificate
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Cancel Confirmation Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <h3>❌ Cancel Booking</h3>
            <p id="cancelDetails"></p>
            <form id="cancelForm" method="POST" action="cancel_booking.php">
                <input type="hidden" name="enrollment_id" id="cancelEnrollmentId">
                <div class="form-group">
                    <label>Reason for cancellation (optional):</label>
                    <textarea name="reason" rows="3" placeholder="Enter reason..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Keep Booking</button>
                    <button type="submit" class="btn btn-danger">Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Sidebar toggle
        let sidebar = document.querySelector(".sidebar");
        let sidebarBtn = document.querySelector(".sidebarBtn");
        sidebarBtn.onclick = function() {
            sidebar.classList.toggle("active");
        };
        
        function showTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tab + '-tab').classList.add('active');
        }
        
        function confirmCancel(enrollmentId, schoolName, date) {
            document.getElementById('cancelEnrollmentId').value = enrollmentId;
            document.getElementById('cancelDetails').innerHTML = 
                `Are you sure you want to cancel your booking at:<br><br>
                <strong>${schoolName}</strong><br>
                📅 ${date}<br><br>
                This action cannot be undone.`;
            document.getElementById('cancelModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }
        
        // Close modal on outside click
        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
