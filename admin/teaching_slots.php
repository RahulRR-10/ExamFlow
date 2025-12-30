<?php
/**
 * Admin - Teaching Slots Management Page
 * Phase 1: Full CRUD for teaching slots
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];
$message = '';
$error = '';

// Filter parameters
$filter_school = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_slot') {
        $school_id = intval($_POST['school_id']);
        $slot_date = $_POST['slot_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $teachers_required = intval($_POST['teachers_required'] ?? 1);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($school_id) || empty($slot_date) || empty($start_time) || empty($end_time)) {
            $error = 'All required fields must be filled.';
        } elseif ($start_time >= $end_time) {
            $error = 'End time must be after start time.';
        } elseif (strtotime($slot_date) < strtotime(date('Y-m-d'))) {
            $error = 'Slot date cannot be in the past.';
        } else {
            // Check for overlapping slots
            $overlap_sql = "SELECT COUNT(*) as cnt FROM school_teaching_slots 
                           WHERE school_id = ? AND slot_date = ? 
                           AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
            $overlap_stmt = mysqli_prepare($conn, $overlap_sql);
            mysqli_stmt_bind_param($overlap_stmt, "isssss", $school_id, $slot_date, $start_time, $start_time, $end_time, $end_time);
            mysqli_stmt_execute($overlap_stmt);
            $overlap_result = mysqli_stmt_get_result($overlap_stmt);
            $overlap_count = mysqli_fetch_assoc($overlap_result)['cnt'];
            
            if ($overlap_count > 0) {
                $error = 'This slot overlaps with an existing slot for this school.';
            } else {
                $sql = "INSERT INTO school_teaching_slots (school_id, slot_date, start_time, end_time, teachers_required, description, created_by, slot_status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'open')";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "isssisi", $school_id, $slot_date, $start_time, $end_time, $teachers_required, $description, $admin_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Teaching slot added successfully!";
                    logAdminAction($conn, $admin_id, 'add_slot', "Added teaching slot for $slot_date", null, 'school_teaching_slots', mysqli_insert_id($conn));
                } else {
                    $error = 'Error adding slot: ' . mysqli_error($conn);
                }
            }
        }
    } elseif ($action === 'update_slot') {
        $slot_id = intval($_POST['slot_id']);
        $school_id = intval($_POST['school_id']);
        $slot_date = $_POST['slot_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $teachers_required = intval($_POST['teachers_required'] ?? 1);
        $description = trim($_POST['description'] ?? '');
        $slot_status = $_POST['slot_status'];
        
        // Check current enrollment count
        $check_sql = "SELECT teachers_enrolled FROM school_teaching_slots WHERE slot_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $slot_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $current = mysqli_fetch_assoc($check_result);
        
        if ($teachers_required < $current['teachers_enrolled']) {
            $error = "Cannot reduce teachers required below current enrollment ({$current['teachers_enrolled']}).";
        } else {
            // Auto-update status based on enrollment
            if ($slot_status === 'open' && $current['teachers_enrolled'] >= $teachers_required) {
                $slot_status = 'full';
            } elseif ($slot_status === 'full' && $current['teachers_enrolled'] < $teachers_required) {
                if ($current['teachers_enrolled'] > 0) {
                    $slot_status = 'partially_filled';
                } else {
                    $slot_status = 'open';
                }
            }
            
            $sql = "UPDATE school_teaching_slots SET school_id = ?, slot_date = ?, start_time = ?, end_time = ?, 
                    teachers_required = ?, description = ?, slot_status = ? WHERE slot_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isssissi", $school_id, $slot_date, $start_time, $end_time, 
                                   $teachers_required, $description, $slot_status, $slot_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Teaching slot updated successfully!";
                logAdminAction($conn, $admin_id, 'update_slot', "Updated teaching slot #$slot_id", null, 'school_teaching_slots', $slot_id);
            } else {
                $error = 'Error updating slot: ' . mysqli_error($conn);
            }
        }
    } elseif ($action === 'delete_slot') {
        $slot_id = intval($_POST['slot_id']);
        
        // Check if slot has enrollments
        $check_sql = "SELECT teachers_enrolled FROM school_teaching_slots WHERE slot_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $slot_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $slot_data = mysqli_fetch_assoc($check_result);
        
        if ($slot_data['teachers_enrolled'] > 0) {
            $error = "Cannot delete slot with enrolled teachers. Cancel the slot instead.";
        } else {
            $sql = "DELETE FROM school_teaching_slots WHERE slot_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $slot_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Teaching slot deleted successfully!";
                logAdminAction($conn, $admin_id, 'delete_slot', "Deleted teaching slot #$slot_id", null, 'school_teaching_slots', $slot_id);
            } else {
                $error = 'Error deleting slot: ' . mysqli_error($conn);
            }
        }
    } elseif ($action === 'cancel_slot') {
        $slot_id = intval($_POST['slot_id']);
        
        // Cancel all enrollments first
        $cancel_enrollments = "UPDATE slot_teacher_enrollments SET enrollment_status = 'cancelled', 
                              cancelled_at = NOW(), cancellation_reason = 'Slot cancelled by admin' 
                              WHERE slot_id = ? AND enrollment_status = 'booked'";
        $cancel_stmt = mysqli_prepare($conn, $cancel_enrollments);
        mysqli_stmt_bind_param($cancel_stmt, "i", $slot_id);
        mysqli_stmt_execute($cancel_stmt);
        
        // Update slot status
        $sql = "UPDATE school_teaching_slots SET slot_status = 'cancelled', teachers_enrolled = 0 WHERE slot_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $slot_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Teaching slot cancelled. All enrolled teachers have been notified.";
            logAdminAction($conn, $admin_id, 'cancel_slot', "Cancelled teaching slot #$slot_id", null, 'school_teaching_slots', $slot_id);
        } else {
            $error = 'Error cancelling slot: ' . mysqli_error($conn);
        }
    }
}

// Get all schools for dropdown
$schools_sql = "SELECT school_id, school_name FROM schools ORDER BY school_name";
$schools = mysqli_query($conn, $schools_sql);
$schools_list = [];
while ($s = mysqli_fetch_assoc($schools)) {
    $schools_list[$s['school_id']] = $s['school_name'];
}

// Build slots query with filters
$slots_sql = "SELECT sts.*, s.school_name, a.fname as created_by_name,
              (SELECT GROUP_CONCAT(t.fname SEPARATOR ', ') 
               FROM slot_teacher_enrollments ste 
               JOIN teacher t ON ste.teacher_id = t.id 
               WHERE ste.slot_id = sts.slot_id AND ste.enrollment_status = 'booked') as enrolled_teachers
              FROM school_teaching_slots sts
              JOIN schools s ON sts.school_id = s.school_id
              LEFT JOIN admin a ON sts.created_by = a.id
              WHERE 1=1";

if ($filter_school > 0) {
    $slots_sql .= " AND sts.school_id = $filter_school";
}
if (!empty($filter_status)) {
    $slots_sql .= " AND sts.slot_status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
if (!empty($filter_date)) {
    $slots_sql .= " AND sts.slot_date = '" . mysqli_real_escape_string($conn, $filter_date) . "'";
}

$slots_sql .= " ORDER BY sts.slot_date DESC, sts.start_time ASC";
$slots = mysqli_query($conn, $slots_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teaching Slots | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .filters-bar {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 13px;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }
        .slot-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        .slot-status.open { background: #dcfce7; color: #166534; }
        .slot-status.partially_filled { background: #fef3c7; color: #92400e; }
        .slot-status.full { background: #dbeafe; color: #1e40af; }
        .slot-status.completed { background: #e5e7eb; color: #374151; }
        .slot-status.cancelled { background: #fee2e2; color: #991b1b; }
        
        .enrollment-bar {
            background: #e5e7eb;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 5px;
        }
        .enrollment-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-color));
            transition: width 0.3s ease;
        }
        
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
            max-width: 500px;
            width: 90%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-muted);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .enrolled-list {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1><i class='bx bx-calendar-event'></i> Teaching Slots</h1>
            <p class="subtitle">Create and manage teaching time slots for schools</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters-bar">
            <div class="filter-group">
                <label>School</label>
                <select id="filter-school" onchange="applyFilters()">
                    <option value="">All Schools</option>
                    <?php foreach ($schools_list as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $filter_school == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select id="filter-status" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="partially_filled" <?= $filter_status === 'partially_filled' ? 'selected' : '' ?>>Partially Filled</option>
                    <option value="full" <?= $filter_status === 'full' ? 'selected' : '' ?>>Full</option>
                    <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Date</label>
                <input type="date" id="filter-date" value="<?= htmlspecialchars($filter_date) ?>" onchange="applyFilters()">
            </div>
            <div class="filter-group" style="flex: 0;">
                <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
            </div>
            <div class="filter-group" style="flex: 0;">
                <button class="btn btn-primary" onclick="openAddModal()">+ Add Slot</button>
            </div>
        </div>
        
        <!-- Slots Table -->
        <div class="card">
            <div class="card-header">
                <h2><i class='bx bx-list-ul'></i> All Slots</h2>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($slots) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Enrollment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($slot = mysqli_fetch_assoc($slots)): 
                            $fill_pct = $slot['teachers_required'] > 0 ? 
                                        ($slot['teachers_enrolled'] / $slot['teachers_required']) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($slot['school_name']) ?></strong>
                                <?php if ($slot['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($slot['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= date('D, M d, Y', strtotime($slot['slot_date'])) ?></td>
                            <td><?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?></td>
                            <td>
                                <strong><?= $slot['teachers_enrolled'] ?></strong> / <?= $slot['teachers_required'] ?> teachers
                                <div class="enrollment-bar">
                                    <div class="enrollment-fill" style="width: <?= min(100, $fill_pct) ?>%"></div>
                                </div>
                                <?php if ($slot['enrolled_teachers']): ?>
                                <div class="enrolled-list"><i class='bx bx-user'></i> <?= htmlspecialchars($slot['enrolled_teachers']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="slot-status <?= $slot['slot_status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $slot['slot_status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!in_array($slot['slot_status'], ['completed', 'cancelled'])): ?>
                                <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($slot) ?>)'>Edit</button>
                                <?php if ($slot['teachers_enrolled'] == 0): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this slot?')">
                                    <input type="hidden" name="action" value="delete_slot">
                                    <input type="hidden" name="slot_id" value="<?= $slot['slot_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                                <?php else: ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('Cancel this slot? All enrolled teachers will be notified.')">
                                    <input type="hidden" name="action" value="cancel_slot">
                                    <input type="hidden" name="slot_id" value="<?= $slot['slot_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                                </form>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted">No teaching slots found. Click "Add Slot" to create one.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Slot Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Add Teaching Slot</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_slot">
                
                <div class="form-group">
                    <label>School *</label>
                    <select name="school_id" required>
                        <option value="">Select School</option>
                        <?php foreach ($schools_list as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $filter_school == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="slot_date" required min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time *</label>
                        <input type="time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label>End Time *</label>
                        <input type="time" name="end_time" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Teachers Required *</label>
                    <input type="number" name="teachers_required" value="1" min="1" max="50" required>
                </div>
                
                <div class="form-group">
                    <label>Description (optional)</label>
                    <textarea name="description" rows="2" placeholder="e.g., Science lab supervision"></textarea>
                </div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Slot</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Slot Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class='bx bx-edit'></i> Edit Teaching Slot</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_slot">
                <input type="hidden" name="slot_id" id="edit_slot_id">
                
                <div class="form-group">
                    <label>School *</label>
                    <select name="school_id" id="edit_school_id" required>
                        <?php foreach ($schools_list as $id => $name): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="slot_date" id="edit_slot_date" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time *</label>
                        <input type="time" name="start_time" id="edit_start_time" required>
                    </div>
                    <div class="form-group">
                        <label>End Time *</label>
                        <input type="time" name="end_time" id="edit_end_time" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Teachers Required *</label>
                        <input type="number" name="teachers_required" id="edit_teachers_required" min="1" max="50" required>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="slot_status" id="edit_slot_status" required>
                            <option value="open">Open</option>
                            <option value="partially_filled">Partially Filled</option>
                            <option value="full">Full</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="2"></textarea>
                </div>
                
                <div id="edit_enrollment_info" class="alert alert-info" style="display:none;"></div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Slot</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function applyFilters() {
            const school = document.getElementById('filter-school').value;
            const status = document.getElementById('filter-status').value;
            const date = document.getElementById('filter-date').value;
            
            let url = 'teaching_slots.php?';
            if (school) url += `school_id=${school}&`;
            if (status) url += `status=${status}&`;
            if (date) url += `date=${date}&`;
            
            window.location.href = url;
        }
        
        function clearFilters() {
            window.location.href = 'teaching_slots.php';
        }
        
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }
        
        function openEditModal(slot) {
            document.getElementById('edit_slot_id').value = slot.slot_id;
            document.getElementById('edit_school_id').value = slot.school_id;
            document.getElementById('edit_slot_date').value = slot.slot_date;
            document.getElementById('edit_start_time').value = slot.start_time;
            document.getElementById('edit_end_time').value = slot.end_time;
            document.getElementById('edit_teachers_required').value = slot.teachers_required;
            document.getElementById('edit_slot_status').value = slot.slot_status;
            document.getElementById('edit_description').value = slot.description || '';
            
            // Show enrollment info
            const infoDiv = document.getElementById('edit_enrollment_info');
            if (slot.teachers_enrolled > 0) {
                infoDiv.textContent = `⚠ This slot has ${slot.teachers_enrolled} enrolled teacher(s): ${slot.enrolled_teachers || 'N/A'}`;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
