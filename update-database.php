<?php
require_once 'config.php';

// Check if the is_admin column already exists in the users table
$check_column_sql = "SHOW COLUMNS FROM users LIKE 'is_admin'";
$column_exists = $conn->query($check_column_sql)->num_rows > 0;

if (!$column_exists) {
    // Add the is_admin column to the users table
    $add_column_sql = "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0";
    
    if ($conn->query($add_column_sql) === TRUE) {
        echo "Column 'is_admin' added successfully to the users table.<br>";
        
        // Make the first user an admin
        $update_admin_sql = "UPDATE users SET is_admin = 1 WHERE id = 1";
        if ($conn->query($update_admin_sql) === TRUE) {
            echo "First user (ID: 1) has been set as an admin.<br>";
        } else {
            echo "Error setting admin user: " . $conn->error . "<br>";
        }
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'is_admin' already exists in the users table.<br>";
}

echo "Database update completed.<br>";
echo "<a href='login.php'>Go to Login Page</a>";
?>
