# Geotagged Teaching Activity Verification - Implementation Plan

## Overview

This document outlines the phase-by-phase implementation plan for adding **Geotagged Teaching Activity Verification** to ExamFlow. This feature allows teachers to upload geotagged photos as proof of teaching activity, with verification handled by a new Admin role.

**Key Principles:**

- ✅ Strictly additive - no modifications to existing tables or workflows
- ✅ Full backward compatibility with existing features
- ✅ Isolated from exams, grading, certificates, and analytics
- ✅ Modular and maintainable architecture

---

## Phase 1: Admin Role Introduction

### Objective

Introduce a new user role called **Admin** with limited permissions for verification tasks only.

### 1.1 Database Schema (New Tables)

**File:** `db/migrate_admin_role.sql`

```sql
-- =====================================================
-- Admin Role Migration Script
-- Version: 1.0
-- =====================================================

-- Create admin table (separate from teacher/student)
CREATE TABLE IF NOT EXISTS admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uname VARCHAR(100) NOT NULL UNIQUE,
    pword VARCHAR(255) NOT NULL,
    fname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create admin audit log for tracking admin actions
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_table VARCHAR(50),
    target_id INT,
    action_details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin(id)
);

-- Create indexes
CREATE INDEX idx_admin_uname ON admin(uname);
CREATE INDEX idx_admin_audit_admin ON admin_audit_log(admin_id);
CREATE INDEX idx_admin_audit_action ON admin_audit_log(action_type);
CREATE INDEX idx_admin_audit_date ON admin_audit_log(created_at);
```

### 1.2 New Files to Create

| File Path              | Purpose                                           |
| ---------------------- | ------------------------------------------------- |
| `login_admin.php`      | Admin login page (similar to `login_teacher.php`) |
| `register_admin.php`   | Admin registration (restricted/invite-only)       |
| `admin/`               | New directory for admin portal                    |
| `admin/index.php`      | Redirect to login if not authenticated            |
| `admin/dash.php`       | Admin dashboard                                   |
| `admin/css/style.css`  | Admin-specific styles                             |
| `utils/admin_auth.php` | Admin authentication helper functions             |

### 1.3 Authentication Flow

**File:** `login_admin.php`

```php
<?php
session_start();
include 'config.php';

if (isset($_SESSION["admin_id"])) {
    header("Location: admin/dash.php");
    exit;
}

if (isset($_POST["signin"])) {
    $uname = mysqli_real_escape_string($conn, $_POST["uname"]);
    $pword = mysqli_real_escape_string($conn, md5($_POST["pword"]));

    $check_user = mysqli_query($conn,
        "SELECT * FROM admin WHERE uname='$uname' AND pword='$pword' AND status='active'"
    );

    if (mysqli_num_rows($check_user) > 0) {
        $row = mysqli_fetch_assoc($check_user);
        $_SESSION["admin_id"] = $row['id'];
        $_SESSION["admin_fname"] = $row['fname'];
        $_SESSION["admin_email"] = $row['email'];
        $_SESSION["admin_uname"] = $row['uname'];
        $_SESSION["user_role"] = "admin";

        header("Location: admin/dash.php");
    } else {
        echo "<script>alert('Invalid credentials or inactive account.');</script>";
    }
}
?>
```

**File:** `utils/admin_auth.php`

```php
<?php
/**
 * Admin Authentication Helper Functions
 */

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && $_SESSION['user_role'] === 'admin';
}

function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header("Location: ../login_admin.php");
        exit;
    }
}

function logAdminAction($conn, $admin_id, $action_type, $target_table = null, $target_id = null, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = mysqli_prepare($conn,
        "INSERT INTO admin_audit_log (admin_id, action_type, target_table, target_id, action_details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "ississ", $admin_id, $action_type, $target_table, $target_id, $details, $ip);
    mysqli_stmt_execute($stmt);
}

function getAdminById($conn, $admin_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM admin WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}
```

### 1.4 Permission Matrix

| Feature                     | Student | Teacher | Admin |
| --------------------------- | ------- | ------- | ----- |
| Take Exams                  | ✅      | ❌      | ❌    |
| Create Exams                | ❌      | ✅      | ❌    |
| Grade Submissions           | ❌      | ✅      | ❌    |
| View Analytics              | ❌      | ✅      | ❌    |
| Generate Certificates       | ✅      | ✅      | ❌    |
| Upload Teaching Activity    | ❌      | ✅      | ❌    |
| Verify Teaching Activity    | ❌      | ❌      | ✅    |
| View Verification Dashboard | ❌      | ❌      | ✅    |

---

## Phase 2: Database & Backend Foundations

### Objective

Create new database tables for storing teaching activity verification data.

### 2.1 Database Schema

**File:** `db/migrate_teaching_verification.sql`

