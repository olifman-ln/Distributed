<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}
require_once "db.php";
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM owners WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: owners.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean($_POST['full_name']);
    $phone = clean($_POST['phone']);
    $username = clean($_POST['username']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    $license_number = clean($_POST['license_number']);
    $national_id = clean($_POST['national_id']);
 if (!empty($_POST['id'])) {
        // Update
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("
            UPDATE owners SET 
            full_name = ?, phone = ?,username = ?, email = ?, address = ?, 
            license_number = ?, national_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "sssssssi",
            $full_name,
            $phone,
            $username,
            $email,
            $address,
            $license_number,
            $national_id,
            $id
        );
        $stmt->execute();
    } else {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
         $stmt = $conn->prepare("
            INSERT INTO owners 
            (full_name, phone,username, email,password,address, license_number, national_id)
            VALUES (?, ?,?, ?,?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssss",
            $full_name,
            $phone,
            $username,
            $email,
            $password,
            $address,
            $license_number,
            $national_id
        );
        $stmt->execute();
    }
header("Location: owners.php");
    exit();
}
$search = $_GET['search'] ?? '';
if ($search) {
    $stmt = $conn->prepare("
    SELECT * FROM owners 
    WHERE full_name LIKE ? OR phone LIKE ? OR license_number LIKE ?
    ORDER BY created_at DESC
    ");
    $like = "%$search%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $owners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $owners = $conn->query("
        SELECT * FROM owners ORDER BY created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}
$editOwner = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM owners WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editOwner = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Owners Management - Traffic Monitoring System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/owner.css">
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
                    <li class="active"><a href="owners.php"><i class="fas fa-users"></i> Owners</a></li>
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
                <h2><i class="fas fa-users"></i> Vehicle Owners Management</h2>
<form method="get" class="search-form">
<input type="text" name="search" placeholder="Search by name, phone, or license..."
value="<?= htmlspecialchars($search) ?>">
<button type="submit">
<i class="fas fa-search"></i> Search
 </button>
</form>
<div class="owner-form">
<h3><i class="fas fa-<?= $editOwner ? 'edit' : 'plus-circle' ?>"></i>
<?= $editOwner ? 'Edit Owner' : 'Add New Owner' ?>
</h3>
<form method="post">
<input type="hidden" name="id" value="<?= $editOwner['id'] ?? '' ?>">
<div class="form-grid">
<div class="form-group">
<label>Full Name *</label>
<input type="text" name="full_name" placeholder="Enter full name" required
value="<?= htmlspecialchars($editOwner['full_name'] ?? '') ?>">
</div>
<div class="form-group">
<label>Phone Number</label>
<input type="text" name="phone" placeholder="Enter phone number"
value="<?= htmlspecialchars($editOwner['phone'] ?? '') ?>">
</div>
<div class="form-group">
<label>Username *</label>
<input type="text" name="username" placeholder="Enter username" required
value="<?= htmlspecialchars($editOwner['username'] ?? '') ?>">
</div>
<div class="form-group">
<label>Email Address</label>
<input type="email" name="email" placeholder="Enter email"
value="<?= htmlspecialchars($editOwner['email'] ?? '') ?>">
</div>
<?php if (!$editOwner): ?>
<div class="form-group">
<label>Password *</label>
<input type="password" name="password" placeholder="Enter password" required>
</div>
<?php endif; ?>

<div class="form-group">
<label>License Number</label>
<input type="text" name="license_number" placeholder="Enter license number"
value="<?= htmlspecialchars($editOwner['license_number'] ?? '') ?>">
</div>
<div class="form-group">
<label>National ID</label>
<input type="text" name="national_id" placeholder="Enter national ID"
value="<?= htmlspecialchars($editOwner['national_id'] ?? '') ?>">
</div>
<div class="form-group" style="grid-column: 1 / -1;">
<label>Address</label>
<textarea name="address" placeholder="Enter full address" rows="3"><?=
htmlspecialchars($editOwner['address'] ?? '') ?></textarea>
</div>
</div>
<button type="submit" class="submit-btn">
<i class="fas fa-<?= $editOwner ? 'save' : 'user-plus' ?>"></i>
<?= $editOwner ? 'Update Owner' : 'Add Owner' ?>
</button>
</form>
</div>
<h3><i class="fas fa-list"></i> Owners List (<?= count($owners) ?>)</h3>
<div class="table-container">
<?php if (empty($owners)): ?>
<div class="empty-state">
 <i class="fas fa-users-slash fa-5x"></i>
<h3>No owners found</h3>
<p>Start by adding a new owner using the form above</p>
</div>
<?php else: ?>
<table>
<thead>
<tr>
<th>Name</th>
<th>Phone</th>
<th>License</th>
<th>Email</th>
<th>Registered</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($owners as $owner): ?>
<tr>
<td>
<strong><?= htmlspecialchars($owner['full_name']) ?></strong>
<?php if ($owner['national_id']): ?>
<br><small>ID: <?= htmlspecialchars($owner['national_id']) ?></small>
<?php endif; ?>
</td>
<td><?= $owner['phone'] ? htmlspecialchars($owner['phone']) : '<span class="text-muted">N/A</span>' ?></td>
<td><?= htmlspecialchars($owner['license_number']) ?></td>
<td><?= $owner['email'] ? htmlspecialchars($owner['email']) : '<span class="text-muted">N/A</span>' ?></td>
<td><?= date('M d, Y', strtotime($owner['created_at'])) ?></td>
<td>
<div class="actions">
<a href="?edit=<?= $owner['id'] ?>" class="action-btn edit-btn">
<i class="fas fa-edit"></i> Edit
</a>
<a href="?delete=<?= $owner['id'] ?>"
class="action-btn delete-btn"
onclick="return confirm('Are you sure you want to delete this owner? This action cannot be undone.')">
<i class="fas fa-trash"></i> Delete
</a>
</div>
</td>
 </tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
 </div>
</div>
<script>
    window.editId = <?= $editOwner['id'] ?? 'null' ?>;
</script>
<script src="js/owners.js"></script>
        </div>
    </main>
</div>
</body>
</html>