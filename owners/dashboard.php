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

// Fetch Owner Profile Picture
$owner_profile_pic = '';
$stmt_pic = $conn->prepare("SELECT profile_picture FROM owners WHERE id = ?");
$stmt_pic->bind_param("i", $owner_id);
$stmt_pic->execute();
$owner_profile_pic = $stmt_pic->get_result()->fetch_assoc()['profile_picture'] ?? '';

try {
    // 1. Total Vehicles
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM vehicles WHERE owner_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalVehicles = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 2. Total Violations
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM violations v JOIN vehicles ve ON v.vehicle_id = ve.id WHERE ve.owner_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalViolations = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 3. Unpaid Violations
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unpaid 
        FROM violations v 
        JOIN vehicles ve ON v.vehicle_id = ve.id 
        WHERE ve.owner_id = ? AND v.status != 'paid'
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $unpaidViolations = $stmt->get_result()->fetch_assoc()['unpaid'] ?? 0;

    // 4. Total Accidents
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM incidents i JOIN vehicles ve ON i.vehicle_id = ve.id WHERE ve.owner_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalAccidents = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 5. Recent Violations
    $stmt = $conn->prepare("
        SELECT v.*, n.name AS node_name, ve.plate_number
        FROM violations v
        JOIN vehicles ve ON v.vehicle_id = ve.id
        LEFT JOIN nodes n ON v.node_id = n.id
        WHERE ve.owner_id = ?
        ORDER BY violation_time DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $recentViolations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 6. Recent Accidents
    $stmt = $conn->prepare("
        SELECT i.*, n.name AS node_name, ve.plate_number
        FROM incidents i
        JOIN vehicles ve ON i.vehicle_id = ve.id
        LEFT JOIN nodes n ON i.node_id = n.id
        WHERE ve.owner_id = ?
        ORDER BY reported_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $recentAccidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 7. My Vehicles
    $stmt = $conn->prepare("
        SELECT v.*, vt.type_name
        FROM vehicles v
        LEFT JOIN vehicle_types vt ON v.type_id = vt.id
        WHERE v.owner_id = ?
        ORDER BY v.registered_at DESC
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $myVehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 8. Pending Payments
    $stmt = $conn->prepare("
        SELECT p.*, 
               v.violation_type, 
               v.vehicle_id as v_vehicle_id,
               i.incident_type, 
               i.severity,
               i.vehicle_id as i_vehicle_id,
               COALESCE(vv.plate_number, vi.plate_number) as plate_number
        FROM payments p
        LEFT JOIN violations v ON p.violation_id = v.id
        LEFT JOIN incidents i ON p.incident_id = i.id
        LEFT JOIN vehicles vv ON v.vehicle_id = vv.id
        LEFT JOIN vehicles vi ON i.vehicle_id = vi.id
        WHERE p.owner_id = ? AND p.payment_status = 'pending'
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 9. Recent Notifications
    $stmt = $conn->prepare("
        SELECT * FROM notifications
        WHERE owner_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $recentNotifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 10. Total Pending Payments Amount
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total_pending
        FROM payments
        WHERE owner_id = ? AND payment_status = 'pending'
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $totalPendingAmount = $stmt->get_result()->fetch_assoc()['total_pending'] ?? 0;

} catch (Exception $e) {
    $totalVehicles = 0;
    $totalViolations = 0;
    $unpaidViolations = 0;
    $totalAccidents = 0;
    $recentViolations = [];
    $recentAccidents = [];
    $myVehicles = [];
    $pendingPayments = [];
    $recentNotifications = [];
    $totalPendingAmount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - Traffic Monitoring System</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> My Violations <span class="badge badge-warning"><?= $unpaidViolations ?></span></a></li>
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
                <h1>Owner Dashboard</h1>
                <div class="breadcrumb"><span class="active">Dashboard</span></div>
            </div>
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
                    <div class="dropdown-menu">
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        <div class="divider"></div>
                        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
                <button class="btn-theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
            </div>
        </nav>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-car"></i></div>
                <div class="stat-info">
                    <h3><?= $totalVehicles ?></h3>
                    <p>Total Vehicles</p>
                </div>
                <div class="stat-trend positive"><i class="fas fa-check"></i><span>Active</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <h3><?= $totalViolations ?></h3>
                    <p>Total Violations</p>
                </div>
                <div class="stat-trend <?= ($totalViolations > 0) ? 'negative' : 'positive' ?>"><i class="fas fa-exclamation-circle"></i><span>Recorded</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div class="stat-info">
                    <h3><?= $unpaidViolations ?></h3>
                    <p>Unpaid Fines</p>
                </div>
                <div class="stat-trend <?= ($unpaidViolations > 0) ? 'negative' : 'positive' ?>"><i class="fas fa-clock"></i><span>Pending</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-car-crash"></i></div>
                <div class="stat-info">
                    <h3><?= $totalAccidents ?></h3>
                    <p>Total Accidents</p>
                </div>
                <div class="stat-trend <?= ($totalAccidents > 0) ? 'negative' : 'positive' ?>"><i class="fas fa-exclamation-triangle"></i><span>Reported</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($totalPendingAmount, 2) ?> ETB</h3>
                    <p>Pending Payments</p>
                </div>
                <div class="stat-trend <?= ($totalPendingAmount > 0) ? 'warning' : 'positive' ?>">
                    <i class="fas fa-exclamation-circle"></i><span><?= ($totalPendingAmount > 0) ? 'Action Required' : 'Clear' ?></span>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid: Left & Right Columns -->
        <div class="dashboard-grid">
            <!-- Left Column: Pending Payments -->
            <div class="dashboard-main">
                <?php if (!empty($pendingPayments)): ?>
                    <div class="activity-card pending-payments-alert">
                        <div class="activity-header">
                            <h3><i class="fas fa-exclamation-triangle"></i> Pending Payments - Action Required</h3>
                            <a href="payments.php" class="view-all">View All (<?= count($pendingPayments) ?>)</a>
                        </div>
                        <div class="activity-list">
                            <?php foreach ($pendingPayments as $payment): ?>
                                <div class="activity-item payment-item">
                                    <div class="activity-icon warning">
                                        <i class="fas fa-<?= $payment['payment_type'] === 'violation' ? 'exclamation-triangle' : 'car-crash' ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <h4>
                                            <?= ucfirst($payment['payment_type'] ?? 'Payment') ?> 
                                            <?php if (!empty($payment['plate_number'])): ?>
                                                - <?= htmlspecialchars($payment['plate_number']) ?>
                                            <?php else: ?>
                                                - Vehicle
                                            <?php endif; ?>
                                        </h4>
                                        <p>
                                            <?php if ($payment['payment_type'] === 'violation' && !empty($payment['violation_type'])): ?>
                                                Type: <?= htmlspecialchars(str_replace('_', ' ', ucfirst($payment['violation_type']))) ?>
                                            <?php elseif ($payment['payment_type'] === 'accident'): ?>
                                                <?= htmlspecialchars($payment['incident_type'] ?? 'Accident') ?> 
                                                <?php if (!empty($payment['severity'])): ?>
                                                    (<?= htmlspecialchars($payment['severity']) ?> severity)
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($payment['penalty_reason'] ?? 'Fine payment required') ?>
                                            <?php endif; ?>
                                        </p>
                                        <p style="color: var(--danger-color); font-weight: 600; font-size: 1.1rem;">
                                            Amount: <?= number_format($payment['amount'], 2) ?> ETB
                                        </p>
                                        <p style="font-size: 0.85rem; color: var(--text-light);">
                                            <i class="fas fa-clock"></i> <?= date('M d, Y H:i', strtotime($payment['created_at'])) ?>
                                        </p>
                                    </div>
                                    <div class="activity-status">
                                        <a href="payments.php?pay=<?= $payment['id'] ?>" class="btn-pay">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="activity-card" style="background: #f0f9ff; border-left: 4px solid #3b82f6;">
                        <div class="activity-header">
                            <h3><i class="fas fa-info-circle"></i> Payment Status</h3>
                        </div>
                        <p style="padding: 15px; color: #1e40af;">
                            <i class="fas fa-check-circle"></i> You have no pending payments at this time.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar: Notifications & Vehicles -->
            <div class="dashboard-sidebar">
                <!-- Recent Notifications -->
                <?php if (!empty($recentNotifications)): ?>
                <div class="activity-card">
                    <div class="activity-header"><h3><i class="fas fa-bell"></i> Notifications</h3></div>
                    <div class="activity-list">
                        <?php foreach ($recentNotifications as $notif): ?>
                            <div class="activity-item">
                                <div class="activity-icon notification-icon"><i class="fas fa-bell"></i></div>
                                <div class="activity-details">
                                    <h4><?= htmlspecialchars($notif['title']) ?></h4>
                                    <p><?= htmlspecialchars(substr($notif['message'], 0, 80)) ?>...</p>
                                </div>
                                <div class="activity-time"><?= date('M d', strtotime($notif['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- My Vehicles -->
                <div class="activity-card">
                    <div class="activity-header"><h3><i class="fas fa-car"></i> My Vehicles</h3></div>
                    <div class="vehicles-grid">
                        <?php if (!empty($myVehicles)): ?>
                            <?php foreach ($myVehicles as $vehicle): ?>
                                <div class="vehicle-card">
                                    <div class="vehicle-image">
                                        <?php if(!empty($vehicle['vehicle_image'])): ?>
                                            <img src="../<?= htmlspecialchars($vehicle['vehicle_image']) ?>" alt="Car">
                                        <?php else: ?>
                                            <i class="fas fa-car fa-2x" style="color: #adb5bd;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vehicle-details">
                                        <div class="vehicle-plate"><?= htmlspecialchars($vehicle['plate_number']) ?></div>
                                        <div style="font-size: 0.9rem; color: var(--text-color); font-weight: 500;"><?= htmlspecialchars($vehicle['model'] ?? 'Unknown Model') ?></div>
                                        <div class="vehicle-info"><span><i class="fas fa-tag"></i> <?= htmlspecialchars($vehicle['type_name']) ?></span></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="grid-column: 1/-1; text-align: center; padding: 30px; color: #64748b;">
                                <i class="fas fa-car fa-2x" style="margin-bottom: 10px;"></i>
                                <p>No vehicles registered</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
      </body>
      </html>