```sql
-- =====================================================
-- Teaching Activity Verification Tables
-- Version: 1.0
-- =====================================================

-- Main teaching activity submissions table
CREATE TABLE IF NOT EXISTS teaching_activity_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    school_id INT NOT NULL,

    -- Image storage
    image_path VARCHAR(500) NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    image_size INT NOT NULL,
    image_mime_type VARCHAR(100) NOT NULL,
    ipfs_hash VARCHAR(100) DEFAULT NULL,

    -- GPS/Location data extracted from EXIF
    gps_latitude DECIMAL(10, 8) DEFAULT NULL,
    gps_longitude DECIMAL(11, 8) DEFAULT NULL,
    gps_altitude DECIMAL(10, 2) DEFAULT NULL,
    location_accuracy VARCHAR(50) DEFAULT NULL,

    -- Dates
    activity_date DATE NOT NULL,
    photo_taken_at DATETIME DEFAULT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Verification status
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verified_by INT DEFAULT NULL,
    verified_at TIMESTAMP NULL,
    admin_remarks TEXT,

    -- Location validation
    location_match_status ENUM('matched', 'mismatched', 'unknown') DEFAULT 'unknown',
    distance_from_school DECIMAL(10, 2) DEFAULT NULL,

    -- Metadata
    exif_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (teacher_id) REFERENCES teacher(id),
    FOREIGN KEY (school_id) REFERENCES schools(school_id),
    FOREIGN KEY (verified_by) REFERENCES admin(id),

    -- Prevent duplicate submissions
    UNIQUE KEY unique_teacher_school_date (teacher_id, school_id, activity_date)
);

-- School GPS coordinates for location validation
CREATE TABLE IF NOT EXISTS school_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT NOT NULL UNIQUE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    address TEXT,
    validation_radius_meters INT DEFAULT 500,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(school_id)
);

-- Configuration table for verification settings
CREATE TABLE IF NOT EXISTS verification_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO verification_settings (setting_key, setting_value, description) VALUES
('max_file_size_mb', '10', 'Maximum upload file size in megabytes'),
('allowed_formats', 'jpg,jpeg,png,heic', 'Comma-separated allowed image formats'),
('default_radius_meters', '500', 'Default radius for GPS matching in meters'),
('require_gps', 'true', 'Whether GPS data is required for uploads'),
('max_photo_age_days', '7', 'Maximum age of photo based on EXIF date')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Create indexes for performance
CREATE INDEX idx_tas_teacher ON teaching_activity_submissions(teacher_id);
CREATE INDEX idx_tas_school ON teaching_activity_submissions(school_id);
CREATE INDEX idx_tas_status ON teaching_activity_submissions(verification_status);
CREATE INDEX idx_tas_activity_date ON teaching_activity_submissions(activity_date);
CREATE INDEX idx_tas_upload_date ON teaching_activity_submissions(upload_date);
CREATE INDEX idx_tas_verified_by ON teaching_activity_submissions(verified_by);
```

### 2.2 Backend Services

**File:** `utils/exif_extractor.php`

```php
<?php
/**
 * EXIF Data Extractor for Geotagged Images
 */

class ExifExtractor {

    /**
     * Extract GPS coordinates from image EXIF data
     */
    public static function extractGPS($imagePath) {
        if (!file_exists($imagePath)) {
            return ['error' => 'File not found'];
        }

        $exif = @exif_read_data($imagePath, 'GPS', true);

        if (!$exif || !isset($exif['GPS'])) {
            return ['error' => 'No GPS data found in image'];
        }

        $gps = $exif['GPS'];

        if (!isset($gps['GPSLatitude']) || !isset($gps['GPSLongitude'])) {
            return ['error' => 'Incomplete GPS data'];
        }

        $lat = self::getGPSCoordinate($gps['GPSLatitude'], $gps['GPSLatitudeRef'] ?? 'N');
        $lng = self::getGPSCoordinate($gps['GPSLongitude'], $gps['GPSLongitudeRef'] ?? 'E');

        $altitude = null;
        if (isset($gps['GPSAltitude'])) {
            $altitude = self::parseRational($gps['GPSAltitude']);
            if (isset($gps['GPSAltitudeRef']) && $gps['GPSAltitudeRef'] == 1) {
                $altitude *= -1;
            }
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'altitude' => $altitude,
            'raw_gps' => $gps
        ];
    }

    /**
     * Extract photo timestamp from EXIF
     */
    public static function extractTimestamp($imagePath) {
        $exif = @exif_read_data($imagePath, 'EXIF', true);

        if ($exif && isset($exif['EXIF']['DateTimeOriginal'])) {
            return DateTime::createFromFormat('Y:m:d H:i:s', $exif['EXIF']['DateTimeOriginal']);
        }

        if ($exif && isset($exif['IFD0']['DateTime'])) {
            return DateTime::createFromFormat('Y:m:d H:i:s', $exif['IFD0']['DateTime']);
        }

        return null;
    }

    /**
     * Get all EXIF data as JSON
     */
    public static function getAllExifData($imagePath) {
        $exif = @exif_read_data($imagePath, 0, true);
        return $exif ? json_encode($exif, JSON_PARTIAL_OUTPUT_ON_ERROR) : null;
    }

    private static function getGPSCoordinate($coordinate, $ref) {
        $degrees = self::parseRational($coordinate[0]);
        $minutes = self::parseRational($coordinate[1]);
        $seconds = self::parseRational($coordinate[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if ($ref == 'S' || $ref == 'W') {
            $decimal *= -1;
        }

        return round($decimal, 8);
    }

    private static function parseRational($value) {
        if (is_string($value) && strpos($value, '/') !== false) {
            $parts = explode('/', $value);
            return floatval($parts[0]) / floatval($parts[1]);
        }
        return floatval($value);
    }
}
```

