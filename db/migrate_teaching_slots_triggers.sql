-- =====================================================
-- Teaching Slots Triggers Migration Script
-- Phase 2: Auto-update enrollment counts and status
-- Date: December 30, 2025
-- =====================================================

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS trg_enrollment_insert;
DROP TRIGGER IF EXISTS trg_enrollment_update;
DROP TRIGGER IF EXISTS trg_session_status_update;

DELIMITER //

-- Trigger 1: After inserting a new enrollment, update slot counts
CREATE TRIGGER trg_enrollment_insert
AFTER INSERT ON slot_teacher_enrollments
FOR EACH ROW
BEGIN
    IF NEW.enrollment_status = 'booked' THEN
        UPDATE school_teaching_slots 
        SET teachers_enrolled = teachers_enrolled + 1,
            slot_status = CASE 
                WHEN teachers_enrolled + 1 >= teachers_required THEN 'full'
                WHEN teachers_enrolled + 1 > 0 THEN 'partially_filled'
                ELSE 'open'
            END
        WHERE slot_id = NEW.slot_id;
    END IF;
END//

-- Trigger 2: After updating an enrollment (cancel/complete), update slot counts
CREATE TRIGGER trg_enrollment_update
AFTER UPDATE ON slot_teacher_enrollments
FOR EACH ROW
BEGIN
    -- If enrollment was cancelled (from booked)
    IF OLD.enrollment_status = 'booked' AND NEW.enrollment_status IN ('cancelled', 'no_show') THEN
        UPDATE school_teaching_slots 
        SET teachers_enrolled = GREATEST(0, teachers_enrolled - 1),
            slot_status = CASE 
                WHEN GREATEST(0, teachers_enrolled - 1) = 0 THEN 'open'
                WHEN GREATEST(0, teachers_enrolled - 1) < teachers_required THEN 'partially_filled'
                ELSE 'full'
            END
        WHERE slot_id = NEW.slot_id;
    END IF;
    
    -- If enrollment was restored (from cancelled back to booked)
    IF OLD.enrollment_status IN ('cancelled', 'no_show') AND NEW.enrollment_status = 'booked' THEN
        UPDATE school_teaching_slots 
        SET teachers_enrolled = teachers_enrolled + 1,
            slot_status = CASE 
                WHEN teachers_enrolled + 1 >= teachers_required THEN 'full'
                WHEN teachers_enrolled + 1 > 0 THEN 'partially_filled'
                ELSE 'open'
            END
        WHERE slot_id = NEW.slot_id;
    END IF;
END//

-- Trigger 3: Auto-create teaching session when teacher enrolls
CREATE TRIGGER trg_create_session_on_enroll
AFTER INSERT ON slot_teacher_enrollments
FOR EACH ROW
BEGIN
    DECLARE v_slot_date DATE;
    DECLARE v_school_id INT;
    
    IF NEW.enrollment_status = 'booked' THEN
        -- Get slot details
        SELECT slot_date, school_id INTO v_slot_date, v_school_id
        FROM school_teaching_slots WHERE slot_id = NEW.slot_id;
        
        -- Create pending session for this enrollment
        INSERT INTO teaching_sessions (
            enrollment_id, slot_id, teacher_id, school_id, 
            session_date, session_status
        ) VALUES (
            NEW.enrollment_id, NEW.slot_id, NEW.teacher_id, v_school_id,
            v_slot_date, 'pending'
        );
    END IF;
END//

DELIMITER ;

-- Create event to auto-complete past slots (runs daily)
-- Note: Requires event scheduler to be enabled: SET GLOBAL event_scheduler = ON;
DROP EVENT IF EXISTS evt_complete_past_slots;

CREATE EVENT evt_complete_past_slots
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO
    UPDATE school_teaching_slots 
    SET slot_status = 'completed'
    WHERE slot_date < CURDATE() 
    AND slot_status NOT IN ('completed', 'cancelled');

SELECT 'Teaching Slots Triggers Created Successfully!' as status;
