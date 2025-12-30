<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../auth/login.php");
  exit();
}
$payments = $conn->query("
SELECT p.id, p.amount, p.payment_type, p.payment_method, p.payment_status, p.transaction_ref, p.paid_at, p.created_at,
o.full_name, v.plate_number
FROM payments p
JOIN owners o ON p.owner_id = o.id
LEFT JOIN violations vi ON p.violation_id = vi.id
LEFT JOIN vehicles v ON (vi.vehicle_id = v.id OR p.payment_type='accident' AND v.id=vi.vehicle_id)
ORDER BY p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
if (isset($_GET['mark_paid'])) {
$payment_id = (int)$_GET['mark_paid'];
$stmt = $conn->prepare("UPDATE payments SET payment_status='paid', paid_at=NOW() WHERE id=?");
$stmt->bind_param("i", $payment_id);
if ($stmt->execute()) {
header("Location: payments.php");
exit();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payments Management - Admin</title>
<link rel="stylesheet" href="css/payments.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="action-bar" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <h2><i class="fas fa-money-bill"></i> Payments Management</h2>
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search payments..." style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 300px;">
    </div>
</div>
<table class="payments-table" border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse;">
<thead>
<tr>
<th>ID</th>
<th>Owner</th>
<th>Vehicle</th>
<th>Type</th>
<th>Amount (ETB)</th>
<th>Method</th>
<th>Status</th>
<th>Transaction Ref</th>
<th>Paid At</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($payments as $p): ?>
<tr>
<td data-label="ID"><?= $p['id'] ?></td>
<td data-label="Owner"><?= htmlspecialchars($p['full_name']) ?></td>
<td data-label="Vehicle"><?= htmlspecialchars($p['plate_number'] ?? '-') ?></td>
<td data-label="Type"><?= ucfirst($p['payment_type']) ?></td>
<td data-label="Amount (ETB)"><?= number_format($p['amount'], 2) ?></td>
<td data-label="Method"><?= ucfirst($p['payment_method']) ?></td>
<td data-label="Status"><?= ucfirst($p['payment_status']) ?></td>
<td data-label="Transaction Ref"><?= htmlspecialchars($p['transaction_ref'] ?? '-') ?></td>
<td data-label="Paid At"><?= $p['paid_at'] ?? '-' ?></td>
<td data-label="Action">
<td>
<?php if ($p['payment_status'] !== 'paid'): ?>
<a href="?mark_paid=<?= $p['id'] ?>" style="color:green;">Mark Paid</a>
<?php else: ?>
-
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<script src="js/payments.js"></script>
</body>
</html>