<?php
/**
 * Admin - School Management Page
 * Phase 1: Full CRUD for schools with enhanced fields
 */
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$admin_id = $_SESSION['admin_id'];
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_school') {
        // Add new school
        $school_name = trim($_POST['school_name']);
        $gps_latitude = floatval($_POST['gps_latitude']);
        $gps_longitude = floatval($_POST['gps_longitude']);
        $allowed_radius = intval($_POST['allowed_radius'] ?? 200);
        $school_type = $_POST['school_type'] ?? 'secondary';
        $full_address = trim($_POST['full_address'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        
        if (empty($school_name) || $gps_latitude == 0 || $gps_longitude == 0) {
            $error = 'School name and valid GPS coordinates are required.';
        } else {
            $sql = "INSERT INTO schools (school_name, gps_latitude, gps_longitude, allowed_radius, 
                    school_type, full_address, contact_person, contact_phone, contact_email) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sddisssss", $school_name, $gps_latitude, $gps_longitude, 
                                   $allowed_radius, $school_type, $full_address, 
                                   $contact_person, $contact_phone, $contact_email);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "School '$school_name' added successfully!";
                logAdminAction($conn, $admin_id, 'add_school', "Added school: $school_name", null, 'schools', mysqli_insert_id($conn));
            } else {
                $error = 'Error adding school: ' . mysqli_error($conn);
            }
        }
    } elseif ($action === 'update_school') {
        // Update existing school
        $school_id = intval($_POST['school_id']);
        $school_name = trim($_POST['school_name']);
        $gps_latitude = floatval($_POST['gps_latitude']);
        $gps_longitude = floatval($_POST['gps_longitude']);
        $allowed_radius = intval($_POST['allowed_radius'] ?? 200);
        $school_type = $_POST['school_type'] ?? 'secondary';
        $full_address = trim($_POST['full_address'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        
        $sql = "UPDATE schools SET school_name = ?, gps_latitude = ?, gps_longitude = ?, 
                allowed_radius = ?, school_type = ?, full_address = ?, 
                contact_person = ?, contact_phone = ?, contact_email = ? 
                WHERE school_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sddisssssi", $school_name, $gps_latitude, $gps_longitude, 
                               $allowed_radius, $school_type, $full_address, 
                               $contact_person, $contact_phone, $contact_email, $school_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "School updated successfully!";
            logAdminAction($conn, $admin_id, 'update_school', "Updated school: $school_name", null, 'schools', $school_id);
        } else {
            $error = 'Error updating school: ' . mysqli_error($conn);
        }
    } elseif ($action === 'delete_school') {
        // Delete school
        $school_id = intval($_POST['school_id']);
        
        // Check if school has active slots
        $check_sql = "SELECT COUNT(*) as cnt FROM school_teaching_slots WHERE school_id = ? AND slot_status NOT IN ('completed', 'cancelled')";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $school_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $active_slots = mysqli_fetch_assoc($check_result)['cnt'];
        
        if ($active_slots > 0) {
            $error = "Cannot delete school with $active_slots active teaching slot(s). Please cancel or complete all slots first.";
        } else {
            // Get school name for log
            $name_sql = "SELECT school_name FROM schools WHERE school_id = ?";
            $name_stmt = mysqli_prepare($conn, $name_sql);
            mysqli_stmt_bind_param($name_stmt, "i", $school_id);
            mysqli_stmt_execute($name_stmt);
            $name_result = mysqli_stmt_get_result($name_stmt);
            $school_name = mysqli_fetch_assoc($name_result)['school_name'] ?? 'Unknown';
            
            $sql = "DELETE FROM schools WHERE school_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $school_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "School deleted successfully!";
                logAdminAction($conn, $admin_id, 'delete_school', "Deleted school: $school_name", null, 'schools', $school_id);
            } else {
                $error = 'Error deleting school: ' . mysqli_error($conn);
            }
        }
    }
}

