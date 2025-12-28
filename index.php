<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}
require_once 'db.php';
createVehicleTables();
try {
$result = $conn->query("SELECT COUNT(*) AS total FROM nodes");
$row = $result->fetch_assoc();
$totalNodes = $row['total'] ?? 0;
$result = $conn->query("SELECT COUNT(*) AS online FROM nodes WHERE status = 'online'");
$row = $result->fetch_assoc();
$onlineNodes = $row['online'] ?? 0;
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) AS incidents FROM incidents WHERE DATE(reported_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$todayIncidents = ($result->fetch_assoc())['incidents'] ?? 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS violations FROM violations WHERE DATE(violation_time) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$todayViolations = ($result->fetch_assoc())['violations'] ?? 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM vehicles");
$row = $result->fetch_assoc();
$totalVehicles = $row['total'] ?? 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM owners");
$row = $result->fetch_assoc();
$totalOwners = $row['total'] ?? 0;
$sql = "
SELECT i.*, n.name AS node_name
FROM incidents i
LEFT JOIN nodes n ON i.node_id = n.id
ORDER BY reported_at DESC
LIMIT 5
    ";
$result = $conn->query($sql);
$recentIncidents = $result->fetch_all(MYSQLI_ASSOC);
$sql = "
SELECT v.*, n.name AS node_name
FROM violations v
LEFT JOIN nodes n ON v.node_id = n.id
ORDER BY violation_time DESC
 LIMIT 5
    ";
$result = $conn->query($sql);
$recentViolations = $result->fetch_all(MYSQLI_ASSOC);
$sql = "
SELECT v.*, vt.type_name, o.full_name 
FROM vehicles v
LEFT JOIN vehicle_types vt ON v.type_id = vt.id
LEFT JOIN owners o ON v.owner_id = o.id
ORDER BY v.registered_at DESC
LIMIT 5
    ";
$result = $conn->query($sql);
$recentVehicles = $result->fetch_all(MYSQLI_ASSOC);
$nodeHealth = ($totalNodes > 0)
? round(($onlineNodes / $totalNodes) * 100)
        : 0;
$sql = "
SELECT vt.type_name, COUNT(v.id) as count
FROM vehicles v
RIGHT JOIN vehicle_types vt ON v.type_id = vt.id
GROUP BY vt.id
ORDER BY count DESC
    ";
$result = $conn->query($sql);
$vehiclesByType = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
$totalNodes = 0;
$onlineNodes = 0;
$todayIncidents = 0;
$todayViolations = 0;
$totalVehicles = 0;
$totalOwners = 0;
$recentIncidents = [];
$recentViolations = [];
$recentVehicles = [];
$vehiclesByType = [];
 $nodeHealth = 0;
}
$userName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Guest');
$userRole = htmlspecialchars($_SESSION['role'] ?? '');

