# Multi-School Support Implementation Plan

## Overview

This document outlines the phased implementation of multi-school support for the examination system. The implementation is divided into 5 phases to ensure seamless execution without breaking existing functionality.

---

## Phase 1: Database Schema & Core Infrastructure

### Objectives

- Create school-related database tables
- Establish relationships between schools, teachers, and students
- Migrate existing data to new schema

### Database Changes

#### 1.1 Create `schools` Table

```sql
CREATE TABLE schools (
    school_id INT PRIMARY KEY AUTO_INCREMENT,
    school_name VARCHAR(255) NOT NULL UNIQUE,
    school_code VARCHAR(50) UNIQUE,
    address TEXT,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 1.2 Create `teacher_schools` Table (Many-to-Many Relationship)

```sql
CREATE TABLE teacher_schools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    school_id INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teacher(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_school (teacher_id, school_id)
);
```

#### 1.3 Modify `student` Table

```sql
ALTER TABLE student
ADD COLUMN school_id INT,
ADD FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE SET NULL;
```

#### 1.4 Modify `exm_list` Table (Exam School Association)

```sql
ALTER TABLE exm_list
ADD COLUMN school_id INT,
ADD FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE CASCADE;

CREATE INDEX idx_exam_school ON exm_list(school_id);
```

#### 1.5 Modify `mock_exm_list` Table

```sql
ALTER TABLE mock_exm_list
ADD COLUMN school_id INT,
ADD FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE CASCADE;

