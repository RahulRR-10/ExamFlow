-- =====================================================
-- PHASE 5: Enhanced Location Validation Settings
-- Run this migration to add advanced validation options
-- =====================================================

-- Add enhanced validation settings
INSERT INTO verification_settings (setting_key, setting_value, description) VALUES
('strict_date_validation', 'true', 'Reject photos if EXIF date does not match activity date'),
('date_tolerance_days', '1', 'Days tolerance for date validation (0 = exact match required)'),
('block_future_dates', 'true', 'Prevent activity dates in the future'),
('min_gps_accuracy', '100', 'Minimum GPS accuracy in meters (if available in EXIF)'),
('allow_no_gps_approval', 'true', 'Allow admin to approve submissions without GPS data'),
('daily_upload_limit', '5', 'Maximum uploads per teacher per day'),
('enable_device_tracking', 'true', 'Track and display device info from EXIF'),
('suspicious_distance_threshold', '1000', 'Distance in meters that triggers suspicious flag'),
('enable_auto_reject', 'false', 'Automatically reject submissions exceeding suspicious distance'),
('require_activity_description', 'false', 'Require teachers to provide activity description')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Add new columns to teaching_activity_submissions for enhanced tracking
ALTER TABLE teaching_activity_submissions
ADD COLUMN IF NOT EXISTS activity_description TEXT DEFAULT NULL AFTER activity_date,
ADD COLUMN IF NOT EXISTS device_make VARCHAR(100) DEFAULT NULL AFTER exif_data,
ADD COLUMN IF NOT EXISTS device_model VARCHAR(100) DEFAULT NULL AFTER device_make,
ADD COLUMN IF NOT EXISTS gps_accuracy DECIMAL(10,2) DEFAULT NULL AFTER gps_altitude,
ADD COLUMN IF NOT EXISTS is_suspicious TINYINT(1) DEFAULT 0 AFTER location_match_status,
ADD COLUMN IF NOT EXISTS suspicious_reason VARCHAR(255) DEFAULT NULL AFTER is_suspicious,
ADD COLUMN IF NOT EXISTS upload_ip VARCHAR(45) DEFAULT NULL AFTER upload_date;

-- Create index for suspicious submissions
CREATE INDEX IF NOT EXISTS idx_suspicious ON teaching_activity_submissions(is_suspicious);

-- Create rate limiting tracking table
CREATE TABLE IF NOT EXISTS upload_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    upload_date DATE NOT NULL,
    upload_count INT DEFAULT 1,
    last_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_date (teacher_id, upload_date),
    FOREIGN KEY (teacher_id) REFERENCES teacher(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for efficient lookups
CREATE INDEX IF NOT EXISTS idx_rate_limit_lookup ON upload_rate_limits(teacher_id, upload_date);

SELECT 'Phase 5 migration complete - Enhanced validation settings added' AS status;