// Get all schools with slot counts
$schools_sql = "SELECT s.*, 
                (SELECT COUNT(*) FROM school_teaching_slots WHERE school_id = s.school_id AND slot_status = 'open') as open_slots,
                (SELECT COUNT(*) FROM school_teaching_slots WHERE school_id = s.school_id AND slot_status = 'full') as full_slots,
                (SELECT COUNT(*) FROM teacher_schools WHERE school_id = s.school_id) as enrolled_teachers
                FROM schools s 
                ORDER BY s.school_name";
$schools = mysqli_query($conn, $schools_sql);

// Get single school for editing if requested
$edit_school = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_sql = "SELECT * FROM schools WHERE school_id = ?";
    $edit_stmt = mysqli_prepare($conn, $edit_sql);
    mysqli_stmt_bind_param($edit_stmt, "i", $edit_id);
    mysqli_stmt_execute($edit_stmt);
    $edit_result = mysqli_stmt_get_result($edit_stmt);
    $edit_school = mysqli_fetch_assoc($edit_result);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Schools | Admin | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-grid .full-width {
            grid-column: span 2;
        }
        #map {
            height: 300px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .gps-inputs {
            display: flex;
            gap: 15px;
        }
        .gps-inputs input {
            flex: 1;
        }
        .school-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .school-info h3 {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .school-type-badge {
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 15px;
            background: var(--info-color);
            color: white;
            font-weight: 500;
        }
        .school-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        .school-stat {
            font-size: 13px;
            color: var(--text-muted);
        }
        .school-stat strong {
            color: var(--text-color);
        }
        .school-actions {
            display: flex;
            gap: 10px;
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
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>🏫 School Management</h1>
            <p class="subtitle">Add, edit, and manage schools for teaching slots</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>📋 All Schools</h2>
                <button class="btn btn-primary" onclick="openAddModal()">+ Add New School</button>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($schools) > 0): ?>
                    <?php while ($school = mysqli_fetch_assoc($schools)): ?>
                    <div class="school-card">
                        <div class="school-info">
                            <h3>
                                <?= htmlspecialchars($school['school_name']) ?>
                                <span class="school-type-badge"><?= ucfirst($school['school_type'] ?? 'secondary') ?></span>
                            </h3>
                            <p class="text-muted">
                                📍 <?= htmlspecialchars($school['full_address'] ?: 'No address provided') ?>
                            </p>
                            <p class="text-muted">
                                🎯 GPS: <?= number_format($school['gps_latitude'], 6) ?>, <?= number_format($school['gps_longitude'], 6) ?>
                                | Radius: <?= $school['allowed_radius'] ?>m
                            </p>
                            <?php if ($school['contact_person']): ?>
                            <p class="text-muted">
                                👤 <?= htmlspecialchars($school['contact_person']) ?>
                                <?= $school['contact_phone'] ? '| 📞 ' . htmlspecialchars($school['contact_phone']) : '' ?>
                            </p>
                            <?php endif; ?>
                            <div class="school-stats">
                                <span class="school-stat">
                                    <strong><?= $school['open_slots'] ?></strong> Open Slots
                                </span>
                                <span class="school-stat">
                                    <strong><?= $school['full_slots'] ?></strong> Full Slots
                                </span>
                                <span class="school-stat">
                                    <strong><?= $school['enrolled_teachers'] ?></strong> Teachers
                                </span>
                            </div>
                        </div>
                        <div class="school-actions">
                            <a href="teaching_slots.php?school_id=<?= $school['school_id'] ?>" class="btn btn-primary btn-sm">View Slots</a>
                            <button class="btn btn-secondary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($school)) ?>)">Edit</button>
                            <form method="post" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this school?');">
                                <input type="hidden" name="action" value="delete_school">
                                <input type="hidden" name="school_id" value="<?= $school['school_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted">No schools found. Click "Add New School" to get started.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add School Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Add New School</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_school">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>School Name *</label>
                        <input type="text" name="school_name" required placeholder="Enter school name">
                    </div>
                    
                    <div class="form-group">
                        <label>School Type *</label>
                        <select name="school_type" required>
                            <option value="primary">Primary School</option>
                            <option value="secondary" selected>Secondary School</option>
                            <option value="higher_secondary">Higher Secondary</option>
                            <option value="college">College</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Allowed Radius (meters) *</label>
                        <input type="number" name="allowed_radius" value="200" min="50" max="1000" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Full Address</label>
                        <textarea name="full_address" rows="2" placeholder="Enter complete address"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>📍 Click on map to set location</label>
                        <div id="add-map"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Latitude *</label>
                        <input type="text" name="gps_latitude" id="add_lat" required placeholder="e.g., 28.6139">
                    </div>
                    
                    <div class="form-group">
                        <label>Longitude *</label>
                        <input type="text" name="gps_longitude" id="add_lng" required placeholder="e.g., 77.2090">
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" placeholder="Principal/Admin name">
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" placeholder="Phone number">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" placeholder="Email address">
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add School</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit School Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit School</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_school">
                <input type="hidden" name="school_id" id="edit_school_id">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>School Name *</label>
                        <input type="text" name="school_name" id="edit_school_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>School Type *</label>
                        <select name="school_type" id="edit_school_type" required>
                            <option value="primary">Primary School</option>
                            <option value="secondary">Secondary School</option>
                            <option value="higher_secondary">Higher Secondary</option>
                            <option value="college">College</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Allowed Radius (meters) *</label>
                        <input type="number" name="allowed_radius" id="edit_allowed_radius" min="50" max="1000" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Full Address</label>
                        <textarea name="full_address" id="edit_full_address" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>📍 Click on map to update location</label>
                        <div id="edit-map"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Latitude *</label>
                        <input type="text" name="gps_latitude" id="edit_lat" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Longitude *</label>
                        <input type="text" name="gps_longitude" id="edit_lng" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" id="edit_contact_person">
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" id="edit_contact_phone">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" id="edit_contact_email">
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update School</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let addMap, addMarker, editMap, editMarker;
        
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
            setTimeout(() => {
                if (!addMap) {
                    addMap = L.map('add-map').setView([20.5937, 78.9629], 5); // India center
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(addMap);
                    
                    addMap.on('click', function(e) {
                        if (addMarker) {
                            addMap.removeLayer(addMarker);
                        }
                        addMarker = L.marker(e.latlng).addTo(addMap);
                        document.getElementById('add_lat').value = e.latlng.lat.toFixed(8);
                        document.getElementById('add_lng').value = e.latlng.lng.toFixed(8);
                    });
                }
                addMap.invalidateSize();
            }, 100);
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }
        
        function openEditModal(school) {
            document.getElementById('edit_school_id').value = school.school_id;
            document.getElementById('edit_school_name').value = school.school_name;
            document.getElementById('edit_school_type').value = school.school_type || 'secondary';
            document.getElementById('edit_allowed_radius').value = school.allowed_radius;
            document.getElementById('edit_full_address').value = school.full_address || '';
            document.getElementById('edit_lat').value = school.gps_latitude;
            document.getElementById('edit_lng').value = school.gps_longitude;
            document.getElementById('edit_contact_person').value = school.contact_person || '';
            document.getElementById('edit_contact_phone').value = school.contact_phone || '';
            document.getElementById('edit_contact_email').value = school.contact_email || '';
            
            document.getElementById('editModal').classList.add('active');
            
            setTimeout(() => {
                if (editMap) {
                    editMap.remove();
                }
                
                const lat = parseFloat(school.gps_latitude) || 20.5937;
                const lng = parseFloat(school.gps_longitude) || 78.9629;
                
                editMap = L.map('edit-map').setView([lat, lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(editMap);
                
                editMarker = L.marker([lat, lng]).addTo(editMap);
                
                editMap.on('click', function(e) {
                    if (editMarker) {
                        editMap.removeLayer(editMarker);
                    }
                    editMarker = L.marker(e.latlng).addTo(editMap);
                    document.getElementById('edit_lat').value = e.latlng.lat.toFixed(8);
                    document.getElementById('edit_lng').value = e.latlng.lng.toFixed(8);
                });
                
                editMap.invalidateSize();
            }, 100);
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
