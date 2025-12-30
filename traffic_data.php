<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

require_once 'db.php';

// Initialize variables
$todayIncidents = 0;
$todayViolations = 0;
$onlineNodes = 0;
$totalNodes = 0;
$totalVehicles = 0;
$recentIncidents = [];
$recentViolations = [];
$trafficVolume = [];
$violationsByNode = [];

try {
    $today = date('Y-m-d');

    // Today's incidents
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM incidents WHERE DATE(reported_at) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayIncidents = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Today's violations
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM violations WHERE DATE(violation_time) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayViolations = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Nodes
    $totalNodes = $conn->query("SELECT COUNT(*) AS total FROM nodes")->fetch_assoc()['total'] ?? 0;
    $onlineNodes = $conn->query("SELECT COUNT(*) AS online FROM nodes WHERE status='online'")->fetch_assoc()['online'] ?? 0;

    // Vehicles
    $totalVehicles = $conn->query("SELECT COUNT(*) AS total FROM vehicles")->fetch_assoc()['total'] ?? 0;

    // Recent incidents
    $recentIncidents = $conn->query("
        SELECT i.*, n.name AS node_name 
        FROM incidents i 
        LEFT JOIN nodes n ON i.node_id = n.id 
        ORDER BY reported_at DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    // Recent violations
    $recentViolations = $conn->query("
        SELECT v.*, n.name AS node_name 
        FROM violations v 
        LEFT JOIN nodes n ON v.node_id = n.id 
        ORDER BY violation_time DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    // Traffic volume per hour
    $stmt = $conn->prepare("
        SELECT HOUR(violation_time) AS hour, COUNT(*) AS count
        FROM violations
        WHERE DATE(violation_time) = ?
        GROUP BY HOUR(violation_time)
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $trafficVolume = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Violations by node
    $violationsByNode = $conn->query("
        SELECT n.name AS node_name, COUNT(v.id) AS count
        FROM violations v
        LEFT JOIN nodes n ON v.node_id = n.id
        GROUP BY v.node_id
    ")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    // Handle errors
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Dashboard</title>
    <link rel="stylesheet" href="css/traffic_data.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
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
                    <li class="active"><a href="traffic_data.php"><i class="fas fa-chart-line"></i> Traffic Data</a></li>
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
            <div style="padding: 30px;">

    <!-- Navbar -->
    <nav class="navbar">
        <h1><i class="fas fa-chart-line"></i> Traffic Dashboard</h1>
        <div class="navbar-right">
            <div class="time-range">
                <button class="time-range-btn active">Today</button>
                <button class="time-range-btn">Week</button>
                <button class="time-range-btn">Month</button>
                <select class="date-filter">
                    <option value="<?= date('Y-m-d') ?>">Today</option>
                    <option value="<?= date('Y-m-d', strtotime('-1 day')) ?>">Yesterday</option>
                    <option value="<?= date('Y-m-d', strtotime('-7 days')) ?>">Last 7 Days</option>
                    <option value="<?= date('Y-m-d', strtotime('-30 days')) ?>">Last 30 Days</option>
                </select>
            </div>
            <a href="download_report.php" target="_blank" class="btn-export">
                <i class="fas fa-download"></i> Export Report
            </a>
        </div>
    </nav>

    <!-- Stats Cards -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-car-crash"></i></div>
            <div class="stat-info">
                <h3><?= $todayIncidents ?></h3>
                <p>Today's Incidents</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <h3><?= $todayViolations ?></h3>
                <p>Today's Violations</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-satellite-dish"></i></div>
            <div class="stat-info">
                <h3><?= $onlineNodes ?>/<?= $totalNodes ?></h3>
                <p>Nodes Online</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-car"></i></div>
            <div class="stat-info">
                <h3><?= $totalVehicles ?></h3>
                <p>Total Vehicles</p>
            </div>
        </div>
    </section>

    <!-- KPI Section -->
    <section class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-value"><?= $todayIncidents ?></div>
            <div class="kpi-label">Incidents Today</div>
            <span class="kpi-change positive">+12% from yesterday</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-value"><?= $todayViolations ?></div>
            <div class="kpi-label">Violations Today</div>
            <span class="kpi-change negative">-5% from yesterday</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-value"><?= $onlineNodes ?></div>
            <div class="kpi-label">Active Nodes</div>
            <span class="kpi-change positive"><?= round(($onlineNodes / $totalNodes) * 100) ?>% uptime</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-value"><?= $totalVehicles ?></div>
            <div class="kpi-label">Vehicles</div>
            <span class="kpi-change positive">+3% this month</span>
        </div>
    </section>

    <!-- Charts Section -->
    <section class="charts-grid">
        <div class="chart-card">
            <h3><i class="fas fa-chart-area"></i> Traffic Volume Today (Hourly)</h3>
            <div id="trafficVolumeChart"></div>
        </div>

        <div class="chart-card">
            <h3><i class="fas fa-chart-bar"></i> Violations by Node</h3>
            <div id="violationsByNodeChart"></div>
        </div>
    </section>

    <!-- Recent Activities -->
    <section class="activity-grid">
        <div class="activity-card">
            <h3><i class="fas fa-car-crash"></i> Recent Incidents</h3>
            <ul>
                <?php if ($recentIncidents): ?>
                    <?php foreach ($recentIncidents as $incident): ?>
                        <li>
                            <strong><?= htmlspecialchars($incident['incident_type']) ?></strong> at <?= htmlspecialchars($incident['node_name']) ?>
                            <span class="time"><?= date('H:i', strtotime($incident['reported_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No incidents reported today</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="activity-card">
            <h3><i class="fas fa-exclamation-triangle"></i> Recent Violations</h3>
            <ul>
                <?php if ($recentViolations): ?>
                    <?php foreach ($recentViolations as $violation): ?>
                        <li>
                            <strong><?= htmlspecialchars($violation['violation_type']) ?></strong> - <?= htmlspecialchars($violation['plate_number'] ?? 'Unknown') ?>
                            <span class="time"><?= date('H:i', strtotime($violation['violation_time'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No violations recorded today</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <!-- Violations Table -->
    <section class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Node</th>
                    <th>Vehicle</th>
                    <th>Violation Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentViolations): ?>
                    <?php foreach ($recentViolations as $violation): ?>
                        <tr>
                            <td><?= date('H:i', strtotime($violation['violation_time'])) ?></td>
                            <td><?= htmlspecialchars($violation['node_name']) ?></td>
                            <td><?= htmlspecialchars($violation['plate_number'] ?? 'Unknown') ?></td>
                            <td><span class="violation-badge"><?= htmlspecialchars($violation['violation_type']) ?></span></td>
                            <td><span class="status-badge pending">Pending</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:20px; color: var(--text-light);">
                            No violation data available
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <!-- Inject Data for Charts -->
    <script>
        window.trafficVolumeData = <?php 
            $hourlyData = array_fill(0, 24, 0);
            foreach ($trafficVolume as $row) {
                $hourlyData[(int)$row['hour']] = (int)$row['count'];
            }
            echo json_encode($hourlyData); 
        ?>;
        
        window.violationsByNodeLabels = <?php 
            echo json_encode(array_column($violationsByNode, 'node_name')); 
        ?>;
        
        window.violationsByNodeData = <?php 
            echo json_encode(array_column($violationsByNode, 'count')); 
        ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.min.js"></script>
    <script src="js/traffic_data.js"></script>
            </div>
        </main>
    </div>
</body>
</html>
