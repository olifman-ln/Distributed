<?php
session_start();
require_once 'db.php';

// Only define clean() function if it doesn't already exist
if (!function_exists('clean')) {
    function clean($data)
    {
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

/* ===================== ADD / UPDATE ACCIDENT ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id     = (int) $_POST['vehicle_id'];
    $camera_id      = (int) $_POST['camera_id'];
    $node_id        = (int) $_POST['node_id'];
    $incident_type  = clean($_POST['type']);
    $description    = clean($_POST['description']);
    $severity       = clean($_POST['severity']);

    try {
        if (isset($_POST['incident_id']) && !empty($_POST['incident_id'])) {
            // UPDATE
            $id = (int)$_POST['incident_id'];
            $stmt = $conn->prepare("
                UPDATE incidents 
                SET vehicle_id=?, camera_id=?, node_id=?, incident_type=?, description=?, severity=?
                WHERE id=?
            ");
            $stmt->bind_param("iiisssi", $vehicle_id, $camera_id, $node_id, $incident_type, $description, $severity, $id);
            $stmt->execute();
            $message = "Accident report updated successfully.";
        } else {
            // INSERT - Create accident, payment, and notification
            $conn->begin_transaction();
            
            // 1. Insert accident
            $stmt = $conn->prepare("
                INSERT INTO incidents 
                (vehicle_id, camera_id, node_id, incident_type, description, severity)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiisss", $vehicle_id, $camera_id, $node_id, $incident_type, $description, $severity);
            $stmt->execute();
            $incident_id = $conn->insert_id;
            
            // 2. Get owner_id from vehicle
            $owner_stmt = $conn->prepare("SELECT owner_id FROM vehicles WHERE id = ?");
            $owner_stmt->bind_param("i", $vehicle_id);
            $owner_stmt->execute();
            $owner_data = $owner_stmt->get_result()->fetch_assoc();
            
            if ($owner_data && $owner_data['owner_id']) {
                $owner_id = $owner_data['owner_id'];
                
                // 3. Calculate fine based on severity
                $fine_amount = 500; // Base fine
                switch(strtolower($severity)) {
                    case 'critical':
                        $fine_amount = 5000;
                        break;
                    case 'high':
                        $fine_amount = 3000;
                        break;
                    case 'medium':
                        $fine_amount = 1500;
                        break;
                    case 'low':
                        $fine_amount = 800;
                        break;
                }
                
                // 4. Create payment record
                $pay_stmt = $conn->prepare("
                    INSERT INTO payments 
                    (owner_id, incident_id, payment_type, amount, penalty_reason, payment_status)
                    VALUES (?, ?, 'accident', ?, ?, 'pending')
                ");
                $penalty_reason = "Fine for " . $incident_type . " (" . $severity . " severity)";
                $pay_stmt->bind_param("iids", $owner_id, $incident_id, $fine_amount, $penalty_reason);
                $pay_stmt->execute();
                
                // 5. Create notification for owner
                $notif_stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (owner_id, title, message, reference_id)
                    VALUES (?, ?, ?, ?)
                ");
                $notif_title = "Accident Report - Payment Required";
                $notif_message = "An accident involving your vehicle has been reported. Severity: " . $severity . ". Fine amount: " . number_format($fine_amount, 2) . " ETB. Please review and pay the fine.";
                $notif_stmt->bind_param("issi", $owner_id, $notif_title, $notif_message, $incident_id);
                $notif_stmt->execute();
            }
            
            $conn->commit();
            $message = "Accident reported successfully. Owner has been notified and payment created.";
        }
    } catch (Exception $e) {
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        $error = "Error: " . $e->getMessage();
    }
}

/* ===================== DELETE ACCIDENT ===================== */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM incidents WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Accident report deleted successfully.";
    } else {
        $error = "Error deleting report: " . $conn->error;
    }
}

/* ===================== EDIT DATA ===================== */
$editIncident = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM incidents WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editIncident = $stmt->get_result()->fetch_assoc();
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