**File:** `utils/location_validator.php`

```php
<?php
/**
 * GPS Location Validator
 * Validates if uploaded photo GPS matches school location
 */

class LocationValidator {

    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Calculate distance between two GPS coordinates (Haversine formula)
     * Returns distance in meters
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371000; // meters

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLng / 2) * sin($deltaLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Validate if image GPS matches school location
     */
    public function validateLocation($schoolId, $imageLat, $imageLng) {
        // Get school location
        $stmt = mysqli_prepare($this->conn,
            "SELECT latitude, longitude, validation_radius_meters
             FROM school_locations WHERE school_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $schoolId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$result) {
            return [
                'status' => 'unknown',
                'message' => 'School location not configured',
                'distance' => null
            ];
        }

        $distance = self::calculateDistance(
            $imageLat, $imageLng,
            $result['latitude'], $result['longitude']
        );

        $radius = $result['validation_radius_meters'] ?? 500;

        return [
            'status' => $distance <= $radius ? 'matched' : 'mismatched',
            'distance' => round($distance, 2),
            'radius' => $radius,
            'message' => $distance <= $radius
                ? "Location verified within {$radius}m radius"
                : "Location is " . round($distance) . "m from school (allowed: {$radius}m)"
        ];
    }

    /**
     * Get verification settings
     */
    public function getSetting($key, $default = null) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT setting_value FROM verification_settings WHERE setting_key = ?"
        );
        mysqli_stmt_bind_param($stmt, "s", $key);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $result ? $result['setting_value'] : $default;
    }
}
```

**File:** `utils/image_upload_handler.php`

```php
<?php
/**
 * Secure Image Upload Handler for Teaching Activity
 */

require_once __DIR__ . '/exif_extractor.php';
require_once __DIR__ . '/location_validator.php';

class ImageUploadHandler {

    private $conn;
    private $uploadDir;
    private $maxFileSize;
    private $allowedFormats;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->uploadDir = __DIR__ . '/../uploads/teaching_activity/';

        $validator = new LocationValidator($conn);
        $this->maxFileSize = intval($validator->getSetting('max_file_size_mb', 10)) * 1024 * 1024;
        $this->allowedFormats = explode(',', $validator->getSetting('allowed_formats', 'jpg,jpeg,png,heic'));

        // Create upload directory if not exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Process and validate uploaded image
     */
    public function processUpload($file, $teacherId, $schoolId, $activityDate) {
        // Validate file
        $validation = $this->validateFile($file);
        if ($validation['error']) {
            return $validation;
        }

        // Check for duplicate submission
        if ($this->isDuplicate($teacherId, $schoolId, $activityDate)) {
            return ['error' => 'A submission already exists for this school and date'];
        }

        // Extract GPS data
        $gpsData = ExifExtractor::extractGPS($file['tmp_name']);
        if (isset($gpsData['error'])) {
            $requireGps = (new LocationValidator($this->conn))->getSetting('require_gps', 'true');
            if ($requireGps === 'true') {
                return ['error' => 'Image must contain GPS location data. ' . $gpsData['error']];
            }
        }

        // Extract timestamp
        $photoTimestamp = ExifExtractor::extractTimestamp($file['tmp_name']);

        // Generate unique filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = sprintf('%d_%d_%s_%s.%s',
            $teacherId, $schoolId, $activityDate, uniqid(), $extension
        );
        $filepath = $this->uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['error' => 'Failed to save uploaded file'];
        }

        // Validate location if GPS data available
        $locationResult = ['status' => 'unknown', 'distance' => null];
        if (!isset($gpsData['error'])) {
            $validator = new LocationValidator($this->conn);
            $locationResult = $validator->validateLocation(
                $schoolId, $gpsData['latitude'], $gpsData['longitude']
            );
        }

        // Get all EXIF data
        $exifJson = ExifExtractor::getAllExifData($filepath);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => 'uploads/teaching_activity/' . $filename,
            'filesize' => $file['size'],
            'mimetype' => $file['type'],
            'gps' => $gpsData,
            'photo_timestamp' => $photoTimestamp ? $photoTimestamp->format('Y-m-d H:i:s') : null,
            'location_validation' => $locationResult,
            'exif_data' => $exifJson
        ];
    }

    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'File upload failed with error code: ' . $file['error']];
        }

        if ($file['size'] > $this->maxFileSize) {
            return ['error' => 'File size exceeds maximum allowed (' . ($this->maxFileSize / 1024 / 1024) . 'MB)'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedFormats)) {
            return ['error' => 'Invalid file format. Allowed: ' . implode(', ', $this->allowedFormats)];
        }

        // Verify it's actually an image
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            return ['error' => 'File is not a valid image'];
        }

        return ['error' => null];
    }

    private function isDuplicate($teacherId, $schoolId, $activityDate) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT id FROM teaching_activity_submissions
             WHERE teacher_id = ? AND school_id = ? AND activity_date = ?"
        );
        mysqli_stmt_bind_param($stmt, "iis", $teacherId, $schoolId, $activityDate);
        mysqli_stmt_execute($stmt);
        return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
    }
}
```

### 2.3 New Directory Structure