CREATE INDEX idx_mock_exam_school ON mock_exm_list(school_id);
```

### Migration Script

#### 1.6 Create Migration File: `db/migrate_multi_school.sql`

```sql
-- Step 1: Create schools table
CREATE TABLE IF NOT EXISTS schools (
    school_id INT PRIMARY KEY AUTO_INCREMENT,
    school_name VARCHAR(255) NOT NULL UNIQUE,
    school_code VARCHAR(50) UNIQUE,
    address TEXT,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 2: Create a default school for existing data
INSERT INTO schools (school_name, school_code, status)
VALUES ('Default School', 'DEFAULT001', 'active');

-- Step 3: Create teacher-school relationship table
CREATE TABLE IF NOT EXISTS teacher_schools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    school_id INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teacher(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_school (teacher_id, school_id)
);

-- Step 4: Migrate all existing teachers to default school
INSERT INTO teacher_schools (teacher_id, school_id, is_primary)
SELECT id, 1, 1 FROM teacher;

-- Step 5: Add school_id to student
ALTER TABLE student
ADD COLUMN IF NOT EXISTS school_id INT,
ADD FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE SET NULL;

-- Step 6: Migrate all existing students to default school
UPDATE student SET school_id = 1 WHERE school_id IS NULL;

-- Step 7: Add school_id to exm_list
ALTER TABLE exm_list
ADD COLUMN IF NOT EXISTS school_id INT,
ADD FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_exam_school ON exm_list(school_id);

-- Step 8: Migrate all existing exams to default school
UPDATE exm_list SET school_id = 1 WHERE school_id IS NULL;

-- Step 9: Add school_id to mock_exm_list
ALTER TABLE mock_exm_list
ADD COLUMN IF NOT EXISTS school_id INT,
ADD FOREIGN KEY (school_id) REFERENCES schools(school_id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_mock_exam_school ON mock_exm_list(school_id);

-- Step 10: Migrate all existing mock exams to default school
UPDATE mock_exm_list SET school_id = 1 WHERE school_id IS NULL;
```

### Implementation Tasks

- [ ] Create migration script
- [ ] Test migration on development database
- [ ] Backup production database
- [ ] Execute migration script
- [ ] Verify data integrity
- [ ] Create rollback script (if needed)

### Testing Checklist

- [ ] All tables created successfully
- [ ] Foreign keys established correctly
- [ ] Existing data migrated without loss
- [ ] Indexes created for performance

---

## Phase 2: Teacher Dashboard - School Management

### Objectives

- Add Schools tab to teacher dashboard
- Allow teachers to view enrolled schools
- Allow teachers to browse and enroll in new schools
- Implement school enrollment approval workflow (optional)

### Backend Files to Create/Modify

#### 2.1 Create `teachers/school_management.php`

```php
<?php
include('../config.php');
session_start();

if (!isset($_SESSION['tid'])) {
    header('Location: ../login_teacher.php');
    exit;
}

$tid = $_SESSION['tid'];

// Get schools teacher is enrolled in
$enrolled_sql = "SELECT s.*, ts.enrollment_status, ts.enrolled_at
                 FROM schools s
                 INNER JOIN teacher_schools ts ON s.school_id = ts.school_id
                 WHERE ts.tid = '$tid'
                 ORDER BY s.school_name";
$enrolled_schools = mysqli_query($conn, $enrolled_sql);

// Get available schools (not enrolled yet)
$available_sql = "SELECT s.* FROM schools s
                  WHERE s.status = 'active'
                  AND s.school_id NOT IN (
                      SELECT school_id FROM teacher_schools WHERE tid = '$tid'
                  )
                  ORDER BY s.school_name";
$available_schools = mysqli_query($conn, $available_sql);
?>
```

#### 2.2 Create `teachers/enroll_school.php` (AJAX endpoint)

```php
<?php
include('../config.php');
session_start();

if (!isset($_SESSION['tid'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_id'])) {
    $tid = $_SESSION['tid'];
    $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);

    // Check if school exists and is active
    $check_sql = "SELECT * FROM schools WHERE school_id = '$school_id' AND status = 'active'";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        // Enroll teacher
        $enroll_sql = "INSERT INTO teacher_schools (tid, school_id, enrollment_status)
                       VALUES ('$tid', '$school_id', 'active')";

        if (mysqli_query($conn, $enroll_sql)) {
            echo json_encode(['success' => true, 'message' => 'Successfully enrolled']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Enrollment failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'School not found']);
    }
}
?>
```

#### 2.3 Create `teachers/create_school.php` (Allow teachers to create schools)

```php
<?php
include('../config.php');
session_start();

if (!isset($_SESSION['tid'])) {
    header('Location: ../login_teacher.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_school'])) {
    $school_name = mysqli_real_escape_string($conn, $_POST['school_name']);
    $school_code = mysqli_real_escape_string($conn, $_POST['school_code']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
    $contact_phone = mysqli_real_escape_string($conn, $_POST['contact_phone']);

    $tid = $_SESSION['tid'];

    // Insert school
    $insert_sql = "INSERT INTO schools (school_name, school_code, address, contact_email, contact_phone)
                   VALUES ('$school_name', '$school_code', '$address', '$contact_email', '$contact_phone')";

    if (mysqli_query($conn, $insert_sql)) {
        $new_school_id = mysqli_insert_id($conn);

        // Auto-enroll the creator
        $enroll_sql = "INSERT INTO teacher_schools (tid, school_id, enrollment_status)
                       VALUES ('$tid', '$new_school_id', 'active')";
        mysqli_query($conn, $enroll_sql);

        header('Location: school_management.php?success=created');
    } else {
        header('Location: school_management.php?error=creation_failed');
    }
}
?>
```

#### 2.4 Update `teachers/dash.php` Navigation

Add new menu item for Schools tab:

```php
<li><a href="school_management.php">Schools</a></li>
```

### UI Components

#### 2.5 Schools Tab UI (`teachers/school_management.php` frontend)

- Display enrolled schools in a grid/table
- Display available schools with "Enroll" button
- Add "Create New School" button
- Show enrollment status (active/pending/inactive)

### Implementation Tasks

- [ ] Create backend files
- [ ] Create UI for school management
- [ ] Implement enrollment functionality
- [ ] Add create school functionality
- [ ] Update navigation menu
- [ ] Add success/error notifications

### Testing Checklist

- [ ] Teachers can view enrolled schools
- [ ] Teachers can browse available schools
- [ ] Enrollment works correctly
- [ ] School creation works
- [ ] UI is responsive and user-friendly

---

## Phase 3: Student Registration & Login Revamp

### Objectives

- Update student registration to require school selection
- Modify login to validate school association
- Create school dropdown in registration form

### Backend Files to Modify

#### 3.1 Update `index.php` (Student Registration Form)

Add school selection dropdown:

```php
// Add after other form fields
<div class="form-group">
    <label>Select School *</label>
    <select name="school_id" class="form-control" required>
        <option value="">-- Select School --</option>
        <?php
        $schools_sql = "SELECT school_id, school_name FROM schools WHERE status = 'active' ORDER BY school_name";
        $schools_result = mysqli_query($conn, $schools_sql);
        while ($school = mysqli_fetch_assoc($schools_result)) {
            echo "<option value='{$school['school_id']}'>{$school['school_name']}</option>";
        }
        ?>
    </select>
</div>
```

#### 3.2 Update Student Registration Handler

Modify the registration logic to include school_id:

```php
if (isset($_POST["register"])) {
    $uname = mysqli_real_escape_string($conn, $_POST["uname"]);
    $email = mysqli_real_escape_string($conn, $_POST["email"]);
    $pass = mysqli_real_escape_string($conn, $_POST["pass"]);
    $school_id = mysqli_real_escape_string($conn, $_POST["school_id"]);

    // Validate school exists
    $school_check = "SELECT school_id FROM schools WHERE school_id = '$school_id' AND status = 'active'";
    $school_result = mysqli_query($conn, $school_check);

    if (mysqli_num_rows($school_result) > 0) {
        $sql = "INSERT INTO student_log (uname, email, pass, school_id)
                VALUES ('$uname', '$email', '$pass', '$school_id')";

        if (mysqli_query($conn, $sql)) {
            // Registration successful
            header('Location: login_student.php?registered=success');
        }
    } else {
        echo "Invalid school selection";
    }
}
```

#### 3.3 Update `login_student.php`

Add school validation during login:

```php
// After successful authentication
$student_sql = "SELECT s.*, sch.school_name, sch.status as school_status
                FROM student_log s
                LEFT JOIN schools sch ON s.school_id = sch.school_id
                WHERE s.sid = '$sid'";
$student_result = mysqli_query($conn, $student_sql);
$student = mysqli_fetch_assoc($student_result);

// Check if student's school is active
if ($student['school_status'] !== 'active') {
    echo "Your school is currently inactive. Please contact administrator.";
    exit;
}

// Store school info in session
$_SESSION['school_id'] = $student['school_id'];
$_SESSION['school_name'] = $student['school_name'];
```

### UI Improvements

#### 3.4 Enhanced Registration UI

- Add school logo display (optional)
- Add school search/filter functionality
- Show school details on hover/click
- Add "School not listed?" link to request new school

#### 3.5 Enhanced Login UI

- Display student's school name after login
- Show school-specific branding (optional)

### Implementation Tasks

- [ ] Update registration form with school dropdown
- [ ] Modify registration backend
- [ ] Update login validation
- [ ] Add school info to student session
- [ ] Create UI for school selection
- [ ] Add validation and error handling

### Testing Checklist

- [ ] Students can select school during registration
- [ ] Registration fails with invalid school
- [ ] Login validates school status
- [ ] School info stored in session
- [ ] UI displays correctly

---

## Phase 4: Exam & Mock Test Scoping

### Objectives

- Add school selection when creating exams
- Add school selection when generating mock tests
- Filter exams by school for teachers
- Restrict exam creation to enrolled schools only

### Backend Files to Modify

#### 4.1 Update `teachers/addexam.php`

Add school selection before exam creation:

```php
// Show school selection form first
if (!isset($_POST['school_selected'])) {
    // Display form to select school
    $enrolled_sql = "SELECT s.school_id, s.school_name
                     FROM schools s
                     INNER JOIN teacher_schools ts ON s.school_id = ts.school_id
                     WHERE ts.tid = '{$_SESSION['tid']}'
                     AND ts.enrollment_status = 'active'
                     ORDER BY s.school_name";
    $schools = mysqli_query($conn, $enrolled_sql);

    // Show dropdown
} else {
    // Store selected school in session for this exam creation
    $_SESSION['exam_school_id'] = $_POST['school_id'];
}
```

#### 4.2 Modify Exam Creation

Update exam insertion to include school_id:

```php
if (isset($_POST["addexm"])) {
    $exname = mysqli_real_escape_string($conn, $_POST["exname"]);
    $nq = mysqli_real_escape_string($conn, $_POST["nq"]);
    $desp = mysqli_real_escape_string($conn, $_POST["desp"]);
    $subt = mysqli_real_escape_string($conn, $_POST["subt"]);
    $extime = mysqli_real_escape_string($conn, $_POST["extime"]);
    $subject = mysqli_real_escape_string($conn, $_POST["subject"]);
    $duration = mysqli_real_escape_string($conn, $_POST["duration"]);
    $school_id = mysqli_real_escape_string($conn, $_SESSION['exam_school_id']);

    // Verify teacher is enrolled in this school
    $verify_sql = "SELECT * FROM teacher_schools
                   WHERE tid = '{$_SESSION['tid']}'
                   AND school_id = '$school_id'
                   AND enrollment_status = 'active'";
    $verify_result = mysqli_query($conn, $verify_sql);

    if (mysqli_num_rows($verify_result) > 0) {
        $sql = "INSERT INTO exm_list (exname, nq, desp, subt, extime, subject, duration, school_id)
                VALUES ('$exname', '$nq', '$desp', '$subt', '$extime', '$subject', '$duration', '$school_id')";

        if (mysqli_query($conn, $sql)) {
            $exam_id = mysqli_insert_id($conn);
            // Continue with question addition
        }
    } else {
        echo "You are not enrolled in this school";
        exit;
    }
}
```

#### 4.3 Update `teachers/mock_exam_helper.php`

Modify mock exam generation to include school_id:

```php
function generateMockExamsHelper($exid, $exname, $description, $subject)
{
    global $conn, $grok_api_key, $grok_model, $max_retries, $retry_delay;

    // Get the school_id from the original exam
    $exam_sql = "SELECT school_id FROM exm_list WHERE exid = '$exid'";
    $exam_result = mysqli_query($conn, $exam_sql);
    $exam = mysqli_fetch_assoc($exam_result);
    $school_id = $exam['school_id'];

    // Insert mock exam with school_id
    $sql = "INSERT INTO mock_exm_list (original_exid, mock_number, exname, nq, desp, subt, extime, subject, status, school_id)
            VALUES ('$exid', '$i', '$mock_exam_name', '5', '$mock_exam_desc', '$submission_time', '$current_date', '$subject', 'pending', '$school_id')";

    // Rest of the function...
}
```

#### 4.4 Update `teachers/exams.php` (Exam List)

Filter exams by teacher's enrolled schools:

```php
$exams_sql = "SELECT e.*, s.school_name
              FROM exm_list e
              INNER JOIN schools s ON e.school_id = s.school_id
              WHERE e.school_id IN (
                  SELECT school_id FROM teacher_schools
                  WHERE tid = '{$_SESSION['tid']}'
                  AND enrollment_status = 'active'
              )
              ORDER BY e.extime DESC";
```

### UI Changes

#### 4.5 Exam Creation Flow

1. Teacher clicks "Create Exam"
2. Select School (dropdown of enrolled schools)
3. Enter exam details
4. Add questions

#### 4.6 Exam List Display

- Show school name badge/tag for each exam
- Add school filter dropdown
- Group exams by school (optional)

### Implementation Tasks

- [ ] Add school selection to exam creation
- [ ] Update exam insertion with school_id
- [ ] Modify mock exam generation
- [ ] Update exam list with school filter
- [ ] Add school display in exam list
- [ ] Implement access validation

### Testing Checklist

- [ ] Teachers can only create exams for enrolled schools
- [ ] School_id is saved with exams
- [ ] Mock exams inherit school from parent exam
- [ ] Exam list shows correct school info
- [ ] Teachers see only their schools' exams

---

## Phase 5: Access Control & Student Visibility

### Objectives

- Students see only their school's exams
- Students can only attempt their school's exams
- Implement strict cross-school access prevention
- Add security checks to all exam endpoints

### Backend Files to Modify

#### 5.1 Update `students/exams.php`

Filter exams by student's school:

```php
session_start();
if (!isset($_SESSION['sid'])) {
    header('Location: ../login_student.php');
    exit;
}

$sid = $_SESSION['sid'];
$school_id = $_SESSION['school_id'];

// Get only exams from student's school
$exams_sql = "SELECT e.* FROM exm_list e
              WHERE e.school_id = '$school_id'
              AND e.status = 'active'
              ORDER BY e.extime DESC";
$exams_result = mysqli_query($conn, $exams_sql);
```

#### 5.2 Update `students/mock_exams.php`

Filter mock exams by school:

```php
$mock_exams_sql = "SELECT me.* FROM mock_exm_list me
                   WHERE me.school_id = '$school_id'
                   AND me.status = 'ready'
                   ORDER BY me.extime DESC";
```

#### 5.3 Update `students/examportal.php`

Add school validation before allowing exam attempt:

```php
session_start();
if (!isset($_SESSION['sid']) || !isset($_GET['exid'])) {
    header('Location: exams.php');
    exit;
}

$sid = $_SESSION['sid'];
$exid = mysqli_real_escape_string($conn, $_GET['exid']);
$school_id = $_SESSION['school_id'];

// Verify exam belongs to student's school
$verify_sql = "SELECT * FROM exm_list
               WHERE exid = '$exid'
               AND school_id = '$school_id'";
$verify_result = mysqli_query($conn, $verify_sql);

if (mysqli_num_rows($verify_result) === 0) {
    echo "<script>alert('Access denied: This exam does not belong to your school');</script>";
    header('Location: exams.php');
    exit;
}
```

#### 5.4 Update `students/mockexamportal.php`

Add same validation for mock exams:

```php
$mock_exid = mysqli_real_escape_string($conn, $_GET['mock_exid']);
$school_id = $_SESSION['school_id'];

// Verify mock exam belongs to student's school
$verify_sql = "SELECT * FROM mock_exm_list
               WHERE mock_exid = '$mock_exid'
               AND school_id = '$school_id'";
$verify_result = mysqli_query($conn, $verify_sql);

if (mysqli_num_rows($verify_result) === 0) {
    echo "<script>alert('Access denied');</script>";
    header('Location: mock_exams.php');
    exit;
}
```

#### 5.5 Update `students/submit.php` & `students/submit_mock.php`

Add school validation on submission:

```php
// Before processing submission
$school_id = $_SESSION['school_id'];
$verify_sql = "SELECT * FROM exm_list
               WHERE exid = '$exid'
               AND school_id = '$school_id'";
$verify_result = mysqli_query($conn, $verify_sql);

if (mysqli_num_rows($verify_result) === 0) {
    die("Unauthorized submission attempt");
}
```

#### 5.6 Update `students/results.php`

Filter results by school:

```php
$results_sql = "SELECT sr.*, e.exname, e.nq
                FROM student_result sr
                INNER JOIN exm_list e ON sr.exid = e.exid
                WHERE sr.sid = '$sid'
                AND e.school_id = '$school_id'
                ORDER BY sr.submitted_at DESC";
```

#### 5.7 Update `teachers/viewresults.php`

Teachers should only see results from their enrolled schools:

```php
$results_sql = "SELECT sr.*, s.uname, e.exname
                FROM student_result sr
                INNER JOIN student_log s ON sr.sid = s.sid
                INNER JOIN exm_list e ON sr.exid = e.exid
                WHERE e.exid = '$exid'
                AND e.school_id IN (
                    SELECT school_id FROM teacher_schools
                    WHERE tid = '{$_SESSION['tid']}'
                    AND enrollment_status = 'active'
                )";
```

### Security Enhancements

#### 5.8 Create Security Helper: `utils/school_access_control.php`

```php
<?php
function validateStudentExamAccess($conn, $sid, $exid) {
    $school_id = $_SESSION['school_id'];
    $sql = "SELECT * FROM exm_list
            WHERE exid = '$exid'
            AND school_id = '$school_id'";
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
}

function validateTeacherSchoolAccess($conn, $tid, $school_id) {
    $sql = "SELECT * FROM teacher_schools
            WHERE tid = '$tid'
            AND school_id = '$school_id'
            AND enrollment_status = 'active'";
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
}

function getStudentSchoolId($conn, $sid) {
    $sql = "SELECT school_id FROM student_log WHERE sid = '$sid'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['school_id'];
}
?>
```

### Implementation Tasks

- [ ] Add school filtering to all student exam views
- [ ] Add validation to exam portal
- [ ] Add validation to mock exam portal
- [ ] Add validation to submission handlers
- [ ] Filter results by school
- [ ] Create security helper functions
- [ ] Test all access control scenarios

### Testing Checklist

- [ ] Students see only their school's exams
- [ ] Direct URL access to other schools' exams is blocked
- [ ] Submission validation works
- [ ] Teachers see only their schools' data
- [ ] No cross-school data leakage
- [ ] All endpoints are secured

---

## Phase 6: UI Polish & Final Testing

### Objectives

- Improve overall UI/UX
- Add school branding support
- Comprehensive testing
- Documentation

### UI Enhancements

#### 6.1 Dashboard Improvements

- Show current school name prominently in student/teacher dashboards
- Add school switcher for teachers (if enrolled in multiple schools)
- Display school statistics

#### 6.2 Branding Support (Optional)

```sql
ALTER TABLE schools
ADD COLUMN logo_url VARCHAR(255),
ADD COLUMN primary_color VARCHAR(7),
ADD COLUMN secondary_color VARCHAR(7);
```

#### 6.3 Analytics Updates

Update `teachers/view_analytics.php` to filter by school:

```php
$analytics_sql = "SELECT ...
                  FROM student_result sr
                  INNER JOIN exm_list e ON sr.exid = e.exid
                  WHERE e.school_id = '$selected_school_id'";
```

### Documentation

#### 6.4 Create User Guide

- Teacher: How to manage schools
- Teacher: How to create school-specific exams
- Student: How to register with school
- Admin: How to manage schools (if admin panel exists)

#### 6.5 Create API Documentation

Document all school-related endpoints and parameters

### Testing Scenarios

#### 6.6 Comprehensive Test Cases

**Student Tests:**

- [ ] Registration with valid school
- [ ] Registration with invalid school
- [ ] Login with active school
- [ ] Login with inactive school
- [ ] View exams (should see only their school)
- [ ] Attempt exam from own school (should work)
- [ ] Attempt exam from other school via URL (should be blocked)
- [ ] View results (should see only own school)

**Teacher Tests:**

- [ ] View enrolled schools
- [ ] Enroll in new school
- [ ] Create new school
- [ ] Create exam for enrolled school
- [ ] Create exam for non-enrolled school (should fail)
- [ ] View exams (should see only enrolled schools)
- [ ] View results (should see only enrolled schools)
- [ ] Generate mock exams (should inherit school)

**Cross-School Tests:**

- [ ] Student A cannot see Student B's school exams
- [ ] Teacher A cannot create exams for Teacher B's school
- [ ] Direct URL manipulation is blocked
- [ ] SQL injection attempts fail

### Performance Optimization

#### 6.7 Add Indexes

```sql
CREATE INDEX idx_student_school ON student_log(school_id);
CREATE INDEX idx_teacher_school_status ON teacher_schools(tid, enrollment_status);
CREATE INDEX idx_exam_school_status ON exm_list(school_id, status);
```

#### 6.8 Cache School Data

Implement session-based caching for frequently accessed school info

### Implementation Tasks

- [ ] Enhance UI for school display
- [ ] Add school branding support
- [ ] Update analytics
- [ ] Create user documentation
- [ ] Run comprehensive tests
- [ ] Add performance indexes
- [ ] Fix any bugs found
- [ ] Final security audit

---

## Rollout Strategy

### Pre-Rollout

1. **Backup database** - Complete backup before any changes
2. **Test on staging** - Full testing on staging environment
3. **User notification** - Inform users about upcoming changes
4. **Create rollback plan** - Document steps to revert if needed

### Rollout Order

1. **Phase 1** - During off-hours (database migration)
2. **Phase 2** - Teachers first (school management)
3. **Phase 3** - New student registrations (updated form)
4. **Phase 4** - Exam creation (scoping)
5. **Phase 5** - Access control (security)
6. **Phase 6** - Polish and optimization

### Post-Rollout

1. Monitor error logs
2. Collect user feedback
3. Fix critical bugs immediately
4. Schedule follow-up improvements

---

## Estimated Timeline

| Phase     | Tasks                       | Estimated Time |
| --------- | --------------------------- | -------------- |
| Phase 1   | Database & Migration        | 1-2 days       |
| Phase 2   | Teacher School Management   | 2-3 days       |
| Phase 3   | Student Registration Update | 2 days         |
| Phase 4   | Exam Scoping                | 2-3 days       |
| Phase 5   | Access Control              | 2-3 days       |
| Phase 6   | Testing & Polish            | 2-3 days       |
| **Total** |                             | **11-16 days** |

---

## Success Criteria

### Phase Completion Criteria

- All tests pass
- No data loss
- No regression in existing functionality
- User acceptance testing completed
- Documentation updated

### Final Success Criteria

- ✅ Multiple schools can be created and managed
- ✅ Teachers can enroll in multiple schools
- ✅ Students belong to exactly one school
- ✅ Exams are scoped to schools
- ✅ Cross-school access is prevented
- ✅ Performance is acceptable
- ✅ Users can perform all required tasks
- ✅ System is secure and stable

---

## Risk Mitigation

### Potential Risks

1. **Data loss during migration** → Complete backups + test migrations
2. **Performance degradation** → Add indexes, optimize queries
3. **User confusion** → Clear documentation + training
4. **Security vulnerabilities** → Security audit + penetration testing
5. **Rollback complexity** → Document rollback procedure

---

## Support & Maintenance

### Ongoing Tasks

- Monitor school creation and enrollments
- Handle school data update requests
- Manage inactive/deleted schools
- Assist with cross-school transfers (if needed)
- Regular security audits

---

## Future Enhancements (Post-MVP)

1. **School Admin Role** - Dedicated role for school administrators
2. **Bulk Student Import** - Import students via CSV with school assignment
3. **School-specific Reporting** - Advanced analytics per school
4. **Multi-language Support** - School-specific language preferences
5. **School Branding** - Custom themes per school
6. **Inter-school Competitions** - Optional cross-school exams
7. **School Hierarchy** - Support for districts/regions

---

## Contact & Support

For questions or issues during implementation:

- Document all issues in a tracking system
- Create detailed bug reports
- Test each phase thoroughly before proceeding

---

**Document Version:** 1.0  
**Last Updated:** December 22, 2025  
**Status:** Ready for Implementation
