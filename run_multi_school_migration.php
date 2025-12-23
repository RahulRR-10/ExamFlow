<?php

/**
 * Multi-School Migration Script
 * Phase 1: Database Schema & Core Infrastructure
 * 
 * Run this script once to set up multi-school support
 */

include('config.php');

echo "==============================================\n";
echo "Multi-School Support Migration\n";
echo "==============================================\n\n";

$errors = [];
$success = [];

// Step 1: Create schools table
echo "Step 1: Creating schools table...\n";
$sql1 = "CREATE TABLE IF NOT EXISTS schools (
    school_id INT PRIMARY KEY AUTO_INCREMENT,
    school_name VARCHAR(255) NOT NULL UNIQUE,
    school_code VARCHAR(50) UNIQUE,
    address TEXT,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql1)) {
    $success[] = "Schools table created/verified";
    echo "   ✓ Schools table ready\n";
} else {
    $errors[] = "Failed to create schools table: " . mysqli_error($conn);
    echo "   ✗ Error: " . mysqli_error($conn) . "\n";
}

// Step 2: Insert default school
echo "Step 2: Creating default school...\n";
$check_default = mysqli_query($conn, "SELECT school_id FROM schools WHERE school_code = 'DEFAULT001'");
if (mysqli_num_rows($check_default) == 0) {
    $sql2 = "INSERT INTO schools (school_name, school_code, status) VALUES ('Default School', 'DEFAULT001', 'active')";
    if (mysqli_query($conn, $sql2)) {
        $success[] = "Default school created";
        echo "   ✓ Default school created\n";
    } else {
        $errors[] = "Failed to create default school: " . mysqli_error($conn);
        echo "   ✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✓ Default school already exists\n";
}

// Step 3: Create teacher_schools table
echo "Step 3: Creating teacher_schools table...\n";
$sql3 = "CREATE TABLE IF NOT EXISTS teacher_schools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    school_id INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_school (teacher_id, school_id)
)";

if (mysqli_query($conn, $sql3)) {
    $success[] = "Teacher_schools table created/verified";
    echo "   ✓ Teacher_schools table ready\n";
} else {
    $errors[] = "Failed to create teacher_schools table: " . mysqli_error($conn);
    echo "   ✗ Error: " . mysqli_error($conn) . "\n";
}