```
uploads/
├── teaching_activity/     # NEW - Geotagged photo uploads
│   └── .htaccess          # Prevent direct execution
```

**File:** `uploads/teaching_activity/.htaccess`

```apache
# Prevent PHP execution in upload directory
php_flag engine off

# Only allow image files
<FilesMatch "\.(?:jpe?g|png|heic)$">
    Allow from all
</FilesMatch>

<FilesMatch "\.(?:php|php3|php4|php5|phtml|pl|py|cgi)$">
    Deny from all
</FilesMatch>
```

---

## Phase 3: Teacher Portal - Image Upload Dashboard

### Objective

Add an isolated section in the Teacher Portal for uploading geotagged photos.

### 3.1 New Files

| File Path                            | Purpose                     |
| ------------------------------------ | --------------------------- |
| `teachers/teaching_activity.php`     | Main upload dashboard       |
| `teachers/upload_activity.php`       | Handle AJAX upload requests |
| `teachers/get_activity_history.php`  | Fetch upload history        |
| `teachers/css/teaching_activity.css` | Styles for activity section |

### 3.2 Teacher Dashboard Integration

**File:** `teachers/teaching_activity.php`

```php
<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_teacher.php");
    exit;
}
include '../config.php';

$teacher_id = $_SESSION['user_id'];

// Get teacher's enrolled schools
$schools_sql = "SELECT s.school_id, s.school_name
                FROM schools s
                INNER JOIN teacher_schools ts ON s.school_id = ts.school_id
                WHERE ts.teacher_id = ? AND s.status = 'active'
                ORDER BY s.school_name ASC";
$stmt = mysqli_prepare($conn, $schools_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$schools = mysqli_stmt_get_result($stmt);

// Get submission history
$history_sql = "SELECT tas.*, s.school_name, a.fname as admin_name
                FROM teaching_activity_submissions tas
                JOIN schools s ON tas.school_id = s.school_id
                LEFT JOIN admin a ON tas.verified_by = a.id
                WHERE tas.teacher_id = ?
                ORDER BY tas.upload_date DESC
                LIMIT 20";
$stmt = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$history = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teaching Activity Upload | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/teaching_activity.css">
</head>
<body>
    <!-- Include existing teacher navigation -->
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <h1>📍 Teaching Activity Upload</h1>
        <p class="subtitle">Upload geotagged photos to verify your teaching activity</p>

        <!-- Upload Form -->
        <div class="upload-section">
            <h2>New Submission</h2>
            <form id="activityUploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="school_id">Select School</label>
                    <select name="school_id" id="school_id" required>
                        <option value="">-- Select School --</option>
                        <?php while ($school = mysqli_fetch_assoc($schools)): ?>
                            <option value="<?= $school['school_id'] ?>">
                                <?= htmlspecialchars($school['school_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="activity_date">Activity Date</label>
                    <input type="date" name="activity_date" id="activity_date"
                           max="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="photo">Geotagged Photo</label>
                    <input type="file" name="photo" id="photo"
                           accept="image/jpeg,image/png,image/heic" required>
                    <small>Photo must contain GPS location data (taken with location enabled)</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    📤 Upload Photo
                </button>
            </form>

            <div id="uploadResult" class="result-message"></div>
        </div>

        <!-- Submission History -->
        <div class="history-section">
            <h2>Submission History</h2>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>School</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Uploaded</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($history)): ?>
                    <tr class="status-<?= $row['verification_status'] ?>">
                        <td><?= date('M d, Y', strtotime($row['activity_date'])) ?></td>
                        <td><?= htmlspecialchars($row['school_name']) ?></td>
                        <td>
                            <span class="badge badge-<?= $row['verification_status'] ?>">
                                <?= ucfirst($row['verification_status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="location-<?= $row['location_match_status'] ?>">
                                <?= $row['distance_from_school']
                                    ? round($row['distance_from_school']) . 'm'
                                    : 'N/A' ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y H:i', strtotime($row['upload_date'])) ?></td>
                        <td><?= htmlspecialchars($row['admin_remarks'] ?? '-') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.getElementById('activityUploadForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        const resultDiv = document.getElementById('uploadResult');

        resultDiv.innerHTML = '<p class="loading">Uploading and processing...</p>';

        try {
            const response = await fetch('upload_activity.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                resultDiv.innerHTML = `
                    <p class="success">✅ ${result.message}</p>
                    <p>Location: ${result.location_status}</p>
                `;
                e.target.reset();
                setTimeout(() => location.reload(), 2000);
            } else {
                resultDiv.innerHTML = `<p class="error">❌ ${result.error}</p>`;
            }
        } catch (err) {
            resultDiv.innerHTML = `<p class="error">❌ Upload failed: ${err.message}</p>`;
        }
    });
    </script>
</body>
</html>
```

**File:** `teachers/upload_activity.php`

