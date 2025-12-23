<?php

/**
 * Objective Exam System - Database Migration Script
 * 
 * This script creates all necessary tables and directories for the
 * Objective/Descriptive Answer Exam feature.
 * 
 * Run this script once to set up the infrastructure.
 */

// Prevent accidental execution
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$confirm) {
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Setup Objective Exams</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 900px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }

            .card {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                margin-bottom: 20px;
            }

            h1 {
                color: #17684f;
            }

            h2 {
                color: #333;
                border-bottom: 2px solid #17684f;
                padding-bottom: 10px;
            }

            .info {
                background: #e3f2fd;
                border-left: 4px solid #1976d2;
                padding: 15px;
                margin: 15px 0;
            }

            .warning {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 15px 0;
            }

            ul {
                line-height: 2;
            }

            .btn {
                display: inline-block;
                padding: 12px 30px;
                margin: 10px 5px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: bold;
            }

            .btn-primary {
                background: #17684f;
                color: white;
            }

            .btn-secondary {
                background: #6c757d;
                color: white;
            }

            code {
                background: #f4f4f4;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
            }
        </style>
    </head>

    <body>
        <div class="card">
            <h1>🎯 Objective Exam System Setup</h1>

            <div class="info">
                <strong>This script will set up the Objective/Descriptive Exam feature.</strong>
            </div>

            <h2>Tables to be Created</h2>
            <ul>
                <li><code>objective_exm_list</code> - Objective Exam Definitions</li>
                <li><code>objective_questions</code> - Questions for Objective Exams</li>
                <li><code>objective_submissions</code> - Student Answer Submissions</li>
                <li><code>objective_answer_images</code> - Uploaded Answer Sheet Images</li>
                <li><code>objective_answer_grades</code> - Per-Question Grades</li>
            </ul>

            <h2>Directories to be Created</h2>
            <ul>
                <li><code>uploads/answer_keys/</code> - Teacher answer key uploads</li>
                <li><code>uploads/student_answers/</code> - Student answer sheet uploads</li>
                <li><code>uploads/ocr_temp/</code> - Temporary OCR processing files</li>
            </ul>

            <div class="warning">
                <strong>⚠️ Note:</strong> This is safe to run multiple times. Existing tables will not be overwritten.
            </div>

            <p>
                <a href="?confirm=yes" class="btn btn-primary">Run Setup</a>
                <a href="teachers/dash.php" class="btn btn-secondary">Cancel</a>
            </p>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Execute setup
include('config.php');

echo "<!DOCTYPE html><html><head><title>Setup Progress</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
    .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #17684f; }
    .success { color: #155724; background: #d4edda; padding: 8px 15px; border-radius: 4px; margin: 5px 0; }
    .error { color: #721c24; background: #f8d7da; padding: 8px 15px; border-radius: 4px; margin: 5px 0; }
    .info { color: #0c5460; background: #d1ecf1; padding: 8px 15px; border-radius: 4px; margin: 5px 0; }
    .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 6px; }
    .btn { display: inline-block; padding: 12px 30px; margin: 10px 5px; border-radius: 6px; text-decoration: none; font-weight: bold; background: #17684f; color: white; }
</style></head><body><div class='card'>";
echo "<h1>🔧 Setting Up Objective Exam System</h1>";

$errors = [];
$success_count = 0;

// ============================================
// STEP 1: Create Tables
// ============================================
echo "<div class='section'><h2>Step 1: Creating Database Tables</h2>";

// Table 1: objective_exm_list
$sql1 = "CREATE TABLE IF NOT EXISTS objective_exm_list (
    exam_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_name VARCHAR(255) NOT NULL,
    school_id INT NOT NULL,
    teacher_id INT NOT NULL,
    grading_mode ENUM('ai', 'manual') NOT NULL,
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_school (school_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_status (status),
    INDEX idx_exam_date (exam_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql1)) {
    echo "<div class='success'>✅ Table <code>objective_exm_list</code> created/verified</div>";
    $success_count++;
} else {
    echo "<div class='error'>❌ Error creating objective_exm_list: " . mysqli_error($conn) . "</div>";
    $errors[] = 'objective_exm_list';
}

// Table 2: objective_questions
$sql2 = "CREATE TABLE IF NOT EXISTS objective_questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    question_number INT NOT NULL,
    question_text TEXT NOT NULL,
    max_marks INT NOT NULL DEFAULT 10,
    answer_key_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exam (exam_id),
    FOREIGN KEY (exam_id) REFERENCES objective_exm_list(exam_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql2)) {
    echo "<div class='success'>✅ Table <code>objective_questions</code> created/verified</div>";
    $success_count++;
} else {
    echo "<div class='error'>❌ Error creating objective_questions: " . mysqli_error($conn) . "</div>";
    $errors[] = 'objective_questions';
}

// Table 3: objective_submissions
$sql3 = "CREATE TABLE IF NOT EXISTS objective_submissions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql3)) {
    echo "<div class='success'>✅ Table <code>objective_submissions</code> created/verified</div>";
    $success_count++;
} else {
    echo "<div class='error'>❌ Error creating objective_submissions: " . mysqli_error($conn) . "</div>";
    $errors[] = 'objective_submissions';
}

// Add missing columns if table already exists (for upgrades)
$alter_queries = [
    "ALTER TABLE objective_submissions ADD COLUMN IF NOT EXISTS total_marks DECIMAL(5,2) NULL AFTER graded_by",
    "ALTER TABLE objective_submissions ADD COLUMN IF NOT EXISTS scored_marks DECIMAL(5,2) NULL AFTER total_marks"
];
foreach ($alter_queries as $alter_sql) {
    mysqli_query($conn, $alter_sql); // Ignore errors - column may already exist
}

// Table 4: objective_scan_pages (for storing scanned answer sheet pages)
$sql_scan_pages = "CREATE TABLE IF NOT EXISTS objective_scan_pages (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql_scan_pages)) {
    echo "<div class='success'>✅ Table <code>objective_scan_pages</code> created/verified</div>";
    $success_count++;
} else {
    echo "<div class='error'>❌ Error creating objective_scan_pages: " . mysqli_error($conn) . "</div>";
    $errors[] = 'objective_scan_pages';
}