// Step 4: Add school_id to student table
echo "Step 4: Adding school_id to student table...\n";
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM student LIKE 'school_id'");
if (mysqli_num_rows($check_col) == 0) {
    $sql4 = "ALTER TABLE student ADD COLUMN school_id INT DEFAULT 1";
    if (mysqli_query($conn, $sql4)) {
        $success[] = "Added school_id to student";
        echo "   ✓ school_id column added to student\n";
    } else {
        $errors[] = "Failed to add school_id to student: " . mysqli_error($conn);
        echo "   ✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✓ school_id already exists in student\n";
}

// Step 5: Add school_id to exm_list
echo "Step 5: Adding school_id to exm_list...\n";
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM exm_list LIKE 'school_id'");
if (mysqli_num_rows($check_col) == 0) {
    $sql5 = "ALTER TABLE exm_list ADD COLUMN school_id INT DEFAULT 1";
    if (mysqli_query($conn, $sql5)) {
        $success[] = "Added school_id to exm_list";
        echo "   ✓ school_id column added to exm_list\n";
    } else {
        $errors[] = "Failed to add school_id to exm_list: " . mysqli_error($conn);
        echo "   ✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✓ school_id already exists in exm_list\n";
}

// Step 6: Add school_id to mock_exm_list
echo "Step 6: Adding school_id to mock_exm_list...\n";
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM mock_exm_list LIKE 'school_id'");
if (mysqli_num_rows($check_col) == 0) {
    $sql6 = "ALTER TABLE mock_exm_list ADD COLUMN school_id INT DEFAULT 1";
    if (mysqli_query($conn, $sql6)) {
        $success[] = "Added school_id to mock_exm_list";
        echo "   ✓ school_id column added to mock_exm_list\n";
    } else {
        $errors[] = "Failed to add school_id to mock_exm_list: " . mysqli_error($conn);
        echo "   ✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✓ school_id already exists in mock_exm_list\n";
}

// Step 7: Migrate existing students to default school
echo "Step 7: Migrating existing students to default school...\n";
$sql7 = "UPDATE student SET school_id = 1 WHERE school_id IS NULL OR school_id = 0";
if (mysqli_query($conn, $sql7)) {
    $affected = mysqli_affected_rows($conn);
    $success[] = "Migrated $affected students to default school";
    echo "   ✓ Migrated $affected students to default school\n";
} else {
    $errors[] = "Failed to migrate students: " . mysqli_error($conn);
    echo "   ✗ Error: " . mysqli_error($conn) . "\n";
}

// Step 8: Migrate existing exams to default school
echo "Step 8: Migrating existing exams to default school...\n";
$sql8 = "UPDATE exm_list SET school_id = 1 WHERE school_id IS NULL OR school_id = 0";
if (mysqli_query($conn, $sql8)) {
    $affected = mysqli_affected_rows($conn);
    $success[] = "Migrated $affected exams to default school";
    echo "   ✓ Migrated $affected exams to default school\n";
} else {
    $errors[] = "Failed to migrate exams: " . mysqli_error($conn);
    echo "   ✗ Error: " . mysqli_error($conn) . "\n";
}

// Step 9: Migrate existing mock exams to default school
echo "Step 9: Migrating existing mock exams to default school...\n";
$sql9 = "UPDATE mock_exm_list SET school_id = 1 WHERE school_id IS NULL OR school_id = 0";
if (mysqli_query($conn, $sql9)) {
    $affected = mysqli_affected_rows($conn);
    $success[] = "Migrated $affected mock exams to default school";
    echo "   ✓ Migrated $affected mock exams to default school\n";
} else {
    $errors[] = "Failed to migrate mock exams: " . mysqli_error($conn);
    echo "   ✗ Error: " . mysqli_error($conn) . "\n";
}

// Step 10: Enroll existing teachers in default school
echo "Step 10: Enrolling existing teachers in default school...\n";
$sql10 = "INSERT IGNORE INTO teacher_schools (teacher_id, school_id, is_primary)
          SELECT id, 1, 1 FROM teacher";
if (mysqli_query($conn, $sql10)) {
    $affected = mysqli_affected_rows($conn);
    $success[] = "Enrolled $affected teachers in default school";
    echo "   ✓ Enrolled $affected teachers in default school\n";
} else {
    $errors[] = "Failed to enroll teachers: " . mysqli_error($conn);
    echo "   ✗ Error: " . mysqli_error($conn) . "\n";
}

// Step 11: Create indexes for performance
echo "Step 11: Creating performance indexes...\n";

// Index on student.school_id
$check_idx = mysqli_query($conn, "SHOW INDEX FROM student WHERE Key_name = 'idx_student_school'");
if (mysqli_num_rows($check_idx) == 0) {
    mysqli_query($conn, "CREATE INDEX idx_student_school ON student(school_id)");
    echo "   ✓ Created index on student.school_id\n";
} else {
    echo "   ✓ Index on student.school_id already exists\n";
}

// Index on exm_list.school_id
$check_idx = mysqli_query($conn, "SHOW INDEX FROM exm_list WHERE Key_name = 'idx_exam_school'");
if (mysqli_num_rows($check_idx) == 0) {
    mysqli_query($conn, "CREATE INDEX idx_exam_school ON exm_list(school_id)");
    echo "   ✓ Created index on exm_list.school_id\n";
} else {
    echo "   ✓ Index on exm_list.school_id already exists\n";
}

// Index on mock_exm_list.school_id
$check_idx = mysqli_query($conn, "SHOW INDEX FROM mock_exm_list WHERE Key_name = 'idx_mock_exam_school'");
if (mysqli_num_rows($check_idx) == 0) {
    mysqli_query($conn, "CREATE INDEX idx_mock_exam_school ON mock_exm_list(school_id)");
    echo "   ✓ Created index on mock_exm_list.school_id\n";
} else {
    echo "   ✓ Index on mock_exm_list.school_id already exists\n";
}

// Index on teacher_schools
$check_idx = mysqli_query($conn, "SHOW INDEX FROM teacher_schools WHERE Key_name = 'idx_teacher_school_primary'");
if (mysqli_num_rows($check_idx) == 0) {
    mysqli_query($conn, "CREATE INDEX idx_teacher_school_primary ON teacher_schools(teacher_id, is_primary)");
    echo "   ✓ Created index on teacher_schools\n";
} else {
    echo "   ✓ Index on teacher_schools already exists\n";
}

// Summary
echo "\n==============================================\n";
echo "MIGRATION SUMMARY\n";
echo "==============================================\n";

echo "\n✓ Successful operations: " . count($success) . "\n";
foreach ($success as $s) {
    echo "   - $s\n";
}

if (count($errors) > 0) {
    echo "\n✗ Errors: " . count($errors) . "\n";
    foreach ($errors as $e) {
        echo "   - $e\n";
    }
} else {
    echo "\n✓ No errors!\n";
}

// Verification
echo "\n==============================================\n";
echo "VERIFICATION\n";
echo "==============================================\n";

// Check schools table
$schools = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM schools");
$school_count = mysqli_fetch_assoc($schools)['cnt'];
echo "Schools in database: $school_count\n";

// Check teacher enrollments
$enrollments = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM teacher_schools");
$enrollment_count = mysqli_fetch_assoc($enrollments)['cnt'];
echo "Teacher enrollments: $enrollment_count\n";

// Check students with school
$students = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM student WHERE school_id IS NOT NULL");
$student_count = mysqli_fetch_assoc($students)['cnt'];
echo "Students with school: $student_count\n";

// Check exams with school
$exams = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM exm_list WHERE school_id IS NOT NULL");
$exam_count = mysqli_fetch_assoc($exams)['cnt'];
echo "Exams with school: $exam_count\n";

// Check mock exams with school
$mock_exams = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM mock_exm_list WHERE school_id IS NOT NULL");
$mock_exam_count = mysqli_fetch_assoc($mock_exams)['cnt'];
echo "Mock exams with school: $mock_exam_count\n";

echo "\n==============================================\n";
echo "Phase 1 Migration Complete!\n";
echo "==============================================\n";

mysqli_close($conn);
