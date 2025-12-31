<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Owner');
$userRole = 'Vehicle Owner';
$owner_profile_pic = '';
$stmt_pic = $conn->prepare("SELECT profile_picture FROM owners WHERE id = ?");
$stmt_pic->bind_param("i", $owner_id);
$stmt_pic->execute();
$owner_profile_pic = $stmt_pic->get_result()->fetch_assoc()['profile_picture'] ?? '';
function getFine($type) {
    return match ($type) {
        'speeding' => 2000,
        'red_light' => 3000,
        'lane_violation' => 1500,
        'illegal_parking' => 1000,
        'wrong_direction' => 4000,
        default => 0
    };
}

try {
    $stmt = $conn->prepare("
        SELECT v.*, ve.plate_number, n.name AS node_name
        FROM violations v
        JOIN vehicles ve ON v.vehicle_id = ve.id
        LEFT JOIN nodes n ON v.node_id = n.id
        WHERE ve.owner_id = ?
        ORDER BY v.violation_time DESC
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $violations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unpaid 
        FROM violations v 
        JOIN vehicles ve ON v.vehicle_id = ve.id 
        WHERE ve.owner_id = ? AND v.status != 'paid'
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $unpaidViolations = $stmt->get_result()->fetch_assoc()['unpaid'] ?? 0;

} catch (Exception $e) {
    $violations = [];
    $unpaidViolations = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Violations - Traffic Monitoring System</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .violation-table-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-top: 24px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .data-table th {
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .pay-btn {
            background: var(--primary-color);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: var(--transition);
        }
        .pay-btn:hover { background: var(--primary-dark); }
</style>
</head>
<body>
<div class="container">
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
<div class="sidebar-header">
<div class="logo">
<img src="../assets/images/logo.png" alt="Logo" onerror="this.src='https://placehold.co/40x40?text=TS'">
<h2>TrafficSense</h2>
</div>
<button class="toggle-sidebar" id="toggleSidebar">
<i class="fas fa-bars"></i>
</button>
</div>
<div class="sidebar-menu">
<ul>
<li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
<li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
<li class="active"><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> My Violations <span class="badge badge-warning"><?= $unpaidViolations ?></span></a></li>
<li><a href="accident.php"><i class="fas fa-car-crash"></i> My Accidents</a></li>
<li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
<li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>
</aside>
<!-- Main Content -->
<main class="main-content">
<nav class="navbar">
<div class="navbar-left">
<h1>My Violations</h1>
<div class="breadcrumb">
<a href="dashboard.php">Dashboard</a>
<i class="fas fa-chevron-right"></i>
 <span class="active">Violations</span>
</div>
</div>
<!-- User menu and theme toggle same as dashboard -->
<div class="navbar-right">
<div class="user-menu">
<div class="user-info">
<span class="user-name"><?= $userName ?></span>
 <span class="user-role"><?= $userRole ?></span>
    </div>
<div class="user-avatar">
<?php if(!empty($owner_profile_pic)): ?>
<img src="../<?= htmlspecialchars($owner_profile_pic) ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
<?php else: ?>
<i class="fas fa-user-circle"></i>
<?php endif; ?>
</div>
</div>
<button class="btn-theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
</di>
</nav>
<div class="violation-table-card">
<h3><i class="fas fa-list"></i> Traffic Violations List</h3>
<div style="overflow-x: auto;">
<?php if (empty($violations)): ?>
<div class="empty-state" style="text-align: center; padding: 40px;">
<i class="fas fa-check-circle fa-4x" style="color: var(--secondary-color); margin-bottom: 20px;"></i>
<h3>No Violations Found</h3>
<p>Great! You have no traffic violations recorded.</p>
</div>
<?php else: ?>
<table class="data-table">
<thead>
<tr>
<th>Violation Type</th>
<th>Plate Number</th>
<th>Location</th>
<th>Date & Time</th>
<th>Fine (ETB)</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($violations as $v): 
$fine = getFine($v['violation_type']);
$statusClass = 'status-' . strtolower($v['status']);
?>
<tr>
<td><strong><?= ucfirst(str_replace('_', ' ', $v['violation_type'])) ?></strong></td>
<td><?= htmlspecialchars($v['plate_number']) ?></td>
<td><?= htmlspecialchars($v['node_name'] ?? 'Unknown') ?></td>
<td><?= date('M d, Y h:i A', strtotime($v['violation_time'])) ?></td>
<td><?= number_format($fine, 2) ?></td>
<td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($v['status']) ?></span></td>
<td>
 <?php if ($v['status'] !== 'paid'): ?>
 <a href="payment_method.php?violation_id=<?= $v['id'] ?>" class="pay-btn">
<i class="fas fa-credit-card"></i> Pay Fine
</a>
<?php else: ?>
<span style="color: var(--secondary-color);"><i class="fas fa-check"></i> Settle</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>
</main>
</div>
<script>
document.getElementById('toggleSidebar')?.addEventListener('click', () => {
document.getElementById('sidebar').classList.toggle('collapsed');
        });
document.getElementById('themeToggle')?.addEventListener('click', () => {
document.body.classList.toggle('dark-theme');
        });
    </script>
</body>
</html>
