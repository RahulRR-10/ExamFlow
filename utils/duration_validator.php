<?php
/**
 * Duration Validator Utility Class
 * Validates teaching session durations for dual photo verification
 * 
 * @package ExamFlow
 * @since December 2025
 */

class DurationValidator {
    
    /**
     * Calculate duration between two timestamps in minutes
     * 
     * @param string|DateTime $startTime Start photo timestamp
     * @param string|DateTime $endTime End photo timestamp
     * @return int Duration in minutes
     */
    public static function calculateDuration($startTime, $endTime): int {
        $start = $startTime instanceof DateTime ? $startTime : new DateTime($startTime);
        $end = $endTime instanceof DateTime ? $endTime : new DateTime($endTime);
        
        $interval = $start->diff($end);
        $minutes = ($interval->h * 60) + $interval->i + ($interval->days * 24 * 60);
        
        // If end is before start, return negative (invalid)
        if ($interval->invert) {
            return -$minutes;
        }
        
        return $minutes;
    }
    
    /**
     * Calculate expected duration from slot times
     * 
     * @param string $slotStartTime Slot start time (HH:MM:SS or HH:MM)
     * @param string $slotEndTime Slot end time (HH:MM:SS or HH:MM)
     * @return int Expected duration in minutes
     */
    public static function calculateExpectedDuration($slotStartTime, $slotEndTime): int {
        $start = strtotime($slotStartTime);
        $end = strtotime($slotEndTime);
        
        // Handle overnight slots (end time is next day)
        if ($end < $start) {
            $end += 86400; // Add 24 hours
        }
        
        return (int) round(($end - $start) / 60);
    }
    
    /**
     * Verify if actual duration matches expected duration within tolerance
     * 
     * @param int $actualMinutes Actual duration in minutes
     * @param int $expectedMinutes Expected duration in minutes
     * @param int|null $toleranceMinutes Tolerance in minutes (default from config)
     * @return bool True if duration is within acceptable range
     */
    public static function verifyDuration(int $actualMinutes, int $expectedMinutes, ?int $toleranceMinutes = null): bool {
        $tolerance = $toleranceMinutes ?? DURATION_TOLERANCE_MINUTES;
        
        return abs($actualMinutes - $expectedMinutes) <= $tolerance;
    }
    
    /**
     * Check if duration meets minimum percentage requirement
     * 
     * @param int $actualMinutes Actual duration in minutes
     * @param int $expectedMinutes Expected duration in minutes
     * @param int|null $minPercent Minimum percentage required (default from config)
     * @return bool True if meets minimum duration
     */
    public static function meetsMinimumDuration(int $actualMinutes, int $expectedMinutes, ?int $minPercent = null): bool {
        $minRequired = $minPercent ?? MIN_DURATION_PERCENT;
        
        if ($expectedMinutes <= 0) {
            return false;
        }
        
        $actualPercent = ($actualMinutes / $expectedMinutes) * 100;
        
        return $actualPercent >= $minRequired;
    }
    
    /**
     * Get duration compliance status with detailed information
     * 
     * @param int $actualMinutes Actual duration
     * @param int $expectedMinutes Expected duration
     * @return array Status information array
     */
    public static function getDurationStatus(int $actualMinutes, int $expectedMinutes): array {
        $tolerance = DURATION_TOLERANCE_MINUTES;
        $minPercent = MIN_DURATION_PERCENT;
        
        $difference = $actualMinutes - $expectedMinutes;
        $percentComplete = $expectedMinutes > 0 ? round(($actualMinutes / $expectedMinutes) * 100, 1) : 0;
        
        $isWithinTolerance = abs($difference) <= $tolerance;
        $meetsMinimum = self::meetsMinimumDuration($actualMinutes, $expectedMinutes);
        
        // Determine status
        if ($actualMinutes < 0) {
            $status = 'invalid';
            $statusText = 'Invalid - End time before start time';
            $statusClass = 'danger';
        } elseif ($percentComplete < 50) {
            $status = 'rejected';
            $statusText = 'Auto-Reject - Duration too short';
            $statusClass = 'danger';
        } elseif (!$meetsMinimum) {
            $status = 'warning';
            $statusText = 'Warning - Below minimum duration';
            $statusClass = 'warning';
        } elseif ($isWithinTolerance) {
            $status = 'verified';
            $statusText = 'Verified - Duration matches expected';
            $statusClass = 'success';
        } elseif ($difference > 0) {
            $status = 'extended';
            $statusText = 'Extended - Teacher stayed longer';
            $statusClass = 'info';
        } else {
            $status = 'short';
            $statusText = 'Acceptable - Slightly shorter than expected';
            $statusClass = 'warning';
        }
        
        return [
            'status' => $status,
            'statusText' => $statusText,
            'statusClass' => $statusClass,
            'actualMinutes' => $actualMinutes,
            'expectedMinutes' => $expectedMinutes,
            'difference' => $difference,
            'percentComplete' => $percentComplete,
            'isWithinTolerance' => $isWithinTolerance,
            'meetsMinimum' => $meetsMinimum,
            'formattedActual' => self::formatDuration($actualMinutes),
            'formattedExpected' => self::formatDuration($expectedMinutes),
            'formattedDifference' => ($difference >= 0 ? '+' : '') . self::formatDuration(abs($difference))
        ];
    }
    
