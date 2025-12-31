<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
  header("Location: ../auth/login.php");
  exit();
}

$owner_id   = $_SESSION['user_id'];
$payment_id = (int)$_POST['payment_id'];
$method     = $_POST['method'];

$stmt = $conn->prepare("
UPDATE payments
SET payment_method=?, payment_status='paid', paid_at=NOW()
WHERE id=? AND owner_id=?
");
$stmt->bind_param("sii", $method, $payment_id, $owner_id);
$stmt->execute();

header("Location: payments.php?success=1");
exit();
