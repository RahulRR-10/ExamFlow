<?php
/**
 * Verification Alert System
 * 
 * Generates alerts and flags for suspicious submissions
 * to help admins quickly identify potential issues.
 */

require_once __DIR__ . '/location_validator.php';

class VerificationAlerts {

    private $conn;
    private $validator;

    /**
     * Alert severity levels
     */
    const ALERT_INFO = 'info';
    const ALERT_WARNING = 'warning';
    const ALERT_DANGER = 'danger';
    const ALERT_SUCCESS = 'success';

    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->validator = new LocationValidator($conn);
    }

    /**
     * Check submission for all possible alerts
     * 
     * @param int $submissionId Submission ID
     * @return array Array of alert objects
     */
    public function checkSubmissionAlerts($submissionId) {
        $alerts = [];

        // Get submission data
        $submission = $this->getSubmission($submissionId);
        if (!$submission) {
            return [['type' => self::ALERT_DANGER, 'title' => 'Error', 'message' => 'Submission not found']];
        }

        // Check location match
        $alerts = array_merge($alerts, $this->checkLocationAlerts($submission));

        // Check GPS data availability
        $alerts = array_merge($alerts, $this->checkGpsAlerts($submission));

        // Check date consistency
        $alerts = array_merge($alerts, $this->checkDateAlerts($submission));

        // Check for suspicious patterns
        $alerts = array_merge($alerts, $this->checkSuspiciousPatterns($submission));

        // Check teacher history
        $alerts = array_merge($alerts, $this->checkTeacherHistory($submission));

        return $alerts;
    }

    /**
     * Get quick summary counts for dashboard
     * 
     * @return array Summary counts
     */
    public function getAlertSummary() {
        $summary = [
            'mismatched_locations' => 0,
            'no_gps' => 0,
            'date_mismatch' => 0,
            'suspicious' => 0
        ];

        // Mismatched locations pending review
        $sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions 
                WHERE verification_status = 'pending' AND location_match_status = 'mismatched'";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $summary['mismatched_locations'] = $row['cnt'];
        }

        // No GPS data
        $sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions 
                WHERE verification_status = 'pending' AND (gps_latitude IS NULL OR gps_longitude IS NULL)";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $summary['no_gps'] = $row['cnt'];
        }

        // Date mismatches (photo date != activity date)
        $sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions 
                WHERE verification_status = 'pending' 
                AND photo_taken_at IS NOT NULL 
                AND DATE(photo_taken_at) != activity_date";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $summary['date_mismatch'] = $row['cnt'];
        }

        // Suspicious submissions
        $sql = "SELECT COUNT(*) as cnt FROM teaching_activity_submissions 
                WHERE verification_status = 'pending' AND is_suspicious = 1";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $summary['suspicious'] = $row['cnt'];
        }

        return $summary;
    }

    /**
     * Check location-related alerts
     */
    private function checkLocationAlerts($submission) {
        $alerts = [];
        $suspiciousThreshold = intval($this->validator->getSetting('suspicious_distance_threshold', 1000));

        if ($submission['location_match_status'] === 'mismatched') {
            $distance = round($submission['distance_from_school']);
            $severity = $distance > $suspiciousThreshold ? self::ALERT_DANGER : self::ALERT_WARNING;

            $alerts[] = [
                'type' => $severity,
                'title' => 'Location Mismatch',
                'message' => "GPS location is {$distance}m from school",
                'icon' => '📍',
                'detail' => $distance > $suspiciousThreshold 
                    ? "Distance exceeds suspicious threshold ({$suspiciousThreshold}m)" 
                    : null
            ];
        } elseif ($submission['location_match_status'] === 'matched') {
            $alerts[] = [
                'type' => self::ALERT_SUCCESS,
                'title' => 'Location Verified',
                'message' => 'Photo taken within school radius',
                'icon' => '✓'
            ];
        }

        return $alerts;
    }

    /**
     * Check GPS data alerts
     */
    private function checkGpsAlerts($submission) {
        $alerts = [];

        if (!$submission['gps_latitude'] || !$submission['gps_longitude']) {
            $alerts[] = [
                'type' => self::ALERT_WARNING,
                'title' => 'No GPS Data',
                'message' => 'Photo does not contain GPS location information',
                'icon' => '⚠️',
                'detail' => 'Location cannot be verified without GPS coordinates'
            ];
        } elseif ($submission['gps_accuracy'] && $submission['gps_accuracy'] > 100) {
            $alerts[] = [
                'type' => self::ALERT_INFO,
                'title' => 'Low GPS Accuracy',
                'message' => "GPS accuracy: ±{$submission['gps_accuracy']}m",
                'icon' => 'ℹ️'
            ];
        }

        return $alerts;
    }

    /**
     * Check date-related alerts
     */
    private function checkDateAlerts($submission) {
        $alerts = [];
        $toleranceDays = intval($this->validator->getSetting('date_tolerance_days', 1));

        // Check if photo date exists
        if (!$submission['photo_taken_at']) {
            $alerts[] = [
                'type' => self::ALERT_INFO,
                'title' => 'No Photo Timestamp',
                'message' => 'Photo metadata does not contain capture date',
                'icon' => 'ℹ️'
            ];
            return $alerts;
        }

        $photoDate = new DateTime($submission['photo_taken_at']);
        $activityDate = new DateTime($submission['activity_date']);
        $diff = $photoDate->diff($activityDate)->days;

        if ($photoDate->format('Y-m-d') !== $activityDate->format('Y-m-d')) {
            if ($diff <= $toleranceDays) {
                $alerts[] = [
                    'type' => self::ALERT_INFO,
                    'title' => 'Date Difference',
                    'message' => "Photo taken on {$photoDate->format('M d')} for activity on {$activityDate->format('M d')}",
                    'icon' => 'ℹ️',
                    'detail' => "Within tolerance of {$toleranceDays} day(s)"
                ];
            } else {
                $alerts[] = [
                    'type' => self::ALERT_WARNING,
                    'title' => 'Date Mismatch',
                    'message' => "Photo date ({$photoDate->format('M d')}) differs from activity date ({$activityDate->format('M d')})",
                    'icon' => '📅',
                    'detail' => "{$diff} day(s) difference exceeds tolerance"
                ];
            }
        }

        // Check if photo was taken in the future (suspicious)
        $now = new DateTime();
        if ($photoDate > $now) {
            $alerts[] = [
                'type' => self::ALERT_DANGER,
                'title' => 'Future Timestamp',
                'message' => 'Photo timestamp is in the future - possible manipulation',
                'icon' => '🚨'
            ];
        }

        return $alerts;
    }

    /**
     * Check for suspicious patterns
     */
    private function checkSuspiciousPatterns($submission) {
        $alerts = [];

        // Already flagged as suspicious
        if ($submission['is_suspicious']) {
            $alerts[] = [
                'type' => self::ALERT_DANGER,
                'title' => 'Flagged as Suspicious',
                'message' => $submission['suspicious_reason'] ?? 'This submission has been flagged for review',
                'icon' => '🚩'
            ];
        }

        // Check for rapid successive uploads (same day, multiple schools)
        $stmt = mysqli_prepare($this->conn,
            "SELECT COUNT(DISTINCT school_id) as school_count
             FROM teaching_activity_submissions
             WHERE teacher_id = ? AND DATE(upload_date) = DATE(?)
             AND id != ?"
        );
        mysqli_stmt_bind_param($stmt, "isi", 
            $submission['teacher_id'], 
            $submission['upload_date'],
            $submission['id']
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($result && $result['school_count'] >= 3) {
            $alerts[] = [
                'type' => self::ALERT_WARNING,
                'title' => 'Multiple Schools',
                'message' => "Teacher uploaded for {$result['school_count']} different schools today",
                'icon' => '🏫',
                'detail' => 'Verify if teacher is assigned to multiple schools'
            ];
        }

        return $alerts;
    }

    /**
     * Check teacher's verification history
     */
    private function checkTeacherHistory($submission) {
        $alerts = [];

        // Get rejection rate
        $stmt = mysqli_prepare($this->conn,
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
             FROM teaching_activity_submissions
             WHERE teacher_id = ? AND id != ?"
        );
        mysqli_stmt_bind_param($stmt, "ii", $submission['teacher_id'], $submission['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($result && $result['total'] >= 5) {
            $rejectionRate = ($result['rejected'] / $result['total']) * 100;
            
            if ($rejectionRate >= 50) {
                $alerts[] = [
                    'type' => self::ALERT_DANGER,
                    'title' => 'High Rejection Rate',
                    'message' => "Teacher has " . round($rejectionRate) . "% rejection rate",
                    'icon' => '⚠️',
                    'detail' => "{$result['rejected']} rejected out of {$result['total']} submissions"
                ];
            } elseif ($rejectionRate >= 25) {
                $alerts[] = [
                    'type' => self::ALERT_WARNING,
                    'title' => 'Elevated Rejection Rate',
                    'message' => "Teacher has " . round($rejectionRate) . "% rejection rate",
                    'icon' => 'ℹ️'
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get submission data
     */
    private function getSubmission($submissionId) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT * FROM teaching_activity_submissions WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $submissionId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Flag submission as suspicious
     * 
     * @param int $submissionId Submission ID
     * @param string $reason Reason for flagging
     * @return bool Success status
     */
    public function flagAsSuspicious($submissionId, $reason) {
        $stmt = mysqli_prepare($this->conn,
            "UPDATE teaching_activity_submissions 
             SET is_suspicious = 1, suspicious_reason = ?
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "si", $reason, $submissionId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Clear suspicious flag
     * 
     * @param int $submissionId Submission ID
     * @return bool Success status
     */
    public function clearSuspiciousFlag($submissionId) {
        $stmt = mysqli_prepare($this->conn,
            "UPDATE teaching_activity_submissions 
             SET is_suspicious = 0, suspicious_reason = NULL
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $submissionId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
}
?>
