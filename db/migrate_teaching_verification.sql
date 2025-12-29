-- =====================================================
-- Teaching Activity Verification Tables
-- Version: 1.0
-- Date: December 29, 2025
-- =====================================================

-- Main teaching activity submissions table
CREATE TABLE IF NOT EXISTS teaching_activity_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    school_id INT NOT NULL,

    -- Image storage
    image_path VARCHAR(500) NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    image_size INT NOT NULL,
    image_mime_type VARCHAR(100) NOT NULL,
    ipfs_hash VARCHAR(100) DEFAULT NULL,

    -- GPS/Location data extracted from EXIF
    gps_latitude DECIMAL(10, 8) DEFAULT NULL,
    gps_longitude DECIMAL(11, 8) DEFAULT NULL,
    gps_altitude DECIMAL(10, 2) DEFAULT NULL,
    location_accuracy VARCHAR(50) DEFAULT NULL,

    -- Dates
    activity_date DATE NOT NULL,
    photo_taken_at DATETIME DEFAULT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Verification status
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verified_by INT DEFAULT NULL,
    verified_at TIMESTAMP NULL,
    admin_remarks TEXT,

    -- Location validation
    location_match_status ENUM('matched', 'mismatched', 'unknown') DEFAULT 'unknown',
    distance_from_school DECIMAL(10, 2) DEFAULT NULL,

    -- Metadata
    exif_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (teacher_id) REFERENCES teacher(id),
    FOREIGN KEY (school_id) REFERENCES schools(school_id),
    FOREIGN KEY (verified_by) REFERENCES admin(id)
);

-- Add unique constraint separately to handle potential issues
ALTER TABLE teaching_activity_submissions
    ADD UNIQUE KEY unique_teacher_school_date (teacher_id, school_id, activity_date);

-- School GPS coordinates for location validation
CREATE TABLE IF NOT EXISTS school_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT NOT NULL UNIQUE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    address TEXT,
    validation_radius_meters INT DEFAULT 500,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(school_id)
);

-- Configuration table for verification settings
CREATE TABLE IF NOT EXISTS verification_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO verification_settings (setting_key, setting_value, description) VALUES
('max_file_size_mb', '10', 'Maximum upload file size in megabytes'),
('allowed_formats', 'jpg,jpeg,png,heic', 'Comma-separated allowed image formats'),
('default_radius_meters', '500', 'Default radius for GPS matching in meters'),
('require_gps', 'true', 'Whether GPS data is required for uploads'),
('max_photo_age_days', '7', 'Maximum age of photo based on EXIF date')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Create indexes for performance
CREATE INDEX idx_tas_teacher ON teaching_activity_submissions(teacher_id);
CREATE INDEX idx_tas_school ON teaching_activity_submissions(school_id);
CREATE INDEX idx_tas_status ON teaching_activity_submissions(verification_status);
CREATE INDEX idx_tas_activity_date ON teaching_activity_submissions(activity_date);
CREATE INDEX idx_tas_upload_date ON teaching_activity_submissions(upload_date);
CREATE INDEX idx_tas_verified_by ON teaching_activity_submissions(verified_by);
CREATE INDEX idx_tas_location_match ON teaching_activity_submissions(location_match_status);

-- =====================================================
-- Migration Complete
-- =====================================================
