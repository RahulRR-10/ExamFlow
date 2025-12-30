# Teaching Slots & Session Verification Implementation Plan

> Extend the existing ExamFlow system to support **Admin-managed teaching slots, teacher slot booking, session-wise photo verification, and controlled enrollment/unenrollment**.

**Status**: � In Progress  
**Created**: December 30, 2025  
**Estimated Phases**: 8  

---

## Overview

This implementation adds slot-based governance and verifiable teaching activity while preserving all existing ExamFlow functionality (exams, certificates, proctoring, analytics).

### Key Features
- Admin creates schools and teaching slots
- Teachers browse and book specific time slots
- Session-wise geotagged photo verification
- Controlled unenrollment based on slot completion
- Full audit trail and approval workflow

---

## Phase 1 – Admin: School & Teaching Slot Management

**Status**: ✅ Complete

### Objectives
- Extend Admin capabilities to manage schools requiring teaching support
- Allow Admin to create teaching slots for each school

### Tasks

#### 1.1 Enhance School Management
- [ ] Add school creation form with full details:
  - School name
  - Full address
  - GPS coordinates (latitude, longitude)
  - Contact person name
  - Contact phone
  - Contact email
  - School type (primary, secondary, higher secondary, etc.)
  - Status (active, inactive)

#### 1.2 Teaching Slot Creation
- [ ] Create slot management interface at `admin/teaching_slots.php`
- [ ] Slot creation form with:
  - Select school (dropdown)
  - Date (date picker)
  - Start time
  - End time
  - Number of teachers required
  - Slot description/notes
- [ ] Validate:
  - End time > Start time
  - Teachers required >= 1
  - No overlapping slots for same school

#### 1.3 Slot Status Management
- [ ] Auto-calculate slot status:
  - `open` - 0 teachers enrolled
  - `partially_filled` - Some teachers enrolled, not full
  - `full` - Required count reached
  - `completed` - Date has passed
  - `cancelled` - Admin cancelled

### Files to Create/Modify
```
admin/
├── manage_schools.php (enhance existing)
├── teaching_slots.php (new)
├── add_slot.php (new)
├── edit_slot.php (new)
└── delete_slot.php (new)
```

### UI Components
- Schools list with slot count indicators
- Slot calendar view (optional)
- Slot creation modal/form
- Status badges (Open, Partial, Full)

---

## Phase 2 – Database & Data Model Changes

**Status**: ✅ Complete

### Objectives
- Introduce new tables without affecting existing ones
- Establish proper foreign key relationships

### Tasks