```php
<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config.php';
require_once '../utils/image_upload_handler.php';

$teacher_id = $_SESSION['user_id'];
$school_id = intval($_POST['school_id'] ?? 0);
$activity_date = $_POST['activity_date'] ?? '';

// Validate inputs
if (!$school_id || !$activity_date) {
    echo json_encode(['error' => 'School and activity date are required']);
    exit;
}

// Verify teacher is enrolled in this school
$check_sql = "SELECT 1 FROM teacher_schools WHERE teacher_id = ? AND school_id = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
mysqli_stmt_execute($stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
    echo json_encode(['error' => 'You are not enrolled in this school']);
    exit;
}

// Process upload
$handler = new ImageUploadHandler($conn);
$result = $handler->processUpload($_FILES['photo'], $teacher_id, $school_id, $activity_date);

if (isset($result['error'])) {
    echo json_encode(['error' => $result['error']]);
    exit;
}

// Save to database
$insert_sql = "INSERT INTO teaching_activity_submissions
    (teacher_id, school_id, image_path, image_filename, image_size, image_mime_type,
     gps_latitude, gps_longitude, gps_altitude, activity_date, photo_taken_at,
     location_match_status, distance_from_school, exif_data)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $insert_sql);

$lat = $result['gps']['latitude'] ?? null;
$lng = $result['gps']['longitude'] ?? null;
$alt = $result['gps']['altitude'] ?? null;
$locStatus = $result['location_validation']['status'];
$distance = $result['location_validation']['distance'];

mysqli_stmt_bind_param($stmt, "iissisdddsssds",
    $teacher_id,
    $school_id,
    $result['filepath'],
    $result['filename'],
    $result['filesize'],
    $result['mimetype'],
    $lat,
    $lng,
    $alt,
    $activity_date,
    $result['photo_timestamp'],
    $locStatus,
    $distance,
    $result['exif_data']
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Photo uploaded successfully. Pending verification.',
        'location_status' => $result['location_validation']['message'] ?? 'Location data not available'
    ]);
} else {
    echo json_encode(['error' => 'Failed to save submission']);
}
```

### 3.3 Navigation Update

Add a new menu item to the existing teacher navigation without modifying existing items:

**Suggested addition to `teachers/includes/nav.php` or sidebar:**

```html
<!-- Add this as a new menu item -->
<li>
  <a href="teaching_activity.php">
    <i class="icon-location"></i> Teaching Activity
  </a>
</li>
```

---

## Phase 4: Admin Portal - Verification Dashboard

### Objective

Create a dedicated Admin Dashboard for reviewing and verifying submissions.

### 4.1 New Files

| File Path                         | Purpose                           |
| --------------------------------- | --------------------------------- |
| `admin/index.php`                 | Redirect to login                 |
| `admin/dash.php`                  | Main admin dashboard              |
| `admin/pending_verifications.php` | List pending submissions          |
| `admin/verify_submission.php`     | View and verify single submission |
| `admin/process_verification.php`  | Handle approve/reject actions     |
| `admin/audit_log.php`             | View admin action history         |
| `admin/includes/nav.php`          | Admin navigation                  |
| `admin/css/style.css`             | Admin portal styles               |

### 4.2 Admin Dashboard

**File:** `admin/dash.php`

```php
<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

// Get statistics
$stats = [];

// Pending verifications
$pending_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions WHERE verification_status = 'pending'";
$stats['pending'] = mysqli_fetch_assoc(mysqli_query($conn, $pending_sql))['cnt'];

// Today's submissions
$today_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions WHERE DATE(upload_date) = CURDATE()";
$stats['today'] = mysqli_fetch_assoc(mysqli_query($conn, $today_sql))['cnt'];

// Location mismatches
$mismatch_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions
                 WHERE verification_status = 'pending' AND location_match_status = 'mismatched'";
$stats['mismatched'] = mysqli_fetch_assoc(mysqli_query($conn, $mismatch_sql))['cnt'];

// My verifications today
$my_sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions
           WHERE verified_by = ? AND DATE(verified_at) = CURDATE()";
$stmt = mysqli_prepare($conn, $my_sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['admin_id']);
mysqli_stmt_execute($stmt);
$stats['my_today'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard | ExamFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="dashboard-container">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['admin_fname']) ?></h1>
        <p class="subtitle">Teaching Activity Verification Dashboard</p>

        <div class="stats-grid">
            <div class="stat-card pending">
                <h3><?= $stats['pending'] ?></h3>
                <p>Pending Verifications</p>
                <a href="pending_verifications.php">View All →</a>
            </div>

            <div class="stat-card warning">
                <h3><?= $stats['mismatched'] ?></h3>
                <p>Location Mismatches</p>
                <a href="pending_verifications.php?filter=mismatched">Review →</a>
            </div>

            <div class="stat-card info">
                <h3><?= $stats['today'] ?></h3>
                <p>Submitted Today</p>
            </div>

            <div class="stat-card success">
                <h3><?= $stats['my_today'] ?></h3>
                <p>Verified by Me Today</p>
            </div>
        </div>

        <!-- Recent Pending Submissions -->
        <div class="recent-section">
            <h2>Recent Pending Submissions</h2>
            <?php
            $recent_sql = "SELECT tas.*, t.fname as teacher_name, s.school_name
                           FROM teaching_activity_submissions tas
                           JOIN teacher t ON tas.teacher_id = t.id
                           JOIN schools s ON tas.school_id = s.school_id
                           WHERE tas.verification_status = 'pending'
                           ORDER BY tas.upload_date DESC
                           LIMIT 10";
            $recent = mysqli_query($conn, $recent_sql);
            ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>School</th>
                        <th>Activity Date</th>
                        <th>Location</th>
                        <th>Uploaded</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($recent)): ?>
                    <tr class="location-<?= $row['location_match_status'] ?>">
                        <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                        <td><?= htmlspecialchars($row['school_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($row['activity_date'])) ?></td>
                        <td>
                            <?php if ($row['location_match_status'] === 'mismatched'): ?>
                                <span class="badge badge-warning">⚠️ Mismatch (<?= round($row['distance_from_school']) ?>m)</span>
                            <?php elseif ($row['location_match_status'] === 'matched'): ?>
                                <span class="badge badge-success">✓ Matched</span>
                            <?php else: ?>
                                <span class="badge badge-info">Unknown</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, H:i', strtotime($row['upload_date'])) ?></td>
                        <td>
                            <a href="verify_submission.php?id=<?= $row['id'] ?>" class="btn btn-sm">
                                Review
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
```

