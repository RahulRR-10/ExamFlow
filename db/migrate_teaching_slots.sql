-- =====================================================
-- Teaching Slots Migration Script
-- Phase 1 & 2: Database tables for slot management
-- Date: December 30, 2025
-- =====================================================

-- Table 1: school_teaching_slots - Admin-created teaching time slots
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

-- Table 2: slot_teacher_enrollments - Teacher bookings for slots
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

-- Table 3: teaching_sessions - Individual session records with photo proofs
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

-- Add additional columns to schools table if not exists
ALTER TABLE schools ADD COLUMN IF NOT EXISTS contact_person VARCHAR(100) NULL;
ALTER TABLE schools ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(20) NULL;
ALTER TABLE schools ADD COLUMN IF NOT EXISTS contact_email VARCHAR(100) NULL;
ALTER TABLE schools ADD COLUMN IF NOT EXISTS school_type ENUM('primary', 'secondary', 'higher_secondary', 'college', 'other') DEFAULT 'secondary';
ALTER TABLE schools ADD COLUMN IF NOT EXISTS full_address TEXT NULL;

-- Create directory for session photos
-- Note: This needs to be done via PHP or manually

-- =====================================================
-- Phase 8: Performance Optimization - Additional Indexes
-- =====================================================

-- Composite indexes for common queries
CREATE INDEX IF NOT EXISTS idx_slot_date_status ON school_teaching_slots(slot_date, slot_status);
CREATE INDEX IF NOT EXISTS idx_enrollment_status_teacher ON slot_teacher_enrollments(teacher_id, enrollment_status);
CREATE INDEX IF NOT EXISTS idx_session_teacher_status ON teaching_sessions(teacher_id, session_status);
CREATE INDEX IF NOT EXISTS idx_session_school_status ON teaching_sessions(school_id, session_status);
CREATE INDEX IF NOT EXISTS idx_session_enrollment ON teaching_sessions(enrollment_id);

-- Index for admin verification queue
CREATE INDEX IF NOT EXISTS idx_session_verification ON teaching_sessions(session_status, verified_by);

-- Index for photo timestamp queries
CREATE INDEX IF NOT EXISTS idx_session_photo_date ON teaching_sessions(photo_uploaded_at);

SELECT 'Teaching Slots Migration Complete!' as status;
