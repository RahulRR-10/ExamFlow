-- =====================================================
-- Dual Photo Verification Rollback Script
-- Reverts to single photo verification
-- WARNING: Data in end_photo fields will be lost!
-- Date: December 2025
-- =====================================================

-- Drop the view first
DROP VIEW IF EXISTS teacher_session_stats;

-- Drop indexes
DROP INDEX idx_session_start_photo ON teaching_sessions;
DROP INDEX idx_session_end_photo ON teaching_sessions;
DROP INDEX idx_session_duration ON teaching_sessions;

-- Remove duration verification fields
ALTER TABLE teaching_sessions
    DROP COLUMN IF EXISTS actual_duration_minutes,
    DROP COLUMN IF EXISTS expected_duration_minutes,
    DROP COLUMN IF EXISTS duration_verified;

-- Remove end photo fields
ALTER TABLE teaching_sessions
    DROP COLUMN IF EXISTS end_photo_path,
    DROP COLUMN IF EXISTS end_photo_uploaded_at,
    DROP COLUMN IF EXISTS end_gps_latitude,
    DROP COLUMN IF EXISTS end_gps_longitude,
    DROP COLUMN IF EXISTS end_photo_taken_at,
    DROP COLUMN IF EXISTS end_distance_from_school;

-- Rename start photo fields back to original names
ALTER TABLE teaching_sessions 
    CHANGE COLUMN start_photo_path photo_path VARCHAR(500) NULL,
    CHANGE COLUMN start_photo_uploaded_at photo_uploaded_at TIMESTAMP NULL,
    CHANGE COLUMN start_gps_latitude gps_latitude DECIMAL(10, 8) NULL,
    CHANGE COLUMN start_gps_longitude gps_longitude DECIMAL(11, 8) NULL,
    CHANGE COLUMN start_photo_taken_at photo_taken_at DATETIME NULL,
    CHANGE COLUMN start_distance_from_school distance_from_school DECIMAL(10, 2) NULL;

-- Revert ENUM to original values
-- Note: Sessions with new statuses will need to be handled first
ALTER TABLE teaching_sessions 
    MODIFY COLUMN session_status ENUM(
        'pending', 'photo_submitted', 'approved', 'rejected'
    ) DEFAULT 'pending';

SELECT 'Dual Photo Verification Rollback Complete!' as status;