**File:** `admin/verify_submission.php`

```php
<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$submission_id = intval($_GET['id'] ?? 0);

if (!$submission_id) {
    header("Location: pending_verifications.php");
    exit;
}

// Get submission details
$sql = "SELECT tas.*,
               t.fname as teacher_name, t.email as teacher_email,
               s.school_name, s.school_code,
               sl.latitude as school_lat, sl.longitude as school_lng, sl.validation_radius_meters
        FROM teaching_activity_submissions tas
        JOIN teacher t ON tas.teacher_id = t.id
        JOIN schools s ON tas.school_id = s.school_id
        LEFT JOIN school_locations sl ON s.school_id = sl.school_id
        WHERE tas.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $submission_id);
mysqli_stmt_execute($stmt);
$submission = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$submission) {
    header("Location: pending_verifications.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Submission | ExamFlow Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="verification-container">
        <h1>Verification Review</h1>

        <div class="submission-details">
            <div class="detail-grid">
                <div class="detail-card">
                    <h3>Teacher Information</h3>
                    <p><strong>Name:</strong> <?= htmlspecialchars($submission['teacher_name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($submission['teacher_email']) ?></p>
                </div>

                <div class="detail-card">
                    <h3>School Information</h3>
                    <p><strong>School:</strong> <?= htmlspecialchars($submission['school_name']) ?></p>
                    <p><strong>Code:</strong> <?= htmlspecialchars($submission['school_code'] ?? 'N/A') ?></p>
                </div>

                <div class="detail-card">
                    <h3>Activity Details</h3>
                    <p><strong>Activity Date:</strong> <?= date('F d, Y', strtotime($submission['activity_date'])) ?></p>
                    <p><strong>Photo Taken:</strong> <?= $submission['photo_taken_at']
                        ? date('F d, Y H:i:s', strtotime($submission['photo_taken_at']))
                        : 'Unknown' ?></p>
                    <p><strong>Uploaded:</strong> <?= date('F d, Y H:i:s', strtotime($submission['upload_date'])) ?></p>
                </div>

                <div class="detail-card location-<?= $submission['location_match_status'] ?>">
                    <h3>Location Verification</h3>
                    <p><strong>Photo GPS:</strong>
                        <?= $submission['gps_latitude'] ?>, <?= $submission['gps_longitude'] ?>
                    </p>
                    <p><strong>Distance from School:</strong>
                        <?= $submission['distance_from_school']
                            ? round($submission['distance_from_school']) . ' meters'
                            : 'N/A' ?>
                    </p>
                    <p><strong>Status:</strong>
                        <span class="badge badge-<?= $submission['location_match_status'] ?>">
                            <?= ucfirst($submission['location_match_status']) ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Image Preview -->
            <div class="image-section">
                <h3>Uploaded Photo</h3>
                <img src="../<?= htmlspecialchars($submission['image_path']) ?>"
                     alt="Teaching Activity Photo" class="preview-image">
            </div>

            <!-- Map Preview -->
            <?php if ($submission['gps_latitude'] && $submission['gps_longitude']): ?>
            <div class="map-section">
                <h3>Location Map</h3>
                <div id="map" style="height: 400px;"></div>
            </div>
            <?php endif; ?>

            <!-- Verification Form -->
            <?php if ($submission['verification_status'] === 'pending'): ?>
            <div class="action-section">
                <h3>Verification Action</h3>
                <form action="process_verification.php" method="POST">
                    <input type="hidden" name="submission_id" value="<?= $submission['id'] ?>">

                    <div class="form-group">
                        <label for="remarks">Admin Remarks</label>
                        <textarea name="remarks" id="remarks" rows="3"
                                  placeholder="Enter verification remarks (optional)"></textarea>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" name="action" value="approve" class="btn btn-success">
                            ✓ Approve
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">
                            ✕ Reject
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="verified-info">
                <h3>Already Verified</h3>
                <p><strong>Status:</strong> <?= ucfirst($submission['verification_status']) ?></p>
                <p><strong>Remarks:</strong> <?= htmlspecialchars($submission['admin_remarks'] ?? 'None') ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($submission['gps_latitude'] && $submission['gps_longitude']): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('map').setView([<?= $submission['gps_latitude'] ?>, <?= $submission['gps_longitude'] ?>], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Photo location marker
        L.marker([<?= $submission['gps_latitude'] ?>, <?= $submission['gps_longitude'] ?>])
            .addTo(map)
            .bindPopup('📷 Photo Location')
            .openPopup();

        <?php if ($submission['school_lat'] && $submission['school_lng']): ?>
        // School location marker
        L.marker([<?= $submission['school_lat'] ?>, <?= $submission['school_lng'] ?>], {
            icon: L.divIcon({
                html: '🏫',
                className: 'school-marker',
                iconSize: [30, 30]
            })
        }).addTo(map).bindPopup('🏫 School Location');

        // Validation radius circle
        L.circle([<?= $submission['school_lat'] ?>, <?= $submission['school_lng'] ?>], {
            radius: <?= $submission['validation_radius_meters'] ?? 500 ?>,
            color: '<?= $submission['location_match_status'] === 'matched' ? 'green' : 'red' ?>',
            fillOpacity: 0.1
        }).addTo(map);
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>
```

