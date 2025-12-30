<?php
/**
 * Session Validator Utility Class
 * Comprehensive validation for dual photo teaching sessions
 * 
 * Phase 5: Validation Logic & Business Rules
 * 
 * Combines:
 * - Duration validation (from DurationValidator)
 * - Location validation (from LocationValidator)
 * - Timestamp validation
 * - Business rule enforcement
 * 
 * @package ExamFlow
 * @since December 2025
 */

require_once __DIR__ . '/duration_validator.php';
require_once __DIR__ . '/location_validator.php';

class SessionValidator {
    
    private $conn;
    
    /**
     * Validation result constants
     */
    const RESULT_VALID = 'valid';
    const RESULT_WARNING = 'warning';
    const RESULT_REJECT = 'reject';
    const RESULT_MANUAL_REVIEW = 'manual_review';
    
    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Perform comprehensive validation on a teaching session
     * 
     * @param array $session Session data with all fields
     * @param array $slot Slot data (slot_date, start_time, end_time)
     * @param array $school School data (school_lat, school_lng, allowed_radius)
     * @return array Comprehensive validation result
     */
    public function validateSession(array $session, array $slot, array $school): array {
        $result = [
            'isValid' => true,
            'canAutoApprove' => true,
            'requiresManualReview' => false,
            'shouldAutoReject' => false,
            'overallStatus' => self::RESULT_VALID,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'validations' => [
                'startPhoto' => null,
                'endPhoto' => null,
                'duration' => null,
                'location' => null,
                'timing' => null
            ]
        ];
        
        // 1. Validate Start Photo
        $result['validations']['startPhoto'] = $this->validateStartPhoto($session, $slot, $school);
        $this->mergeValidationResult($result, $result['validations']['startPhoto']);
        
        // 2. Validate End Photo (if present)
        if ($session['end_photo_path']) {
            $result['validations']['endPhoto'] = $this->validateEndPhoto($session, $slot, $school);
            $this->mergeValidationResult($result, $result['validations']['endPhoto']);
        }
        
        // 3. Validate Duration (if both photos present)
        if ($session['start_photo_taken_at'] && $session['end_photo_taken_at']) {
            $result['validations']['duration'] = $this->validateDuration($session, $slot);
            $this->mergeValidationResult($result, $result['validations']['duration']);
        }
        
        // 4. Validate Overall Location Consistency
        $result['validations']['location'] = $this->validateLocationConsistency($session, $school);
        $this->mergeValidationResult($result, $result['validations']['location']);
        
        // 5. Validate Timing relative to slot
        $result['validations']['timing'] = $this->validateTiming($session, $slot);
        $this->mergeValidationResult($result, $result['validations']['timing']);
        
        // Determine overall status
        if ($result['shouldAutoReject']) {
            $result['overallStatus'] = self::RESULT_REJECT;
            $result['isValid'] = false;
            $result['canAutoApprove'] = false;
        } elseif ($result['requiresManualReview']) {
            $result['overallStatus'] = self::RESULT_MANUAL_REVIEW;
            $result['canAutoApprove'] = false;
        } elseif (!empty($result['warnings'])) {
            $result['overallStatus'] = self::RESULT_WARNING;
            $result['canAutoApprove'] = false;
        }
        
        return $result;
    }
    
