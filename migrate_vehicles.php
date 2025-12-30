<?php
require_once 'db.php';

echo "Checking vehicles table...\n";
$result = $conn->query("DESCRIBE vehicles");
$hasImage = false;
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
    if ($row['Field'] == 'vehicle_image') {
        $hasImage = true;
    }
}

if (!$hasImage) {
    echo "Adding vehicle_image column...\n";
    $conn->query("ALTER TABLE vehicles ADD COLUMN vehicle_image VARCHAR(255) DEFAULT NULL");
    echo "Done.\n";
} else {
    echo "vehicle_image column already exists.\n";
}
?>
