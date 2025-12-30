<?php
/**
 * Dual Photo Verification - Comprehensive Test Suite
 * Phase 7: Testing Checklist Implementation
 * 
 * Run from command line: php tests/test_dual_photo_verification.php
 */

// Change to project root
chdir(dirname(__DIR__));

// Load dependencies
require_once 'config.php';
require_once 'utils/duration_validator.php';
require_once 'utils/session_validator.php';
require_once 'utils/location_validator.php';

class TestRunner {
    private $passed = 0;
    private $failed = 0;
    private $results = [];
    
    public function assert($condition, $testName, $details = '') {
        if ($condition) {
            $this->passed++;
            $this->results[] = ['status' => 'PASS', 'name' => $testName, 'details' => $details];
            echo "✓ PASS: $testName\n";
        } else {
            $this->failed++;
            $this->results[] = ['status' => 'FAIL', 'name' => $testName, 'details' => $details];
            echo "✗ FAIL: $testName" . ($details ? " - $details" : "") . "\n";
        }
    }
    
    public function assertEquals($expected, $actual, $testName) {
        $this->assert($expected === $actual, $testName, "Expected: $expected, Got: $actual");
    }
    
    public function assertTrue($condition, $testName) {
        $this->assert($condition === true, $testName);
    }
    
    public function assertFalse($condition, $testName) {
        $this->assert($condition === false, $testName);
    }
    
    public function getSummary() {
        $total = $this->passed + $this->failed;
        return [
            'total' => $total,
            'passed' => $this->passed,
            'failed' => $this->failed,
            'percentage' => $total > 0 ? round(($this->passed / $total) * 100, 1) : 0
        ];
    }
}

// Initialize test runner
$test = new TestRunner();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     DUAL PHOTO VERIFICATION - TEST SUITE                     ║\n";
echo "║     Phase 7: Testing Checklist                               ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================
// SECTION 1: Configuration Tests
// ============================================================
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 1. Configuration Tests                                      │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";

$test->assertTrue(defined('DURATION_TOLERANCE_MINUTES'), 'DURATION_TOLERANCE_MINUTES is defined');
$test->assertTrue(defined('MIN_DURATION_PERCENT'), 'MIN_DURATION_PERCENT is defined');
$test->assertTrue(defined('AUTO_APPROVE_START_DISTANCE'), 'AUTO_APPROVE_START_DISTANCE is defined');
$test->assertTrue(defined('MAX_TIME_BEFORE_SLOT_START'), 'MAX_TIME_BEFORE_SLOT_START is defined');
$test->assertTrue(defined('MAX_TIME_AFTER_SLOT_END'), 'MAX_TIME_AFTER_SLOT_END is defined');
$test->assertTrue(defined('REQUIRE_GPS_FOR_APPROVAL'), 'REQUIRE_GPS_FOR_APPROVAL is defined');

$test->assertEquals(15, DURATION_TOLERANCE_MINUTES, 'DURATION_TOLERANCE_MINUTES = 15');
$test->assertEquals(80, MIN_DURATION_PERCENT, 'MIN_DURATION_PERCENT = 80');
$test->assertEquals(100, AUTO_APPROVE_START_DISTANCE, 'AUTO_APPROVE_START_DISTANCE = 100');

echo "\n";

// ============================================================
// SECTION 2: Duration Validator Tests
// ============================================================
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 2. Duration Validator Tests                                 │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";

// Test calculateDuration
$duration = DurationValidator::calculateDuration('2025-12-30 09:00:00', '2025-12-30 11:00:00');
$test->assertEquals(120, $duration, 'Calculate 2-hour duration = 120 minutes');

$duration = DurationValidator::calculateDuration('2025-12-30 09:00:00', '2025-12-30 09:30:00');
$test->assertEquals(30, $duration, 'Calculate 30-minute duration');

// Negative duration (end before start)
$duration = DurationValidator::calculateDuration('2025-12-30 11:00:00', '2025-12-30 09:00:00');
$test->assertTrue($duration < 0, 'Negative duration when end before start');

// Test calculateExpectedDuration
$expected = DurationValidator::calculateExpectedDuration('09:00:00', '11:00:00');
$test->assertEquals(120, $expected, 'Expected duration 09:00-11:00 = 120 minutes');

$expected = DurationValidator::calculateExpectedDuration('09:00', '10:30');
$test->assertEquals(90, $expected, 'Expected duration 09:00-10:30 = 90 minutes');

