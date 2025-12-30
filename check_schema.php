<?php
require_once 'db.php';
$res = $conn->query("DESCRIBE payments");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
