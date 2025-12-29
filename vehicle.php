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
$vehicleId = intval($_GET['id'] ?? 0);
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate_number = clean($_POST['plate_number']);
    $type_id = intval($_POST['type_id']);
    $model = clean($_POST['model']);
    $manufacture_year = intval($_POST['manufacture_year'] ?? 0);
    $color = clean($_POST['color']);
    $owner_id = intval($_POST['owner_id']);

    try {
        $vehicle_image = null;
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['vehicle_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $newFilename = "vec_" . time() . "_" . $plate_number . "." . $ext;
                if (!file_exists('uploads/vehicles')) {
                    mkdir('uploads/vehicles', 0777, true);
                }
                $targetPath = "uploads/vehicles/" . $newFilename;
                if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $targetPath)) {
                    $vehicle_image = $targetPath;
                }
            }
        }

        if ($action === 'add') {
            $checkStmt = $conn->prepare("SELECT id FROM vehicles WHERE plate_number = ?");
            $checkStmt->bind_param("s", $plate_number);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $error = "A vehicle with this plate number already exists!";
            } else {
                $stmt = $conn->prepare("INSERT INTO vehicles (plate_number, type_id, model, manufacture_year, color, owner_id, vehicle_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisisis", $plate_number, $type_id, $model, $manufacture_year, $color, $owner_id, $vehicle_image);
                if ($stmt->execute()) {
                    $message = "Vehicle added successfully!";
                } else {
                    $error = "Database error: " . $stmt->error;
                }
            }
        } elseif ($action === 'edit' && $vehicleId > 0) {
            if ($vehicle_image) {
                $stmt = $conn->prepare("UPDATE vehicles SET plate_number=?, type_id=?, model=?, manufacture_year=?, color=?, owner_id=?, vehicle_image=? WHERE id=?");
                $stmt->bind_param("sisisisi", $plate_number, $type_id, $model, $manufacture_year, $color, $owner_id, $vehicle_image, $vehicleId);
            } else {
                $stmt = $conn->prepare("UPDATE vehicles SET plate_number=?, type_id=?, model=?, manufacture_year=?, color=?, owner_id=? WHERE id=?");
                $stmt->bind_param("sisisii", $plate_number, $type_id, $model, $manufacture_year, $color, $owner_id, $vehicleId);
            }

            if ($stmt->execute()) {
                $message = "Vehicle updated successfully!";
            } else {
                $error = "Database error: " . $stmt->error;
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
if ($action === 'delete' && $vehicleId > 0) {
    $stmt = $conn->prepare("DELETE FROM vehicles WHERE id=?");
    $stmt->bind_param("i", $vehicleId);
    if ($stmt->execute()) {
        $message = "Vehicle deleted successfully!";
    } else {
        $error = "Error deleting vehicle: " . $stmt->error;
    }
    header("Location: vehicle.php");
    exit();
}
$sql = "
    SELECT v.*, vt.type_name, o.full_name AS owner_name
    FROM vehicles v
    LEFT JOIN vehicle_types vt ON v.type_id = vt.id
    LEFT JOIN owners o ON v.owner_id = o.id
    ORDER BY v.registered_at DESC
";
$result = $conn->query($sql);
$vehicles = $result->fetch_all(MYSQLI_ASSOC);
$ownersResult = $conn->query("SELECT id, full_name FROM owners ORDER BY full_name ASC");
$owners = $ownersResult->fetch_all(MYSQLI_ASSOC);
$typesResult = $conn->query("SELECT id, type_name FROM vehicle_types ORDER BY type_name ASC");
$types = $typesResult->fetch_all(MYSQLI_ASSOC);
$vehicleToEdit = null;
if ($action === 'edit' && $vehicleId > 0) {
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE id=?");
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicleToEdit = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicles - Traffic Monitoring System</title>
    <link rel="stylesheet" href="css/vehicles.css">
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
                    <li class="active"><a href="vehicle.php"><i class="fas fa-car"></i> Vehicles</a></li>
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
            <div style="padding: 30px;">
<h1><i class="fas fa-car"></i> Vehicles Management</h1>
<?php if ($message): ?>
<div class="alert alert-success">
<i class="fas fa-check-circle"></i> <?php echo $message; ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error">
<i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
</div>
 <?php endif; ?>
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card">
<div class="card-header">
<h2><i class="fas fa-<?php echo $action === 'add' ? 'plus-circle' : 'edit'; ?>"></i>
<?php echo ucfirst($action); ?> Vehicle
</h2>
<a href="vehicle.php" class="btn-back">
<i class="fas fa-arrow-left"></i> Back to List
</a>
</div>
<div class="card-body">
<form method="post" enctype="multipart/form-data" class="vehicle-form">
<input type="hidden" name="id" value="<?php echo $vehicleToEdit['id'] ?? ''; ?>">
<div class="form-grid">
<div class="form-group">
<label for="plate_number">
 <i class="fas fa-id-card"></i> Plate Number *
</label>
<input type="text" id="plate_number" name="plate_number"
 value="<?php echo htmlspecialchars($vehicleToEdit['plate_number'] ?? ''); ?>"
 required placeholder="e.g., ABC-1234">
</div>
<div class="form-group">
<label for="type_id">
<i class="fas fa-car-side"></i> Vehicle Type *
 </label>
<select id="type_id" name="type_id" required>
<option value="">Select Type</option>
<?php foreach ($types as $type): ?>
<option value="<?php echo $type['id']; ?>"
<?php echo (isset($vehicleToEdit['type_id']) && $vehicleToEdit['type_id'] == $type['id']) ? 'selected' : ''; ?>>
 <?php echo htmlspecialchars($type['type_name']); ?>
</option>
 <?php endforeach; ?>
</select>
</div>
<div class="form-group">
 <label for="model">
 <i class="fas fa-cog"></i> Model
</label>
<input type="text" id="model" name="model"
value="<?php echo htmlspecialchars($vehicleToEdit['model'] ?? ''); ?>"
placeholder="e.g., Toyota Corolla">
</div>
<div class="form-group">
<label for="manufacture_year">
<i class="fas fa-calendar-alt"></i> Manufacture Year </label>
<input type="number" id="manufacture_year" name="manufacture_year"
min="1900" max="<?php echo date('Y') + 1; ?>"
value="<?php echo htmlspecialchars($vehicleToEdit['manufacture_year'] ?? date('Y')); ?>"
 placeholder="e.g., 2020">
</div>
<div class="form-group">
<label for="color">
<i class="fas fa-palette"></i> Color
</label>
<input type="text" id="color" name="color"
 value="<?php echo htmlspecialchars($vehicleToEdit['color'] ?? ''); ?>"
placeholder="e.g., Red, Blue, Black">
</div>
<div class="form-group">
<label for="owner_id">
<i class="fas fa-user"></i> Owner *
</label>
<select id="owner_id" name="owner_id" required>
<option value="">Select Owner</option>
<?php foreach ($owners as $owner): ?>
<option value="<?php echo $owner['id']; ?>"
<?php echo (isset($vehicleToEdit['owner_id']) && $vehicleToEdit['owner_id'] == $owner['id']) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($owner['full_name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group" style="grid-column: span 2;">
    <label for="vehicle_image">
        <i class="fas fa-image"></i> Vehicle Image
    </label>
    <div style="display: flex; align-items: center; gap: 15px;">
        <?php if(!empty($vehicleToEdit['vehicle_image'])): ?>
            <img src="<?php echo htmlspecialchars($vehicleToEdit['vehicle_image']); ?>" alt="Current Car" style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
        <?php endif; ?>
        <input type="file" id="vehicle_image" name="vehicle_image" accept="image/*">
    </div>
    <small style="color: #666; display: block; margin-top: 5px;">Upload a clear photo of the vehicle (JPG, PNG, GIF).</small>
</div>
</div>
<div class="form-actions">
<button type="submit" class="btn btn-primary">
<i class="fas fa-<?php echo $action === 'add' ? 'save' : 'edit'; ?>"></i>
<?php echo ucfirst($action); ?> Vehicle
</button>
<a href="vehicle.php" class="btn btn-secondary">
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
<i class="fas fa-car"></i>
</div>
<div class="stat-info">
<h3><?php echo count($vehicles); ?></h3>
<p>Total Vehicles</p>
</div>
</div>
<div class="stat-card">
<div class="stat-icon">
<i class="fas fa-id-card"></i>
</div>
<div class="stat-info">
<h3><?php
$uniqueOwners = [];
foreach ($vehicles as $v) {
 $uniqueOwners[$v['owner_id']] = true;
}
echo count($uniqueOwners);
?></h3>
<p>Unique Owners</p>
</d>
</div>
</div>
<!-- Add Vehicle Button -->
<div class="action-bar">
<a href="vehicle.php?action=add" class="btn-add">
<i class="fas fa-plus"></i> Add New Vehicle
</a>
<div class="search-box">
<input type="text" id="searchInput" placeholder="Search vehicles...">
<button class="search-btn"><i class="fas fa-search"></i></button>
 </div>
</div>
<!-- Vehicles Table -->
<div class="table-container">
<table class="vehicles-table">
<thead>
<tr>
    <th>Vehicle Details</th>
    <th>Type</th>
    <th>Owner</th>
    <th>Registered</th>
    <th>Actions</th>
    </tr>
    </thead>
    <tbody>
 <?php if (!empty($vehicles)): ?>
<?php foreach ($vehicles as $v): ?>
    <tr>
    <td>
        <div class="vehicle-detail-cell">
            <div class="vehicle-thumb">
                <?php if(!empty($v['vehicle_image'])): ?>
                    <img src="<?php echo htmlspecialchars($v['vehicle_image']); ?>" alt="Car">
                <?php else: ?>
                    <i class="fas fa-car" style="color: #adb5bd;"></i>
                <?php endif; ?>
            </div>
            <div class="vehicle-info-text">
                <span class="plate-number">
                    <?php echo htmlspecialchars($v['plate_number']); ?>
                </span>
                <span class="vehicle-meta">
                    <?php echo htmlspecialchars($v['model'] ?? 'Unknown Model'); ?> (<?php echo htmlspecialchars($v['manufacture_year'] ?? 'N/A'); ?>) • 
                    <span style="color: <?php echo htmlspecialchars($v['color'] ?: '#64748b'); ?>;"><?php echo htmlspecialchars($v['color'] ?: 'N/A'); ?></span>
                </span>
            </div>
        </div>
</td>
<td>
<span class="badge badge-type">
<?php echo htmlspecialchars($v['type_name']); ?>
</span>
</td>
 <td>
<a href="owners.php?edit=<?php echo $v['owner_id']; ?>" class="owner-link">
<i class="fas fa-user"></i>
<?php echo htmlspecialchars($v['owner_name']); ?>
</a>
</td>
 <td>
<?php echo date('M d, Y', strtotime($v['registered_at'])); ?>
</td>
<td>
<div class="action-buttons">
<a href="vehicle.php?action=edit&id=<?php echo $v['id']; ?>"
 class="btn-action btn-edit" title="Edit">
<i class="fas fa-edit"></i>
</a>
<a href="vehicle.php?action=delete&id=<?php echo $v['id']; ?>"
class="btn-action btn-delete" title="Delete"
onclick="return confirm('Are you sure you want to delete this vehicle? This action cannot be undone.')">
<i class="fas fa-trash"></i>
</a>
<a href="violations.php?vehicle=<?php echo $v['id']; ?>"
class="btn-action btn-view" title="View Violations">
<i class="fas fa-exclamation-triangle"></i>
</a>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="8">
<div class="empty-state">
<i class="fas fa-car fa-4x"></i>
<h3>No Vehicles Found</h3>
<p>Start by adding a new vehicle using the "Add New Vehicle" button above.</p>
 </div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
<script src="js/vehicles.js"></script>
        </div>
    </main>
</div>
</body>
</html>