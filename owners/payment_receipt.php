<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];

if (!isset($_GET['payment_id'])) {
    header("Location: payments.php");
    exit();
}

$payment_id = (int)$_GET['payment_id'];

// Fetch payment details
$stmt = $conn->prepare("
    SELECT p.*, v.plate_number AS vehicle_plate, vi.violation_type, i.incident_type, i.severity
    FROM payments p
    LEFT JOIN violations vi ON p.violation_id = vi.id
    LEFT JOIN incidents i ON p.payment_type='accident' AND p.violation_id IS NULL AND i.id = p.id
    LEFT JOIN vehicles v ON (v.id = vi.vehicle_id OR v.id = i.vehicle_id)
    WHERE p.id=? AND p.owner_id=?
");
$stmt->bind_param("ii", $payment_id, $owner_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

// Generate receipt
ob_start();
?>
<html>
<head><title>Payment Receipt</title></head>
<body>
<h2>Payment Receipt</h2>
<p><strong>Owner:</strong> <?= htmlspecialchars($_SESSION['username']) ?></p>
<p><strong>Payment ID:</strong> <?= $payment['id'] ?></p>
<p><strong>Type:</strong> <?= ucfirst($payment['payment_type']) ?></p>
<p><strong>Vehicle:</strong> <?= $payment['vehicle_plate'] ?? 'N/A' ?></p>
<p><strong>Violation/Accident:</strong> <?= $payment['payment_type'] === 'violation' ? ucfirst($payment['violation_type']) : ucfirst($payment['incident_type']) ?></p>
<?php if($payment['payment_type'] === 'accident'): ?>
<p><strong>Severity:</strong> <?= ucfirst($payment['severity']) ?></p>
<?php endif; ?>
<p><strong>Amount Paid:</strong> <?= number_format($payment['amount'], 2) ?> ETB</p>
<p><strong>Payment Method:</strong> <?= ucfirst($payment['payment_method']) ?></p>
<p><strong>Date:</strong> <?= date('M d, Y H:i', strtotime($payment['paid_at'])) ?></p>
<p>Thank you for your payment!</p>
</body>
</html>
<?php
$html = ob_get_clean();

// Force download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="payment_receipt_' . $payment_id . '.html"');
echo $html;
exit();
?>