// Table 5: objective_answer_images
$sql4 = "CREATE TABLE IF NOT EXISTS objective_answer_images (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql4)) {
    echo "<div class='success'>✅ Table <code>objective_answer_images</code> created/verified</div>";
    $success_count++;
} else {
    echo "<div class='error'>❌ Error creating objective_answer_images: " . mysqli_error($conn) . "</div>";
    $errors[] = 'objective_answer_images';
}

// Table 5: objective_answer_grades
$sql5 = "CREATE TABLE IF NOT EXISTS objective_answer_grades (
    grade_id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    question_id INT NOT NULL,
    extracted_answer TEXT NULL,
    marks_obtained DECIMAL(5,2) NULL,
    max_marks DECIMAL(5,2) NOT NULL,
    ai_score DECIMAL(5,2) NULL,
    ai_feedback TEXT NULL,
    ai_confidence DECIMAL(5,2) NULL,
    manual_score DECIMAL(5,2) NULL,
    manual_feedback TEXT NULL,
    final_score DECIMAL(5,2) NULL,
    grading_method ENUM('ai', 'manual', 'ai_override') NULL,
    graded_at TIMESTAMP NULL,
    INDEX idx_submission (submission_id),
    INDEX idx_question (question_id),
    FOREIGN KEY (submission_id) REFERENCES objective_submissions(submission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql5)) {
    echo "<div class='success'>✅ Table <code>objective_answer_grades</code> created/verified</div>";
    $success_count++;
} else {
    echo "<div class='error'>❌ Error creating objective_answer_grades: " . mysqli_error($conn) . "</div>";
    $errors[] = 'objective_answer_grades';
}

echo "</div>";

// ============================================
// STEP 2: Create Directories
// ============================================
echo "<div class='section'><h2>Step 2: Creating Upload Directories</h2>";

$directories = [
    'uploads/answer_keys',
    'uploads/student_answers',
    'uploads/ocr_temp'
];

foreach ($directories as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (!file_exists($full_path)) {
        if (mkdir($full_path, 0755, true)) {
            echo "<div class='success'>✅ Created directory: <code>$dir</code></div>";
            $success_count++;
        } else {
            echo "<div class='error'>❌ Failed to create directory: $dir</div>";
            $errors[] = $dir;
        }
    } else {
        echo "<div class='info'>ℹ️ Directory already exists: <code>$dir</code></div>";
        $success_count++;
    }

    // Create .htaccess to prevent direct access
    $htaccess_path = $full_path . '/.htaccess';
    if (!file_exists($htaccess_path)) {
        file_put_contents($htaccess_path, "Options -Indexes\nDeny from all");
    }

    // Create index.php as additional protection
    $index_path = $full_path . '/index.php';
    if (!file_exists($index_path)) {
        file_put_contents($index_path, "<?php header('HTTP/1.0 403 Forbidden'); exit;");
    }
}

echo "</div>";

// ============================================
// STEP 3: Verify Foreign Keys (Optional)
// ============================================
echo "<div class='section'><h2>Step 3: Verification</h2>";

// Check if schools table exists
$check_schools = mysqli_query($conn, "SHOW TABLES LIKE 'schools'");
if (mysqli_num_rows($check_schools) > 0) {
    echo "<div class='success'>✅ Schools table exists - foreign keys compatible</div>";
} else {
    echo "<div class='error'>⚠️ Schools table not found - run multi-school setup first</div>";
}

// Check if teacher table exists
$check_teacher = mysqli_query($conn, "SHOW TABLES LIKE 'teacher'");
if (mysqli_num_rows($check_teacher) > 0) {
    echo "<div class='success'>✅ Teacher table exists - foreign keys compatible</div>";
} else {
    echo "<div class='error'>⚠️ Teacher table not found</div>";
}

// Check if student table exists
$check_student = mysqli_query($conn, "SHOW TABLES LIKE 'student'");
if (mysqli_num_rows($check_student) > 0) {
    echo "<div class='success'>✅ Student table exists - foreign keys compatible</div>";
} else {
    echo "<div class='error'>⚠️ Student table not found</div>";
}

echo "</div>";

// ============================================
// Summary
// ============================================
echo "<div class='section'><h2>Setup Summary</h2>";

if (empty($errors)) {
    echo "<div class='success' style='font-size: 18px; padding: 20px;'>
        🎉 <strong>Setup completed successfully!</strong><br>
        All $success_count components have been created/verified.
    </div>";
} else {
    echo "<div class='error' style='font-size: 18px; padding: 20px;'>
        ⚠️ <strong>Setup completed with errors.</strong><br>
        Failed components: " . implode(', ', $errors) . "
    </div>";
}

echo "<p><a href='teachers/dash.php' class='btn'>Go to Dashboard</a></p>";
echo "</div></div></body></html>";
?>