**File:** `admin/process_verification.php`

```php
<?php
session_start();
require_once '../utils/admin_auth.php';
requireAdminAuth();

include '../config.php';

$submission_id = intval($_POST['submission_id'] ?? 0);
$action = $_POST['action'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

if (!$submission_id || !in_array($action, ['approve', 'reject'])) {
    header("Location: pending_verifications.php?error=invalid");
    exit;
}

$status = $action === 'approve' ? 'approved' : 'rejected';
$admin_id = $_SESSION['admin_id'];

// Update submission
$update_sql = "UPDATE teaching_activity_submissions
               SET verification_status = ?,
                   verified_by = ?,
                   verified_at = NOW(),
                   admin_remarks = ?
               WHERE id = ? AND verification_status = 'pending'";

$stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($stmt, "sisi", $status, $admin_id, $remarks, $submission_id);

if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    // Log admin action
    logAdminAction($conn, $admin_id, 'verify_' . $action, 'teaching_activity_submissions', $submission_id, $remarks);

    header("Location: pending_verifications.php?success=" . $action);
} else {
    header("Location: verify_submission.php?id=$submission_id&error=failed");
}
exit;
```

---

## Phase 5: Location Validation & Safeguards

### Objective

Implement robust location validation and prevent abuse.

### 5.1 Configuration

Add to `db/migrate_teaching_verification.sql`:

```sql
-- Add more validation settings
INSERT INTO verification_settings (setting_key, setting_value, description) VALUES
('strict_date_validation', 'true', 'Reject photos if EXIF date does not match activity date'),
('date_tolerance_days', '1', 'Days tolerance for date validation'),
('block_future_dates', 'true', 'Prevent activity dates in the future'),
('min_gps_accuracy', '100', 'Minimum GPS accuracy in meters (if available in EXIF)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
```

### 5.2 Enhanced Validation

Update `utils/image_upload_handler.php` with additional checks:

```php
/**
 * Validate photo date matches activity date
 */
private function validatePhotoDate($photoTimestamp, $activityDate) {
    if (!$photoTimestamp) {
        return true; // Can't validate without timestamp
    }

    $validator = new LocationValidator($this->conn);
    $strictValidation = $validator->getSetting('strict_date_validation', 'true');

    if ($strictValidation !== 'true') {
        return true;
    }

    $tolerance = intval($validator->getSetting('date_tolerance_days', 1));
    $activityDateTime = new DateTime($activityDate);

    $diff = $photoTimestamp->diff($activityDateTime)->days;

    return $diff <= $tolerance;
}

/**
 * Validate photo is not too old
 */
private function validatePhotoAge($photoTimestamp) {
    if (!$photoTimestamp) {
        return true;
    }

    $validator = new LocationValidator($this->conn);
    $maxAge = intval($validator->getSetting('max_photo_age_days', 7));

    $now = new DateTime();
    $diff = $photoTimestamp->diff($now)->days;

    return $diff <= $maxAge;
}
```

### 5.3 Admin Alert System

**File:** `utils/verification_alerts.php`

```php
<?php
/**
 * Alert system for suspicious submissions
 */

function checkSubmissionAlerts($conn, $submissionId) {
    $alerts = [];

    $sql = "SELECT * FROM teaching_activity_submissions WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $submissionId);
    mysqli_stmt_execute($stmt);
    $submission = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$submission) return $alerts;

    // Location mismatch alert
    if ($submission['location_match_status'] === 'mismatched') {
        $alerts[] = [
            'type' => 'warning',
            'message' => 'GPS location does not match school location',
            'detail' => 'Distance: ' . round($submission['distance_from_school']) . ' meters'
        ];
    }

    // No GPS data alert
    if (!$submission['gps_latitude'] || !$submission['gps_longitude']) {
        $alerts[] = [
            'type' => 'warning',
            'message' => 'Photo does not contain GPS data'
        ];
    }

    // Photo date vs activity date mismatch
    if ($submission['photo_taken_at']) {
        $photoDate = date('Y-m-d', strtotime($submission['photo_taken_at']));
        if ($photoDate !== $submission['activity_date']) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'Photo date does not match activity date',
                'detail' => "Photo: $photoDate, Activity: {$submission['activity_date']}"
            ];
        }
    }

    return $alerts;
}
```

---

## Phase 6: Integration & Backward Compatibility