// Test verifyDuration (within tolerance)
$test->assertTrue(
    DurationValidator::verifyDuration(120, 120), 
    'Exact match duration is verified'
);

$test->assertTrue(
    DurationValidator::verifyDuration(110, 120), 
    'Duration 10 min short is within tolerance (15 min)'
);

$test->assertTrue(
    DurationValidator::verifyDuration(130, 120), 
    'Duration 10 min over is within tolerance'
);

$test->assertFalse(
    DurationValidator::verifyDuration(100, 120), 
    'Duration 20 min short is outside tolerance'
);

// Test meetsMinimumDuration (80% threshold)
$test->assertTrue(
    DurationValidator::meetsMinimumDuration(96, 120), 
    '96/120 = 80% meets minimum'
);

$test->assertTrue(
    DurationValidator::meetsMinimumDuration(100, 120), 
    '100/120 = 83% meets minimum'
);

$test->assertFalse(
    DurationValidator::meetsMinimumDuration(90, 120), 
    '90/120 = 75% below minimum'
);

// Test getDurationStatus
$status = DurationValidator::getDurationStatus(59, 120);
$test->assertEquals('rejected', $status['status'], 'Duration <50% (49%) = rejected');

$status = DurationValidator::getDurationStatus(60, 120);
$test->assertEquals('warning', $status['status'], 'Duration =50% = warning (boundary)');

$status = DurationValidator::getDurationStatus(90, 120);
$test->assertEquals('warning', $status['status'], 'Duration 75% = warning');

$status = DurationValidator::getDurationStatus(115, 120);
$test->assertEquals('verified', $status['status'], 'Duration within tolerance = verified');

$status = DurationValidator::getDurationStatus(150, 120);
$test->assertEquals('extended', $status['status'], 'Duration significantly over = extended');

// Test formatDuration
$test->assertEquals('2h', DurationValidator::formatDuration(120), 'Format 120 min = 2h');
$test->assertEquals('1h 30m', DurationValidator::formatDuration(90), 'Format 90 min = 1h 30m');
$test->assertEquals('45m', DurationValidator::formatDuration(45), 'Format 45 min = 45m');

echo "\n";

// ============================================================
// SECTION 3: Location Validator Tests
// ============================================================
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 3. Location Validator Tests                                 │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";

// Test calculateDistance (Haversine formula)
// Using known coordinates: approximately 1km apart
$lat1 = 12.9716; // Bangalore
$lng1 = 77.5946;
$lat2 = 12.9806; // ~1km north
$lng2 = 77.5946;

$distance = LocationValidator::calculateDistance($lat1, $lng1, $lat2, $lng2);
$test->assertTrue(
    $distance >= 900 && $distance <= 1100, 
    "Distance calculation ~1km (got: " . round($distance) . "m)"
);

// Same location = 0 distance
$distance = LocationValidator::calculateDistance($lat1, $lng1, $lat1, $lng1);
$test->assertTrue($distance < 1, 'Same location = 0 distance');

// Test within radius check
$distance = LocationValidator::calculateDistance(12.9716, 77.5946, 12.9718, 77.5948);
$test->assertTrue($distance < 500, 'Nearby points within 500m');

echo "\n";

// ============================================================
// SECTION 4: Session Validator Tests
// ============================================================
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 4. Session Validator Tests                                  │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";

$validator = new SessionValidator($conn);

// Mock session data for testing
$mockSession = [
    'start_photo_path' => 'uploads/test_start.jpg',
    'start_photo_taken_at' => '2025-12-30 09:00:00',
    'start_gps_latitude' => 12.9716,
    'start_gps_longitude' => 77.5946,
    'start_distance_from_school' => 50,
    'end_photo_path' => 'uploads/test_end.jpg',
    'end_photo_taken_at' => '2025-12-30 11:00:00',
    'end_gps_latitude' => 12.9716,
    'end_gps_longitude' => 77.5946,
    'end_distance_from_school' => 50,
    'actual_duration_minutes' => 120,
    'expected_duration_minutes' => 120
];

$mockSlot = [
    'slot_date' => '2025-12-30',
    'start_time' => '09:00:00',
    'end_time' => '11:00:00'
];

$mockSchool = [
    'school_lat' => 12.9716,
    'school_lng' => 77.5946,
    'allowed_radius' => 500
];

