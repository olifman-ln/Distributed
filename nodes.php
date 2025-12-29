<?php
session_start();
require_once 'db.php';
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
    header('Location: auth/login.php');
    exit();
}
$action = $_GET['action'] ?? '';
$nodeId = intval($_GET['id'] ?? 0);
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name']);
    $location = clean($_POST['location']);
    $lat = !empty($_POST['lat']) ? floatval($_POST['lat']) : null;
    $lng = !empty($_POST['lng']) ? floatval($_POST['lng']) : null;
    $status = $_POST['status'] ?? 'offline';
try {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO nodes (name, location, status, lat, lng) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdd", $name, $location, $status, $lat, $lng);
            if ($stmt->execute()) {
                $message = "Node added successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
        } elseif ($action === 'edit' && $nodeId > 0) {
            $stmt = $conn->prepare("UPDATE nodes SET name=?, location=?, status=?, lat=?, lng=? WHERE id=?");
            $stmt->bind_param("sssddi", $name, $location, $status, $lat, $lng, $nodeId);
            if ($stmt->execute()) {
                $message = "Node updated successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
if ($action === 'delete' && $nodeId > 0) {
    $stmt = $conn->prepare("DELETE FROM nodes WHERE id=?");
    $stmt->bind_param("i", $nodeId);
    if ($stmt->execute()) {
        $message = "Node deleted successfully!";
    } else {
        $error = "Error deleting node: " . $stmt->error;
    }
    header("Location: nodes.php");
    exit();
}
$sql = "SELECT * FROM nodes ORDER BY id DESC";
$result = $conn->query($sql);
$nodes = $result->fetch_all(MYSQLI_ASSOC);
$totalNodes = count($nodes);
$onlineNodes = 0;
$offlineNodes = 0;
foreach ($nodes as $node) {
    if ($node['status'] === 'online') {
        $onlineNodes++;
    } else {
        $offlineNodes++;
    }
}
$nodeToEdit = null;
if ($action === 'edit' && $nodeId > 0) {
    $stmt = $conn->prepare("SELECT * FROM nodes WHERE id=?");
    $stmt->bind_param("i", $nodeId);
    $stmt->execute();
    $res = $stmt->get_result();
    $nodeToEdit = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nodes - Traffic Monitoring System</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/nodes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
                    <li class="active"><a href="nodes.php"><i class="fas fa-satellite-dish"></i> Nodes</a></li>
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
            <div style="padding: 30px;">
        <h1><i class="fas fa-network-wired"></i> Traffic Nodes Management</h1>
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <!-- Node Form (Add/Edit) -->
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-<?php echo $action === 'add' ? 'plus-circle' : 'edit'; ?>"></i>
                        <?php echo ucfirst($action); ?> Node
                    </h2>
                    <a href="nodes.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form method="post" class="node-form">
                        <input type="hidden" name="id" value="<?php echo $nodeToEdit['id'] ?? ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">
                                    <i class="fas fa-microchip"></i> Node Name *
                                </label>
                                <input type="text" id="name" name="name"
                                    value="<?php echo htmlspecialchars($nodeToEdit['name'] ?? ''); ?>"
                                    required placeholder="e.g., Node-01, Downtown Camera">
                            </div>
                            <div class="form-group">
                                <label for="location">
                                    <i class="fas fa-map-marker-alt"></i> Location *
                                </label>
                                <input type="text" id="location" name="location"
                                    value="<?php echo htmlspecialchars($nodeToEdit['location'] ?? ''); ?>"
                                    required placeholder="e.g., Main Street, Highway Exit 5">
                            </div>
                            <div class="form-group">
                                <label for="status">
                                    <i class="fas fa-power-off"></i> Status
                                </label>
                                <select id="status" name="status">
                                    <option value="online" <?php echo (isset($nodeToEdit['status']) && $nodeToEdit['status'] === 'online') ? 'selected' : ''; ?>>
                                        Online
                                    </option>
                                    <option value="offline" <?php echo (isset($nodeToEdit['status']) && $nodeToEdit['status'] === 'offline') ? 'selected' : ''; ?>>
                                        Offline
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lat">
                                    <i class="fas fa-globe"></i> Latitude
                                </label>
                                <input type="number" step="any" id="lat" name="lat"
                                    value="<?php echo htmlspecialchars($nodeToEdit['lat'] ?? ''); ?>"
                                    placeholder="e.g., 9.0249">
                            </div>
                            <div class="form-group">
                                <label for="lng">
                                    <i class="fas fa-globe"></i> Longitude
                                </label>
                                <input type="number" step="any" id="lng" name="lng"
                                    value="<?php echo htmlspecialchars($nodeToEdit['lng'] ?? ''); ?>"
                                    placeholder="e.g., 38.7468">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-<?php echo $action === 'add' ? 'save' : 'edit'; ?>"></i>
                                <?php echo ucfirst($action); ?> Node
                            </button>
                            <a href="nodes.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <!-- Main Content Area -->
        <?php if ($action !== 'add' && $action !== 'edit'): ?>
            <!-- Quick Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalNodes; ?></h3>
                        <p>Total Nodes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #27ae60, #219653);">
                        <i class="fas fa-signal"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $onlineNodes; ?></h3>
                        <p>Online Nodes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $offlineNodes; ?></h3>
                        <p>Offline Nodes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #d68910);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalNodes > 0 ? round(($onlineNodes / $totalNodes) * 100) : 0; ?>%</h3>
                        <p>Uptime Rate</p>
                    </div>
                </div>
            </div>
            <!-- Action Bar -->
            <div class="action-bar">
                <div class="action-left">
                    <a href="nodes.php?action=add" class="btn-add">
                        <i class="fas fa-plus"></i> Add New Node
                    </a>
                    <div class="view-toggles" style="margin-left: 15px; display: inline-flex; gap: 5px;">
                        <button id="btn-list-view" class="btn-action active" title="List View"><i class="fas fa-list"></i></button>
                        <button id="btn-grid-view" class="btn-action" title="Grid View"><i class="fas fa-th-large"></i></button>
                        <button id="btn-map-view" class="btn-action" title="Map View"><i class="fas fa-map-marked-alt"></i></button>
                    </div>
                </div>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search nodes by name or location...">
                    <button class="search-btn"><i class="fas fa-search"></i> Search</button>
                </div>
            </div>
            <!-- Nodes Table View -->
            <div id="nodes-table-view" class="table-container">
                <table class="nodes-table">
                    <thead>
                        <tr>
                            <th>Node Name</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Registered At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($nodes)): ?>
                            <?php foreach ($nodes as $node): ?>
                                <tr>
                                    <td>
                                        <div class="node-info">
                                            <i class="fas fa-microchip"></i>
                                            <strong><?php echo htmlspecialchars($node['name']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="location-info">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($node['location']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $node['status'] === 'online' ? 'status-online' : 'status-offline'; ?>">
                                            <?php echo ucfirst($node['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($node['created_at'])); ?>
                                        <br>
                                        <small><?php echo date('h:i A', strtotime($node['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="nodes.php?action=edit&id=<?php echo $node['id']; ?>" class="btn-action btn-edit" title="Edit Node">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="nodes.php?action=delete&id=<?php echo $node['id']; ?>"
                                                class="btn-action btn-delete" title="Delete Node"
                                                onclick="return confirm('Are you sure you want to delete this node? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="violations.php?node=<?php echo $node['id']; ?>"
                                                class="btn-action btn-view" title="View Violations">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-network-wired fa-4x"></i>
                                        <h3>No Nodes Found</h3>
                                        <p>Start by adding a new node using the "Add New Node" button above.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Map View for Nodes -->
            <div id="nodes-map-view" style="display: none; height: 600px; border-radius: 12px; overflow: hidden; border: 1px solid #dee2e6; margin-top: 20px;">
                <div id="nodesMap" style="height: 100%; width: 100%;"></div>
            </div>

            <!-- Optional: Grid View for Nodes -->
            <div id="nodes-grid-view" class="nodes-grid" style="display: none; margin-top: 30px;">
                <h2><i class="fas fa-th-large"></i> Nodes Grid View</h2>
                <div class="grid-container">
                    <?php foreach ($nodes as $node): ?>
                        <div class="node-card">
                            <div class="node-card-header">
                                <div class="node-card-title">
                                    <i class="fas fa-microchip"></i>
                                    <?php echo htmlspecialchars($node['name']); ?>
                                </div>
                                <span class="status-badge <?php echo $node['status'] === 'online' ? 'status-online' : 'status-offline'; ?>">
                                    <?php echo ucfirst($node['status']); ?>
                                </span>
                            </div>
                            <div class="node-card-body">
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($node['location']); ?></p>
                                <p><i class="fas fa-calendar-alt"></i> Added: <?php echo date('M d, Y', strtotime($node['created_at'])); ?></p>
                            </div>
                            <div class="node-card-footer">
                                <small>Node ID: <?php echo $node['id']; ?></small>
                                <div class="action-buttons">
                                    <a href="nodes.php?action=edit&id=<?php echo $node['id']; ?>" class="btn-action btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="nodes.php?action=delete&id=<?php echo $node['id']; ?>" class="btn-action btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </main>
</div>

    <script>
        // View Toggles
        const btnList = document.getElementById('btn-list-view');
        const btnGrid = document.getElementById('btn-grid-view');
        const btnMap = document.getElementById('btn-map-view');
        
        const listView = document.getElementById('nodes-table-view');
        const gridView = document.getElementById('nodes-grid-view');
        const mapView = document.getElementById('nodes-map-view');

        let map = null;

        function setActiveView(btn, view) {
            [btnList, btnGrid, btnMap].forEach(b => b.classList.remove('active'));
            [listView, gridView, mapView].forEach(v => v.style.display = 'none');
            
            btn.classList.add('active');
            view.style.display = 'block';

            if (view === mapView) {
                initMap();
            }
        }

        btnList.onclick = () => setActiveView(btnList, listView);
        btnGrid.onclick = () => setActiveView(btnGrid, gridView);
        btnMap.onclick = () => setActiveView(btnMap, mapView);

        function initMap() {
            if (map) {
                setTimeout(() => map.invalidateSize(), 100);
                return;
            }

            // Addis Ababa center as default if no nodes
            const defaultLat = 9.02497;
            const defaultLng = 38.74689;

            map = L.map('nodesMap').setView([defaultLat, defaultLng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            const nodes = <?php echo json_encode($nodes); ?>;
            const markers = [];

            nodes.forEach(node => {
                if (node.lat && node.lng) {
                    const marker = L.marker([node.lat, node.lng])
                        .addTo(map)
                        .bindPopup(`
                            <div style="font-family: 'Poppins', sans-serif;">
                                <strong style="font-size: 1.1rem;">${node.name}</strong><br>
                                <span style="color: #64748b;"><i class="fas fa-map-marker-alt"></i> ${node.location}</span><br>
                                <span class="status-badge status-${node.status}" style="margin-top: 8px;">${node.status.toUpperCase()}</span><hr>
                                <a href="violations.php?node=${node.id}" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                                    <i class="fas fa-exclamation-triangle"></i> View Violations
                                </a>
                            </div>
                        `);
                    markers.push(marker);
                }
            });

            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        // Search functionality
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.nodes-table tbody tr');
            const cards = document.querySelectorAll('.node-card');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });

            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Form validation
        document.querySelector('.node-form')?.addEventListener('submit', function(e) {
            const name = document.getElementById('name')?.value.trim();
            const location = document.getElementById('location')?.value.trim();

            if (!name) {
                e.preventDefault();
                alert('Node name is required!');
                return false;
            }

            if (!location) {
                e.preventDefault();
                alert('Location is required!');
                return false;
            }
        });
    </script>
    <script src="js/nodes.js"></script>
</body>
</html>