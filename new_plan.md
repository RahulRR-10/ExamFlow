# Dual Photo Verification System - Implementation Plan

> **Feature**: Start & End Photo Verification for Teaching Sessions  
> **Status**: 📋 Planning  
> **Created**: December 30, 2025  
> **Estimated Phases**: 6

---

## Executive Summary

### Current System
Currently, volunteers (teachers) follow this workflow:
1. Admin creates teaching slots at partner schools
2. Teacher books a slot
3. On the day of teaching, teacher uploads **one** geotagged photo at the school
4. System extracts EXIF metadata (GPS coordinates, timestamp)
5. System calculates distance from school location
6. If within allowed radius (default 500m), admin reviews and approves
7. Session marked as "completed"

### Problem
A volunteer could:
- Arrive at the school location
- Upload the initial photo to get approved
- Leave without actually teaching the session
- There's no verification that they stayed for the full duration

### Proposed Solution
Implement **Dual Photo Verification**:
1. **Start Photo** - Taken when teacher arrives (before teaching)
2. **End Photo** - Taken when teaching is complete (after teaching)
3. Both photos must be geotagged and within school proximity
4. Time difference between photos must match slot duration (±tolerance)
5. Only then is the session considered "fulfilled"

Additionally, add an **Admin Teacher Stats Dashboard** showing:
- List of all teachers
- Number of slots taught (completed sessions)
- Completion rates and other metrics

---

## Current Database Schema Reference

### Relevant Tables

```sql
-- Current teaching_sessions table structure:
CREATE TABLE teaching_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    enrollment_id INT NOT NULL,
    slot_id INT NOT NULL,
    teacher_id INT NOT NULL,
    school_id INT NOT NULL,
    session_date DATE NOT NULL,
    
    -- Current single photo proof fields
    photo_path VARCHAR(500) NULL,
    photo_uploaded_at TIMESTAMP NULL,
    gps_latitude DECIMAL(10, 8) NULL,
    gps_longitude DECIMAL(11, 8) NULL,
    photo_taken_at DATETIME NULL,
    distance_from_school DECIMAL(10, 2) NULL,
    
    -- Session status
    session_status ENUM('pending', 'photo_submitted', 'approved', 'rejected'),
    
    -- Admin verification
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    admin_remarks TEXT NULL,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Slot information (for duration)
CREATE TABLE school_teaching_slots (
    slot_id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT NOT NULL,
    slot_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    -- ... other fields
);
```

---

## Phase 1: Database Schema Updates

### Objective
Extend the `teaching_sessions` table to support dual photo verification.

### 1.1 New Migration Script

**File**: `db/migrate_dual_photo_verification.sql`

