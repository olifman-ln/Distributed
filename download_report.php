<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: auth/login.php');
  exit();
}
require_once 'db.php';

// ========== COPY ALL DATA FETCHING CODE FROM traffic_data.php ==========
$todayIncidents = 0;
$todayViolations = 0;
$onlineNodes = 0;
$totalNodes = 0;
$totalVehicles = 0;
$recentIncidents = [];
$recentViolations = [];

try {
  $today = date('Y-m-d');

  // Today's Incidents
  $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM incidents WHERE DATE(reported_at) = ?");
  $stmt->bind_param("s", $today);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $todayIncidents = $row['total'] ?? 0;

  // Today's Violations
  $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM violations WHERE DATE(violation_time) = ?");
  $stmt->bind_param("s", $today);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $todayViolations = $row['total'] ?? 0;

  // Total Nodes
  $result = $conn->query("SELECT COUNT(*) AS total FROM nodes");
  $row = $result->fetch_assoc();
  $totalNodes = $row['total'] ?? 0;

  // Online Nodes
  $result = $conn->query("SELECT COUNT(*) AS online FROM nodes WHERE status = 'online'");
  $row = $result->fetch_assoc();
  $onlineNodes = $row['online'] ?? 0;

  // Total Vehicles
  $result = $conn->query("SELECT COUNT(*) AS total FROM vehicles");
  $row = $result->fetch_assoc();
  $totalVehicles = $row['total'] ?? 0;

  // Recent Incidents
  $result = $conn->query("SELECT i.*, n.name AS node_name FROM incidents i LEFT JOIN nodes n ON i.node_id = n.id ORDER BY reported_at DESC LIMIT 5");
  $recentIncidents = $result->fetch_all(MYSQLI_ASSOC);

  // Recent Violations
  $result = $conn->query("SELECT v.*, n.name AS node_name FROM violations v LEFT JOIN nodes n ON v.node_id = n.id ORDER BY violation_time DESC LIMIT 5");
  $recentViolations = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
  // Handle error if needed
}
// ========== END OF DATA FETCHING CODE ==========

// Generate HTML report
$html = '<!DOCTYPE html>
<html>
<head>
    <title>Traffic Data Report</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 40px; 
            line-height: 1.6;
        }
        h1 { 
            color: #333; 
            border-bottom: 2px solid #333; 
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        h2 {
            color: #444;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }
        .report-date { 
            color: #666; 
            margin-bottom: 30px;
            font-size: 14px;
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 20px; 
            margin: 30px 0; 
        }
        .stat-box { 
            background: #f5f5f5; 
            padding: 20px; 
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .stat-box h3 {
            margin-top: 0;
            color: #555;
            font-size: 16px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
            font-size: 14px;
        }
        th { 
            background: #333; 
            color: white; 
            padding: 12px 10px; 
            text-align: left;
            font-weight: 600;
        }
        td { 
            padding: 10px; 
            border-bottom: 1px solid #ddd; 
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #888;
            font-size: 12px;
        }
        @media print { 
            .no-print { display: none; } 
            body { margin: 20px; }
            .stat-box { break-inside: avoid; }
            table { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <h1>Traffic Monitoring System Report</h1>
    <div class="report-date">Generated on: ' . date('F j, Y \a\t h:i A') . '</div>
    
    <h2>Daily Statistics</h2>
    <div class="stats-grid">
        <div class="stat-box">
            <h3>Today\'s Incidents</h3>
            <p style="font-size: 28px; font-weight: bold; color: #dc2626; margin: 10px 0;">' . $todayIncidents . '</p>
            <small>Traffic incidents reported today</small>
        </div>
        <div class="stat-box">
            <h3>Today\'s Violations</h3>
            <p style="font-size: 28px; font-weight: bold; color: #f59e0b; margin: 10px 0;">' . $todayViolations . '</p>
            <small>Traffic violations detected today</small>
        </div>
        <div class="stat-box">
            <h3>Monitoring Nodes</h3>
            <p style="font-size: 28px; font-weight: bold; color: #10b981; margin: 10px 0;">' . $onlineNodes . ' / ' . $totalNodes . '</p>
            <small>Active / Total monitoring nodes</small>
        </div>
        <div class="stat-box">
            <h3>Total Vehicles</h3>
            <p style="font-size: 28px; font-weight: bold; color: #3b82f6; margin: 10px 0;">' . $totalVehicles . '</p>
            <small>Vehicles registered in system</small>
        </div>
    </div>
    
    <h2>Recent Incidents (Last 5)</h2>';

if (!empty($recentIncidents)) {
  $html .= '<table>
        <tr>
            <th>Type</th>
            <th>Location</th>
            <th>Time</th>
            <th>Description</th>
        </tr>';

  foreach ($recentIncidents as $incident) {
    $html .= '<tr>
            <td><strong>' . htmlspecialchars($incident['incident_type']) . '</strong></td>
            <td>' . htmlspecialchars($incident['node_name'] ?? 'Unknown') . '</td>
            <td>' . date('H:i', strtotime($incident['reported_at'])) . '</td>
            <td>' . htmlspecialchars(substr($incident['description'] ?? 'No description', 0, 50)) . '...</td>
        </tr>';
  }

  $html .= '</table>';
} else {
  $html .= '<p style="color: #666; font-style: italic;">No incidents reported today.</p>';
}

$html .= '<h2>Recent Violations (Last 5)</h2>';

if (!empty($recentViolations)) {
  $html .= '<table>
        <tr>
            <th>Type</th>
            <th>Plate Number</th>
            <th>Location</th>
            <th>Time</th>
            <th>Status</th>
        </tr>';

  foreach ($recentViolations as $violation) {
    $html .= '<tr>
            <td><strong>' . htmlspecialchars($violation['violation_type']) . '</strong></td>
            <td>' . htmlspecialchars($violation['plate_number'] ?? 'Unknown') . '</td>
            <td>' . htmlspecialchars($violation['node_name'] ?? 'Unknown') . '</td>
            <td>' . date('H:i', strtotime($violation['violation_time'])) . '</td>
            <td><span style="padding: 4px 12px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 12px;">Pending</span></td>
        </tr>';
  }

  $html .= '</table>';
} else {
  $html .= '<p style="color: #666; font-style: italic;">No violations recorded today.</p>';
}

$html .= '<div class="footer">
        <p><strong>Traffic Monitoring System - Official Report</strong></p>
        <p>This report was automatically generated by the system</p>
        <p>Report ID: ' . date('YmdHis') . '</p>
    </div>
    
    <div class="no-print" style="margin-top: 40px; padding: 20px; background: #f8fafc; border-radius: 8px; text-align: center;">
        <button onclick="window.print()" style="padding: 12px 30px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold;">
            📄 Print / Save as PDF
        </button>
        <p style="color: #666; margin-top: 15px; font-size: 14px;">
            Click the button above, then in the print dialog:<br>
            1. Choose "Save as PDF" as printer<br>
            2. Adjust margins to "Default" or "None"<br>
            3. Click "Save"
        </p>
        <button onclick="window.close()" style="padding: 8px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px;">
            Close Window
        </button>
    </div>
</body>
</html>';

echo $html;
