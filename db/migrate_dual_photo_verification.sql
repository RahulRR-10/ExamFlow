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

-- Add teacher statistics view (for performance)
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