// Fetch Admin Profile Picture
$admin_profile_pic = '';
if (isset($_SESSION['user_id'])) {
    $stmt_pic = $conn->prepare("SELECT profile_picture FROM admin WHERE id = ?");
    $stmt_pic->bind_param("i", $_SESSION['user_id']);
    $stmt_pic->execute();
    $admin_data = $stmt_pic->get_result()->fetch_assoc();
    $admin_profile_pic = $admin_data['profile_picture'] ?? '';
}
function createVehicleTables() {
    global $conn;
$sql = "
CREATE TABLE IF NOT EXISTS vehicle_types (
id INT AUTO_INCREMENT PRIMARY KEY,
type_name VARCHAR(50) NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
$conn->query($sql);
$result = $conn->query("SELECT COUNT(*) as count FROM vehicle_types");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
$types = ['Sedan', 'SUV', 'Truck', 'Bus', 'Motorcycle'];
foreach ($types as $type) {
 $stmt = $conn->prepare("INSERT INTO vehicle_types (type_name) VALUES (?)");
 $stmt->bind_param("s", $type);
 $stmt->execute();
        }
    }
$sql = "
CREATE TABLE IF NOT EXISTS owners (
id INT AUTO_INCREMENT PRIMARY KEY,
full_name VARCHAR(100) NOT NULL,
phone VARCHAR(20),
address TEXT,
license_number VARCHAR(50),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX idx_phone (phone),
INDEX idx_license (license_number)
        )
    ";
$conn->query($sql);
$sql = "
CREATE TABLE IF NOT EXISTS vehicles (
id INT AUTO_INCREMENT PRIMARY KEY,
plate_number VARCHAR(20) UNIQUE NOT NULL,
type_id INT,
model VARCHAR(50),
year YEAR,
color VARCHAR(30),
owner_id INT,
registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (type_id) REFERENCES vehicle_types(id) ON DELETE SET NULL,
FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE SET NULL,
INDEX idx_plate (plate_number),
INDEX idx_owner (owner_id),
 INDEX idx_type (type_id)
        )
    ";
    $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Traffic Monitoring System</title>
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.css">
</head>
<body>
<div class="container">
        <!-- Sidebar -->
<aside class="sidebar" id="sidebar">
<div class="sidebar-header">
<div class="logo">
<img src="assets/images/logo.png" alt="Logo">
<h2>TrafficSense</h2>
</div>
<button class="toggle-sidebar" id="toggleSidebar">
<i class="fas fa-bars"></i>
</button>
</div>
<div class="sidebar-menu">
<ul>
<li class="active">
<a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
</li>
<li><a href="vehicle.php"><i class="fas fa-car"></i> Vehicles <span class="badge"><?php echo $totalVehicles; ?></span></a></li>
<li><a href="owners.php"><i class="fas fa-users"></i> Owners <span class="badge"><?php echo $totalOwners; ?></span></a></li>
<li><a href="nodes.php"><i class="fas fa-satellite-dish"></i> Nodes <span class="badge"><?php echo $totalNodes; ?></span></a></li>
<li><a href="cameras.php"><i class="fas fa-video"></i> Cameras</a></li>
<li><a href="cameras_live.php"><i class="fas fa-broadcast-tower"></i> Live Surveillance</a></li>
<li><a href="watchlist.php"><i class="fas fa-list-ul"></i> Watchlist</a></li>
<li><a href="traffic_data.php"><i class="fas fa-chart-line"></i> Traffic Data</a></li>
<li><a href="accidents.php"><i class="fas fa-car-crash"></i> Accidents <span class="badge badge-danger"><?php echo $todayIncidents; ?></span></a></li>
                <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> Violations <span class="badge badge-warning"><?php echo $todayViolations; ?></span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </aside>
<!-- Main Content -->
<main class="main-content">
<nav class="navbar">
<div class="navbar-left">
<h1>Dashboard</h1>
<div class="breadcrumb">
<span class="active">Dashboard</span>
</div>
</div>
<div class="navbar-right">
<div class="user-menu">
<div class="user-info">
<span class="user-name"><?php echo $userName; ?></span>
<span class="user-role"><?php echo $userRole; ?></span>
</div>
<div class="user-avatar">
    <?php if(!empty($admin_profile_pic)): ?>
        <img src="<?= htmlspecialchars($admin_profile_pic) ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
    <?php else: ?>
        <i class="fas fa-user-circle"></i>
    <?php endif; ?>
</div>
<div class="dropdown-menu">
<a href="profile.php"><i class="fas fa-user"></i> Profile</a>
<a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
<div class="divider"></div>
<a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
</div>
<a href="notifications.php" class="notification-bell">
<i class="fas fa-bell"></i>
<span class="notification-count">3</span>
</a>
<button class="btn-theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
</div>
 </nav>
 <!-- Stats Cards -->
<div class="stats-grid">
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-car"></i></div>
<div class="stat-info"><h3><?php echo $totalVehicles; ?></h3><p>Total Vehicles</p></div>
<div class="stat-trend <?php echo ($totalVehicles > 0) ? 'positive' : 'negative'; ?>">
<i class="fas fa-arrow-up"></i><span>Registered</span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-users"></i></div>
<div class="stat-info"><h3><?php echo $totalOwners; ?></h3><p>Total Owners</p></div>
<div class="stat-trend <?php echo ($totalOwners > 0) ? 'positive' : 'negative'; ?>">
<i class="fas fa-arrow-up"></i><span>Registered</span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-satellite-dish"></i></div>
<div class="stat-info"><h3><?php echo $totalNodes; ?></h3><p>Total Nodes</p></div>
<div class="stat-trend <?php echo ($onlineNodes > 0) ? 'positive' : 'negative'; ?>">
<i class="fas fa-arrow-up"></i><span><?php echo $onlineNodes; ?> online</span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-car-crash"></i></div>
<div class="stat-info"><h3><?php echo $todayIncidents; ?></h3><p>Today's Incidents</p></div>
<div class="stat-trend <?php echo ($todayIncidents > 5) ? 'negative' : 'positive'; ?>">
<i class="fas fa-arrow-<?php echo ($todayIncidents > 5) ? 'up' : 'down'; ?>"></i>
<span><?php echo ($todayIncidents > 0) ? 'Active' : 'None'; ?></span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
<div class="stat-info"><h3><?php echo $todayViolations; ?></h3><p>Violations Today</p></div>
 <div class="stat-trend <?php echo ($todayViolations > 10) ? 'negative' : 'positive'; ?>">
 <i class="fas fa-arrow-<?php echo ($todayViolations > 10) ? 'up' : 'down'; ?>"></i>
<span><?php echo ($todayViolations > 0) ? 'Reported' : 'Clean'; ?></span>
</div>
</div>
</div>
 <!-- Charts and Activity Grid -->
<div class="dashboard-content">
<!-- Left Column: Charts -->
 <div class="dashboard-left">
<!-- Traffic Volume Chart -->
<div class="chart-card">
<div class="chart-header">
<h3><i class="fas fa-chart-line"></i> Traffic Volume (Last 24 Hours)</h3>
<select class="chart-filter" onchange="updateTrafficChart(this.value)">
<option value="24h">24 Hours</option>
<option value="7d">7 Days</option>
<option value="30d">30 Days</option>
</select>
</div>
<div id="trafficChart"></div>
</div>
<!-- Vehicles by Type Chart -->
<div class="chart-card">
<div class="chart-header">
<h3><i class="fas fa-car"></i> Vehicles by Type</h3>
</div>
<div class="vehicles-type-stats">
<?php if (!empty($vehiclesByType)): ?>
<?php foreach ($vehiclesByType as $type): ?>
<div class="type-item">
 <div class="type-info">
<span class="type-name"><?php echo htmlspecialchars($type['type_name']); ?></span>
<span class="type-count"><?php echo $type['count']; ?></span>
</div>
<div class="type-bar">
<div class="type-progress" style="width: <?php echo ($totalVehicles > 0) ? ($type['count'] / $totalVehicles * 100) : 0; ?>%"></div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="empty-chart">
<i class="fas fa-car"></i>
<p>No vehicles registered yet</p>
</div>
<?php endif; ?>
</div>
</div>
</div>
<!-- Right Column: Activity Lists -->
<div class="dashboard-right">
<!-- Recent Vehicles -->
<div class="activity-card">
<div class="activity-header">
<h3><i class="fas fa-car"></i> Recently Registered Vehicles</h3>
 <a href="vehicle.php" class="view-all">View All</a>
    </div>
<div class="activity-list">
<?php if (!empty($recentVehicles)): ?>
<?php foreach ($recentVehicles as $vehicle): ?>
<div class="activity-item">
<div class="activity-icon vehicle" style="width: 50px; height: 35px; overflow: hidden; border-radius: 4px; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; background: #fff;">
<?php if(!empty($vehicle['vehicle_image'])): ?>
    <img src="<?php echo htmlspecialchars($vehicle['vehicle_image']); ?>" alt="Car" style="width: 100%; height: 100%; object-fit: cover;">
<?php else: ?>
    <i class="fas fa-car" style="color: #adb5bd;"></i>
<?php endif; ?>
</div>
<div class="activity-details">
<h4><?php echo htmlspecialchars($vehicle['plate_number']); ?></h4>
<p><?php echo htmlspecialchars($vehicle['type_name']); ?> - <?php echo htmlspecialchars($vehicle['model'] ?? 'Unknown'); ?></p>
<p class="owner-info">
<i class="fas fa-user"></i> <?php echo htmlspecialchars($vehicle['full_name'] ?? 'Unknown Owner'); ?>
</p>
</div>
<div class="activity-time">
<?php echo date('H:i', strtotime($vehicle['registered_at'])); ?>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="empty-state">
<i class="fas fa-car"></i>
<p>No vehicles registered</p>
<a href="vehicle.php?action=add" class="btn-add">Add First Vehicle</a>
</div>
<?php endif; ?>
</div>
</div>
<!-- Recent Incidents -->
<div class="activity-card">
<div class="activity-header">
<h3><i class="fas fa-car-crash"></i> Recent Incidents</h3>
<a href="accidents.php" class="view-all">View All</a>
</div>
<div class="activity-list">
<?php if (!empty($recentIncidents)): ?>
<?php foreach ($recentIncidents as $incident): ?>
<div class="activity-item">
<div class="activity-icon <?php echo strtolower($incident['severity']); ?>">
<i class="fas fa-exclamation-circle"></i>
</div>
<div class="activity-details">
<h4><?php echo htmlspecialchars($incident['incident_type']); ?></h4>
<p><?php echo htmlspecialchars($incident['node_name'] ?? 'Unknown Node'); ?></p>
<p class="incident-desc"><?php echo htmlspecialchars(substr($incident['description'] ?? '', 0, 50)); ?>...</p>
</div>
<div class="activity-time">
<?php echo date('H:i', strtotime($incident['reported_at'])); ?>
</div>
</div>
 <?php endforeach; ?>
<?php else: ?>
<div class="empty-state">
<i class="fas fa-check-circle"></i>
 <p>No incidents today</p>
</div>
<?php endif; ?>
</div>
</div>
 <!-- Recent Violations -->
<div class="activity-card">
<div class="activity-header">
<h3><i class="fas fa-exclamation-triangle"></i> Recent Violations</h3>
<a href="violations.php" class="view-all">View All</a>
</div>
<div class="activity-list">
<?php if (!empty($recentViolations)): ?>
<?php foreach ($recentViolations as $violation): ?>
<div class="activity-item">
<div class="activity-icon violation">
<i class="fas fa-car"></i>
</div>
<div class="activity-details">
<h4><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $violation['violation_type']))); ?></h4>
<p>
<?php if (!empty($violation['vehicle_number'])): ?>
<i class="fas fa-tag"></i> <?php echo htmlspecialchars($violation['vehicle_number']); ?>
 <?php else: ?>
