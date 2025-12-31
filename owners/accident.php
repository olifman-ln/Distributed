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

function getPenalty($severity) {
    return match ($severity) {
        'low' => 500,
        'medium' => 1000,
        'high' => 2000,
        'critical' => 5000,
        default => 0
    };
}

try {
    $stmt = $conn->prepare("
        SELECT i.*, v.plate_number, n.name AS node_name
        FROM incidents i
        JOIN vehicles v ON i.vehicle_id = v.id
        LEFT JOIN nodes n ON i.node_id = n.id
        WHERE v.owner_id = ?
        ORDER BY i.reported_at DESC
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $accidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    $accidents = [];
    $unpaidViolations = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Accidents - Traffic Monitoring System</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .accident-card-container {
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
        .severity-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .sev-low { background: #d1fae5; color: #065f46; }
        .sev-medium { background: #fef3c7; color: #92400e; }
        .sev-high { background: #fee2e2; color: #991b1b; }
        .sev-critical { background: #7f1d1d; color: white; }
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
    <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> My Violations <span class="badge badge-warning"><?= $unpaidViolations ?></span></a></li>
    <li class="active"><a href="accident.php"><i class="fas fa-car-crash"></i> My Accidents</a></li>
    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
        <nav class="navbar">
        <div class="navbar-left">
        <h1>Accident Records</h1>
        <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        <i class="fas fa-chevron-right"></i>
        <span class="active">Accidents</span>
        </div>
        </div>
        <div class="navbar-right">
        <div class="user-menu">
        <div class="user-info">
        <span class="user-name"><?= $userName ?></span>
        <span class="user-role"><?= $userRole ?></span>
        </div>
        <div class="user-avatar">
         <?php 
    $stmt_pic = $conn->prepare("SELECT profile_picture FROM owners WHERE id = ?");
    $stmt_pic->bind_param("i", $owner_id);
    $stmt_pic->execute();
    $pic_result = $stmt_pic->get_result()->fetch_assoc();
    if(!empty($pic_result['profile_picture'])): 
        ?>
    <img src="../<?= htmlspecialchars($pic_result['profile_picture']) ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
         <i class="fas fa-user-circle"></i>
        <?php endif; ?>
        </div>
        </div>
        <button class="btn-theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
        </div>
        </nav>
        <div class="accident-card-container">
        <h3><i class="fas fa-car-crash"></i> Traffic Incidents History</h3>
        <div style="overflow-x: auto;">
        <?php if (empty($accidents)): ?>
         <div class="empty-state" style="text-align: center; padding: 40px;">
         <i class="fas fa-car-side fa-4x" style="color: var(--secondary-color); margin-bottom: 20px;"></i>
        <h3>No Records Found</h3>
        <p>Good news! No accident records associated with your vehicles.</p>
        </div>
        <?php else: ?>
        <table class="data-table">
        <thead>
        <tr>
        <th>Incident Type</th>
        <th>Vehicle</th>
        <th>Location</th>
        <th>Severity</th>
        <th>Date</th>
         <th>Description</th>
        <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($accidents as $a): 
        $severityClass = 'sev-' . strtolower($a['severity']);
        ?>
        <tr>
         <td><strong><?= ucfirst($a['incident_type'] ?? 'Accident') ?></strong></td>
        <td><?= htmlspecialchars($a['plate_number']) ?></td>
        <td><?= htmlspecialchars($a['node_name'] ?? 'Unknown') ?></td>
        <td><span class="severity-badge <?= $severityClass ?>"><?= ucfirst($a['severity']) ?></span></td>
        <td><?= date('M d, Y', strtotime($a['reported_at'])) ?></td>
         <td><small><?= htmlspecialchars($a['description']) ?></small></td>
         <td>
        <?php 
        // Check if penalty needs payment
        $stmtPay = $conn->prepare("SELECT payment_status FROM payments WHERE incident_id = ? ORDER BY id DESC LIMIT 1");
        $stmtPay->bind_param("i", $a['id']);
        $stmtPay->execute();
        $payment = $stmtPay->get_result()->fetch_assoc();
        if ($payment && $payment['payment_status'] === 'paid'): ?>
        <span style="color: var(--secondary-color); font-weight: 500;"><i class="fas fa-check"></i> Settle</span>
        <?php else: ?>
        <span style="color: var(--warning-color); font-weight: 500;"><i class="fas fa-clock"></i> Pending</span>
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
