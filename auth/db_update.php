<?php
require __DIR__ . '/../db.php';

function addColumns($conn, $table) {
    echo "Checking table '$table'...<br>";
    
    // Check if columns exist
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE 'reset_token'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE $table ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL";
        if ($conn->query($sql) === TRUE) {
            echo "Added 'reset_token' to $table.<br>";
        } else {
            echo "Error adding 'reset_token' to $table: " . $conn->error . "<br>";
        }
    } else {
        echo "'reset_token' already exists in $table.<br>";
    }

    $result = $conn->query("SHOW COLUMNS FROM $table LIKE 'reset_expires'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE $table ADD COLUMN reset_expires DATETIME NULL DEFAULT NULL";
        if ($conn->query($sql) === TRUE) {
            echo "Added 'reset_expires' to $table.<br>";
        } else {
            echo "Error adding 'reset_expires' to $table: " . $conn->error . "<br>";
        }
    } else {
        echo "'reset_expires' already exists in $table.<br>";
    }
}

// Ensure columns exist for both admin and owners tables
addColumns($conn, 'admin');
addColumns($conn, 'owners');

echo "Database update complete.";
?>