<i class="fas fa-eye-slash"></i> No plate captured
 <?php endif; ?>
 </p>
<?php if ($violation['violation_type'] == 'speeding'): ?>
<p class="speed-info">
<i class="fas fa-tachometer-alt"></i> 
<?php echo $violation['speed_actual']; ?> km/h in <?php echo $violation['speed_limit']; ?> zone
</p>
<?php endif; ?>
</div>
<div class="activity-time">
<?php echo date('H:i', strtotime($violation['violation_time'])); ?>
 </div>
</div>
 <?php endforeach; ?>
 <?php else: ?>
<div class="empty-state">
<i class="fas fa-check-circle"></i>
<p>No violations today</p>
</div>
<?php endif; ?>
 </div>
</div>
</div>
</div>
<!-- Quick Actions Section -->
<div class="quick-actions">
<h3><i class="fas fa-bolt"></i> Quick Actions</h3>
<div class="actions-grid">
<a href="vehicle.php?action=add" class="action-card">
<div class="action-icon">
<i class="fas fa-plus-circle"></i>
</div>
<h4>Add New Vehicle</h4>
<p>Register a new vehicle in the system</p>
</a>
<a href="owners.php?action=add" class="action-card">
<div class="action-icon">
<i class="fas fa-user-plus"></i>
</div>
<h4>Add New Owner</h4>
<p>Register a new vehicle owner</p>
 </a>
