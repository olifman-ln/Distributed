<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../auth/login.php");
  exit();
}
$success = "";
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $owner_id     = (int)$_POST['owner_id'];
  $type         = $_POST['type']; // violation | accident
  $reference_id = (int)$_POST['reference_id'];
  $amount       = (float)$_POST['amount'];
  $message      = trim($_POST['message']);

  if ($owner_id && $type && $reference_id && $amount > 0) {

    /* ---- 1. INSERT PAYMENT ---- */
    $stmt = $conn->prepare("
            INSERT INTO payments 
            (violation_id, owner_id, amount, payment_type, payment_status)
            VALUES (?, ?, ?, ?, 'pending')
        ");

    $violation_id = ($type === 'violation') ? $reference_id : NULL;

    $stmt->bind_param(
      "iids",
      $violation_id,
      $owner_id,
      $amount,
      $type
    );

    if ($stmt->execute()) {

      $payment_id = $conn->insert_id;

      /* ---- 2. INSERT NOTIFICATION ---- */
      $title = ucfirst($type) . " Punishment Issued";

      $stmt2 = $conn->prepare("
                INSERT INTO notifications 
                (owner_id, title, message, reference_id)
                VALUES (?, ?, ?, ?)
            ");

      $stmt2->bind_param(
        "issi",
        $owner_id,
        $title,
        $message,
        $reference_id
      );

      $stmt2->execute();

      $success = "Punishment issued successfully.";
    } else {
      $error = "Failed to issue punishment.";
    }
  } else {
    $error = "All fields are required.";
  }
}
$owners = $conn->query("
SELECT id, full_name 
FROM owners 
ORDER BY full_name
")->fetch_all(MYSQLI_ASSOC);
$violations = $conn->query("
    SELECT v.id, ve.plate_number, v.violation_type
    FROM violations v
    JOIN vehicles ve ON v.vehicle_id = ve.id
    WHERE v.status IN ('confirmed','pending')
")->fetch_all(MYSQLI_ASSOC);
$accidents = $conn->query("
SELECT i.id, ve.plate_number, i.severity
FROM incidents i
JOIN vehicles ve ON i.vehicle_id = ve.id
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punishments Management - Traffic Monitoring System</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/punishments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
<div class="container">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo"><h2>TrafficSense</h2></div>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="vehicle.php"><i class="fas fa-car"></i> Vehicles</a></li>
                <li><a href="owners.php"><i class="fas fa-users"></i> Owners</a></li>
                <li><a href="nodes.php"><i class="fas fa-satellite-dish"></i> Nodes</a></li>
                <li><a href="cameras.php"><i class="fas fa-video"></i> Cameras</a></li>
                <li><a href="cameras_live.php"><i class="fas fa-broadcast-tower"></i> Live Surveillance</a></li>
                <li><a href="watchlist.php"><i class="fas fa-list-ul"></i> Watchlist</a></li>
                <li><a href="traffic_data.php"><i class="fas fa-chart-line"></i> Traffic Data</a></li>
                <li><a href="accidents.php"><i class="fas fa-car-crash"></i> Accidents</a></li>
                <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> Violations</a></li>
                <li style="margin: 15px 20px; height: 1px; background: #334155;"></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
        <div class="main-content-inner">
            <h1><i class="fas fa-gavel"></i> Issue Punishment</h1>

            <?php if ($success): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Owner</label>
                            <select name="owner_id" required>
                                <option value="">Select Owner</option>
                                <?php foreach ($owners as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Punishment Type</label>
                            <select name="type" id="typeSelect" required onchange="toggleReference()">
                                <option value="">Select Type</option>
                                <option value="violation">Violation</option>
                                <option value="accident">Accident</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Violation / Accident Reference</label>
                            <select name="reference_id" id="referenceSelect" required>
                                <option value="">Select</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Amount (ETB)</label>
                            <input type="number" name="amount" min="1" required placeholder="e.g. 500">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label>Message to Owner</label>
                        <textarea name="message" required placeholder="Describe the reason for the punishment..." rows="4"></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Issue Punishment
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    window.violationsData = <?= json_encode($violations) ?>;
    window.accidentsData = <?= json_encode($accidents) ?>;
</script>
<script src="js/punishments.js"></script>
</body>
</html>