#### 2.1 Create `school_teaching_slots` Table
```sql
CREATE TABLE IF NOT EXISTS school_teaching_slots (
    slot_id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT NOT NULL,
    slot_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    teachers_required INT NOT NULL DEFAULT 1,
    teachers_enrolled INT NOT NULL DEFAULT 0,
    slot_status ENUM('open', 'partially_filled', 'full', 'completed', 'cancelled') DEFAULT 'open',
    description TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin(id),
    INDEX idx_school_date (school_id, slot_date),
    INDEX idx_status (slot_status),
    INDEX idx_date (slot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 2.2 Create `slot_teacher_enrollments` Table
```sql
CREATE TABLE IF NOT EXISTS slot_teacher_enrollments (
    enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
    slot_id INT NOT NULL,
    teacher_id INT NOT NULL,
    enrollment_status ENUM('booked', 'cancelled', 'completed', 'no_show') DEFAULT 'booked',
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT NULL,
    
    FOREIGN KEY (slot_id) REFERENCES school_teaching_slots(slot_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teacher(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot_teacher (slot_id, teacher_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_status (enrollment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 2.3 Create `teaching_sessions` Table
```sql
CREATE TABLE IF NOT EXISTS teaching_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    enrollment_id INT NOT NULL,
    slot_id INT NOT NULL,
    teacher_id INT NOT NULL,
    school_id INT NOT NULL,
    session_date DATE NOT NULL,
    
    -- Photo proof
    photo_path VARCHAR(500) NULL,
    photo_uploaded_at TIMESTAMP NULL,
    gps_latitude DECIMAL(10, 8) NULL,
    gps_longitude DECIMAL(11, 8) NULL,
    photo_taken_at DATETIME NULL,
    distance_from_school DECIMAL(10, 2) NULL,
    
    -- Session status
    session_status ENUM('pending', 'photo_submitted', 'approved', 'rejected') DEFAULT 'pending',
    
    -- Admin verification
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    admin_remarks TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (enrollment_id) REFERENCES slot_teacher_enrollments(enrollment_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES school_teaching_slots(slot_id),
    FOREIGN KEY (teacher_id) REFERENCES teacher(id),
    FOREIGN KEY (school_id) REFERENCES schools(school_id),
    FOREIGN KEY (verified_by) REFERENCES admin(id),
    
    UNIQUE KEY unique_session (enrollment_id),
    INDEX idx_teacher_date (teacher_id, session_date),
    INDEX idx_status (session_status),
    INDEX idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 2.4 Update Triggers
```sql
-- Trigger to update slot enrollment count and status
DELIMITER //
CREATE TRIGGER after_slot_enrollment_insert
AFTER INSERT ON slot_teacher_enrollments
FOR EACH ROW
BEGIN
    UPDATE school_teaching_slots 
    SET teachers_enrolled = teachers_enrolled + 1,
        slot_status = CASE 
            WHEN teachers_enrolled + 1 >= teachers_required THEN 'full'
            WHEN teachers_enrolled + 1 > 0 THEN 'partially_filled'
            ELSE 'open'
        END
    WHERE slot_id = NEW.slot_id AND NEW.enrollment_status = 'booked';
END//

CREATE TRIGGER after_slot_enrollment_cancel
AFTER UPDATE ON slot_teacher_enrollments
FOR EACH ROW
BEGIN
    IF OLD.enrollment_status = 'booked' AND NEW.enrollment_status = 'cancelled' THEN
        UPDATE school_teaching_slots 
        SET teachers_enrolled = GREATEST(0, teachers_enrolled - 1),
            slot_status = CASE 
                WHEN teachers_enrolled - 1 <= 0 THEN 'open'
                WHEN teachers_enrolled - 1 < teachers_required THEN 'partially_filled'
                ELSE 'full'
            END
        WHERE slot_id = NEW.slot_id;
    END IF;
END//
DELIMITER ;
```

### Migration Script
- [ ] Create `db/migrate_teaching_slots.sql`
- [ ] Add rollback script for safety

---

## Phase 3 – Teacher Portal: Slot Discovery & Booking

**Status**: ✅ Complete

### Objectives
- Replace single "Enroll in School" button with slot browser
- Enable teachers to book specific slots

### Tasks

#### 3.1 Create Slot Browser Interface
- [ ] Create `teachers/browse_slots.php`
- [ ] Display:
  - School name and details
  - Available slots with dates/times
  - Required vs enrolled teacher count
  - Availability status (Open, Partial, Full)
  - Distance from teacher's location (optional)

#### 3.2 Filtering & Search
- [ ] Filter by:
  - Date range
  - School
  - Availability status
  - Distance (if GPS available)
- [ ] Sort by:
  - Date (upcoming first)
  - Availability
  - School name

#### 3.3 Slot Booking Logic
- [ ] Create `teachers/book_slot.php` (AJAX handler)
- [ ] Validations:
  - Slot not full
  - Teacher not already booked for this slot
  - No overlapping slots (same date/time)
  - Slot date is in the future
- [ ] On successful booking:
  - Insert into `slot_teacher_enrollments`
  - Create `teaching_sessions` record
  - Update slot enrollment count
  - Show confirmation

#### 3.4 My Bookings View
- [ ] Create `teachers/my_slots.php`
- [ ] Show:
  - Upcoming booked slots
  - Past slots with session status
  - Cancel option (for future slots only)
- [ ] Quick access to photo upload per session

#### 3.5 Update Navigation
- [ ] Replace "Enroll in School" with "Browse Teaching Slots"
- [ ] Add "My Bookings" menu item

### Files to Create
```
teachers/
├── browse_slots.php (new)
├── book_slot.php (new - AJAX)
├── my_slots.php (new)
├── cancel_booking.php (new - AJAX)
└── css/
    └── slots.css (new)
```

### UI Components
- Slot cards with booking button
- Booking confirmation modal
- My bookings table with actions
- Overlap warning alerts

---

## Phase 4 – Admin Portal: Slot Monitoring & Teacher Lists

**Status**: ✅ Complete

### Objectives
- Enable Admin to monitor all slots and enrollments
- View real-time teacher lists per slot

### Tasks

#### 4.1 Slot Dashboard
- [ ] Create `admin/slot_dashboard.php`
- [ ] Overview cards:
  - Total slots (this week/month)
  - Open slots
  - Filled slots
  - Completed sessions

#### 4.2 School-wise Slot View
- [ ] List all schools with expandable slot details
- [ ] Per school show:
  - Total slots created
  - Upcoming slots
  - Teacher coverage percentage

#### 4.3 Slot Detail View
- [ ] Create `admin/view_slot.php?id=X`
- [ ] Display:
  - Slot information (date, time, school)
  - Capacity: X/Y teachers
  - Enrolled teachers list with:
    - Teacher name
    - Booking date
    - Session status (pending/submitted/approved)
  - Progress bar for capacity

#### 4.4 Slot Modification
- [ ] Allow Admin to:
  - Edit slot details (if no enrollments)
  - Increase teacher capacity
  - Cancel slot (with notifications concept)
- [ ] Prevent:
  - Reducing capacity below enrolled count
  - Deleting slots with enrollments (soft delete only)

### Files to Create
```
admin/
├── slot_dashboard.php (new)
├── view_slot.php (new)
├── slot_teachers.php (new - AJAX)
└── update_slot.php (new)
```

---

## Phase 5 – Teaching Session Execution & Photo Proof

**Status**: ✅ Complete

### Objectives
- Require geotagged photo proof for every session
- Link photos to teacher, school, slot, and session

### Tasks

#### 5.1 Session Photo Upload
- [ ] Create `teachers/upload_session_photo.php`
- [ ] Reuse existing EXIF extraction from geotagged verification
- [ ] Extract from uploaded photo:
  - GPS coordinates
  - Photo timestamp
  - Camera/device info

#### 5.2 Location Validation
- [ ] Calculate distance from school GPS
- [ ] Use configurable radius (from verification_settings)
- [ ] Store validation result:
  - `distance_from_school`
  - `location_match_status`

#### 5.3 Session Completion Rules
- [ ] Session cannot be marked complete without photo
- [ ] Photo must be uploaded on session date (configurable tolerance)
- [ ] Show warning if:
  - GPS data missing
  - Distance exceeds threshold
  - Photo date doesn't match session date

#### 5.4 Session Status Flow
```
pending → photo_submitted → approved/rejected
                              ↓
                          (rejected can resubmit)
```

#### 5.5 My Sessions Interface
- [ ] Enhance `teachers/my_slots.php` to show:
  - Sessions requiring photo upload
  - Upload status per session
  - Quick upload button
  - View submitted photo

### Files to Create/Modify
```
teachers/
├── upload_session_photo.php (new)
├── my_slots.php (enhance)
└── view_session.php (new)

uploads/
└── session_photos/ (new directory)
```

---

## Phase 6 – Admin Verification of Sessions

**Status**: ✅ Complete

### Objectives
- Enable Admin to review and approve/reject sessions
- Maintain approval audit trail

### Tasks

#### 6.1 Pending Sessions Queue
- [ ] Create `admin/pending_sessions.php`
- [ ] List all sessions with status `photo_submitted`
- [ ] Filters:
  - By school
  - By date range
  - By teacher

#### 6.2 Session Review Interface
- [ ] Create `admin/review_session.php?id=X`
- [ ] Display:
  - Session details (teacher, school, slot, date)
  - Uploaded photo with zoom
  - GPS coordinates on map
  - Distance from school
  - Photo metadata (taken time, device)
  - Location match status

#### 6.3 Approval Actions
- [ ] Approve:
  - Update `session_status` to `approved`
  - Record `verified_by`, `verified_at`
  - Optional remarks
- [ ] Reject:
  - Update `session_status` to `rejected`
  - Require rejection reason
  - Allow teacher to resubmit

#### 6.4 Bulk Actions
- [ ] Bulk approve (for sessions with valid GPS)
- [ ] Bulk reject with common reason

#### 6.5 Session Statistics
- [ ] Dashboard widget showing:
  - Pending reviews count
  - Approved this week
  - Rejection rate

### Files to Create
```
admin/
├── pending_sessions.php (new)
├── review_session.php (new)
├── process_session_review.php (new - AJAX)
└── session_stats.php (new - AJAX)
```

---

## Phase 7 – Controlled Unenrollment from School

**Status**: ✅ Complete

### Objectives
- Prevent unenrollment if pending slots/sessions exist
- Clear messaging for unenrollment restrictions

### Tasks

#### 7.1 Unenrollment Eligibility Check
- [x] Create utility function `canTeacherUnenroll($teacher_id, $school_id)`
- [x] Check conditions:
  - No upcoming booked slots for this school
  - All past sessions are completed (approved/rejected)
  - No pending photo submissions

#### 7.2 Update Unenroll UI
- [x] Modify school management in teacher portal
- [x] If cannot unenroll:
  - Disable unenroll button
  - Show tooltip with reason:
    - "You have X upcoming slots"
    - "X sessions pending photo submission"
    - "X sessions awaiting approval"

#### 7.3 Unenrollment Process
- [x] When allowed:
  - Remove from `teacher_schools`
  - Keep historical session data
  - Log the unenrollment

#### 7.4 Force Unenroll (Admin)
- [x] Admin can force-unenroll teacher
- [x] Requires confirmation
  - Cancel all upcoming slots
  - Mark sessions as `no_show` or `cancelled`
- [x] Audit log entry

### Files Created/Modified
```
utils/
└── enrollment_utils.php ✅ (NEW - canTeacherUnenroll, getTeacherObligations, forceUnenrollTeacher, getTeacherSchoolStats)

teachers/
├── school_management.php ✅ (MODIFIED - unenrollment restrictions UI, school stats badges)
└── enroll_school.php ✅ (MODIFIED - unenrollment eligibility check)

admin/
├── includes/nav.php ✅ (MODIFIED - added Force Unenroll menu item)
└── force_unenroll.php ✅ (NEW - admin force unenrollment with audit)
```

---

## Phase 8 – Backward Compatibility & Safety

**Status**: ✅ Complete

### Objectives
- Ensure existing functionality remains intact
- Safe refactoring of enrollment logic

### Tasks

#### 8.1 Existing Feature Verification
- [x] Test all existing features work:
  - MCQ Exams (create, take, results)
  - Objective Exams (OCR, grading)
  - Mock Exams (generation, taking)
  - Certificates (generation, NFT minting)
  - Proctoring (violation detection)
  - Analytics (all reports)
  - Messages system
  - Student/Teacher registration

#### 8.2 Data Migration
- [x] For existing `teacher_schools` entries:
  - Keep them valid
  - New slot system is additive
  - Teachers can still access their schools

#### 8.3 Edge Case Handling
- [x] Slot overbooking prevention:
  - Database-level constraint (FOR UPDATE locking)
  - Application-level check (transaction with row lock)
  - Race condition handling (transactions)
  
- [x] Teacher cancellation:
  - 24-hour deadline before slot start
  - After deadline: require admin approval
  
- [x] Admin slot deletion:
  - Soft delete only if enrollments exist
  - Keep session history

#### 8.4 Error Handling
- [x] Graceful degradation if new tables missing (isTeachingSlotsEnabled())
- [x] Clear error messages
- [x] Logging for debugging (logAdminAction())

#### 8.5 Performance Considerations
- [x] Index optimization (additional composite indexes)
- [x] Query efficiency for slot browsing
- [x] Database health check utility

### Files Created/Modified
```
utils/
└── teaching_slots_compat.php ✅ (NEW - compatibility layer with graceful degradation)

db/
└── migrate_teaching_slots.sql ✅ (MODIFIED - added performance indexes)

teachers/
├── browse_slots.php ✅ (MODIFIED - added feature availability check)
└── cancel_booking.php ✅ (MODIFIED - added 24-hour cancellation deadline)

admin/
├── force_unenroll.php ✅ (MODIFIED - fixed table names, added audit logging)
└── db_health_check.php ✅ (NEW - database verification utility)
```

### Compatibility Layer Functions
- `isTeachingSlotsEnabled()` - Check if feature is available
- `getAuditLogTable()` - Find correct audit table
- `logAdminAction()` - Cross-compatible audit logging
- `safeBookSlot()` - Transaction-safe slot booking
- `safeCancelBooking()` - Safe cancellation with deadline check
- `checkSlotConflict()` - Time overlap detection
- `getSlotAvailability()` - Detailed availability info
- `verifyTeachingSlotsDatabase()` - Database health check

### Testing Checklist
```
[x] Create school and slots as Admin
[x] Browse slots as Teacher
[x] Book slot successfully
[x] Attempt to book full slot (should fail)
[x] Attempt to double-book overlapping slot (should fail)
[x] Upload session photo
[x] Admin approve session
[x] Admin reject session
[x] Teacher resubmit after rejection
[x] Attempt unenroll with pending slots (should fail)
[x] Unenroll after all complete (should succeed)
[x] Existing exams still work
[x] Existing certificates still generate
[x] Analytics still display correctly
```

---

## File Structure Summary

### New Files
```
admin/
├── teaching_slots.php
├── add_slot.php
├── edit_slot.php
├── delete_slot.php
├── slot_dashboard.php
├── view_slot.php
├── slot_teachers.php
├── pending_sessions.php
├── review_session.php
├── process_session_review.php
├── session_stats.php
└── force_unenroll.php

teachers/
├── browse_slots.php
├── book_slot.php
├── my_slots.php
├── cancel_booking.php
├── upload_session_photo.php
├── view_session.php
└── css/slots.css

utils/
└── enrollment_utils.php

db/
└── migrate_teaching_slots.sql

uploads/
└── session_photos/
```

### Modified Files
```
teachers/
├── school_management.php
├── unenroll_school.php
└── Navigation in all teacher pages

admin/
├── dash.php (add slot stats)
└── Navigation updates
```

---

## Database Tables Summary

| Table | Purpose |
|-------|---------|
| `school_teaching_slots` | Admin-created teaching time slots |
| `slot_teacher_enrollments` | Teacher bookings for slots |
| `teaching_sessions` | Individual session records with photo proofs |

---

## Progress Tracker

| Phase | Description | Status | Completion |
|-------|-------------|--------|------------|
| 1 | Admin: School & Slot Management | ✅ | 100% |
| 2 | Database & Data Model | ✅ | 100% |
| 3 | Teacher: Slot Discovery & Booking | ✅ | 100% |
| 4 | Admin: Slot Monitoring | ✅ | 100% |
| 5 | Session Execution & Photo Proof | ✅ | 100% |
| 6 | Admin Session Verification | ✅ | 100% |
| 7 | Controlled Unenrollment | ✅ | 100% |
| 8 | Backward Compatibility | ✅ | 100% |

---

## Legend

- 🔴 Not Started
- 🟡 In Progress
- ✅ Completed
- ⚠️ Blocked

---

## Implementation Complete! 🎉

All 8 phases have been successfully implemented. The Teaching Slots feature is now fully functional with:

- **Admin Portal**: School/slot management, session verification, force unenrollment
- **Teacher Portal**: Slot browsing, booking, session photo upload
- **Compatibility Layer**: Graceful degradation, audit logging, database health checks
- **Safety Features**: Transaction handling, race condition prevention, cancellation deadlines

---

## Notes

1. **Reuse Existing Components**: Leverage existing EXIF extraction, location validation, and map components from the geotagged verification feature.

2. **Session-based Auth**: All pages must use existing session authentication.

3. **Responsive Design**: All new pages must work on mobile devices.

4. **Audit Trail**: Log all significant actions for accountability.

5. **No Breaking Changes**: This is purely additive - existing workflows must continue to work.
