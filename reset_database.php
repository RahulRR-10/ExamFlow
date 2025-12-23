<?php

/**
 * Database Reset Script
 * 
 * Clears all data while preserving the schema and multi-school structure.
 * Creates a Default School and sample data for testing.
 * 
 * WARNING: This will DELETE all existing data!
 */

// Prevent accidental execution
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$confirm) {
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Database Reset</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
            }

            .warning {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }

            .danger {
                background: #f8d7da;
                border: 1px solid #dc3545;
                padding: 20px;
                border-radius: 8px;
            }

            h1 {
                color: #dc3545;
            }

            .btn {
                display: inline-block;
                padding: 12px 24px;
                margin: 10px 5px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: bold;
            }

            .btn-danger {
                background: #dc3545;
                color: white;
            }

            .btn-secondary {
                background: #6c757d;
                color: white;
            }

            ul {
                line-height: 1.8;
            }
        </style>
    </head>

    <body>
        <h1>⚠️ Database Reset</h1>

        <div class="warning">
            <h3>This script will:</h3>
            <ul>
                <li>Delete ALL students, teachers, exams, results, and attempts</li>
                <li>Delete ALL schools and recreate the Default School</li>
                <li>Create 2 sample schools for testing</li>
                <li>Create 1 sample teacher enrolled in all schools</li>
                <li>Preserve the database schema</li>
            </ul>
        </div>

        <div class="danger">
            <h3>⛔ WARNING: This action cannot be undone!</h3>
            <p>Make sure you have a backup if you need to preserve any data.</p>
        </div>

        <p>
            <a href="?confirm=yes" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This will DELETE ALL DATA!');">Yes, Reset Database</a>
            <a href="teachers/dash.php" class="btn btn-secondary">Cancel</a>
        </p>
    </body>

    </html>
<?php
    exit;
}

// Execute reset
include('config.php');

echo "<!DOCTYPE html><html><head><title>Database Reset</title>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }";
echo ".success { color: green; } .error { color: red; } .info { color: #0066cc; }</style></head><body>";
echo "<h1>🔄 Database Reset in Progress</h1>";

$errors = [];

// Disable foreign key checks temporarily
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

// Tables to clear (in order to avoid FK issues)
$tables_to_clear = [
    'atmpt_list',
    'mock_atmpt_list',
    'student_answers',
    'mock_qstn_ans',
    'cheat_violations',
    'mock_cheat_violations',
    'qstn_list',
    'mock_qstn_list',
    'exm_list',
    'mock_exm_list',
    'message',
    'student',
    'teacher_schools',
    'teacher',
    'schools'
];

echo "<h2>Step 1: Clearing Tables</h2>";
echo "<ul>";

foreach ($tables_to_clear as $table) {
    // Check if table exists
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($check) > 0) {
        if (mysqli_query($conn, "DELETE FROM $table")) {
            // Reset auto increment
            mysqli_query($conn, "ALTER TABLE $table AUTO_INCREMENT = 1");
            echo "<li class='success'>✅ Cleared table: <strong>$table</strong></li>";
        } else {
            echo "<li class='error'>❌ Failed to clear: $table - " . mysqli_error($conn) . "</li>";
            $errors[] = $table;
        }
    } else {
        echo "<li class='info'>ℹ️ Table not found (skipped): $table</li>";
    }
}

echo "</ul>";

// Re-enable foreign key checks
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

// Create sample schools
echo "<h2>Step 2: Creating Sample Schools</h2>";
echo "<ul>";

$schools = [
    ['Default School', 'DEFAULT001', 'Main Campus, City Center', 'admin@default.edu', '123-456-7890'],
    ['Springfield High School', 'SPR001', '123 Main Street, Springfield', 'admin@springfield.edu', '555-0101'],
    ['Riverside Academy', 'RVS001', '456 River Road, Riverside', 'admin@riverside.edu', '555-0202'],
    ['Greenwood International School', 'GRN001', '789 Forest Avenue, Greenwood', 'admin@greenwood.edu', '555-0303'],
    ['Lakeside Preparatory', 'LKS001', '321 Lake Drive, Lakeside', 'admin@lakesideprep.edu', '555-0404']
];

