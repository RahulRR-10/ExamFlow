<?php
/**
 * Admin Settings Management
 * 
 * Allows admins to configure verification settings, view system status,
 * and manage school locations.
 */

session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';
require_once '../utils/location_validator.php';
require_once '../utils/verification_alerts.php';
require_once '../utils/rate_limiter.php';

$validator = new LocationValidator($conn);
$alertSystem = new VerificationAlerts($conn);
$rateLimiter = new RateLimiter($conn);

$success = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        $settings = [
            'validation_radius_meters' => intval($_POST['validation_radius'] ?? 500),
            'max_file_size_mb' => intval($_POST['max_file_size'] ?? 10),
            'max_photo_age_days' => intval($_POST['max_photo_age'] ?? 7),
            'require_gps' => isset($_POST['require_gps']) ? 'true' : 'false',
            'strict_date_validation' => isset($_POST['strict_date']) ? 'true' : 'false',
            'date_tolerance_days' => intval($_POST['date_tolerance'] ?? 1),
            'block_future_dates' => isset($_POST['block_future']) ? 'true' : 'false',
            'daily_upload_limit' => intval($_POST['daily_limit'] ?? 5),
            'suspicious_distance_threshold' => intval($_POST['suspicious_distance'] ?? 1000),
            'enable_auto_reject' => isset($_POST['auto_reject']) ? 'true' : 'false',
            'allow_no_gps_approval' => isset($_POST['allow_no_gps']) ? 'true' : 'false',
            'require_activity_description' => isset($_POST['require_description']) ? 'true' : 'false'
        ];

        $allUpdated = true;
        foreach ($settings as $key => $value) {
            if (!$validator->setSetting($key, $value)) {
                $allUpdated = false;
            }
        }

        if ($allUpdated) {
            $success = 'Settings updated successfully!';
            logAdminAction($conn, $_SESSION['admin_id'], 'update_settings', null, null, 'Updated verification settings');
        } else {
            $error = 'Some settings could not be updated. Please try again.';
        }
    } elseif ($_POST['action'] === 'update_school_location') {
        $schoolId = intval($_POST['school_id']);
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $address = trim($_POST['address'] ?? '');
        $radius = intval($_POST['radius'] ?? 500);

        if ($validator->setSchoolLocation($schoolId, $latitude, $longitude, $address, $radius)) {
            $success = 'School location updated successfully!';
            logAdminAction($conn, $_SESSION['admin_id'], 'update_school_location', 'school_locations', $schoolId, "Updated location for school ID: $schoolId");
        } else {
            $error = 'Failed to update school location.';
        }
    } elseif ($_POST['action'] === 'reset_rate_limit') {
        $teacherId = intval($_POST['teacher_id']);
        if ($rateLimiter->resetLimit($teacherId)) {
            $success = 'Rate limit reset for teacher.';
            logAdminAction($conn, $_SESSION['admin_id'], 'reset_rate_limit', 'teacher', $teacherId, 'Reset daily upload limit');
        } else {
            $error = 'Failed to reset rate limit.';
        }
    }
}

// Get current settings
$settings = $validator->getAllSettings();

// Get alert summary
$alertSummary = $alertSystem->getAlertSummary();

// Get schools with location status
$schools = $validator->getSchoolsWithLocationStatus();

// Get teachers at limit
$teachersAtLimit = $rateLimiter->getTeachersAtLimit();