```sql
-- =====================================================
-- Dual Photo Verification Migration Script
-- Adds support for start and end photo verification
-- Date: December 2025
-- =====================================================

-- Rename existing photo fields to represent "start" photo
ALTER TABLE teaching_sessions 
    CHANGE COLUMN photo_path start_photo_path VARCHAR(500) NULL,
    CHANGE COLUMN photo_uploaded_at start_photo_uploaded_at TIMESTAMP NULL,
    CHANGE COLUMN gps_latitude start_gps_latitude DECIMAL(10, 8) NULL,
    CHANGE COLUMN gps_longitude start_gps_longitude DECIMAL(11, 8) NULL,
    CHANGE COLUMN photo_taken_at start_photo_taken_at DATETIME NULL,
    CHANGE COLUMN distance_from_school start_distance_from_school DECIMAL(10, 2) NULL;

-- Add end photo fields
ALTER TABLE teaching_sessions
    ADD COLUMN end_photo_path VARCHAR(500) NULL AFTER start_distance_from_school,
    ADD COLUMN end_photo_uploaded_at TIMESTAMP NULL AFTER end_photo_path,
    ADD COLUMN end_gps_latitude DECIMAL(10, 8) NULL AFTER end_photo_uploaded_at,
    ADD COLUMN end_gps_longitude DECIMAL(11, 8) NULL AFTER end_gps_latitude,
    ADD COLUMN end_photo_taken_at DATETIME NULL AFTER end_gps_longitude,
    ADD COLUMN end_distance_from_school DECIMAL(10, 2) NULL AFTER end_photo_taken_at;

-- Add duration verification fields
ALTER TABLE teaching_sessions
    ADD COLUMN actual_duration_minutes INT NULL AFTER end_distance_from_school,
    ADD COLUMN expected_duration_minutes INT NULL AFTER actual_duration_minutes,
    ADD COLUMN duration_verified BOOLEAN DEFAULT FALSE AFTER expected_duration_minutes;

-- Update session_status ENUM to include new states
ALTER TABLE teaching_sessions 
    MODIFY COLUMN session_status ENUM(
        'pending',           -- No photos uploaded yet
        'start_submitted',   -- Start photo uploaded, awaiting end photo
        'start_approved',    -- Start photo verified by admin, awaiting end photo
        'end_submitted',     -- Both photos uploaded, awaiting final review
        'approved',          -- Fully verified and approved
        'rejected',          -- Rejected at any stage
        'partial'            -- Start approved but end photo missing/invalid
    ) DEFAULT 'pending';

-- Add indexes for new columns
CREATE INDEX idx_session_start_photo ON teaching_sessions(start_photo_taken_at);
CREATE INDEX idx_session_end_photo ON teaching_sessions(end_photo_taken_at);
CREATE INDEX idx_session_duration ON teaching_sessions(duration_verified);

-- Add teacher statistics view (optional, for performance)
CREATE OR REPLACE VIEW teacher_session_stats AS
SELECT 
    t.id as teacher_id,
    t.fname as teacher_name,
    t.email,
    t.subject,
    COUNT(DISTINCT ts.session_id) as total_sessions,
    SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) as completed_sessions,
    SUM(CASE WHEN ts.session_status = 'rejected' THEN 1 ELSE 0 END) as rejected_sessions,
    SUM(CASE WHEN ts.session_status IN ('start_submitted', 'start_approved', 'end_submitted') THEN 1 ELSE 0 END) as pending_sessions,
    ROUND(
        SUM(CASE WHEN ts.session_status = 'approved' THEN 1 ELSE 0 END) * 100.0 / 
        NULLIF(COUNT(DISTINCT ts.session_id), 0), 
        2
    ) as completion_rate,
    SUM(CASE WHEN ts.session_status = 'approved' THEN ts.actual_duration_minutes ELSE 0 END) as total_teaching_minutes
FROM teacher t
LEFT JOIN teaching_sessions ts ON t.id = ts.teacher_id
GROUP BY t.id, t.fname, t.email, t.subject;

SELECT 'Dual Photo Verification Migration Complete!' as status;
```

### 1.2 Rollback Script

**File**: `db/rollback_dual_photo_verification.sql`

```sql
-- Rollback script (use with caution)
-- This will revert to single photo verification

-- Note: Data in end_photo fields will be lost!

ALTER TABLE teaching_sessions
    DROP COLUMN IF EXISTS end_photo_path,
    DROP COLUMN IF EXISTS end_photo_uploaded_at,
    DROP COLUMN IF EXISTS end_gps_latitude,
    DROP COLUMN IF EXISTS end_gps_longitude,
    DROP COLUMN IF EXISTS end_photo_taken_at,
    DROP COLUMN IF EXISTS end_distance_from_school,
    DROP COLUMN IF EXISTS actual_duration_minutes,
    DROP COLUMN IF EXISTS expected_duration_minutes,
    DROP COLUMN IF EXISTS duration_verified;

-- Rename back to original
ALTER TABLE teaching_sessions 
    CHANGE COLUMN start_photo_path photo_path VARCHAR(500) NULL,
    CHANGE COLUMN start_photo_uploaded_at photo_uploaded_at TIMESTAMP NULL,
    CHANGE COLUMN start_gps_latitude gps_latitude DECIMAL(10, 8) NULL,
    CHANGE COLUMN start_gps_longitude gps_longitude DECIMAL(11, 8) NULL,
    CHANGE COLUMN start_photo_taken_at photo_taken_at DATETIME NULL,
    CHANGE COLUMN start_distance_from_school distance_from_school DECIMAL(10, 2) NULL;

-- Revert ENUM
ALTER TABLE teaching_sessions 
    MODIFY COLUMN session_status ENUM(
        'pending', 'photo_submitted', 'approved', 'rejected'
    ) DEFAULT 'pending';

DROP VIEW IF EXISTS teacher_session_stats;
```

