<?php
require_once 'db.php';

function addColumnIfNotExist($conn, $table, $column, $type) {
    if (!$conn) {
        die("Connection failed");
    }
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $type");
        echo "Added $column to $table\n";
    } else {
        echo "$column already exists in $table\n";
    }
}

addColumnIfNotExist($conn, 'admin', 'profile_picture', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExist($conn, 'owners', 'profile_picture', "VARCHAR(255) DEFAULT NULL");

echo "Migration finished.";
?>
