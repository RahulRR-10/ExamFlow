-- Objective Exam Tables

CREATE TABLE IF NOT EXISTS objective_exm_list (
    exam_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_name VARCHAR(255) NOT NULL,
    school_id INT NOT NULL,
    teacher_id INT NOT NULL,
    grading_mode ENUM('ai', 'manual') NOT NULL DEFAULT 'manual',
    answer_key_path VARCHAR(500) NULL,
    answer_key_text LONGTEXT NULL,
    total_marks INT NOT NULL DEFAULT 100,
    passing_marks INT NOT NULL DEFAULT 40,
    exam_instructions TEXT NULL,
    exam_date DATETIME NOT NULL,
    submission_deadline DATETIME NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 60,
    status ENUM('draft', 'active', 'closed', 'graded') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS objective_questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    question_number INT NOT NULL,
    question_text TEXT NOT NULL,
    max_marks INT NOT NULL DEFAULT 10,
    answer_key_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exam (exam_id),
    FOREIGN KEY (exam_id) REFERENCES objective_exm_list(exam_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS objective_submissions (
    submission_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_status ENUM('pending', 'ocr_processing', 'ocr_complete', 'grading', 'graded', 'error') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ocr_completed_at TIMESTAMP NULL,
    graded_at TIMESTAMP NULL,
    graded_by INT NULL,
    total_marks DECIMAL(5,2) NULL,
    scored_marks DECIMAL(5,2) NULL,
    percentage DECIMAL(5,2) NULL,
    pass_status ENUM('pass', 'fail', 'pending') DEFAULT 'pending',
    feedback TEXT NULL,
    INDEX idx_exam (exam_id),
    INDEX idx_student (student_id),
    INDEX idx_status (submission_status),
    UNIQUE KEY unique_submission (exam_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS objective_scan_pages (
    page_id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    page_number INT NOT NULL DEFAULT 1,
    image_path VARCHAR(500) NOT NULL,
    ocr_text LONGTEXT NULL,
    ocr_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    ocr_confidence DECIMAL(5,2) NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_submission (submission_id),
    INDEX idx_status (ocr_status),
    FOREIGN KEY (submission_id) REFERENCES objective_submissions(submission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS objective_answer_images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_order INT NOT NULL DEFAULT 1,
    ocr_text LONGTEXT NULL,
    ocr_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    ocr_confidence DECIMAL(5,2) NULL,
    ocr_error_message TEXT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_submission (submission_id),
    INDEX idx_ocr_status (ocr_status),
    FOREIGN KEY (submission_id) REFERENCES objective_submissions(submission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS objective_answer_grades (
    grade_id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    question_id INT NOT NULL,
    student_answer_text TEXT NULL,
    ai_suggested_marks DECIMAL(5,2) NULL,
    ai_feedback TEXT NULL,
    final_marks DECIMAL(5,2) NULL,
    teacher_feedback TEXT NULL,
    graded_by ENUM('ai', 'teacher', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_submission (submission_id),
    INDEX idx_question (question_id),
    FOREIGN KEY (submission_id) REFERENCES objective_submissions(submission_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES objective_questions(question_id) ON DELETE CASCADE,
    UNIQUE KEY unique_grade (submission_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