// Tab handling
$activeTab = $_GET['tab'] ?? 'general';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verification Settings | Admin</title>
    <link rel="stylesheet" href="css/style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0;
        }
        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab-btn:hover { color: #4f46e5; }
        .tab-btn.active {
            color: #4f46e5;
            border-bottom-color: #4f46e5;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .setting-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        .setting-card h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .setting-group {
            margin-bottom: 15px;
        }
        .setting-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            cursor: pointer;
        }
        .setting-group input[type="number"],
        .setting-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 5px;
        }
        .setting-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #4f46e5;
        }
        .setting-description {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }

        .alert-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .alert-card {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .alert-card.warning { background: #fef3c7; border: 1px solid #f59e0b; }
        .alert-card.danger { background: #fee2e2; border: 1px solid #ef4444; }
        .alert-card.info { background: #e0e7ff; border: 1px solid #6366f1; }
        .alert-card .number {
            font-size: 32px;
            font-weight: 700;
        }
        .alert-card.warning .number { color: #d97706; }
        .alert-card.danger .number { color: #dc2626; }
        .alert-card.info .number { color: #4f46e5; }
        .alert-card .label {
            font-size: 13px;
            color: #6b7280;
            margin-top: 5px;
        }

        .school-table th { white-space: nowrap; }
        .location-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        .location-badge.configured { background: #d1fae5; color: #059669; }
        .location-badge.missing { background: #fee2e2; color: #dc2626; }

        .coords {
            font-family: monospace;
            font-size: 12px;
            color: #6b7280;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 { margin: 0; font-size: 20px; }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1><i class='bx bx-cog'></i> Verification Settings</h1>
                <p class="subtitle">Configure location validation and upload policies</p>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <span>✓</span> <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <span>✕</span> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Alert Summary -->
        <div class="alert-cards">
            <div class="alert-card warning">
                <div class="number"><?= $alertSummary['mismatched_locations'] ?></div>
                <div class="label">Location Mismatches</div>
            </div>
            <div class="alert-card warning">
                <div class="number"><?= $alertSummary['no_gps'] ?></div>
                <div class="label">No GPS Data</div>
            </div>
            <div class="alert-card info">
                <div class="number"><?= $alertSummary['date_mismatch'] ?></div>
                <div class="label">Date Mismatches</div>
            </div>
            <div class="alert-card danger">
                <div class="number"><?= $alertSummary['suspicious'] ?></div>
                <div class="label">Suspicious</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn <?= $activeTab === 'general' ? 'active' : '' ?>" onclick="showTab('general')">
                <i class='bx bx-cog'></i> General Settings
            </button>
            <button class="tab-btn <?= $activeTab === 'schools' ? 'active' : '' ?>" onclick="showTab('schools')">
                <i class='bx bx-building'></i> School Locations
            </button>
            <button class="tab-btn <?= $activeTab === 'limits' ? 'active' : '' ?>" onclick="showTab('limits')">
                <i class='bx bx-stopwatch'></i> Rate Limits
            </button>
        </div>

        <!-- General Settings Tab -->
        <div id="tab-general" class="tab-content <?= $activeTab === 'general' ? 'active' : '' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">

                <div class="settings-grid">
                    <!-- Location Settings -->
                    <div class="setting-card">
                        <h3><i class='bx bx-map-pin'></i> Location Validation</h3>

                        <div class="setting-group">
                            <label>Default Validation Radius (meters)</label>
                            <input type="number" name="validation_radius" min="50" max="5000"
                                   value="<?= $settings['validation_radius_meters']['value'] ?? 500 ?>">
                            <p class="setting-description">Distance from school within which photos are considered valid</p>
                        </div>

                        <div class="setting-group">
                            <label>
                                <input type="checkbox" name="require_gps"
                                       <?= ($settings['require_gps']['value'] ?? 'true') === 'true' ? 'checked' : '' ?>>
                                Require GPS data in photos
                            </label>
                            <p class="setting-description">Reject uploads without GPS coordinates</p>
                        </div>

                        <div class="setting-group">
                            <label>Suspicious Distance Threshold (meters)</label>
                            <input type="number" name="suspicious_distance" min="500" max="10000"
                                   value="<?= $settings['suspicious_distance_threshold']['value'] ?? 1000 ?>">
                            <p class="setting-description">Flag submissions exceeding this distance</p>
                        </div>

                        <div class="setting-group">
                            <label>
                                <input type="checkbox" name="allow_no_gps"
                                       <?= ($settings['allow_no_gps_approval']['value'] ?? 'true') === 'true' ? 'checked' : '' ?>>
                                Allow approval without GPS
                            </label>
                            <p class="setting-description">Admin can approve submissions lacking GPS data</p>
                        </div>
                    </div>

                    <!-- Date Validation -->
                    <div class="setting-card">
                        <h3><i class='bx bx-calendar'></i> Date Validation</h3>

                        <div class="setting-group">
                            <label>
                                <input type="checkbox" name="strict_date"
                                       <?= ($settings['strict_date_validation']['value'] ?? 'true') === 'true' ? 'checked' : '' ?>>
                                Strict date validation
                            </label>
                            <p class="setting-description">Validate photo date matches activity date</p>
                        </div>

                        <div class="setting-group">
                            <label>Date Tolerance (days)</label>
                            <input type="number" name="date_tolerance" min="0" max="7"
                                   value="<?= $settings['date_tolerance_days']['value'] ?? 1 ?>">
                            <p class="setting-description">Allowed difference between photo and activity dates</p>
                        </div>

                        <div class="setting-group">
                            <label>Maximum Photo Age (days)</label>
                            <input type="number" name="max_photo_age" min="1" max="30"
                                   value="<?= $settings['max_photo_age_days']['value'] ?? 7 ?>">
                            <p class="setting-description">Reject photos older than this</p>
                        </div>

                        <div class="setting-group">
                            <label>
                                <input type="checkbox" name="block_future"
                                       <?= ($settings['block_future_dates']['value'] ?? 'true') === 'true' ? 'checked' : '' ?>>
                                Block future dates
                            </label>
                            <p class="setting-description">Prevent activity dates in the future</p>
                        </div>
                    </div>

                    <!-- Upload Settings -->
                    <div class="setting-card">
                        <h3><i class='bx bx-upload'></i> Upload Settings</h3>

                        <div class="setting-group">
                            <label>Maximum File Size (MB)</label>
                            <input type="number" name="max_file_size" min="1" max="50"
                                   value="<?= $settings['max_file_size_mb']['value'] ?? 10 ?>">
                            <p class="setting-description">Maximum allowed photo file size</p>
                        </div>

                        <div class="setting-group">
                            <label>Daily Upload Limit</label>
                            <input type="number" name="daily_limit" min="1" max="50"
                                   value="<?= $settings['daily_upload_limit']['value'] ?? 5 ?>">
                            <p class="setting-description">Maximum uploads per teacher per day</p>
                        </div>

                        <div class="setting-group">
                            <label>
                                <input type="checkbox" name="require_description"
                                       <?= ($settings['require_activity_description']['value'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                Require activity description
                            </label>
                            <p class="setting-description">Teachers must describe the activity</p>
                        </div>

                        <div class="setting-group">
                            <label>
                                <input type="checkbox" name="auto_reject"
                                       <?= ($settings['enable_auto_reject']['value'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                Enable auto-reject
                            </label>
                            <p class="setting-description">Auto-reject highly suspicious submissions</p>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 25px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                        <i class='bx bx-save'></i> Save Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- School Locations Tab -->
        <div id="tab-schools" class="tab-content <?= $activeTab === 'schools' ? 'active' : '' ?>">
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table school-table">
                        <thead>
                            <tr>
                                <th>School</th>
                                <th>Status</th>
                                <th>Coordinates</th>
                                <th>Radius</th>
                                <th>Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schools as $school): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($school['school_name']) ?></strong></td>
                                <td>
                                    <?php if ($school['has_location']): ?>
                                    <span class="location-badge configured">✓ Configured</span>
                                    <?php else: ?>
                                    <span class="location-badge missing">✕ Not Set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($school['latitude'] && $school['longitude']): ?>
                                    <span class="coords"><?= number_format($school['latitude'], 6) ?>, <?= number_format($school['longitude'], 6) ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $school['validation_radius_meters'] ?? '—' ?>m</td>
                                <td><?= htmlspecialchars($school['address'] ?? '—') ?></td>
                                <td>
                                    <button class="btn btn-sm" onclick="openLocationModal(<?= htmlspecialchars(json_encode($school)) ?>)">
                                        <i class='bx bx-edit'></i> Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($schools)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding: 40px;">
                                    No active schools found
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rate Limits Tab -->
        <div id="tab-limits" class="tab-content <?= $activeTab === 'limits' ? 'active' : '' ?>">
            <div class="card">
                <h3 style="margin-bottom: 20px;">Teachers at Daily Limit</h3>

                <?php if (empty($teachersAtLimit)): ?>
                <div class="empty-state" style="padding: 40px;">
                    <span class="empty-icon"><i class='bx bx-check-circle'></i></span>
                    <h3>No Rate Limits Reached</h3>
                    <p>No teachers have reached their daily upload limit today.</p>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Email</th>
                            <th>Uploads Today</th>
                            <th>Last Upload</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachersAtLimit as $teacher): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($teacher['fname']) ?></strong></td>
                            <td><?= htmlspecialchars($teacher['email']) ?></td>
                            <td>
                                <span class="badge badge-warning"><?= $teacher['upload_count'] ?></span>
                            </td>
                            <td><?= date('g:i A', strtotime($teacher['last_upload'])) ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reset limit for this teacher?')">
                                    <input type="hidden" name="action" value="reset_rate_limit">
                                    <input type="hidden" name="teacher_id" value="<?= $teacher['teacher_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">🔄 Reset</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Location Edit Modal -->
    <div id="locationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class='bx bx-map-pin'></i> Edit School Location</h2>
                <button class="modal-close" onclick="closeLocationModal()">&times;</button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_school_location">
                <input type="hidden" name="school_id" id="modal_school_id">

                <div class="setting-group">
                    <label>School Name</label>
                    <input type="text" id="modal_school_name" disabled>
                </div>

                <div class="setting-group">
                    <label>Latitude</label>
                    <input type="text" name="latitude" id="modal_latitude" required
                           placeholder="e.g., 14.5995" step="any">
                </div>

                <div class="setting-group">
                    <label>Longitude</label>
                    <input type="text" name="longitude" id="modal_longitude" required
                           placeholder="e.g., 120.9842" step="any">
                </div>

                <div class="setting-group">
                    <label>Address</label>
                    <input type="text" name="address" id="modal_address"
                           placeholder="Full school address">
                </div>

                <div class="setting-group">
                    <label>Validation Radius (meters)</label>
                    <input type="number" name="radius" id="modal_radius" min="50" max="5000"
                           value="500" placeholder="500">
                </div>

                <p class="setting-description" style="margin: 15px 0;">
                    <i class='bx bx-bulb'></i> Tip: Use Google Maps to find coordinates. Right-click on the school location and select "What's here?" to see coordinates.
                </p>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class='bx bx-save'></i> Save Location
                    </button>
                    <button type="button" class="btn" onclick="closeLocationModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');

            // Update URL
            history.replaceState(null, '', '?tab=' + tabName);
        }

        function openLocationModal(school) {
            document.getElementById('modal_school_id').value = school.school_id;
            document.getElementById('modal_school_name').value = school.school_name;
            document.getElementById('modal_latitude').value = school.latitude || '';
            document.getElementById('modal_longitude').value = school.longitude || '';
            document.getElementById('modal_address').value = school.address || '';
            document.getElementById('modal_radius').value = school.validation_radius_meters || 500;

            document.getElementById('locationModal').classList.add('show');
        }

        function closeLocationModal() {
            document.getElementById('locationModal').classList.remove('show');
        }

        // Close modal on outside click
        document.getElementById('locationModal').addEventListener('click', function(e) {
            if (e.target === this) closeLocationModal();
        });
    </script>
</body>
</html>