// Test valid session
$result = $validator->validateSession($mockSession, $mockSlot, $mockSchool);
$test->assertTrue($result['isValid'], 'Valid session passes validation');
$test->assertFalse($result['shouldAutoReject'], 'Valid session not auto-rejected');

// Test auto-reject for short duration (<50%)
$shortSession = $mockSession;
$shortSession['actual_duration_minutes'] = 50;
$shortSession['end_photo_taken_at'] = '2025-12-30 09:50:00';

$autoReject = $validator->checkAutoReject($shortSession, $mockSlot, $mockSchool);
$test->assertTrue($autoReject['reject'], 'Duration <50% triggers auto-reject');

// Test auto-reject for invalid timestamps
$invalidSession = $mockSession;
$invalidSession['end_photo_taken_at'] = '2025-12-30 08:30:00'; // Before start
$invalidSession['actual_duration_minutes'] = -30;

$autoReject = $validator->checkAutoReject($invalidSession, $mockSlot, $mockSchool);
$test->assertTrue($autoReject['reject'], 'End before start triggers auto-reject');

// Test auto-reject for distance too far
$farSession = $mockSession;
$farSession['start_distance_from_school'] = 1200; // Way outside radius

$autoReject = $validator->checkAutoReject($farSession, $mockSlot, $mockSchool);
$test->assertTrue($autoReject['reject'], 'Distance >2x radius triggers auto-reject');

// Test auto-approve check
$autoApprove = $validator->checkAutoApprove($mockSession, $mockSlot, $mockSchool);
$test->assertTrue($autoApprove['approve'], 'Valid session eligible for auto-approve');

// Test no auto-approve without GPS
$noGpsSession = $mockSession;
$noGpsSession['start_gps_latitude'] = null;
$noGpsSession['start_gps_longitude'] = null;

$autoApprove = $validator->checkAutoApprove($noGpsSession, $mockSlot, $mockSchool);
$test->assertFalse($autoApprove['approve'], 'No GPS prevents auto-approve');

echo "\n";

// ============================================================
// SECTION 5: Photo Timing Validation Tests
// ============================================================
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 5. Photo Timing Validation Tests                            │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";

// Valid start photo timing
$timing = DurationValidator::validatePhotoTiming(
    '2025-12-30 08:50:00', // 10 min before slot
    '2025-12-30',
    '09:00:00',
    '11:00:00',
    'start'
);
$test->assertTrue($timing['isValid'], 'Start photo 10 min early is valid');
$test->assertTrue(empty($timing['errors']), 'No errors for valid start timing');

// Start photo too early (>60 min before)
$timing = DurationValidator::validatePhotoTiming(
    '2025-12-30 07:30:00', // 90 min before
    '2025-12-30',
    '09:00:00',
    '11:00:00',
    'start'
);
$test->assertTrue(!empty($timing['warnings']), 'Warning for start photo >60 min early');

// End photo after slot end
$timing = DurationValidator::validatePhotoTiming(
    '2025-12-30 11:30:00', // 30 min after end
    '2025-12-30',
    '09:00:00',
    '11:00:00',
    'end'
);
$test->assertTrue($timing['isValid'], 'End photo 30 min after slot is valid');

// End photo way too late
$timing = DurationValidator::validatePhotoTiming(
    '2025-12-30 14:00:00', // 3 hours after
    '2025-12-30',
    '09:00:00',
    '11:00:00',
    'end'
);
$test->assertTrue(!empty($timing['warnings']), 'Warning for end photo >2 hours late');

// Photo on wrong date
$timing = DurationValidator::validatePhotoTiming(
    '2025-12-29 09:00:00', // Day before
    '2025-12-30',
    '09:00:00',
    '11:00:00',
    'start'
);
$test->assertTrue(!empty($timing['warnings']), 'Warning for photo on wrong date');

echo "\n";

// ============================================================
// SECTION 6: Database Schema Tests
// ============================================================
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 6. Database Schema Tests                                    │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";

// Check if teaching_sessions table has required columns
$result = mysqli_query($conn, "DESCRIBE teaching_sessions");
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row['Field'];
}

$requiredColumns = [
    'start_photo_path',
    'start_gps_latitude',
    'start_gps_longitude',
    'start_photo_taken_at',
    'start_distance_from_school',
    'end_photo_path',
    'end_gps_latitude',
    'end_gps_longitude',
    'end_photo_taken_at',
    'end_distance_from_school',
    'actual_duration_minutes',
    'expected_duration_minutes',
    'duration_verified',
    'session_status'
];