### Objective

Ensure zero regressions to existing ExamFlow features.

### 6.1 Isolation Verification Checklist

| Existing Feature       | Impact Assessment           | Status  |
| ---------------------- | --------------------------- | ------- |
| Student Login          | No changes                  | ✅ Safe |
| Teacher Login          | No changes                  | ✅ Safe |
| Exam Creation          | No changes                  | ✅ Safe |
| Exam Taking            | No changes                  | ✅ Safe |
| Grading System         | No changes                  | ✅ Safe |
| Certificate Generation | No changes                  | ✅ Safe |
| NFT Minting            | No changes                  | ✅ Safe |
| Analytics Dashboard    | No changes                  | ✅ Safe |
| Mock Exams             | No changes                  | ✅ Safe |
| Proctoring/Violations  | No changes                  | ✅ Safe |
| Multi-School System    | Only adds new query options | ✅ Safe |
| Messages System        | No changes                  | ✅ Safe |

### 6.2 Testing Checklist

**Pre-Deployment Tests:**

1. [ ] Existing teacher login works
2. [ ] Existing student login works
3. [ ] New admin login works
4. [ ] Exam creation still works
5. [ ] Students can take exams
6. [ ] Grading functions correctly
7. [ ] Certificates generate properly
8. [ ] NFT minting still works
9. [ ] Analytics display correctly

**New Feature Tests:**

1. [ ] Admin can login with new credentials
2. [ ] Teacher can access Teaching Activity page
3. [ ] Teacher can upload geotagged image
4. [ ] Upload rejects images without GPS
5. [ ] Duplicate submission prevention works
6. [ ] Admin can view pending submissions
7. [ ] Admin can approve submissions
8. [ ] Admin can reject submissions
9. [ ] Audit log captures admin actions
10. [ ] Location validation works correctly
11. [ ] Map displays correctly on verification page

### 6.3 Migration Script Execution Order

```bash
# Execute in this order:
1. mysql -u root db_eval < db/migrate_admin_role.sql
2. mysql -u root db_eval < db/migrate_teaching_verification.sql

# Verify tables created:
3. mysql -u root db_eval -e "SHOW TABLES LIKE '%admin%'"
4. mysql -u root db_eval -e "SHOW TABLES LIKE 'teaching_%'"
5. mysql -u root db_eval -e "SHOW TABLES LIKE 'school_locations'"
```

### 6.4 File Structure Summary

```
Hackfest25-42/
├── admin/                              # NEW - Admin portal
│   ├── index.php
│   ├── dash.php
│   ├── pending_verifications.php
│   ├── verify_submission.php
│   ├── process_verification.php
│   ├── audit_log.php
│   ├── includes/
│   │   └── nav.php
│   └── css/
│       └── style.css
│
├── db/
│   ├── db_eval.sql                     # UNCHANGED
│   ├── migrate_multi_school.sql        # UNCHANGED
│   ├── migrate_admin_role.sql          # NEW
│   └── migrate_teaching_verification.sql # NEW
│
├── teachers/
│   ├── teaching_activity.php           # NEW
│   ├── upload_activity.php             # NEW
│   ├── get_activity_history.php        # NEW
│   └── css/
│       └── teaching_activity.css       # NEW
│
├── uploads/
│   ├── teaching_activity/              # NEW
│   │   └── .htaccess
│   └── ... (existing folders unchanged)
│
├── utils/
│   ├── admin_auth.php                  # NEW
│   ├── exif_extractor.php              # NEW
│   ├── location_validator.php          # NEW
│   ├── image_upload_handler.php        # NEW
│   ├── verification_alerts.php         # NEW
│   └── ... (existing files unchanged)
│
├── login_admin.php                     # NEW
├── register_admin.php                  # NEW (optional/restricted)
└── ... (all existing files unchanged)
```

---

## Implementation Timeline

| Phase                          | Duration | Dependencies |
| ------------------------------ | -------- | ------------ |
| Phase 1: Admin Role            | 1-2 days | None         |
| Phase 2: Database & Backend    | 2-3 days | Phase 1      |
| Phase 3: Teacher Upload UI     | 2-3 days | Phase 2      |
| Phase 4: Admin Verification    | 2-3 days | Phase 2      |
| Phase 5: Location Validation   | 1-2 days | Phase 3, 4   |
| Phase 6: Testing & Integration | 2 days   | All phases   |

**Total Estimated Time: 10-15 days**

---

## Security Considerations

1. **File Upload Security**

   - Validate file types via MIME and extension
   - Store uploads outside web root if possible
   - Use `.htaccess` to prevent PHP execution

2. **Admin Access Control**

   - Separate session variables for admin
   - Role-based access checks on every admin page
   - Audit logging for all admin actions

3. **SQL Injection Prevention**

   - Use prepared statements throughout
   - Sanitize all user inputs

4. **GPS Data Integrity**
   - EXIF data can be spoofed - rely on admin judgment
   - Flag suspicious patterns for manual review
   - Consider adding device fingerprinting in future

---

## Future Enhancements (Out of Scope)

- Email notifications for verification status changes
- Bulk verification actions for admins
- Teacher monthly reports
- IPFS storage for images
- Mobile app with live GPS capture
- AI-based image authenticity detection
