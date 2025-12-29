<?php
session_start();
require_once 'db.php';

// Only define clean() function if it doesn't already exist
if (!function_exists('clean')) {
    function clean($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$message = "";
$error = "";

/* ===================== CONFIRM / REJECT VIOLATION ===================== */
if (isset($_GET['confirm'])) {
    $v_id = (int)$_GET['confirm'];
    try {
        // Fetch violation details to calculate fine
        $v_stmt = $conn->prepare("SELECT v.*, ve.owner_id FROM violations v JOIN vehicles ve ON v.vehicle_id = ve.id WHERE v.id = ?");
        $v_stmt->bind_param("i", $v_id);
        $v_stmt->execute();
        $v_data = $v_stmt->get_result()->fetch_assoc();

        if ($v_data) {
            $conn->begin_transaction();
            
            // 1. Update violation status
            $stmt = $conn->prepare("UPDATE violations SET status = 'confirmed' WHERE id = ?");
            $stmt->bind_param("i", $v_id);
            $stmt->execute();

            // 2. Fetch base fine from settings
            $s_res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'fine_base_amount'");
            $base_fine = ($s_res->fetch_assoc())['setting_value'] ?? 500;
            
            // Speeding often has higher fines
            $amount = $base_fine;
            if ($v_data['violation_type'] === 'speeding' && $v_data['speed_actual'] > $v_data['speed_limit']) {
                $over = $v_data['speed_actual'] - $v_data['speed_limit'];
                $amount += ($over * 10); // Example: 10 ETB per km/h over
            }

            // 3. Create payment record
            $pay_stmt = $conn->prepare("
                INSERT INTO payments (owner_id, violation_id, payment_type, amount, penalty_reason, payment_status)
                VALUES (?, ?, 'violation', ?, ?, 'pending')
            ");
            $reason = "Fine for " . str_replace('_', ' ', $v_data['violation_type']);
            $pay_stmt->bind_param("iids", $v_data['owner_id'], $v_id, $amount, $reason);
            $pay_stmt->execute();

            // 4. Create notification for owner
            $notif_stmt = $conn->prepare("
                INSERT INTO notifications 
                (owner_id, title, message, reference_id)
                VALUES (?, ?, ?, ?)
            ");
            $notif_title = "Violation Confirmed - Payment Required";
            $notif_message = "A traffic violation has been confirmed for your vehicle. Type: " . str_replace('_', ' ', $v_data['violation_type']) . ". Fine amount: " . number_format($amount, 2) . " ETB. Please pay the fine as soon as possible.";
            $notif_stmt->bind_param("issi", $v_data['owner_id'], $notif_title, $notif_message, $v_id);
            $notif_stmt->execute();

            $conn->commit();
            $message = "Violation confirmed and fine of " . number_format($amount, 2) . " ETB issued. Owner has been notified.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

if (isset($_GET['reject'])) {
    $v_id = (int)$_GET['reject'];
    $stmt = $conn->prepare("UPDATE violations SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $v_id);
    if ($stmt->execute()) {
        $message = "Violation rejected/dismissed.";
    }
}

/* ===================== ADD VIOLATION ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id     = (int) $_POST['vehicle_id'];
    $camera_id      = (int) $_POST['camera_id'];
    $node_id        = (int) $_POST['node_id'];
    $violation_type = clean($_POST['violation_type']);
    $speed_actual   = !empty($_POST['speed_actual']) ? (int)$_POST['speed_actual'] : null;
    $speed_limit    = !empty($_POST['speed_limit']) ? (int)$_POST['speed_limit'] : null;

    try {
        $stmt = $conn->prepare("
            INSERT INTO violations 
            (vehicle_id, camera_id, node_id, violation_type, speed_actual, speed_limit)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiisii",
            $vehicle_id,
            $camera_id,
            $node_id,
            $violation_type,
            $speed_actual,
            $speed_limit
        );
        
        if ($stmt->execute()) {
            $message = "Violation recorded successfully (Pending confirmation).";
        } else {
            $error = "Error recording violation: " . $stmt->error;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/* ===================== FETCH DATA ===================== */

// Vehicles
$vehicles_result = $conn->query("
    SELECT ve.id, ve.plate_number, o.full_name
    FROM vehicles ve
    JOIN owners o ON ve.owner_id = o.id
    ORDER BY ve.plate_number
");
$vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);

// Cameras + Nodes
// Check what column names exist
$columnCheck = $conn->query("SHOW COLUMNS FROM cameras");
$cameraNameColumn = 'camera_name'; // default
while ($row = $columnCheck->fetch_assoc()) {
    $field = strtolower($row['Field']);
    if (in_array($field, ['camera_name', 'name', 'title'])) {
        $cameraNameColumn = $row['Field'];
        break;
    }
}

$cameras_result = $conn->query("
    SELECT c.id, c.$cameraNameColumn, n.id AS node_id, n.name AS node_name
    FROM cameras c
    JOIN nodes n ON c.node_id = n.id
    WHERE c.status = 'active'
");
$cameras = $cameras_result->fetch_all(MYSQLI_ASSOC);

// Violations List
$violations_result = $conn->query("
    SELECT 
        v.*,
        ve.plate_number,
        o.full_name AS owner_name,
        c.$cameraNameColumn AS camera_name,
        n.name AS node_name,
        TIMESTAMPDIFF(HOUR, v.violation_time, NOW()) as hours_ago
    FROM violations v
    LEFT JOIN vehicles ve ON v.vehicle_id = ve.id
    LEFT JOIN owners o ON ve.owner_id = o.id
    LEFT JOIN cameras c ON v.camera_id = c.id
    LEFT JOIN nodes n ON v.node_id = n.id
    ORDER BY v.violation_time DESC
    LIMIT 100
");
$violations = $violations_result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalViolations = count($violations);
$pendingViolations = 0;
$confirmedViolations = 0;
$speedingViolations = 0;

foreach ($violations as $violation) {
    if ($violation['status'] === 'pending') {
        $pendingViolations++;
    } elseif ($violation['status'] === 'confirmed') {
        $confirmedViolations++;
    }
    
    if ($violation['violation_type'] === 'speeding') {
        $speedingViolations++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Violations - Traffic Monitoring System</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/violations.css">
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
                <li class="active"><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> Violations</a></li>
                <li style="margin: 15px 20px; height: 1px; background: #334155;"></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
        <div class="main-content-inner">
<h1><i class="fas fa-exclamation-triangle"></i> Traffic Violations Management</h1>
 <?php if ($message): ?>
<div class="alert success">
<i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
 <div class="alert error">
 <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>
<!-- Quick Stats -->
<div class="stats-container">
<div class="stat-card">
<div class="stat-icon">
<i class="fas fa-exclamation-triangle"></i>
</div>
<div class="stat-info">
<h3><?php echo $totalViolations; ?></h3>
<p>Total Violations</p>
</div>
</div>
 <div class="stat-card">
<div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #d68910);">
 <i class="fas fa-clock"></i>
</div>
<div class="stat-info">
<h3><?php echo $pendingViolations; ?></h3>
<p>Pending Review</p>
</div>
</div>
<div class="stat-card">
<div class="stat-icon" style="background: linear-gradient(135deg, #27ae60, #219653);">
<i class="fas fa-check-circle"></i>
</div>
<div class="stat-info">
<h3><?php echo $confirmedViolations; ?></h3>
<p>Confirmed</p>
</div>
</div>
<div class="stat-card">
<div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
<i class="fas fa-tachometer-alt"></i>
</div>
<div class="stat-info">
<h3><?php echo $speedingViolations; ?></h3>
<p>Speeding Cases</p>
</div>
</div>
</div>
<!-- Filter Section -->
<div class="filter-section">
<div class="filter-row">
<div class="filter-group">
<label>Date Range</label>
<input type="date" id="dateFrom" class="filter-input">
</div>
<div class="filter-group">
<label>to</label>
<input type="date" id="dateTo" class="filter-input">
</div>
<div class="filter-group">
<label>Violation Type</label>
<select id="violationFilter" class="filter-input">
<option value="">All Types</option>
<option value="speeding">Speeding</option>
<option value="red_light">Red Light</option>
<option value="lane_violation">Lane Violation</option>
<option value="illegal_parking">Illegal Parking</option>
<option value="wrong_direction">Wrong Direction</option>
</select>
</div>
<div class="filter-group">
<label>Status</label>
<select id="statusFilter" class="filter-input">
<option value="">All Status</option>
<option value="pending">Pending</option>
<option value="confirmed">Confirmed</option>
<option value="dismissed">Dismissed</option>
</select>
</div>
<button class="search-btn" onclick="applyFilters()">
<i class="fas fa-filter"></i> Apply Filters
</button>
</div>
</div>
 <!-- Action Bar -->
<div class="action-bar">
<h2><i class="fas fa-plus-circle"></i> Record New Violation</h2>
<div class="search-box">
<input type="text" id="searchInput" placeholder="Search violations by plate number or owner...">
<button class="search-btn"><i class="fas fa-search"></i> Search</button>
</div>
</div>
<!-- Add Violation Form -->
<form method="POST" class="form-card">
<div class="form-grid">
<div class="form-group">
<label for="vehicle_id">
<i class="fas fa-car"></i> Vehicle *
</label>
<select id="vehicle_id" name="vehicle_id" required>
<option value="">Select Vehicle</option>
<?php foreach ($vehicles as $v): ?>
<option value="<?php echo $v['id']; ?>">
<?php echo htmlspecialchars($v['plate_number']); ?> — <?php echo htmlspecialchars($v['full_name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label for="camera_id">
<i class="fas fa-video"></i> Camera *
</label>
<select id="camera_id" name="camera_id" required onchange="document.getElementById('node_id').value = this.options[this.selectedIndex].dataset.node;">
<option value="">Select Camera</option>
<?php foreach ($cameras as $c): ?>
<option value="<?php echo $c['id']; ?>" data-node="<?php echo $c['node_id']; ?>">
<?php echo htmlspecialchars($c[$cameraNameColumn]); ?> — <?php echo htmlspecialchars($c['node_name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<input type="hidden" id="node_id" name="node_id">
<div class="form-group">
<label for="violation_type">
<i class="fas fa-exclamation-circle"></i> Violation Type *
</label>
<select id="violation_type" name="violation_type" required>
<option value="speeding">Speeding</option>
<option value="red_light">Red Light</option>
<option value="lane_violation">Lane Violation</option>
<option value="illegal_parking">Illegal Parking</option>
<option value="wrong_direction">Wrong Direction</option>
</select>
</div>
<div class="form-group">
<label for="speed_actual">
<i class="fas fa-tachometer-alt"></i> Actual Speed (km/h)
</label>
<input type="number" id="speed_actual" name="speed_actual" min="0" max="300" placeholder="e.g., 80">
</div>
 <div class="form-group">
<label for="speed_limit">
 <i class="fas fa-gauge-high"></i> Speed Limit (km/h)
</label>
<input type="number" id="speed_limit" name="speed_limit" min="0" max="300" placeholder="e.g., 60">
</div>
</div>
<div class="form-actions">
<button type="submit" class="btn-record">
<i class="fas fa-paper-plane"></i> Record Violation
</button>
</div>
</form>
<!-- Violations Table -->
<h2><i class="fas fa-list"></i> Recent Violations (<?php echo $totalViolations; ?>)</h2>
<div class="data-table-container">
<table class="data-table">
<thead>
<tr>
<th>Violation Details</th>
<th>Vehicle & Owner</th>
<th>Location</th>
 <th>Speed</th>
<th>Status</th>
<th>Time</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (!empty($violations)): ?>
<?php foreach ($violations as $row): ?>
 <tr>
<td>
<div class="violation-info">
<span class="violation-badge badge-<?php echo htmlspecialchars($row['violation_type']); ?>">
<?php echo ucfirst(str_replace('_',' ',$row['violation_type'])); ?>
</span>
</div>
</td>
<td>
<strong><?php echo htmlspecialchars($row['plate_number']); ?></strong>
<br>
 <small><?php echo htmlspecialchars($row['owner_name']); ?></small>
</td>
<td>
<?php echo htmlspecialchars($row['camera_name'] ?? 'N/A'); ?>
<br>
<small><?php echo htmlspecialchars($row['node_name'] ?? 'N/A'); ?></small>
</td>
<td>
<?php if ($row['speed_actual']): ?>
<div class="speed-display <?php echo ($row['speed_actual'] > $row['speed_limit']) ? 'speed-over' : 'speed-normal'; ?>">
<i class="fas fa-tachometer-alt speed-icon"></i>
<?php echo $row['speed_actual']; ?>
<?php if ($row['speed_limit']): ?>
/ <?php echo $row['speed_limit']; ?> km/h
<?php if ($row['speed_actual'] > $row['speed_limit']): ?>
<span class="speed-over">(+<?php echo $row['speed_actual'] - $row['speed_limit']; ?>)</span>
<?php endif; ?>
<?php endif; ?>
</div>
<?php else: ?>
<span class="text-muted">-</span>
<?php endif; ?>
</td>
<td>
<span class="status-badge status-<?php echo htmlspecialchars($row['status']); ?>">
<?php echo ucfirst($row['status']); ?>
</span>
</td>
<td>
<div class="time-display">
<?php echo date('M d, Y', strtotime($row['violation_time'])); ?>
<br>
<small><?php echo date('h:i A', strtotime($row['violation_time'])); ?></small>
<?php if ($row['hours_ago'] < 24): ?>
<span class="time-ago">
 <?php echo $row['hours_ago'] == 0 ? 'Just now' : $row['hours_ago'] . ' hours ago'; ?>
</span>
<?php endif; ?>
</div>
</td>
<td>
<div class="action-buttons">
<?php if ($row['status'] === 'pending'): ?>
    <a href="?confirm=<?php echo $row['id']; ?>" class="btn-action btn-confirm" title="Confirm & Issue Fine" onclick="return confirm('Confirm this violation and issue a fine?')">
        <i class="fas fa-check"></i>
    </a>
    <a href="?reject=<?php echo $row['id']; ?>" class="btn-action btn-delete" title="Dismiss Violation" onclick="return confirm('Reject this violation?')">
        <i class="fas fa-times"></i>
    </a>
<?php else: ?>
    <span class="btn-action btn-view" style="opacity: 0.5; cursor: not-allowed;" title="Processed">
        <i class="fas fa-lock"></i>
    </span>
<?php endif; ?>
<a href="#" class="btn-action btn-view" title="View Details">
    <i class="fas fa-eye"></i>
</a>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="7">
<div class="empty-state">
<i class="fas fa-exclamation-triangle fa-4x"></i>
<h3>No Violations Found</h3>
<p>No traffic violations have been recorded yet.</p>
</div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
    <!-- Pagination (if needed) -->
<div class="pagination">
<span>Showing <?php echo min(100, $totalViolations); ?> of <?php echo $totalViolations; ?> violations</span>
<div class="pagination-controls">
<button class="pagination-btn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
<span class="pagination-info">Page 1</span>
<button class="pagination-btn">Next <i class="fas fa-chevron-right"></i></button>
</div>
</div>
</div>
<script>
    // Search functionality
    document.getElementById('searchInput')?.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('.data-table tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    // Apply filters
    function applyFilters() {
        const typeFilter = document.getElementById('violationFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        // In a real application, this would make an AJAX request or reload the page with filters
        alert('Filter functionality would be implemented here.\nFilters applied:\n' +
              'Type: ' + (typeFilter || 'All') + '\n' +
              'Status: ' + (statusFilter || 'All') + '\n' +
              'Date Range: ' + (dateFrom || 'Any') + ' to ' + (dateTo || 'Any'));
    }
    
    // Auto-calculate and validate speed
    document.getElementById('speed_actual')?.addEventListener('blur', function() {
        const speedLimit = document.getElementById('speed_limit').value;
        const speedActual = this.value;
        
        if (speedActual && speedLimit && speedActual > speedLimit) {
            // Highlight overspeed
            this.style.borderColor = '#e74c3c';
            this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.2)';
        } else {
            this.style.borderColor = '#dee2e6';
            this.style.boxShadow = 'none';
        }
    });
    
    // Form validation
    document.querySelector('.form-card')?.addEventListener('submit', function(e) {
        const vehicleId = document.getElementById('vehicle_id').value;
        const cameraId = document.getElementById('camera_id').value;
        const violationType = document.getElementById('violation_type').value;
        const speedActual = document.getElementById('speed_actual').value;
        const speedLimit = document.getElementById('speed_limit').value;
        
        if (!vehicleId) {
            e.preventDefault();
            alert('Please select a vehicle!');
            return false;
        }
        
        if (!cameraId) {
            e.preventDefault();
            alert('Please select a camera!');
            return false;
        }
        
        if (speedActual && speedLimit && parseInt(speedActual) < parseInt(speedLimit)) {
            if (!confirm('Actual speed is below the speed limit. Are you sure this is a speeding violation?')) {
                e.preventDefault();
                return false;
            }
        }
        
        return true;
    });
    
    // Focus on search input on page load
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
        }
        
        // Set default date filters to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('dateFrom')?.value = today;
        document.getElementById('dateTo')?.value = today;
    });
</script>
        </div>
    </main>
</div>
</body>
</html>