foreach ($requiredColumns as $col) {
    $test->assertTrue(in_array($col, $columns), "Column '$col' exists in teaching_sessions");
}

// Check session_status ENUM values
$result = mysqli_query($conn, "SHOW COLUMNS FROM teaching_sessions WHERE Field = 'session_status'");
$row = mysqli_fetch_assoc($result);
$enumValues = $row['Type'] ?? '';

$test->assertTrue(strpos($enumValues, 'start_submitted') !== false, "ENUM has 'start_submitted'");
$test->assertTrue(strpos($enumValues, 'start_approved') !== false, "ENUM has 'start_approved'");
$test->assertTrue(strpos($enumValues, 'end_submitted') !== false, "ENUM has 'end_submitted'");
$test->assertTrue(strpos($enumValues, 'approved') !== false, "ENUM has 'approved'");
$test->assertTrue(strpos($enumValues, 'rejected') !== false, "ENUM has 'rejected'");

echo "\n";

// ============================================================
// SECTION 7: Edge Case Tests
// ============================================================
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 7. Edge Case Tests                                          │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";

// Both photos same minute (too fast - should warn)
$fastSession = $mockSession;
$fastSession['start_photo_taken_at'] = '2025-12-30 09:00:00';
$fastSession['end_photo_taken_at'] = '2025-12-30 09:01:00';
$fastSession['actual_duration_minutes'] = 1;

$autoReject = $validator->checkAutoReject($fastSession, $mockSlot, $mockSchool);
$test->assertTrue($autoReject['reject'], 'Photos 1 minute apart triggers auto-reject');

// End photo next day
$nextDaySession = $mockSession;
$nextDaySession['end_photo_taken_at'] = '2025-12-31 09:00:00';

$result = $validator->validateSession($nextDaySession, $mockSlot, $mockSchool);
$test->assertTrue(!empty($result['warnings']), 'End photo next day generates warning');

// Zero expected duration
$zeroDurationSlot = $mockSlot;
$zeroDurationSlot['start_time'] = '09:00:00';
$zeroDurationSlot['end_time'] = '09:00:00';

$test->assertFalse(
    DurationValidator::meetsMinimumDuration(60, 0),
    'Zero expected duration returns false'
);

// Overnight slot handling
$overnightDuration = DurationValidator::calculateExpectedDuration('22:00:00', '02:00:00');
$test->assertEquals(240, $overnightDuration, 'Overnight slot 22:00-02:00 = 4 hours');

echo "\n";

// ============================================================
// SECTION 8: File Existence Tests
// ============================================================
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 8. File Existence Tests                                     │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";

$requiredFiles = [
    'utils/duration_validator.php' => 'Duration Validator utility',
    'utils/session_validator.php' => 'Session Validator utility',
    'utils/location_validator.php' => 'Location Validator utility',
    'admin/teacher_stats.php' => 'Teacher Statistics page',
    'admin/teacher_detail.php' => 'Teacher Detail page',
    'admin/review_session.php' => 'Session Review page',
    'admin/pending_sessions.php' => 'Pending Sessions page',
    'teachers/view_session.php' => 'Teacher View Session page',
    'teachers/my_slots.php' => 'Teacher My Slots page',
    'db/migrate_dual_photo_verification.sql' => 'Migration script',
    'db/rollback_dual_photo_verification.sql' => 'Rollback script'
];

foreach ($requiredFiles as $file => $description) {
    $test->assertTrue(file_exists($file), "$description ($file) exists");
}

echo "\n";

// ============================================================
// SUMMARY
// ============================================================
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    TEST SUMMARY                              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

$summary = $test->getSummary();
echo "\n";
echo "Total Tests:  {$summary['total']}\n";
echo "Passed:       {$summary['passed']} ✓\n";
echo "Failed:       {$summary['failed']} ✗\n";
echo "Pass Rate:    {$summary['percentage']}%\n";
echo "\n";

if ($summary['failed'] === 0) {
    echo "🎉 ALL TESTS PASSED! Dual Photo Verification is ready.\n";
} else {
    echo "⚠️  Some tests failed. Please review and fix issues.\n";
}

echo "\n";

// Return exit code for CI/CD
exit($summary['failed'] > 0 ? 1 : 0);
