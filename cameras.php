<?php
session_start();
require_once 'db.php';
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
$editCamera = null;
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM cameras WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Camera deleted successfully.";
    } else {
        $error = "Error deleting camera: " . $stmt->error;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $node_id   = (int) $_POST['node_id'];
    $camera_name      = clean($_POST['camera_name']);
    $location  = clean($_POST['location']);
    $direction = clean($_POST['direction']);
    $lane      = clean($_POST['lane']);
    $ip        = clean($_POST['ip_address']);
    $status    = clean($_POST['status']);

    try {
        if (!empty($_POST['camera_id'])) {
         $id = (int) $_POST['camera_id'];
            $stmt = $conn->prepare("
                UPDATE cameras 
                SET node_id=?, camera_name=?, location=?, direction=?, lane=?, ip_address=?, status=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "issssssi",
                $node_id, $camera_name, $location, $direction, $lane, $ip, $status, $id
            );
            $stmt->execute();
            $message = "Camera updated successfully.";
        } else {
            // INSERT
            $stmt = $conn->prepare("
                INSERT INTO cameras (node_id, camera_name, location, direction, lane, ip_address, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "issssss",
                $node_id, $camera_name, $location, $direction, $lane, $ip, $status
            );
            $stmt->execute();
            $message = "Camera added successfully.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM cameras WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editCamera = $stmt->get_result()->fetch_assoc();
}
$nodes = $conn->query("SELECT id, name FROM nodes ORDER BY name");
$nodes_data = $nodes->fetch_all(MYSQLI_ASSOC);
$cameras_result = $conn->query("
    SELECT c.*, n.name AS node_name
    FROM cameras c
    LEFT JOIN nodes n ON c.node_id = n.id
    ORDER BY c.created_at DESC
");
$cameras = $cameras_result->fetch_all(MYSQLI_ASSOC);
$totalCameras = count($cameras);
$activeCameras = 0;
$inactiveCameras = 0;

foreach ($cameras as $camera) {
    if ($camera['status'] === 'active') {
        $activeCameras++;
    } else {
        $inactiveCameras++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cameras Management - Traffic Monitoring System</title>
    <link rel="stylesheet" href="css/cameras.css">
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
                    <li class="active"><a href="cameras.php"><i class="fas fa-video"></i> Cameras</a></li>
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
            <div style="padding: 30px;">
    <h1><i class="fas fa-video"></i> Cameras Management</h1>

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

    <!-- Camera Form (Add/Edit) -->
    <?php if (isset($_GET['edit']) || isset($_GET['add'])): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-<?php echo isset($editCamera) ? 'edit' : 'plus-circle'; ?>"></i> 
                    <?php echo isset($editCamera) ? 'Edit Camera' : 'Add New Camera'; ?>
                </h2>
                <a href="cameras.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <form method="POST" class="form-card">
                    <input type="hidden" name="camera_id" value="<?php echo $editCamera['id'] ?? ''; ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-camera"></i> Camera Name *
                            </label>
                            <input type="text" id="camera_name" name="camera_name" required 
                                   value="<?php echo htmlspecialchars($editCamera['camera_name'] ?? ''); ?>"
                                   placeholder="e.g., Main Street Camera">
                        </div>

                        <div class="form-group">
                            <label for="node_id">
                                <i class="fas fa-network-wired"></i> Node *
                            </label>
                            <select id="node_id" name="node_id" required>
                                <option value="">Select Node</option>
                                <?php foreach ($nodes_data as $n): ?>
                                    <option value="<?php echo $n['id']; ?>"
                                        <?php echo (isset($editCamera) && $editCamera['node_id'] == $n['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($n['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="location">
                                <i class="fas fa-map-marker-alt"></i> Location
                            </label>
                            <input type="text" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($editCamera['location'] ?? ''); ?>"
                                   placeholder="e.g., Main Street & 5th Avenue">
                        </div>

                        <div class="form-group">
                            <label for="direction">
                                <i class="fas fa-directions"></i> Direction
                            </label>
                            <select id="direction" name="direction">
                                <option value="north" <?php echo (isset($editCamera['direction']) && $editCamera['direction'] == 'north') ? 'selected' : ''; ?>>North</option>
                                <option value="south" <?php echo (isset($editCamera['direction']) && $editCamera['direction'] == 'south') ? 'selected' : ''; ?>>South</option>
                                <option value="east" <?php echo (isset($editCamera['direction']) && $editCamera['direction'] == 'east') ? 'selected' : ''; ?>>East</option>
                                <option value="west" <?php echo (isset($editCamera['direction']) && $editCamera['direction'] == 'west') ? 'selected' : ''; ?>>West</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="lane">
                                <i class="fas fa-road"></i> Lane
                            </label>
                            <input type="text" id="lane" name="lane" 
                                   value="<?php echo htmlspecialchars($editCamera['lane'] ?? ''); ?>"
                                   placeholder="e.g., Lane A / 1 / Left">
                        </div>

                        <div class="form-group">
                            <label for="ip_address">
                                <i class="fas fa-network-wired"></i> IP Address
                            </label>
                            <input type="text" id="ip_address" name="ip_address" 
                                   value="<?php echo htmlspecialchars($editCamera['ip_address'] ?? ''); ?>"
                                   placeholder="e.g., 192.168.1.100">
                        </div>

                        <div class="form-group">
                            <label for="status">
                                <i class="fas fa-power-off"></i> Status
                            </label>
                            <select id="status" name="status">
                                <option value="active" <?php echo (isset($editCamera['status']) && $editCamera['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($editCamera['status']) && $editCamera['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Camera
                        </button>
                        <a href="cameras.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Main Content Area -->
        <!-- Quick Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-video"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalCameras; ?></h3>
                    <p>Total Cameras</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #27ae60, #219653);">
                    <i class="fas fa-signal"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $activeCameras; ?></h3>
                    <p>Active Cameras</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $inactiveCameras; ?></h3>
                    <p>Inactive Cameras</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #d68910);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalCameras > 0 ? round(($activeCameras / $totalCameras) * 100) : 0; ?>%</h3>
                    <p>Active Rate</p>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <a href="cameras.php?add=true" class="btn-add">
                <i class="fas fa-plus"></i> Add New Camera
            </a>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search cameras by name, IP, or location...">
                <button class="search-btn"><i class="fas fa-search"></i> Search</button>
            </div>
        </div>

        <!-- Cameras Table -->
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Camera Details</th>
                        <th>Node</th>
                        <th>Direction & Lane</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($cameras)): ?>
                        <?php foreach ($cameras as $c): ?>
                            <tr>
                                <td>
                                    <div class="camera-info">
                                        <div class="camera-icon">
                                            <i class="fas fa-video"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($c['camera_name']); ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($c['location']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($c['node_name']): ?>
                                        <span class="badge" style="background: #e9ecef; color: #495057;">
                                            <i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($c['node_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">No node</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['direction']): ?>
                                        <span class="direction-badge <?php echo htmlspecialchars($c['direction']); ?>">
                                            <i class="fas fa-arrow-<?php echo $c['direction'] === 'north' ? 'up' : ($c['direction'] === 'south' ? 'down' : ($c['direction'] === 'east' ? 'right' : 'left')); ?>"></i>
                                            <?php echo ucfirst($c['direction']); ?>
                                        </span>
                                        <?php if ($c['lane']): ?>
                                            <br>
                                            <small>Lane: <?php echo htmlspecialchars($c['lane']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['ip_address']): ?>
                                        <span class="ip-address"><?php echo htmlspecialchars($c['ip_address']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">No IP</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $c['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($c['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?php echo $c['id']; ?>" 
                                           class="btn-action btn-edit" title="Edit Camera">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $c['id']; ?>" 
                                           class="btn-action btn-delete" title="Delete Camera"
                                           onclick="return confirm('Are you sure you want to delete this camera? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <a href="violations.php?camera=<?php echo $c['id']; ?>" 
                                           class="btn-action btn-view" title="View Feed">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-video fa-4x"></i>
                                    <h3>No Cameras Found</h3>
                                    <p>Start by adding a new camera using the "Add New Camera" button above.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
    
    // Form validation
    document.querySelector('.form-card')?.addEventListener('submit', function(e) {
        const name = document.getElementById('camera_name')?.value.trim();
        const nodeId = document.getElementById('node_id')?.value;
        
        if (!name) {
            e.preventDefault();
            alert('Camera name is required!');
            return false;
        }
        
        if (!nodeId) {
            e.preventDefault();
            alert('Node selection is required!');
            return false;
        }
        
        return true;
    });
    
    // Validate IP address format (optional)
    document.getElementById('ip_address')?.addEventListener('blur', function() {
        const ip = this.value.trim();
        if (ip && !/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) {
            alert('Please enter a valid IP address (e.g., 192.168.1.100)');
            this.focus();
        }
    });
    
    // Focus on search input on page load
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
        }
    });
</script>
        </div>
    </main>
</div>
</body>
</html>