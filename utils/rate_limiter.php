<?php
/**
 * Rate Limiter for Upload Restrictions
 * 
 * Prevents abuse by limiting the number of uploads per teacher per day.
 */

require_once __DIR__ . '/location_validator.php';

class RateLimiter {

    private $conn;

    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Check if teacher can upload
     * 
     * @param int $teacherId Teacher ID
     * @return array ['allowed' => bool, 'remaining' => int, 'message' => string]
     */
    public function canUpload($teacherId) {
        $validator = new LocationValidator($this->conn);
        $dailyLimit = intval($validator->getSetting('daily_upload_limit', 5));

        // Get today's upload count
        $today = date('Y-m-d');
        $stmt = mysqli_prepare($this->conn,
            "SELECT upload_count FROM upload_rate_limits
             WHERE teacher_id = ? AND upload_date = ?"
        );
        mysqli_stmt_bind_param($stmt, "is", $teacherId, $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $currentCount = $result ? $result['upload_count'] : 0;
        $remaining = max(0, $dailyLimit - $currentCount);

        if ($currentCount >= $dailyLimit) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'limit' => $dailyLimit,
                'current' => $currentCount,
                'message' => "Daily upload limit reached ({$dailyLimit} uploads per day). Please try again tomorrow."
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $remaining,
            'limit' => $dailyLimit,
            'current' => $currentCount,
            'message' => "You have {$remaining} uploads remaining today."
        ];
    }

    /**
     * Record an upload
     * 
     * @param int $teacherId Teacher ID
     * @return bool Success status
     */
    public function recordUpload($teacherId) {
        $today = date('Y-m-d');

        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
        $stmt = mysqli_prepare($this->conn,
            "INSERT INTO upload_rate_limits (teacher_id, upload_date, upload_count, last_upload)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE
             upload_count = upload_count + 1,
             last_upload = NOW()"
        );
        mysqli_stmt_bind_param($stmt, "is", $teacherId, $today);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $result;
    }

    /**
     * Get teacher's upload stats for today
     * 
     * @param int $teacherId Teacher ID
     * @return array Upload statistics
     */
    public function getUploadStats($teacherId) {
        $validator = new LocationValidator($this->conn);
        $dailyLimit = intval($validator->getSetting('daily_upload_limit', 5));

        $today = date('Y-m-d');
        $stmt = mysqli_prepare($this->conn,
            "SELECT upload_count, last_upload FROM upload_rate_limits
             WHERE teacher_id = ? AND upload_date = ?"
        );
        mysqli_stmt_bind_param($stmt, "is", $teacherId, $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return [
            'today_count' => $result ? $result['upload_count'] : 0,
            'daily_limit' => $dailyLimit,
            'remaining' => max(0, $dailyLimit - ($result ? $result['upload_count'] : 0)),
            'last_upload' => $result ? $result['last_upload'] : null
        ];
    }

    /**
     * Get all teachers who hit their limit today
     * 
     * @return array Teachers at limit
     */
    public function getTeachersAtLimit() {
        $validator = new LocationValidator($this->conn);
        $dailyLimit = intval($validator->getSetting('daily_upload_limit', 5));

        $today = date('Y-m-d');
        $sql = "SELECT rl.*, t.fname, t.email
                FROM upload_rate_limits rl
                JOIN teacher t ON rl.teacher_id = t.id
                WHERE rl.upload_date = ? AND rl.upload_count >= ?
                ORDER BY rl.upload_count DESC";

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $today, $dailyLimit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $teachers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $teachers[] = $row;
        }

        mysqli_stmt_close($stmt);
        return $teachers;
    }

    /**
     * Reset rate limit for a teacher (admin function)
     * 
     * @param int $teacherId Teacher ID
     * @return bool Success status
     */
    public function resetLimit($teacherId) {
        $today = date('Y-m-d');
        $stmt = mysqli_prepare($this->conn,
            "DELETE FROM upload_rate_limits WHERE teacher_id = ? AND upload_date = ?"
        );
        mysqli_stmt_bind_param($stmt, "is", $teacherId, $today);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Clean up old rate limit records (run periodically)
     * 
     * @param int $daysToKeep Number of days to keep records
     * @return int Number of records deleted
     */
    public function cleanup($daysToKeep = 30) {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        $stmt = mysqli_prepare($this->conn,
            "DELETE FROM upload_rate_limits WHERE upload_date < ?"
        );
        mysqli_stmt_bind_param($stmt, "s", $cutoffDate);
        mysqli_stmt_execute($stmt);
        $deleted = mysqli_affected_rows($this->conn);
        mysqli_stmt_close($stmt);
        return $deleted;
    }
}
?>
