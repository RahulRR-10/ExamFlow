<?php
/**
 * EXIF Data Extractor for Geotagged Images
 * 
 * Extracts GPS coordinates, timestamps, and other metadata from uploaded images.
 * Used for verifying teaching activity photo submissions.
 */

class ExifExtractor {

    /**
     * Extract GPS coordinates from image EXIF data
     * 
     * @param string $imagePath Path to the image file
     * @return array GPS data or error message
     */
    public static function extractGPS($imagePath) {
        if (!file_exists($imagePath)) {
            return ['error' => 'File not found'];
        }

        // Check if exif extension is loaded
        if (!function_exists('exif_read_data')) {
            return ['error' => 'EXIF extension not available'];
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
                $altitude *= -1; // Below sea level
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
     * 
     * @param string $imagePath Path to the image file
     * @return DateTime|null Photo timestamp or null if not found
     */
    public static function extractTimestamp($imagePath) {
        if (!function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($imagePath, 'EXIF', true);

        // Try DateTimeOriginal first (when photo was taken)
        if ($exif && isset($exif['EXIF']['DateTimeOriginal'])) {
            $dt = DateTime::createFromFormat('Y:m:d H:i:s', $exif['EXIF']['DateTimeOriginal']);
            if ($dt !== false) {
                return $dt;
            }
        }

        // Fallback to DateTime (when file was created/modified)
        if ($exif && isset($exif['IFD0']['DateTime'])) {
            $dt = DateTime::createFromFormat('Y:m:d H:i:s', $exif['IFD0']['DateTime']);
            if ($dt !== false) {
                return $dt;
            }
        }

        return null;
    }

    /**
     * Get all EXIF data as JSON
     * 
     * @param string $imagePath Path to the image file
     * @return string|null JSON encoded EXIF data or null
     */
    public static function getAllExifData($imagePath) {
        if (!function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($imagePath, 0, true);
        
        if (!$exif) {
            return null;
        }

        // Clean up binary data that can't be JSON encoded
        array_walk_recursive($exif, function(&$value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $value = '[binary data]';
            }
        });

        return json_encode($exif, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * Extract camera/device information
     * 
     * @param string $imagePath Path to the image file
     * @return array Device info
     */
    public static function extractDeviceInfo($imagePath) {
        if (!function_exists('exif_read_data')) {
            return ['error' => 'EXIF extension not available'];
        }

        $exif = @exif_read_data($imagePath, 'IFD0', true);

        if (!$exif || !isset($exif['IFD0'])) {
            return ['error' => 'No device info found'];
        }

        $ifd = $exif['IFD0'];

        return [
            'make' => $ifd['Make'] ?? null,
            'model' => $ifd['Model'] ?? null,
            'software' => $ifd['Software'] ?? null
        ];
    }

    /**
     * Convert GPS coordinate from DMS (degrees, minutes, seconds) to decimal
     * 
     * @param array $coordinate Array of [degrees, minutes, seconds]
     * @param string $ref Reference direction (N, S, E, W)
     * @return float Decimal coordinate
     */
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

    /**
     * Parse EXIF rational number (fraction format)
     * 
     * @param mixed $value Value to parse (may be "numerator/denominator" string)
     * @return float Parsed value
     */
    private static function parseRational($value) {
        if (is_string($value) && strpos($value, '/') !== false) {
            $parts = explode('/', $value);
            if (count($parts) == 2 && floatval($parts[1]) != 0) {
                return floatval($parts[0]) / floatval($parts[1]);
            }
        }
        return floatval($value);
    }

    /**
     * Check if image has valid GPS data
     * 
     * @param string $imagePath Path to the image file
     * @return bool True if GPS data exists
     */
    public static function hasGPSData($imagePath) {
        $gps = self::extractGPS($imagePath);
        return !isset($gps['error']);
    }

    /**
     * Validate photo age against maximum allowed
     * 
     * @param string $imagePath Path to the image file
     * @param int $maxAgeDays Maximum allowed age in days
     * @return array Validation result
     */
    public static function validatePhotoAge($imagePath, $maxAgeDays = 7) {
        $timestamp = self::extractTimestamp($imagePath);

        if (!$timestamp) {
            return [
                'valid' => false,
                'error' => 'Could not determine photo date',
                'photo_date' => null
            ];
        }

        $now = new DateTime();
        $diff = $now->diff($timestamp);
        $daysDiff = $diff->days;

        // Check if photo is from the future
        if ($timestamp > $now) {
            return [
                'valid' => false,
                'error' => 'Photo date is in the future',
                'photo_date' => $timestamp->format('Y-m-d H:i:s'),
                'days_diff' => -$daysDiff
            ];
        }

        if ($daysDiff > $maxAgeDays) {
            return [
                'valid' => false,
                'error' => "Photo is {$daysDiff} days old (maximum allowed: {$maxAgeDays} days)",
                'photo_date' => $timestamp->format('Y-m-d H:i:s'),
                'days_diff' => $daysDiff
            ];
        }

        return [
            'valid' => true,
            'photo_date' => $timestamp->format('Y-m-d H:i:s'),
            'days_diff' => $daysDiff
        ];
    }
}
?>
