<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];
$payment_id = (int)($_GET['payment_id'] ?? 0);

try {
    $stmt = $conn->prepare("
        SELECT * FROM payments 
        WHERE id=? AND owner_id=? AND payment_status='pending'
    ");
    $stmt->bind_param("ii", $payment_id, $owner_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();

    if (!$payment) {
        // Check if violation_id was passed instead
        $violation_id = (int)($_GET['violation_id'] ?? 0);
        if ($violation_id > 0) {
            $stmt = $conn->prepare("SELECT * FROM payments WHERE violation_id=? AND owner_id=? AND payment_status='pending' ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("ii", $violation_id, $owner_id);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();
        }
    }

    if (!$payment) {
        die("Invalid payment request or payment already processed.");
    }

    // Sidebar data
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
    die("Error retrieving payment information.");
}

$userName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Owner');
$userRole = 'Vehicle Owner';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Method - Traffic Monitoring System</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .method-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
            max-width: 600px;
            margin: 40px auto;
        }
        .payment-summary {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-color);
        }
        .method-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: var(--transition);
        }
        .method-option:hover { border-color: var(--primary-color); background: #f0f9ff; }
        .method-option input { margin-right: 15px; width: 18px; height: 18px; }
        .method-option i { font-size: 1.5rem; margin-right: 15px; color: var(--text-light); }
        .confirm-btn {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: var(--transition);
        }
        .confirm-btn:hover { background: var(--primary-dark); }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar same as dashboard -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                   <img src="../assets/images/logo.png" alt="Logo" onerror="this.src='https://placehold.co/40x40?text=TS'">
                   <h2>TrafficSense</h2>
                </div>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> My Violations <span class="badge badge-warning"><?= $unpaidViolations ?></span></a></li>
                    <li><a href="accident.php"><i class="fas fa-car-crash"></i> My Accidents</a></li>
                    <li class="active"><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </aside>

        <main class="main-content">
            <nav class="navbar">
                <div class="navbar-left">
                    <h1>Settle Payment</h1>
                    <div class="breadcrumb">
                        <a href="payments.php">Payments</a>
                        <i class="fas fa-chevron-right"></i>
                        <span class="active">Checkout</span>
                    </div>
                </div>
            </nav>

            <div class="method-card">
                <h3><i class="fas fa-wallet"></i> Choose Payment Method</h3>
                <div class="payment-summary">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Payment for:</span>
                        <span style="font-weight: 600;"><?= ucfirst($payment['payment_type']) ?> #<?= $payment['violation_id'] ?? $payment['id'] ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Total Amount:</span>
                        <span style="font-size: 1.25rem; font-weight: 700; color: var(--primary-color);"><?= number_format($payment['amount'], 2) ?> ETB</span>
                    </div>
                </div>

                <form method="POST" action="payment_process.php">
                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                    
                    <label class="method-option">
                        <input type="radio" name="method" value="telebirr" required>
                        <i class="fas fa-mobile-alt"></i>
                        <span>Telebirr</span>
                    </label>
                    <label class="method-option">
                        <input type="radio" name="method" value="cbe">
                        <i class="fas fa-university"></i>
                        <span>Commercial Bank of Ethiopia (CBE)</span>
                    </label>
                    <label class="method-option">
                        <input type="radio" name="method" value="awash">
                        <i class="fas fa-building-columns"></i>
                        <span>Awash Bank</span>
                    </label>

                    <button type="submit" class="confirm-btn">
                        <i class="fas fa-lock"></i> Process Secure Payment
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>