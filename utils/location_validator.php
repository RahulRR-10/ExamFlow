<?php
/**
 * GPS Location Validator
 * 
 * Validates if uploaded photo GPS coordinates match the registered school location.
 * Uses the Haversine formula for accurate distance calculation.
 */

class LocationValidator {

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
     * Calculate distance between two GPS coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude of point 1
     * @param float $lng1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lng2 Longitude of point 2
     * @return float Distance in meters
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371000; // Earth's radius in meters

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
     * 
     * @param int $schoolId School ID to validate against
     * @param float $imageLat Latitude from image
     * @param float $imageLng Longitude from image
     * @return array Validation result with status, distance, and message
     */
    public function validateLocation($schoolId, $imageLat, $imageLng) {
        // Get school location
        $stmt = mysqli_prepare($this->conn,
            "SELECT latitude, longitude, validation_radius_meters, address
             FROM school_locations WHERE school_id = ?"
        );
        
        if (!$stmt) {
            return [
                'status' => 'unknown',
                'message' => 'Database error',
                'distance' => null
            ];
        }

        mysqli_stmt_bind_param($stmt, "i", $schoolId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$result) {
            return [
                'status' => 'unknown',
                'message' => 'School location not configured. Please contact administrator.',
                'distance' => null,
                'school_location_missing' => true
            ];
        }

        $distance = self::calculateDistance(
            $imageLat, $imageLng,
            $result['latitude'], $result['longitude']
        );

        $radius = $result['validation_radius_meters'] ?? 500;

        $matched = $distance <= $radius;

        return [
            'status' => $matched ? 'matched' : 'mismatched',
            'distance' => round($distance, 2),
            'radius' => $radius,
            'school_lat' => $result['latitude'],
            'school_lng' => $result['longitude'],
            'school_address' => $result['address'],
            'message' => $matched
                ? "✓ Location verified within {$radius}m radius"
                : "⚠ Location is " . round($distance) . "m from school (allowed: {$radius}m)"
        ];
    }

    /**
     * Get school location details
     * 
     * @param int $schoolId School ID
     * @return array|null School location data or null
     */
    public function getSchoolLocation($schoolId) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT sl.*, s.school_name
             FROM school_locations sl
             JOIN schools s ON sl.school_id = s.school_id
             WHERE sl.school_id = ?"
        );
        
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, "i", $schoolId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $result;
    }

    /**
     * Set or update school location
     * 
     * @param int $schoolId School ID
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param string|null $address Address text
     * @param int $radius Validation radius in meters
     * @return bool Success status
     */
    public function setSchoolLocation($schoolId, $latitude, $longitude, $address = null, $radius = 500) {
        $stmt = mysqli_prepare($this->conn,
            "INSERT INTO school_locations (school_id, latitude, longitude, address, validation_radius_meters)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             latitude = VALUES(latitude),
             longitude = VALUES(longitude),
             address = VALUES(address),
             validation_radius_meters = VALUES(validation_radius_meters)"
        );
        
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, "iddsi", $schoolId, $latitude, $longitude, $address, $radius);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $result;
    }

    /**
     * Get verification setting from database
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public function getSetting($key, $default = null) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT setting_value FROM verification_settings WHERE setting_key = ?"
        );
        
        if (!$stmt) {
            return $default;
        }

        mysqli_stmt_bind_param($stmt, "s", $key);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $result ? $result['setting_value'] : $default;
    }

    /**
     * Update verification setting
     * 
     * @param string $key Setting key
     * @param string $value Setting value
     * @return bool Success status
     */
    public function setSetting($key, $value) {
        $stmt = mysqli_prepare($this->conn,
            "UPDATE verification_settings SET setting_value = ? WHERE setting_key = ?"
        );
        
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, "ss", $value, $key);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $result;
    }

    /**
     * Get all verification settings
     * 
     * @return array All settings as key-value pairs
     */
    public function getAllSettings() {
        $result = mysqli_query($this->conn, "SELECT setting_key, setting_value, description FROM verification_settings");
        
        $settings = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['description']
            ];
        }

        return $settings;
    }

    /**
     * Get all schools with location status
     * 
     * @return array Schools with their location configuration status
     */
    public function getSchoolsWithLocationStatus() {
        $sql = "SELECT s.school_id, s.school_name, s.status,
                       sl.latitude, sl.longitude, sl.address, sl.validation_radius_meters,
                       CASE WHEN sl.id IS NOT NULL THEN 1 ELSE 0 END as has_location
                FROM schools s
                LEFT JOIN school_locations sl ON s.school_id = sl.school_id
                WHERE s.status = 'active'
                ORDER BY s.school_name";
        
        $result = mysqli_query($this->conn, $sql);
        
        $schools = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $schools[] = $row;
        }

        return $schools;
    }

    /**
     * Format distance for display
     * 
     * @param float $meters Distance in meters
     * @return string Formatted distance string
     */
    public static function formatDistance($meters) {
        if ($meters < 1000) {
            return round($meters) . 'm';
        }
        return round($meters / 1000, 2) . 'km';
    }
}
?>