foreach ($schools as $school) {
    $sql = "INSERT INTO schools (school_name, school_code, address, contact_email, contact_phone, status) 
            VALUES (?, ?, ?, ?, ?, 'active')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssss", $school[0], $school[1], $school[2], $school[3], $school[4]);

    if (mysqli_stmt_execute($stmt)) {
        $id = mysqli_insert_id($conn);
        echo "<li class='success'>✅ Created school: <strong>{$school[0]}</strong> (ID: $id)</li>";
    } else {
        echo "<li class='error'>❌ Failed to create school: {$school[0]}</li>";
        $errors[] = "school: " . $school[0];
    }
}

echo "</ul>";

// Create sample teacher
echo "<h2>Step 3: Creating Sample Teacher</h2>";
echo "<ul>";

$teacher_pass = md5('teacher123'); // Default password: teacher123
$sql = "INSERT INTO teacher (fname, email, dob, gender, uname, pword, subject) 
        VALUES ('Demo Teacher', 'teacher@example.com', '1990-01-01', 'M', 'teacher', ?, 'General')";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $teacher_pass);

if (mysqli_stmt_execute($stmt)) {
    $teacher_id = mysqli_insert_id($conn);
    echo "<li class='success'>✅ Created teacher: <strong>Demo Teacher</strong> (ID: $teacher_id)</li>";
    echo "<li class='info'>   Username: <code>teacher</code> | Password: <code>teacher123</code></li>";

    // Enroll teacher in all schools
    echo "<li>Enrolling teacher in schools...</li>";

    // Get all school IDs
    $schools_result = mysqli_query($conn, "SELECT school_id, school_name FROM schools");
    $first = true;
    while ($school = mysqli_fetch_assoc($schools_result)) {
        $is_primary = $first ? 1 : 0;
        $first = false;

        $enroll_sql = "INSERT INTO teacher_schools (teacher_id, school_id, is_primary, enrollment_status) 
                       VALUES (?, ?, ?, 'active')";
        $stmt = mysqli_prepare($conn, $enroll_sql);
        mysqli_stmt_bind_param($stmt, "iii", $teacher_id, $school['school_id'], $is_primary);

        if (mysqli_stmt_execute($stmt)) {
            $primary_text = $is_primary ? " (PRIMARY)" : "";
            echo "<li class='success'>   ✅ Enrolled in: {$school['school_name']}$primary_text</li>";
        }
    }
} else {
    echo "<li class='error'>❌ Failed to create teacher: " . mysqli_error($conn) . "</li>";
    $errors[] = "teacher";
}

echo "</ul>";

// Summary
echo "<h2>Summary</h2>";

if (count($errors) == 0) {
    echo "<div style='background: #d4edda; border: 1px solid #28a745; padding: 20px; border-radius: 8px;'>";
    echo "<h3 style='color: #28a745; margin-top: 0;'>✅ Database Reset Successful!</h3>";
    echo "<p><strong>Sample credentials created:</strong></p>";
    echo "<table style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'><th style='padding: 10px; border: 1px solid #ddd;'>Role</th><th style='padding: 10px; border: 1px solid #ddd;'>Username</th><th style='padding: 10px; border: 1px solid #ddd;'>Password</th></tr>";
    echo "<tr><td style='padding: 10px; border: 1px solid #ddd;'>Teacher</td><td style='padding: 10px; border: 1px solid #ddd;'><code>teacher</code></td><td style='padding: 10px; border: 1px solid #ddd;'><code>teacher123</code></td></tr>";
    echo "</table>";
    echo "<p style='margin-top: 15px;'><strong>Schools created:</strong></p>";
    echo "<ul style='margin: 5px 0;'><li>Default School</li><li>Springfield High School</li><li>Riverside Academy</li><li>Greenwood International School</li><li>Lakeside Preparatory</li></ul>";
    echo "<p>You can now register students and teachers through the registration pages.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #dc3545; padding: 20px; border-radius: 8px;'>";
    echo "<h3 style='color: #dc3545;'>⚠️ Reset completed with errors</h3>";
    echo "<p>The following items had issues:</p>";
    echo "<ul>";
    foreach ($errors as $err) {
        echo "<li>$err</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<p style='margin-top: 30px;'>";
echo "<a href='login_teacher.php' style='display: inline-block; padding: 12px 24px; background: #17684f; color: white; text-decoration: none; border-radius: 6px; margin-right: 10px;'>Go to Teacher Login</a>";
echo "<a href='login_student.php' style='display: inline-block; padding: 12px 24px; background: #0A2558; color: white; text-decoration: none; border-radius: 6px;'>Go to Student Login</a>";
echo "</p>";

echo "</body></html>";

mysqli_close($conn);
?>