    /**
     * Validate start photo
     */
    private function validateStartPhoto(array $session, array $slot, array $school): array {
        $result = [
            'status' => self::RESULT_VALID,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'details' => []
        ];
        
        // Check if photo exists
        if (!$session['start_photo_path']) {
            $result['status'] = self::RESULT_REJECT;
            $result['errors'][] = 'Start photo is required';
            return $result;
        }
        
        // Check GPS data
        if ($session['start_gps_latitude'] === null || $session['start_gps_longitude'] === null) {
            if (REQUIRE_GPS_FOR_APPROVAL) {
                $result['status'] = self::RESULT_MANUAL_REVIEW;
                $result['warnings'][] = 'Start photo has no GPS data - requires manual review';
            } else {
                $result['info'][] = 'Start photo has no GPS data';
            }
        } else {
            // Check distance from school
            $distance = $session['start_distance_from_school'];
            $allowedRadius = $school['allowed_radius'] ?? 500;
            
            $result['details']['distance'] = $distance;
            $result['details']['allowedRadius'] = $allowedRadius;
            
            if ($distance !== null && $distance > $allowedRadius) {
                $result['status'] = self::RESULT_REJECT;
                $result['errors'][] = "Start photo location is {$distance}m from school (max allowed: {$allowedRadius}m)";
            } elseif ($distance !== null && $distance <= AUTO_APPROVE_START_DISTANCE) {
                $result['info'][] = "Start photo location verified within {$distance}m (auto-approve threshold)";
                $result['details']['canAutoApprove'] = true;
            } elseif ($distance !== null) {
                $result['info'][] = "Start photo location is {$distance}m from school (within allowed {$allowedRadius}m)";
            }
        }
        
        // Check photo timestamp
        if ($session['start_photo_taken_at']) {
            $validation = DurationValidator::validatePhotoTiming(
                $session['start_photo_taken_at'],
                $slot['slot_date'],
                $slot['start_time'],
                $slot['end_time'],
                'start'
            );
            
            $result['details']['timing'] = $validation;
            
            if (!$validation['isValid']) {
                $result['status'] = self::RESULT_REJECT;
                $result['errors'] = array_merge($result['errors'], $validation['errors']);
            }
            if (!empty($validation['warnings'])) {
                if ($result['status'] === self::RESULT_VALID) {
                    $result['status'] = self::RESULT_WARNING;
                }
                $result['warnings'] = array_merge($result['warnings'], $validation['warnings']);
            }
        } else {
            $result['warnings'][] = 'Could not determine when start photo was taken';
            if ($result['status'] === self::RESULT_VALID) {
                $result['status'] = self::RESULT_WARNING;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate end photo
     */
    private function validateEndPhoto(array $session, array $slot, array $school): array {
        $result = [
            'status' => self::RESULT_VALID,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'details' => []
        ];
        
        // Check if photo exists
        if (!$session['end_photo_path']) {
            $result['status'] = self::RESULT_WARNING;
            $result['warnings'][] = 'End photo not yet submitted';
            return $result;
        }
        
        // Check GPS data
        if ($session['end_gps_latitude'] === null || $session['end_gps_longitude'] === null) {
            if (REQUIRE_GPS_FOR_APPROVAL) {
                $result['status'] = self::RESULT_MANUAL_REVIEW;
                $result['warnings'][] = 'End photo has no GPS data - requires manual review';
            } else {
                $result['info'][] = 'End photo has no GPS data';
            }
        } else {
            // Check distance from school
            $distance = $session['end_distance_from_school'];
            $allowedRadius = $school['allowed_radius'] ?? 500;
            
            $result['details']['distance'] = $distance;
            $result['details']['allowedRadius'] = $allowedRadius;
            
            if ($distance !== null && $distance > $allowedRadius) {
                $result['status'] = self::RESULT_REJECT;
                $result['errors'][] = "End photo location is {$distance}m from school (max allowed: {$allowedRadius}m)";
            } elseif ($distance !== null) {
                $result['info'][] = "End photo location is {$distance}m from school (within allowed {$allowedRadius}m)";
            }
        }
        
        // Check photo timestamp
        if ($session['end_photo_taken_at']) {
            $validation = DurationValidator::validatePhotoTiming(
                $session['end_photo_taken_at'],
                $slot['slot_date'],
                $slot['start_time'],
                $slot['end_time'],
                'end'
            );
            
            $result['details']['timing'] = $validation;
            
            if (!$validation['isValid']) {
                $result['status'] = self::RESULT_REJECT;
                $result['errors'] = array_merge($result['errors'], $validation['errors']);
            }
            if (!empty($validation['warnings'])) {
                if ($result['status'] === self::RESULT_VALID) {
                    $result['status'] = self::RESULT_WARNING;
                }
                $result['warnings'] = array_merge($result['warnings'], $validation['warnings']);
            }
            
            // Check end is after start
            if ($session['start_photo_taken_at']) {
                $startTime = new DateTime($session['start_photo_taken_at']);
                $endTime = new DateTime($session['end_photo_taken_at']);
                
                if ($endTime <= $startTime) {
                    $result['status'] = self::RESULT_REJECT;
                    $result['errors'][] = 'End photo timestamp must be after start photo timestamp';
                }
            }
        } else {
            $result['warnings'][] = 'Could not determine when end photo was taken';
            if ($result['status'] === self::RESULT_VALID) {
                $result['status'] = self::RESULT_WARNING;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate duration between photos
     */
    private function validateDuration(array $session, array $slot): array {
        $result = [
            'status' => self::RESULT_VALID,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'details' => []
        ];
        
        $actualMinutes = $session['actual_duration_minutes'] 
            ?? DurationValidator::calculateDuration(
                $session['start_photo_taken_at'], 
                $session['end_photo_taken_at']
            );
        
        $expectedMinutes = $session['expected_duration_minutes']
            ?? DurationValidator::calculateExpectedDuration(
                $slot['start_time'],
                $slot['end_time']
            );
        
        $result['details'] = DurationValidator::getDurationStatus($actualMinutes, $expectedMinutes);
        
        // Check for auto-reject conditions
        if ($actualMinutes < 0) {
            $result['status'] = self::RESULT_REJECT;
            $result['errors'][] = 'Invalid duration: end time is before start time';
            return $result;
        }
        
        $percentComplete = $result['details']['percentComplete'];
        
        // Auto-reject if less than 50%
        if ($percentComplete < 50) {
            $result['status'] = self::RESULT_REJECT;
            $result['errors'][] = "Duration too short: only {$percentComplete}% of expected time (minimum 50% required)";
            return $result;
        }
        
        // Warning if below minimum (default 80%)
        if (!$result['details']['meetsMinimum']) {
            $result['status'] = self::RESULT_WARNING;
            $result['warnings'][] = "Duration below minimum: {$percentComplete}% of expected time (recommended " . MIN_DURATION_PERCENT . "%)";
        }
        
        // Info about duration status
        if ($result['details']['isWithinTolerance']) {
            $result['info'][] = "Duration verified: {$result['details']['formattedActual']} (expected {$result['details']['formattedExpected']})";
        } elseif ($result['details']['difference'] > 0) {
            $result['info'][] = "Extended duration: {$result['details']['formattedActual']} (+{$result['details']['formattedDifference']} beyond expected)";
        } else {
            $result['info'][] = "Shortened duration: {$result['details']['formattedActual']} ({$result['details']['formattedDifference']} less than expected)";
        }
        
        return $result;
    }
    
    /**
     * Validate location consistency between start and end photos
     */
    private function validateLocationConsistency(array $session, array $school): array {
        $result = [
            'status' => self::RESULT_VALID,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'details' => []
        ];
        
        // Both photos must have GPS for consistency check
        if (($session['start_gps_latitude'] === null || $session['start_gps_longitude'] === null) ||
            ($session['end_gps_latitude'] === null || $session['end_gps_longitude'] === null)) {
            $result['info'][] = 'Location consistency check skipped - GPS data missing';
            return $result;
        }
        
        // Calculate distance between start and end photo locations
        $locationDrift = LocationValidator::calculateDistance(
            $session['start_gps_latitude'], $session['start_gps_longitude'],
            $session['end_gps_latitude'], $session['end_gps_longitude']
        );
        
        $result['details']['locationDrift'] = round($locationDrift, 2);
        
        // Large drift between photos is suspicious
        $maxDrift = ($school['allowed_radius'] ?? 500) * 2; // Allow 2x the school radius
        
        if ($locationDrift > $maxDrift) {
            $result['status'] = self::RESULT_WARNING;
            $result['warnings'][] = "Large location drift between photos: " . round($locationDrift) . "m (start and end photos taken at different locations)";
        } else {
            $result['info'][] = "Location consistency verified: " . round($locationDrift) . "m drift between photos";
        }
        
        return $result;
    }
    
    /**
     * Validate overall timing relative to slot
     */
    private function validateTiming(array $session, array $slot): array {
        $result = [
            'status' => self::RESULT_VALID,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'details' => []
        ];
        
        $slotDate = $slot['slot_date'];
        
        // Check start photo date
        if ($session['start_photo_taken_at']) {
            $startDate = date('Y-m-d', strtotime($session['start_photo_taken_at']));
            if ($startDate !== $slotDate) {
                $result['status'] = self::RESULT_WARNING;
                $result['warnings'][] = "Start photo date ({$startDate}) doesn't match slot date ({$slotDate})";
            }
        }
        
        // Check end photo date
        if ($session['end_photo_taken_at']) {
            $endDate = date('Y-m-d', strtotime($session['end_photo_taken_at']));
            // Allow end photo to be next day for overnight slots
            $slotEndDate = $slotDate;
            if (strtotime($slot['end_time']) < strtotime($slot['start_time'])) {
                $slotEndDate = date('Y-m-d', strtotime($slotDate . ' +1 day'));
            }
            
            if ($endDate !== $slotDate && $endDate !== $slotEndDate) {
                $result['status'] = self::RESULT_WARNING;
                $result['warnings'][] = "End photo date ({$endDate}) doesn't match slot date ({$slotDate})";
            }
        }
        
        return $result;
    }
    
    /**
     * Merge individual validation result into main result
     */
    private function mergeValidationResult(array &$main, array $validation): void {
        // Merge errors
        $main['errors'] = array_merge($main['errors'], $validation['errors'] ?? []);
        
        // Merge warnings
        $main['warnings'] = array_merge($main['warnings'], $validation['warnings'] ?? []);
        
        // Merge info
        $main['info'] = array_merge($main['info'], $validation['info'] ?? []);
        
        // Update flags based on status
        $status = $validation['status'] ?? self::RESULT_VALID;
        
        if ($status === self::RESULT_REJECT) {
            $main['shouldAutoReject'] = true;
            $main['isValid'] = false;
        } elseif ($status === self::RESULT_MANUAL_REVIEW) {
            $main['requiresManualReview'] = true;
            $main['canAutoApprove'] = false;
        } elseif ($status === self::RESULT_WARNING) {
            $main['canAutoApprove'] = false;
        }
    }
    
    /**
     * Quick check if session should be auto-rejected
     * 
     * @param array $session Session data
     * @param array $slot Slot data
     * @param array $school School data
     * @return array ['reject' => bool, 'reason' => string|null]
     */
    public function checkAutoReject(array $session, array $slot, array $school): array {
        // Check 1: Duration < 50%
        if ($session['actual_duration_minutes'] && $session['expected_duration_minutes']) {
            $percentComplete = ($session['actual_duration_minutes'] / $session['expected_duration_minutes']) * 100;
            if ($percentComplete < 50) {
                return [
                    'reject' => true,
                    'reason' => "Duration only " . round($percentComplete) . "% of expected (minimum 50% required)"
                ];
            }
        }
        
        // Check 2: Start photo way outside school
        $allowedRadius = $school['allowed_radius'] ?? 500;
        if ($session['start_distance_from_school'] !== null && 
            $session['start_distance_from_school'] > $allowedRadius * 2) { // 2x radius = definite reject
            return [
                'reject' => true,
                'reason' => "Start photo {$session['start_distance_from_school']}m from school (max allowed: {$allowedRadius}m)"
            ];
        }
        
        // Check 3: End photo way outside school
        if ($session['end_distance_from_school'] !== null && 
            $session['end_distance_from_school'] > $allowedRadius * 2) {
            return [
                'reject' => true,
                'reason' => "End photo {$session['end_distance_from_school']}m from school (max allowed: {$allowedRadius}m)"
            ];
        }
        
        // Check 4: End time before start time
        if ($session['start_photo_taken_at'] && $session['end_photo_taken_at']) {
            if (strtotime($session['end_photo_taken_at']) <= strtotime($session['start_photo_taken_at'])) {
                return [
                    'reject' => true,
                    'reason' => "End photo timestamp is before or same as start photo"
                ];
            }
        }
        
        return ['reject' => false, 'reason' => null];
    }
    
    /**
     * Check if session can be auto-approved
     * 
     * @param array $session Session data
     * @param array $slot Slot data
     * @param array $school School data
     * @return array ['approve' => bool, 'reasons' => array]
     */
    public function checkAutoApprove(array $session, array $slot, array $school): array {
        $reasons = [];
        $canApprove = true;
        
        // Must have both photos
        if (!$session['start_photo_path'] || !$session['end_photo_path']) {
            $canApprove = false;
            $reasons[] = 'Both photos required';
        }
        
        // Must have GPS on both
        if (REQUIRE_GPS_FOR_APPROVAL) {
            if ($session['start_gps_latitude'] === null || $session['end_gps_latitude'] === null) {
                $canApprove = false;
                $reasons[] = 'GPS data required on both photos';
            }
        }
        
        // Start photo within auto-approve distance
        if ($session['start_distance_from_school'] !== null && 
            $session['start_distance_from_school'] > AUTO_APPROVE_START_DISTANCE) {
            $canApprove = false;
            $reasons[] = 'Start photo beyond auto-approve distance';
        }
        
        // End photo within allowed radius
        $allowedRadius = $school['allowed_radius'] ?? 500;
        if ($session['end_distance_from_school'] !== null && 
            $session['end_distance_from_school'] > $allowedRadius) {
            $canApprove = false;
            $reasons[] = 'End photo outside allowed radius';
        }
        
        // Duration must meet minimum
        if ($session['actual_duration_minutes'] && $session['expected_duration_minutes']) {
            if (!DurationValidator::meetsMinimumDuration(
                $session['actual_duration_minutes'],
                $session['expected_duration_minutes']
            )) {
                $canApprove = false;
                $reasons[] = 'Duration below minimum threshold';
            }
        } else {
            $canApprove = false;
            $reasons[] = 'Duration cannot be calculated';
        }
        
        return [
            'approve' => $canApprove,
            'reasons' => $reasons
        ];
    }
    
    /**
     * Get validation summary as HTML for display
     * 
     * @param array $validationResult Result from validateSession()
     * @return string HTML summary
     */
    public static function getValidationSummaryHtml(array $result): string {
        $html = '<div class="validation-summary">';
        
        // Overall status badge
        $statusClass = match($result['overallStatus']) {
            self::RESULT_VALID => 'success',
            self::RESULT_WARNING => 'warning',
            self::RESULT_REJECT => 'danger',
            self::RESULT_MANUAL_REVIEW => 'info',
            default => 'secondary'
        };
        $statusText = match($result['overallStatus']) {
            self::RESULT_VALID => 'All Checks Passed',
            self::RESULT_WARNING => 'Warnings - Manual Review Recommended',
            self::RESULT_REJECT => 'Auto-Reject Conditions Met',
            self::RESULT_MANUAL_REVIEW => 'Requires Manual Review',
            default => 'Unknown'
        };
        
        $html .= "<div class='validation-status status-{$statusClass}'>";
        $html .= "<i class='bx bx-" . ($result['isValid'] ? 'check-circle' : 'x-circle') . "'></i> ";
        $html .= htmlspecialchars($statusText);
        $html .= "</div>";
        
        // Errors
        if (!empty($result['errors'])) {
            $html .= '<div class="validation-errors">';
            $html .= '<h4><i class="bx bx-x-circle"></i> Errors</h4>';
            $html .= '<ul>';
            foreach ($result['errors'] as $error) {
                $html .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            $html .= '</ul></div>';
        }
        
        // Warnings
        if (!empty($result['warnings'])) {
            $html .= '<div class="validation-warnings">';
            $html .= '<h4><i class="bx bx-error"></i> Warnings</h4>';
            $html .= '<ul>';
            foreach ($result['warnings'] as $warning) {
                $html .= '<li>' . htmlspecialchars($warning) . '</li>';
            }
            $html .= '</ul></div>';
        }
        
        // Info
        if (!empty($result['info'])) {
            $html .= '<div class="validation-info">';
            $html .= '<h4><i class="bx bx-info-circle"></i> Details</h4>';
            $html .= '<ul>';
            foreach ($result['info'] as $info) {
                $html .= '<li>' . htmlspecialchars($info) . '</li>';
            }
            $html .= '</ul></div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