---

## Phase 2: Update Teacher Portal - Photo Upload

### Objective
Modify the teacher's photo upload interface to support start and end photos.

### 2.1 Update `teachers/upload_session_photo.php`

**Current Flow**:
- Single upload form
- Uploads one photo
- Session status → `photo_submitted`

**New Flow**:
1. Check session status to determine which photo to upload
2. If `pending` → Show "Upload Start Photo" form
3. If `start_submitted` or `start_approved` → Show "Upload End Photo" form
4. Validate each photo independently
5. Calculate and store duration when end photo is uploaded

**Key Changes**:

```php
// Pseudo-code for new logic

// Determine upload type based on session status
if ($session['session_status'] === 'pending') {
    $upload_type = 'start';
    $page_title = 'Upload Arrival Photo';
    $instructions = 'Take a photo when you arrive at the school to start teaching.';
} elseif (in_array($session['session_status'], ['start_submitted', 'start_approved'])) {
    $upload_type = 'end';
    $page_title = 'Upload Completion Photo';
    $instructions = 'Take a photo after you finish teaching to complete verification.';
} else {
    // Session already completed or rejected
    redirect_to_view_session();
}

// On photo submission
if ($_POST) {
    // Extract EXIF data (existing code)
    $gps_data = ExifExtractor::extractGPS($filepath);
    $timestamp = ExifExtractor::extractTimestamp($filepath);
    
    // Validate proximity (existing code)
    $distance = LocationValidator::calculateDistance(...);
    
    if ($upload_type === 'start') {
        // Update start photo fields
        $sql = "UPDATE teaching_sessions SET 
                start_photo_path = ?,
                start_photo_uploaded_at = NOW(),
                start_gps_latitude = ?,
                start_gps_longitude = ?,
                start_photo_taken_at = ?,
                start_distance_from_school = ?,
                session_status = 'start_submitted'
                WHERE session_id = ?";
    } else {
        // Update end photo fields and calculate duration
        $start_time = strtotime($session['start_photo_taken_at']);
        $end_time = strtotime($photo_taken_at);
        $actual_duration = round(($end_time - $start_time) / 60); // minutes
        
        // Get expected duration from slot
        $expected_duration = calculate_slot_duration($session['start_time'], $session['end_time']);
        
        // Check if duration is reasonable (within tolerance)
        $duration_tolerance = 15; // 15 minutes tolerance
        $duration_verified = abs($actual_duration - $expected_duration) <= $duration_tolerance;
        
        $sql = "UPDATE teaching_sessions SET 
                end_photo_path = ?,
                end_photo_uploaded_at = NOW(),
                end_gps_latitude = ?,
                end_gps_longitude = ?,
                end_photo_taken_at = ?,
                end_distance_from_school = ?,
                actual_duration_minutes = ?,
                expected_duration_minutes = ?,
                duration_verified = ?,
                session_status = 'end_submitted'
                WHERE session_id = ?";
    }
}
```

### 2.2 Create New File: `teachers/upload_end_photo.php` (Optional)

Alternatively, keep a single file but with conditional rendering, or create separate files:
- `teachers/upload_start_photo.php` - For arrival photo
- `teachers/upload_end_photo.php` - For completion photo

**Recommendation**: Keep single file (`upload_session_photo.php`) with conditional logic for maintainability.

### 2.3 Update `teachers/view_session.php`

**Changes**:
- Display both start and end photos
- Show status progression (timeline view)
- Show duration information
- Different action buttons based on current state

**UI Enhancement**:
```
Timeline View:
[✓] Slot Booked → [✓] Arrived (Start Photo) → [○] Teaching → [○] Completed (End Photo) → [○] Verified
```

