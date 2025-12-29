<?php
/**
 * Secure Image Upload Handler for Teaching Activity
 * 
 * Handles the upload, validation, and processing of geotagged photos
 * for teaching activity verification.
 */

require_once __DIR__ . '/exif_extractor.php';
require_once __DIR__ . '/location_validator.php';

class ImageUploadHandler {

    private $conn;
    private $uploadDir;
    private $maxFileSize;
    private $allowedFormats;
    private $requireGps;
    private $maxPhotoAgeDays;

    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->uploadDir = __DIR__ . '/../uploads/teaching_activity/';

        // Load settings from database
        $validator = new LocationValidator($conn);
        $this->maxFileSize = intval($validator->getSetting('max_file_size_mb', 10)) * 1024 * 1024;
        $this->allowedFormats = explode(',', $validator->getSetting('allowed_formats', 'jpg,jpeg,png,heic'));
        $this->requireGps = $validator->getSetting('require_gps', 'true') === 'true';
        $this->maxPhotoAgeDays = intval($validator->getSetting('max_photo_age_days', 7));

        // Create upload directory if not exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Process and validate uploaded image
     * 
     * @param array $file $_FILES array element
     * @param int $teacherId Teacher's user ID
     * @param int $schoolId School ID for the submission
     * @param string $activityDate Date of teaching activity (Y-m-d format)
     * @return array Result with success/error information
     */
    public function processUpload($file, $teacherId, $schoolId, $activityDate) {
        // Validate basic file properties
        $validation = $this->validateFile($file);
        if ($validation['error']) {
            return $validation;
        }

        // Check for duplicate submission
        if ($this->isDuplicate($teacherId, $schoolId, $activityDate)) {
            return ['error' => 'A submission already exists for this school and date. Please delete the existing submission first.'];
        }

        // Extract GPS data
        $gpsData = ExifExtractor::extractGPS($file['tmp_name']);
        if (isset($gpsData['error']) && $this->requireGps) {
            return ['error' => 'Image must contain GPS location data. ' . $gpsData['error'] . ' Please ensure location services were enabled when taking the photo.'];
        }

        // Validate photo age
        $ageValidation = ExifExtractor::validatePhotoAge($file['tmp_name'], $this->maxPhotoAgeDays);
        if (!$ageValidation['valid'] && $this->requireGps) {
            return ['error' => $ageValidation['error']];
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
            return ['error' => 'Failed to save uploaded file. Please try again.'];
        }

        // Validate location if GPS data available
        $locationResult = ['status' => 'unknown', 'distance' => null, 'message' => 'GPS data not available'];
        if (!isset($gpsData['error'])) {
            $validator = new LocationValidator($this->conn);
            $locationResult = $validator->validateLocation(
                $schoolId, $gpsData['latitude'], $gpsData['longitude']
            );
        }

        // Get all EXIF data
        $exifJson = ExifExtractor::getAllExifData($filepath);

        // Get device info
        $deviceInfo = ExifExtractor::extractDeviceInfo($filepath);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => 'uploads/teaching_activity/' . $filename,
            'filesize' => $file['size'],
            'mimetype' => $file['type'],
            'gps' => isset($gpsData['error']) ? null : $gpsData,
            'photo_timestamp' => $photoTimestamp ? $photoTimestamp->format('Y-m-d H:i:s') : null,
            'location_validation' => $locationResult,
            'exif_data' => $exifJson,
            'device_info' => $deviceInfo
        ];
    }

    /**
     * Save submission to database
     * 
     * @param int $teacherId Teacher ID
     * @param int $schoolId School ID
     * @param string $activityDate Activity date
     * @param array $uploadResult Result from processUpload
     * @return array Result with submission ID or error
     */
    public function saveSubmission($teacherId, $schoolId, $activityDate, $uploadResult) {
        if (!$uploadResult['success']) {
            return ['error' => 'Cannot save failed upload'];
        }

        $gps = $uploadResult['gps'];
        $locationValidation = $uploadResult['location_validation'];

        $stmt = mysqli_prepare($this->conn,
            "INSERT INTO teaching_activity_submissions
             (teacher_id, school_id, image_path, image_filename, image_size, image_mime_type,
              gps_latitude, gps_longitude, gps_altitude, activity_date, photo_taken_at,
              location_match_status, distance_from_school, exif_data)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            return ['error' => 'Database error: ' . mysqli_error($this->conn)];
        }

        $latitude = $gps ? $gps['latitude'] : null;
        $longitude = $gps ? $gps['longitude'] : null;
        $altitude = $gps ? $gps['altitude'] : null;
        $matchStatus = $locationValidation['status'];
        $distance = $locationValidation['distance'];

        mysqli_stmt_bind_param($stmt, "iississddsssds",
            $teacherId,
            $schoolId,
            $uploadResult['filepath'],
            $uploadResult['filename'],
            $uploadResult['filesize'],
            $uploadResult['mimetype'],
            $latitude,
            $longitude,
            $altitude,
            $activityDate,
            $uploadResult['photo_timestamp'],
            $matchStatus,
            $distance,
            $uploadResult['exif_data']
        );

        if (!mysqli_stmt_execute($stmt)) {
            // Clean up uploaded file on database error
            @unlink($this->uploadDir . $uploadResult['filename']);
            return ['error' => 'Failed to save submission: ' . mysqli_stmt_error($stmt)];
        }

        $submissionId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        return [
            'success' => true,
            'submission_id' => $submissionId,
            'message' => 'Submission uploaded successfully and pending verification.'
        ];
    }

    /**
     * Validate file before processing
     * 
     * @param array $file $_FILES array element
     * @return array Validation result
     */
    private function validateFile($file) {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['error' => 'Invalid file upload'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['error' => 'File size exceeds the maximum allowed'];
            case UPLOAD_ERR_PARTIAL:
                return ['error' => 'File was only partially uploaded'];
            case UPLOAD_ERR_NO_FILE:
                return ['error' => 'No file was uploaded'];
            default:
                return ['error' => 'Unknown upload error'];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $maxMb = $this->maxFileSize / 1024 / 1024;
            return ['error' => "File size exceeds maximum allowed ({$maxMb}MB)"];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedFormats)) {
            return ['error' => 'Invalid file format. Allowed: ' . implode(', ', $this->allowedFormats)];
        }

        // Verify it's actually an image
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            return ['error' => 'File is not a valid image'];
        }

        // Check for valid image types
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/heic', 'image/heif'];
        if (!in_array($imageInfo['mime'], $allowedMimeTypes)) {
            return ['error' => 'Invalid image type'];
        }

        return ['error' => null];
    }

    /**
     * Check if a submission already exists for teacher/school/date combination
     * 
     * @param int $teacherId Teacher ID
     * @param int $schoolId School ID
     * @param string $activityDate Activity date
     * @return bool True if duplicate exists
     */
    private function isDuplicate($teacherId, $schoolId, $activityDate) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT id FROM teaching_activity_submissions
             WHERE teacher_id = ? AND school_id = ? AND activity_date = ?"
        );
        mysqli_stmt_bind_param($stmt, "iis", $teacherId, $schoolId, $activityDate);
        mysqli_stmt_execute($stmt);
        $result = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Delete a submission (only if pending and owned by teacher)
     * 
     * @param int $submissionId Submission ID
     * @param int $teacherId Teacher ID (for ownership verification)
     * @return array Result
     */
    public function deleteSubmission($submissionId, $teacherId) {
        // Get submission details
        $stmt = mysqli_prepare($this->conn,
            "SELECT image_filename, verification_status FROM teaching_activity_submissions
             WHERE id = ? AND teacher_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "ii", $submissionId, $teacherId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$result) {
            return ['error' => 'Submission not found or access denied'];
        }

        if ($result['verification_status'] !== 'pending') {
            return ['error' => 'Cannot delete a submission that has been verified'];
        }

        // Delete file
        $filepath = $this->uploadDir . $result['image_filename'];
        if (file_exists($filepath)) {
            @unlink($filepath);
        }

        // Delete database record
        $stmt = mysqli_prepare($this->conn,
            "DELETE FROM teaching_activity_submissions WHERE id = ? AND teacher_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "ii", $submissionId, $teacherId);
        
        if (!mysqli_stmt_execute($stmt)) {
            return ['error' => 'Failed to delete submission'];
        }

        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Submission deleted successfully'];
    }

    /**
     * Get submission by ID
     * 
     * @param int $submissionId Submission ID
     * @return array|null Submission data or null
     */
    public function getSubmission($submissionId) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT tas.*, t.fname as teacher_name, s.school_name, a.fname as admin_name
             FROM teaching_activity_submissions tas
             JOIN teacher t ON tas.teacher_id = t.id
             JOIN schools s ON tas.school_id = s.school_id
             LEFT JOIN admin a ON tas.verified_by = a.id
             WHERE tas.id = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $submissionId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Get teacher's submission history
     * 
     * @param int $teacherId Teacher ID
     * @param int $limit Number of records to return
     * @return array Submissions
     */
    public function getTeacherSubmissions($teacherId, $limit = 50) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT tas.*, s.school_name, a.fname as admin_name
             FROM teaching_activity_submissions tas
             JOIN schools s ON tas.school_id = s.school_id
             LEFT JOIN admin a ON tas.verified_by = a.id
             WHERE tas.teacher_id = ?
             ORDER BY tas.upload_date DESC
             LIMIT ?"
        );
        mysqli_stmt_bind_param($stmt, "ii", $teacherId, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $submissions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $submissions[] = $row;
        }

        mysqli_stmt_close($stmt);
        return $submissions;
    }
}
?>
