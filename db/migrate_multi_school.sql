-- =====================================================
-- Multi-School Support Migration Script
-- Version: 1.0
-- Date: December 22, 2025
-- =====================================================

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
VALUES ('Default School', 'DEFAULT001', 'active')
ON DUPLICATE KEY UPDATE school_name = school_name;

-- Step 3: Create teacher-school relationship table (many-to-many)
CREATE TABLE IF NOT EXISTS teacher_schools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    school_id INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_school (teacher_id, school_id)
);

-- Step 4: Add school_id column to student if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'student' 
                   AND COLUMN_NAME = 'school_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE student ADD COLUMN school_id INT DEFAULT 1',
    'SELECT "school_id column already exists in student"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 5: Add school_id column to exm_list if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'exm_list' 
                   AND COLUMN_NAME = 'school_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE exm_list ADD COLUMN school_id INT DEFAULT 1',
    'SELECT "school_id column already exists in exm_list"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 6: Add school_id column to mock_exm_list if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'mock_exm_list' 
                   AND COLUMN_NAME = 'school_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE mock_exm_list ADD COLUMN school_id INT DEFAULT 1',
    'SELECT "school_id column already exists in mock_exm_list"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 7: Migrate all existing students to default school
UPDATE student SET school_id = 1 WHERE school_id IS NULL OR school_id = 0;

-- Step 8: Migrate all existing exams to default school  
UPDATE exm_list SET school_id = 1 WHERE school_id IS NULL OR school_id = 0;

-- Step 9: Migrate all existing mock exams to default school
UPDATE mock_exm_list SET school_id = 1 WHERE school_id IS NULL OR school_id = 0;

-- Step 10: Migrate all existing teachers to default school
INSERT IGNORE INTO teacher_schools (teacher_id, school_id, is_primary)
SELECT id, 1, 1 FROM teacher;

-- Step 11: Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_student_school ON student(school_id);
CREATE INDEX IF NOT EXISTS idx_exam_school ON exm_list(school_id);
CREATE INDEX IF NOT EXISTS idx_mock_exam_school ON mock_exm_list(school_id);
CREATE INDEX IF NOT EXISTS idx_teacher_school_primary ON teacher_schools(teacher_id, is_primary);

-- =====================================================
-- Migration Complete
-- =====================================================