### 2.4 Update `teachers/my_slots.php`

**Changes**:
- Show more granular status for each booking
- Display "Upload Start Photo" vs "Upload End Photo" buttons appropriately
- Show duration info for completed sessions

---

## Phase 3: Update Admin Portal - Review System

### Objective
Enable admins to review both photos and verify duration compliance.

### 3.1 Update `admin/review_session.php`

**Current Flow**:
- Shows single photo
- Shows distance from school
- Approve/Reject buttons

**New Flow**:
1. Show both photos side-by-side (or in tabs)
2. Display map with both photo locations and school marker
3. Show timing information:
   - Start photo timestamp
   - End photo timestamp
   - Actual duration
   - Expected duration (from slot)
   - Duration match status
4. Show distance verification for both photos
5. Provide detailed approval/rejection options

**UI Layout**:
```
┌─────────────────────────────────────────────────────────────┐
│                    Session Review #123                       │
├─────────────────────────────────────────────────────────────┤
│  Teacher: John Doe | School: ABC School | Date: 2025-12-30 │
├───────────────────────────┬─────────────────────────────────┤
│    START PHOTO            │         END PHOTO               │
│  ┌─────────────────┐      │    ┌─────────────────┐          │
│  │                 │      │    │                 │          │
│  │  [Photo Image]  │      │    │  [Photo Image]  │          │
│  │                 │      │    │                 │          │
│  └─────────────────┘      │    └─────────────────┘          │
│  📍 Distance: 45m ✓       │    📍 Distance: 52m ✓           │
│  🕐 Time: 09:02 AM        │    🕐 Time: 11:58 AM            │
├───────────────────────────┴─────────────────────────────────┤
│                    DURATION VERIFICATION                     │
│  Expected: 3 hours (09:00 AM - 12:00 PM)                    │
│  Actual: 2h 56m (09:02 AM - 11:58 AM)                       │
│  Status: ✓ VERIFIED (within 15 min tolerance)               │
├─────────────────────────────────────────────────────────────┤
│                         MAP VIEW                             │
│  [Interactive map showing school location and both          │
│   photo locations with markers]                              │
├─────────────────────────────────────────────────────────────┤
│  [✓ Approve Session]  [✗ Reject Session]  [Request Resubmit]│
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Update `admin/pending_sessions.php`

**Changes**:
- Add filter for new statuses (`start_submitted`, `end_submitted`, etc.)
- Show different queue tabs:
  - "Start Photos Pending" - Sessions with start photo awaiting initial review
  - "End Photos Pending" - Sessions with both photos awaiting final review
  - "All Pending" - Combined view
- Show duration compliance indicator in list view
- Bulk approval only for sessions that pass all checks

### 3.3 Update Status Flow

**New Status Workflow**:

```
pending → start_submitted → [Admin Reviews] → start_approved → end_submitted → [Admin Reviews] → approved
                                    ↓                                                    ↓
                                rejected                                             rejected
                                    ↓                                                    ↓
                          [Teacher can resubmit]                              [Teacher can resubmit end photo]
```

**Alternative Simplified Flow** (Auto-approve start if location valid):

```
pending → start_submitted → [Auto-check location] → start_approved (auto) → end_submitted → [Admin Reviews] → approved
                                    ↓                                                                ↓
                         [Manual review if location suspicious]                                  rejected