// Incidents List
$incidents_result = $conn->query("
    SELECT i.*, ve.plate_number, o.full_name AS owner_name, 
           c.$cameraNameColumn AS camera_name, n.name AS node_name,
           TIMESTAMPDIFF(HOUR, i.reported_at, NOW()) as hours_ago
    FROM incidents i
    LEFT JOIN vehicles ve ON i.vehicle_id = ve.id
    LEFT JOIN owners o ON ve.owner_id = o.id
    LEFT JOIN cameras c ON i.camera_id = c.id
    LEFT JOIN nodes n ON i.node_id = n.id
    ORDER BY i.reported_at DESC
    LIMIT 100
");
$incidents = $incidents_result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalAccidents = count($incidents);
$highSeverity = 0;
$moderateSeverity = 0;
$lowSeverity = 0;

foreach ($incidents as $incident) {
    switch ($incident['severity']) {
        case 'high':
            $highSeverity++;
            break;
        case 'moderate':
            $moderateSeverity++;
            break;
        case 'low':
            $lowSeverity++;
            break;
    }
}

// Calculate today's accidents
$todayAccidents = $conn->query("
    SELECT COUNT(*) as count 
    FROM incidents 
    WHERE DATE(reported_at) = CURDATE()
")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Accidents - Traffic Monitoring System</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/accidents.css">
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
                <li class="active"><a href="accidents.php"><i class="fas fa-car-crash"></i> Accidents</a></li>
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
            <h1><i class="fas fa-car-crash"></i> Traffic Accidents Management</h1>

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
                    <i class="fas fa-car-crash"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalAccidents; ?></h3>
                    <p>Total Accidents</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #27ae60, #219653);">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $lowSeverity; ?></h3>
                    <p>Low Severity</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #d68910);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $moderateSeverity; ?></h3>
                    <p>Moderate</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                    <i class="fas fa-ambulance"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $highSeverity; ?></h3>
                    <p>High Severity</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Date Range</label>
                    <input type="date" id="dateFrom" class="filter-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filter-group">
                    <label>to</label>
                    <input type="date" id="dateTo" class="filter-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filter-group">
                    <label>Severity</label>
                    <select id="severityFilter" class="filter-input">
                        <option value="">All Severity</option>
                        <option value="low">Low</option>
                        <option value="moderate">Moderate</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Accident Type</label>
                    <select id="typeFilter" class="filter-input">
                        <option value="">All Types</option>
                        <option value="collision">Collision</option>
                        <option value="rollover">Rollover</option>
                        <option value="pedestrian_hit">Pedestrian Hit</option>
                        <option value="minor">Minor</option>
                    </select>
                </div>
                <button class="search-btn" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <h2><i class="fas fa-<?php echo $editIncident ? 'edit' : 'plus-circle'; ?>"></i> 
                <?php echo $editIncident ? 'Edit Accident Report' : 'Report New Accident'; ?>
            </h2>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by plate or owner...">
                <button class="search-btn"><i class="fas fa-search"></i> Search</button>
            </div>
        </div>

        <!-- Add/Edit Accident Form -->
        <form method="POST" class="form-card">
            <?php if ($editIncident): ?>
                <input type="hidden" name="incident_id" value="<?php echo $editIncident['id']; ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label for="vehicle_id">
                        <i class="fas fa-car"></i> Vehicle *
                    </label>
                    <select id="vehicle_id" name="vehicle_id" required>
                        <option value="">Select Vehicle</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo ($editIncident && $editIncident['vehicle_id'] == $v['id']) ? 'selected' : ''; ?>>
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
                            <option value="<?php echo $c['id']; ?>" data-node="<?php echo $c['node_id']; ?>" <?php echo ($editIncident && $editIncident['camera_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c[$cameraNameColumn]); ?> — <?php echo htmlspecialchars($c['node_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" id="node_id" name="node_id" value="<?php echo $editIncident['node_id'] ?? ''; ?>">

                <div class="form-group">
                    <label for="type">
                        <i class="fas fa-exclamation-circle"></i> Accident Type *
                    </label>
                    <select id="type" name="type" required>
                        <option value="collision" <?php echo ($editIncident && $editIncident['incident_type'] == 'collision') ? 'selected' : ''; ?>>Collision</option>
                        <option value="rollover" <?php echo ($editIncident && $editIncident['incident_type'] == 'rollover') ? 'selected' : ''; ?>>Rollover</option>
                        <option value="pedestrian_hit" <?php echo ($editIncident && $editIncident['incident_type'] == 'pedestrian_hit') ? 'selected' : ''; ?>>Pedestrian Hit</option>
                        <option value="minor" <?php echo ($editIncident && $editIncident['incident_type'] == 'minor') ? 'selected' : ''; ?>>Minor Accident</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="severity">
                        <i class="fas fa-exclamation-triangle"></i> Severity *
                    </label>
                    <select id="severity" name="severity" required>
                        <option value="low" <?php echo ($editIncident && $editIncident['severity'] == 'low') ? 'selected' : ''; ?>>Low</option>
                        <option value="moderate" <?php echo ($editIncident && $editIncident['severity'] == 'moderate') ? 'selected' : ''; ?>>Moderate</option>
                        <option value="high" <?php echo ($editIncident && $editIncident['severity'] == 'high') ? 'selected' : ''; ?>>High</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="description">
                        <i class="fas fa-file-alt"></i> Description *
                    </label>
                    <textarea id="description" name="description" placeholder="Describe the accident details, location, and any injuries..." required><?php echo $editIncident ? htmlspecialchars($editIncident['description']) : ''; ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-report">
                    <i class="fas fa-<?php echo $editIncident ? 'save' : 'paper-plane'; ?>"></i> 
                    <?php echo $editIncident ? 'Save Changes' : 'Report Accident'; ?>
                </button>
                <?php if ($editIncident): ?>
                    <a href="accidents.php" class="btn-action" style="padding: 10px 20px; text-decoration: none; background: #94a3b8; color: white; border-radius: 50px; font-weight: 600; width: auto; font-size: 0.9rem;">
                        Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Emergency Contacts -->
        <div class="emergency-contacts">
            <h3><i class="fas fa-phone-alt"></i> Emergency Contacts</h3>
            <div class="contacts-grid">
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-ambulance"></i>
                    </div>
                    <div>
                        <strong>Emergency</strong>
                        <p>112 / 911</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-police-car"></i>
                    </div>
                    <div>
                        <strong>Police</strong>
                        <p>999</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-fire-extinguisher"></i>
                    </div>
                    <div>
                        <strong>Fire Department</strong>
                        <p>111</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-road"></i>
                    </div>
                    <div>
                        <strong>Traffic Police</strong>
                        <p>1192</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accidents Table -->
        <h2><i class="fas fa-list"></i> Recent Accidents (<?php echo $totalAccidents; ?>)</h2>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Accident Details</th>
                        <th>Vehicle & Owner</th>
                        <th>Severity</th>
                        <th>Location</th>
                        <th>Description</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($incidents)): ?>
                        <?php foreach ($incidents as $row): ?>
                            <tr>
                                <td>
                                    <div class="accident-info">
                                        <span class="accident-badge badge-<?php echo htmlspecialchars($row['incident_type']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $row['incident_type'])); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['plate_number']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($row['owner_name']); ?></small>
                                </td>
                                <td>
                                    <span class="severity-badge severity-<?php echo htmlspecialchars($row['severity']); ?>">
                                        <?php echo ucfirst($row['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['camera_name'] ?? 'N/A'); ?>
                                    <br>
                                    <small><?php echo htmlspecialchars($row['node_name'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <div class="description-preview" title="<?php echo htmlspecialchars($row['description']); ?>">
                                        <?php echo htmlspecialchars(substr($row['description'], 0, 50)); ?>...
                                    </div>
                                </td>
                                <td>
                                    <div class="time-display">
                                        <?php echo date('M d, Y', strtotime($row['reported_at'])); ?>
                                        <br>
                                        <small><?php echo date('h:i A', strtotime($row['reported_at'])); ?></small>
                                        <?php if ($row['hours_ago'] < 24): ?>
                                            <span class="time-ago">
                                                <?php echo $row['hours_ago'] == 0 ? 'Just now' : $row['hours_ago'] . ' hours ago'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?php echo $row['id']; ?>" class="btn-action btn-edit" title="Edit Report">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="btn-action btn-delete" title="Delete Report" onclick="return confirm('Are you sure you want to delete this accident report?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-car-crash fa-4x"></i>
                                    <h3>No Accidents Reported</h3>
                                    <p>No traffic accidents have been reported yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <span>Showing <?php echo min(100, $totalAccidents); ?> of <?php echo $totalAccidents; ?> accidents</span>
            <div class="pagination-controls">
                <button class="pagination-btn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
                <span class="pagination-info">Page 1</span>
                <button class="pagination-btn">Next <i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        </div>
    </main>
</div>

<script src="js/accidents.js"></script>
</body>

</html>