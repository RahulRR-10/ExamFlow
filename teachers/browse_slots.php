<?php
/**
 * Teacher Portal - Browse & Book Teaching Slots
 * Phase 3: Slot Discovery & Booking
 * Phase 8: Added compatibility layer and graceful degradation
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';
require_once '../utils/teaching_slots_compat.php';

$teacher_id = $_SESSION['user_id'];

// Check if teaching slots feature is available
$feature_available = isTeachingSlotsEnabled($conn);
$feature_error = '';

if (!$feature_available) {
    $feature_error = 'Teaching slots feature is not available. Please contact the administrator.';
}

// Filter parameters
$filter_school = isset($_GET['school']) ? intval($_GET['school']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d', strtotime('+30 days'));
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'available';

// Get all schools for dropdown
$schools_sql = "SELECT school_id, school_name FROM schools WHERE status = 'active' ORDER BY school_name";
$schools = mysqli_query($conn, $schools_sql);
$schools_list = [];
while ($s = mysqli_fetch_assoc($schools)) {
    $schools_list[$s['school_id']] = $s['school_name'];
}

// Get teacher's existing bookings for overlap check
$my_bookings_sql = "SELECT sts.slot_date, sts.start_time, sts.end_time, sts.slot_id
                    FROM slot_teacher_enrollments ste
                    JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                    WHERE ste.teacher_id = ? AND ste.enrollment_status = 'booked'
                    AND sts.slot_date >= CURDATE()";
$stmt = mysqli_prepare($conn, $my_bookings_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$my_bookings_result = mysqli_stmt_get_result($stmt);
$my_bookings = [];
while ($b = mysqli_fetch_assoc($my_bookings_result)) {
    $my_bookings[$b['slot_id']] = $b;
}

// Build slots query
$slots_sql = "SELECT sts.*, s.school_name, s.full_address, s.gps_latitude, s.gps_longitude,
              (sts.teachers_required - sts.teachers_enrolled) as spots_left,
              (SELECT COUNT(*) FROM slot_teacher_enrollments WHERE slot_id = sts.slot_id 
               AND teacher_id = ? AND enrollment_status = 'booked') as already_booked
              FROM school_teaching_slots sts
              JOIN schools s ON sts.school_id = s.school_id
              WHERE sts.slot_date >= ? 
              AND sts.slot_date <= ?
              AND sts.slot_status NOT IN ('completed', 'cancelled')";

$params = [$teacher_id, $filter_date_from, $filter_date_to];
$types = "iss";

if ($filter_school > 0) {
    $slots_sql .= " AND sts.school_id = ?";
    $params[] = $filter_school;
    $types .= "i";
}

if ($filter_status === 'available') {
    $slots_sql .= " AND sts.slot_status IN ('open', 'partially_filled')";
}

$slots_sql .= " ORDER BY sts.slot_date ASC, sts.start_time ASC";

$stmt = mysqli_prepare($conn, $slots_sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$slots = mysqli_stmt_get_result($stmt);

// Count upcoming bookings
$upcoming_sql = "SELECT COUNT(*) as cnt FROM slot_teacher_enrollments ste
                 JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                 WHERE ste.teacher_id = ? AND ste.enrollment_status = 'booked' 
                 AND sts.slot_date >= CURDATE()";
$stmt = mysqli_prepare($conn, $upcoming_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$upcoming_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];

// Check if teacher has an active (non-completed, non-cancelled) slot booking
$active_booking_sql = "SELECT ste.enrollment_id, ste.enrollment_status, sts.slot_date, sts.start_time, sts.end_time, s.school_name, sts.slot_id
                       FROM slot_teacher_enrollments ste
                       JOIN school_teaching_slots sts ON ste.slot_id = sts.slot_id
                       JOIN schools s ON sts.school_id = s.school_id
                       WHERE ste.teacher_id = ? 
                       AND ste.enrollment_status = 'booked'
                       AND sts.slot_status NOT IN ('completed', 'cancelled')
                       AND sts.slot_date >= CURDATE()
                       LIMIT 1";
$stmt = mysqli_prepare($conn, $active_booking_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$active_booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$has_active_booking = ($active_booking !== null);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Browse Teaching Slots</title>
    <link rel="stylesheet" href="css/dash.css">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .slots-container {
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
        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 13px;
            color: #555;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .slot-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .slot-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .slot-card.booked {
            border: 2px solid #10b981;
        }
        .slot-header {
            padding: 15px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .slot-header h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .slot-header .date {
            font-size: 14px;
            opacity: 0.9;
        }
        .slot-body {
            padding: 20px;
        }
        .slot-info {
            margin-bottom: 15px;
        }
        .slot-info p {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
        }
        .slot-info i {
            color: #667eea;
            font-size: 16px;
            width: 20px;
        }
        .availability-bar {
            background: #e5e7eb;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 10px 0;
        }
        .availability-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #667eea);
            transition: width 0.3s;
        }
        .availability-fill.full {
            background: #ef4444;
        }
        .spots-text {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }
        .spots-text strong {
            color: #10b981;
        }
        .spots-text.full strong {
            color: #ef4444;
        }
        .slot-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 10px;
        }
        .slot-status.open { background: #dcfce7; color: #166534; }
        .slot-status.partially_filled { background: #fef3c7; color: #92400e; }
        .slot-status.full { background: #fee2e2; color: #991b1b; }
        .booked-badge {
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
        }
        .slot-actions {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        .empty-state i {
            font-size: 60px;
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
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        
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
            text-align: center;
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
            justify-content: center;
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
            <li><a href="browse_slots.php" class="active"><i class='bx bx-calendar-check'></i><span class="links_name">Teaching Slots</span></a></li>
            <li><a href="my_slots.php"><i class='bx bx-calendar'></i><span class="links_name">My Bookings</span></a></li>
            <li><a href="settings.php"><i class='bx bx-cog'></i><span class="links_name">Settings</span></a></li>
            <li><a href="help.php"><i class='bx bx-help-circle'></i><span class="links_name">Help</span></a></li>
            <li class="log_out"><a href="../logout.php"><i class='bx bx-log-out-circle'></i><span class="links_name">Log out</span></a></li>
        </ul>
    </div>
    
    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <i class='bx bx-menu sidebarBtn'></i>
                <span class="dashboard">Browse Teaching Slots</span>
            </div>
        </nav>
        
        <div class="slots-container">
            <?php if (isset($_GET['booked'])): ?>
            <div class="alert alert-success">
                ✅ Successfully booked the teaching slot! View your bookings in <a href="my_slots.php">My Bookings</a>.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($_GET['error']) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($has_active_booking): ?>
            <div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong>⚠️ Active Booking:</strong> You already have an active slot booked at <strong><?= htmlspecialchars($active_booking['school_name']) ?></strong> 
                on <?= date('M d, Y', strtotime($active_booking['slot_date'])) ?> (<?= date('h:i A', strtotime($active_booking['start_time'])) ?> - <?= date('h:i A', strtotime($active_booking['end_time'])) ?>).
                <br><em>You can only book another slot after your current booking is completed or cancelled.</em>
                <a href="my_slots.php" style="margin-left: 10px; color: #856404; text-decoration: underline;">View My Bookings</a>
            </div>
            <?php endif; ?>
            
            <div class="page-header">
                <h1>📅 Available Teaching Slots</h1>
                <a href="my_slots.php" class="btn btn-primary">
                    <i class='bx bx-calendar'></i> My Bookings (<?= $upcoming_count ?>)
                </a>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label>School</label>
                    <select id="filter-school">
                        <option value="">All Schools</option>
                        <?php foreach ($schools_list as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $filter_school == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" id="filter-date-from" value="<?= htmlspecialchars($filter_date_from) ?>" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" id="filter-date-to" value="<?= htmlspecialchars($filter_date_to) ?>">
                </div>
                <div class="filter-group">
                    <label>Availability</label>
                    <select id="filter-status">
                        <option value="available" <?= $filter_status === 'available' ? 'selected' : '' ?>>Available Only</option>
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>Show All</option>
                    </select>
                </div>
                <div class="filter-group" style="flex: 0;">
                    <button class="btn btn-primary" onclick="applyFilters()">Apply</button>
                </div>
                <div class="filter-group" style="flex: 0;">
                    <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
                </div>
            </div>
            
            <!-- Slots Grid -->
            <?php if (mysqli_num_rows($slots) > 0): ?>
            <div class="slots-grid">
                <?php while ($slot = mysqli_fetch_assoc($slots)): 
                    $fill_pct = $slot['teachers_required'] > 0 ? 
                                ($slot['teachers_enrolled'] / $slot['teachers_required']) * 100 : 0;
                    $is_booked = $slot['already_booked'] > 0;
                    $is_full = $slot['slot_status'] === 'full';
                    // Can only book if: not already booked, not full, AND no active booking
                    $can_book = !$is_booked && !$is_full && !$has_active_booking;
                ?>
                <div class="slot-card <?= $is_booked ? 'booked' : '' ?>">
                    <div class="slot-header">
                        <h3><?= htmlspecialchars($slot['school_name']) ?></h3>
                        <div class="date">
                            📅 <?= date('l, F j, Y', strtotime($slot['slot_date'])) ?>
                        </div>
                    </div>
                    <div class="slot-body">
                        <?php if ($is_booked): ?>
                        <span class="booked-badge">✓ You're Booked</span>
                        <?php else: ?>
                        <span class="slot-status <?= $slot['slot_status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $slot['slot_status'])) ?>
                        </span>
                        <?php endif; ?>
                        
                        <div class="slot-info">
                            <p><i class='bx bx-time'></i> <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?></p>
                            <?php if ($slot['full_address']): ?>
                            <p><i class='bx bx-map'></i> <?= htmlspecialchars($slot['full_address']) ?></p>
                            <?php endif; ?>
                            <?php if ($slot['description']): ?>
                            <p><i class='bx bx-info-circle'></i> <?= htmlspecialchars($slot['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="availability-bar">
                            <div class="availability-fill <?= $is_full ? 'full' : '' ?>" style="width: <?= min(100, $fill_pct) ?>%"></div>
                        </div>
                        <div class="spots-text <?= $is_full ? 'full' : '' ?>">
                            <strong><?= $slot['spots_left'] ?></strong> of <?= $slot['teachers_required'] ?> spots available
                        </div>
                    </div>
                    <div class="slot-actions">
                        <span style="font-size: 13px; color: #999;">
                            ID: #<?= $slot['slot_id'] ?>
                        </span>
                        <?php if ($is_booked): ?>
                        <a href="my_slots.php" class="btn btn-secondary btn-sm">View Booking</a>
                        <?php elseif ($has_active_booking && !$is_full): ?>
                        <button class="btn btn-secondary btn-sm" disabled title="Complete or cancel your current booking first">
                            Has Active Booking
                        </button>
                        <?php elseif ($can_book): ?>
                        <button class="btn btn-success btn-sm" onclick="confirmBooking(<?= $slot['slot_id'] ?>, '<?= htmlspecialchars(addslashes($slot['school_name']), ENT_QUOTES) ?>', '<?= date('M j, Y', strtotime($slot['slot_date'])) ?>', '<?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>')">
                            Book This Slot
                        </button>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled>Slot Full</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-calendar-x'></i>
                <h3>No Slots Found</h3>
                <p>Try adjusting your filters or check back later for new teaching opportunities.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Booking Confirmation Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <h3>📅 Confirm Booking</h3>
            <p id="bookingDetails"></p>
            <form id="bookingForm" method="POST" action="book_slot.php">
                <input type="hidden" name="slot_id" id="bookSlotId">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Sidebar toggle
        let sidebar = document.querySelector(".sidebar");
        let sidebarBtn = document.querySelector(".sidebarBtn");
        if (sidebarBtn) {
            sidebarBtn.onclick = function() {
                sidebar.classList.toggle("active");
            };
        }
        
        function applyFilters() {
            const school = document.getElementById('filter-school').value;
            const dateFrom = document.getElementById('filter-date-from').value;
            const dateTo = document.getElementById('filter-date-to').value;
            const status = document.getElementById('filter-status').value;
            
            let url = 'browse_slots.php?';
            if (school) url += `school=${school}&`;
            if (dateFrom) url += `date_from=${dateFrom}&`;
            if (dateTo) url += `date_to=${dateTo}&`;
            if (status) url += `status=${status}&`;
            
            window.location.href = url;
        }
        
        function clearFilters() {
            window.location.href = 'browse_slots.php';
        }
        
        function confirmBooking(slotId, schoolName, date, time) {
            console.log('Booking clicked:', slotId, schoolName, date, time);
            document.getElementById('bookSlotId').value = slotId;
            document.getElementById('bookingDetails').innerHTML = 
                `You are about to book a teaching slot at:<br><br>
                <strong>${schoolName}</strong><br>
                📅 ${date}<br>
                🕐 ${time}<br><br>
                Are you sure you want to proceed?`;
            document.getElementById('bookingModal').classList.add('active');
            console.log('Modal should be visible now');
        }
        
        function closeModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }
        
        // Close modal on outside click
        document.getElementById('bookingModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
