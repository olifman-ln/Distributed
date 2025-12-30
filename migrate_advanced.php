<?php
require_once 'db.php';

echo "Applying advanced feature migrations...\n";

// 1. Add coordinates to nodes for mapping
$check_lat = $conn->query("SHOW COLUMNS FROM nodes LIKE 'lat'");
if ($check_lat->num_rows == 0) {
    $conn->query("ALTER TABLE nodes ADD COLUMN lat DECIMAL(10, 8) DEFAULT NULL");
}
$check_lng = $conn->query("SHOW COLUMNS FROM nodes LIKE 'lng'");
if ($check_lng->num_rows == 0) {
    $conn->query("ALTER TABLE nodes ADD COLUMN lng DECIMAL(11, 8) DEFAULT NULL");
}

// 2. Create Watchlist table
$conn->query("
CREATE TABLE IF NOT EXISTS watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) UNIQUE NOT NULL,
    reason TEXT,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

// 3. Add status and evidence to violations
$check_status = $conn->query("SHOW COLUMNS FROM violations LIKE 'status'");
if ($check_status->num_rows == 0) {
    $conn->query("ALTER TABLE violations ADD COLUMN status ENUM('pending', 'confirmed', 'rejected') DEFAULT 'pending'");
}
$check_evidence = $conn->query("SHOW COLUMNS FROM violations LIKE 'evidence_image'");
if ($check_evidence->num_rows == 0) {
    $conn->query("ALTER TABLE violations ADD COLUMN evidence_image VARCHAR(255) DEFAULT NULL");
}

// 4. Update existing violations to 'pending' if they had no status
$conn->query("UPDATE violations SET status = 'pending' WHERE status IS NULL");

echo "Migration complete.\n";
?>
