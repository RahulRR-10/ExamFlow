<?php

/**
 * Fix teacher_schools table schema
 * Corrects column names to match PHP code
 */

include('config.php');

echo "Fixing teacher_schools table schema...\n\n";

// Step 1: Rename tid to teacher_id
echo "Step 1: Renaming 'tid' to 'teacher_id'...\n";
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM teacher_schools LIKE 'tid'");
if (mysqli_num_rows($check_col) > 0) {
    $sql1 = "ALTER TABLE teacher_schools CHANGE tid teacher_id INT NOT NULL";
    if (mysqli_query($conn, $sql1)) {
        echo "   ✓ Column 'tid' renamed to 'teacher_id'\n";
    } else {
        echo "   ✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✓ Column already named 'teacher_id'\n";
}

// Step 2: Add is_primary column if not exists
echo "Step 2: Adding 'is_primary' column...\n";
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM teacher_schools LIKE 'is_primary'");
if (mysqli_num_rows($check_col) == 0) {
    $sql2 = "ALTER TABLE teacher_schools ADD COLUMN is_primary TINYINT(1) DEFAULT 0";
    if (mysqli_query($conn, $sql2)) {
        echo "   ✓ Column 'is_primary' added\n";
    } else {
        echo "   ✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✓ Column 'is_primary' already exists\n";
}

// Step 3: Set is_primary = 1 for existing enrollments (first enrollment per teacher)
echo "Step 3: Setting primary schools for existing teachers...\n";
$sql3 = "UPDATE teacher_schools ts
         INNER JOIN (
             SELECT MIN(id) as first_id
             FROM teacher_schools
             GROUP BY teacher_id
         ) first_schools ON ts.id = first_schools.first_id
         SET ts.is_primary = 1";
if (mysqli_query($conn, $sql3)) {
    $affected = mysqli_affected_rows($conn);
    echo "   ✓ Set $affected teacher(s) primary school\n";
} else {
    echo "   ✗ Error: " . mysqli_error($conn) . "\n";
}

// Step 4: Update unique key to use teacher_id
echo "Step 4: Updating unique key...\n";
// First, drop the old unique key if it exists
$result = mysqli_query($conn, "SHOW INDEX FROM teacher_schools WHERE Key_name = 'unique_teacher_school'");
if (mysqli_num_rows($result) > 0) {
    mysqli_query($conn, "ALTER TABLE teacher_schools DROP INDEX unique_teacher_school");
    echo "   ✓ Old unique key dropped\n";
}

$sql4 = "ALTER TABLE teacher_schools ADD UNIQUE KEY unique_teacher_school (teacher_id, school_id)";
if (mysqli_query($conn, $sql4)) {
    echo "   ✓ New unique key created\n";
} else {
    // May already exist with correct columns
    echo "   Note: " . mysqli_error($conn) . "\n";
}

echo "\nVerifying final structure:\n";
$result = mysqli_query($conn, 'DESCRIBE teacher_schools');
while ($row = mysqli_fetch_assoc($result)) {
    echo "  - " . $row['Field'] . ' (' . $row['Type'] . ")\n";
}

echo "\nSample data:\n";
$result2 = mysqli_query($conn, 'SELECT * FROM teacher_schools LIMIT 5');
while ($row = mysqli_fetch_assoc($result2)) {
    print_r($row);
}

echo "\n✓ Schema fix complete!\n";