<a href="violations.php?action=add" class="action-card">
<div class="action-icon">
<i class="fas fa-exclamation-circle"></i>
</div>
<h4>Report Violation</h4>
<p>Manually report a traffic violation</p>
</a>
<a href="payments.php" class="action-card">
<div class="action-icon">
<i class="fas fa-ambulance"></i>
 </div>
<h4>Payment</h4>
<p>paid punishment from owners</p>
</a>
<a href="notifications.php" class="action-card">
<div class="action-icon">
<i class="fas fa-search"></i>
 </div>
<h4>Owner Notifications</h4>
<p>Owner Notification Store</p>
</a>
<a href="punishments.php" class="action-card">
<div class="action-icon">
<i class="fas fa-file-alt"></i>
</div>
<h4>punishment</h4>
<p>punishment notification for owners</p>
</a>
</div>
</div>
</main>
</div>
<!-- Vehicle Search Modal -->
<div id="vehicleSearchModal" class="modal">
<div class="modal-content">
<div class="modal-header">
<h3><i class="fas fa-search"></i> Search Vehicle</h3>
<button class="modal-close">&times;</button>
</div>
<div class="modal-body">
<form id="searchVehicleForm">
<div class="form-group">
<label for="searchPlate">Plate Number</label>
<input type="text" id="searchPlate" placeholder="Enter plate number">
</div>
<div class="form-group">
<label for="searchOwner">Owner Name</label>
<input type="text" id="searchOwner" placeholder="Enter owner name">
</div>
<button type="submit" class="btn-search">
<i class="fas fa-search"></i> Search
</button>
</form>
<div id="searchResults" class="search-results"></div>
</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.min.js"></script>
<script src="js/dashboard.js"></script>

</body>
</html>