```

---

## Phase 4: Admin Dashboard - Teacher Stats

### Objective
Create a new admin page showing all teachers with their teaching statistics.

### 4.1 Create `admin/teacher_stats.php`

**Features**:
- List all teachers who have booked at least one slot
- Show for each teacher:
  - Name, Email, Subject
  - Total slots booked
  - Completed sessions (approved)
  - Rejected sessions
  - Pending sessions
  - Completion rate (%)
  - Total teaching hours
  - Average duration compliance
- Sorting by any column
- Filtering by school, date range
- Export to CSV/Excel

**UI Layout**:
```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         TEACHER TEACHING STATISTICS                              │
├─────────────────────────────────────────────────────────────────────────────────┤
│ Filters: [School ▼] [Date From] [Date To] [Subject ▼]        [Export CSV]       │
├───────┬──────────┬─────────┬───────────┬──────────┬──────────┬─────────┬────────┤
│ Name  │ Email    │ Subject │ Total     │ Completed│ Rejected │ Pending │ Rate % │
│       │          │         │ Slots     │ Sessions │ Sessions │         │        │
├───────┼──────────┼─────────┼───────────┼──────────┼──────────┼─────────┼────────┤
│ John  │j@...     │ Math    │    15     │    12    │    1     │    2    │  80%   │
│ Jane  │ja@...    │ Science │    22     │    20    │    0     │    2    │  91%   │
│ Bob   │b@...     │ English │     8     │     5    │    2     │    1    │  63%   │
├───────┴──────────┴─────────┴───────────┴──────────┴──────────┴─────────┴────────┤
│                              [View Details]                                      │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Create `admin/teacher_detail.php`

**Drill-down view for individual teacher**:
- Complete session history
- Session-by-session breakdown
- Photos from each session (thumbnails)
- Duration compliance history
- Schools taught at

### 4.3 Update Admin Navigation

Add "Teacher Stats" to admin sidebar:
```php
<li><a href="teacher_stats.php"><i class="icon"></i> Teacher Statistics</a></li>
```

### 4.4 Dashboard Widget

Add a summary widget to `admin/dash.php`:
```
┌──────────────────────────────┐
│     TOP TEACHERS (Month)     │
├──────────────────────────────┤
│ 1. Jane D. - 20 sessions     │
│ 2. John S. - 15 sessions     │
│ 3. Bob K.  - 12 sessions     │
├──────────────────────────────┤
│ [View All Teachers →]        │
└──────────────────────────────┘
```

---

## Phase 5: Validation Logic & Business Rules

### 5.1 Duration Validation

**Rules**:
1. End photo timestamp must be AFTER start photo timestamp
2. Actual duration = end_photo_taken_at - start_photo_taken_at
3. Expected duration = slot_end_time - slot_start_time
4. Duration is "verified" if: `|actual - expected| <= tolerance`
5. Default tolerance: 15 minutes (configurable)

**Edge Cases**:
- Photo taken before slot start time → Warning, not auto-reject
- Photo taken after slot end time → Allow (teacher stayed late)
- Duration significantly shorter → Reject or flag
- Duration significantly longer → Allow (teacher stayed extra)

**Configuration** (add to `.env` or settings):
```env
# Duration verification settings
DURATION_TOLERANCE_MINUTES=15
MIN_ACCEPTABLE_DURATION_PERCENT=80  # Must complete at least 80% of slot time
AUTO_APPROVE_LOCATION_THRESHOLD=100  # Auto-approve if within 100m
```

### 5.2 Location Validation (Both Photos)

**Rules for each photo**:
1. GPS coordinates must be present
2. Distance from school <= allowed_radius (default 500m)
3. If start photo location valid but end photo different location → Flag for review

**Formula** (already implemented in `LocationValidator`):
```php
$distance = LocationValidator::calculateDistance(
    $photo_lat, $photo_lng,
    $school_lat, $school_lng
);
```

### 5.3 Timestamp Validation

**Rules**:
1. Start photo date must match slot date (or be within acceptable range)
2. End photo date must match slot date
3. Start photo time should be around slot start time (±30 min)
4. End photo time should be around or after slot end time

**Warnings vs Rejections**:
| Condition | Action |
|-----------|--------|
| Photo date ≠ slot date | Warning (admin review) |
| Start photo > 1 hour before slot | Warning |
| End photo > 2 hours after slot end | Warning |
| No GPS data | Warning (require manual review) |
| Distance > allowed radius | Reject |
| Duration < 50% of expected | Auto-reject |

---

## Phase 6: Files to Create/Modify

### New Files

| File | Description |
|------|-------------|
| `db/migrate_dual_photo_verification.sql` | Database migration script |
| `db/rollback_dual_photo_verification.sql` | Rollback script |
| `admin/teacher_stats.php` | Teacher statistics dashboard |
| `admin/teacher_detail.php` | Individual teacher detail view |
| `utils/duration_validator.php` | Duration validation helper class |

