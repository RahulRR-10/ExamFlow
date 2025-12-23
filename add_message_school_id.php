<?php
include 'config.php';

echo "<h2>Adding school_id column to message table</h2>";

// Check if column already exists
$check = mysqli_query($conn, "SHOW COLUMNS FROM message LIKE 'school_id'");
if (mysqli_num_rows($check) > 0) {
    echo "<p style='color: orange;'>Column 'school_id' already exists in message table.</p>";
} else {
    $sql = "ALTER TABLE message ADD COLUMN school_id INT NULL";
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color: green;'>Successfully added 'school_id' column to message table.</p>";
    } else {
        echo "<p style='color: red;'>Error adding column: " . mysqli_error($conn) . "</p>";
    }
}

// Add index for faster queries
$check_index = mysqli_query($conn, "SHOW INDEX FROM message WHERE Key_name = 'idx_message_school'");
if (mysqli_num_rows($check_index) == 0) {
    $index_sql = "ALTER TABLE message ADD INDEX idx_message_school (school_id)";
    if (mysqli_query($conn, $index_sql)) {
        echo "<p style='color: green;'>Successfully added index on school_id.</p>";
    }
}

echo "<p><a href='index.php'>Go to Home</a></p>";
?>