    /**
     * Format duration in minutes to human-readable string
     * 
     * @param int $minutes Duration in minutes
     * @return string Formatted duration (e.g., "2h 30m")
     */
    public static function formatDuration(int $minutes): string {
        if ($minutes < 0) {
            return '-' . self::formatDuration(abs($minutes));
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$mins}m";
        }
    }
    
    /**
     * Validate photo timing relative to slot
     * 
     * @param string $photoTime Photo timestamp
     * @param string $slotDate Slot date
     * @param string $slotStartTime Slot start time
     * @param string $slotEndTime Slot end time
     * @param string $photoType 'start' or 'end'
     * @return array Validation result
     */
    public static function validatePhotoTiming(
        string $photoTime, 
        string $slotDate, 
        string $slotStartTime, 
        string $slotEndTime, 
        string $photoType = 'start'
    ): array {
        $photoDateTime = new DateTime($photoTime);
        $slotStart = new DateTime("$slotDate $slotStartTime");
        $slotEnd = new DateTime("$slotDate $slotEndTime");
        
        // Handle overnight slots
        if ($slotEnd < $slotStart) {
            $slotEnd->modify('+1 day');
        }
        
        $warnings = [];
        $errors = [];
        $isValid = true;
        
        $maxBefore = MAX_TIME_BEFORE_SLOT_START;
        $maxAfter = MAX_TIME_AFTER_SLOT_END;
        
        // Check photo date matches slot date
        $photoDate = $photoDateTime->format('Y-m-d');
        if ($photoDate !== $slotDate && $photoDate !== $slotEnd->format('Y-m-d')) {
            $warnings[] = "Photo date ($photoDate) doesn't match slot date ($slotDate)";
        }
        
        if ($photoType === 'start') {
            // Start photo timing validation
            $minutesBeforeSlot = ($slotStart->getTimestamp() - $photoDateTime->getTimestamp()) / 60;
            
            if ($minutesBeforeSlot > $maxBefore) {
                $warnings[] = "Start photo taken more than {$maxBefore} minutes before slot start";
            }
            
            if ($photoDateTime > $slotEnd) {
                $errors[] = "Start photo taken after slot end time";
                $isValid = false;
            }
        } else {
            // End photo timing validation
            $minutesAfterSlot = ($photoDateTime->getTimestamp() - $slotEnd->getTimestamp()) / 60;
            
            if ($minutesAfterSlot > $maxAfter) {
                $warnings[] = "End photo taken more than {$maxAfter} minutes after slot end";
            }
            
            if ($photoDateTime < $slotStart) {
                $errors[] = "End photo taken before slot start time";
                $isValid = false;
            }
        }
        
        return [
            'isValid' => $isValid,
            'warnings' => $warnings,
            'errors' => $errors,
            'photoTime' => $photoDateTime->format('Y-m-d H:i:s'),
            'slotStart' => $slotStart->format('Y-m-d H:i:s'),
            'slotEnd' => $slotEnd->format('Y-m-d H:i:s')
        ];
    }
}