### Modified Files

| File | Changes |
|------|---------|
| `teachers/upload_session_photo.php` | Support dual photo upload, conditional forms |
| `teachers/view_session.php` | Display both photos, timeline, duration |
| `teachers/my_slots.php` | Show new statuses, appropriate action buttons |
| `admin/review_session.php` | Dual photo display, duration verification UI |
| `admin/pending_sessions.php` | New status filters, tabs |
| `admin/dash.php` | Add teacher stats widget |
| `admin/includes/nav.php` | Add Teacher Stats menu item |

---

## Phase 7: Testing Checklist

### Functional Tests

- [ ] Upload start photo with valid GPS → Status changes to `start_submitted`
- [ ] Upload start photo without GPS → Warning shown, still uploads
- [ ] Upload start photo too far from school → Error or warning
- [ ] Upload end photo after start approved → Status changes to `end_submitted`
- [ ] Upload end photo before start photo time → Error (invalid)
- [ ] Duration within tolerance → `duration_verified = true`
- [ ] Duration too short → Warning/rejection
- [ ] Admin approve start photo → Status changes to `start_approved`
- [ ] Admin reject start photo → Status changes to `rejected`
- [ ] Admin approve end photo → Status changes to `approved`
- [ ] Admin reject end photo → Status allows resubmit of end photo
- [ ] Teacher stats page shows correct counts
- [ ] Export CSV works correctly
- [ ] Existing single-photo sessions still work (backward compatibility)

### Edge Case Tests

- [ ] Teacher uploads both photos same minute (too fast)
- [ ] Teacher uploads end photo next day
- [ ] Photos have no EXIF data at all
- [ ] School has no GPS coordinates configured
- [ ] Teacher cancels slot after uploading start photo
- [ ] Admin changes slot times after booking

### UI Tests

- [ ] Mobile-friendly photo upload
- [ ] Map shows both locations correctly
- [ ] Timeline displays properly
- [ ] Status badges show correct colors
- [ ] Bulk actions work in pending queue

---

## Implementation Order

### Sprint 1 (Database & Backend Core)
1. Create and run database migration
2. Update `teaching_sessions` model/queries
3. Create `duration_validator.php` utility
4. Update `upload_session_photo.php` for dual upload

### Sprint 2 (Teacher Portal UI)
1. Update `view_session.php` with dual photo display
2. Update `my_slots.php` with new statuses
3. Add timeline component
4. Test teacher workflow end-to-end

### Sprint 3 (Admin Portal - Review)
1. Update `review_session.php` with dual photo review
2. Update `pending_sessions.php` with new filters
3. Update approval/rejection logic
4. Test admin workflow

### Sprint 4 (Admin Portal - Stats)
1. Create `teacher_stats.php`
2. Create `teacher_detail.php`
3. Add dashboard widget
4. Add CSV export

### Sprint 5 (Polish & Testing)
1. Comprehensive testing
2. Bug fixes
3. Documentation
4. Deployment

---

## Configuration Constants

Add to `config.php` or `.env`:

```php
// Dual Photo Verification Settings
define('DURATION_TOLERANCE_MINUTES', 15);
define('MIN_DURATION_PERCENT', 80);
define('AUTO_APPROVE_START_DISTANCE', 100); // meters
define('MAX_TIME_BEFORE_SLOT_START', 60); // minutes
define('MAX_TIME_AFTER_SLOT_END', 120); // minutes
define('REQUIRE_GPS_FOR_APPROVAL', true);
```

---

## Summary

This implementation adds robust verification that teachers actually complete their teaching sessions by:

1. **Requiring two geotagged photos** - arrival and departure
2. **Verifying location** for both photos
3. **Calculating and validating duration** against expected slot time
4. **Providing admin oversight** with detailed review capabilities
5. **Tracking teacher statistics** for accountability

The system maintains backward compatibility with existing single-photo sessions while enhancing fraud prevention for future sessions.

---

*Document Version: 1.0*  
*Last Updated: December 30, 2025*