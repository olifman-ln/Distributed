<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../auth/login.php");
  exit();
}
if (isset($_GET['mark_read'])) {
$id = (int)$_GET['mark_read'];
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
header("Location: notifications.php");
exit();
}
$notifications = $conn->query("
SELECT 
 n.id,
n.title,
n.message,
n.is_read,
n.created_at,
o.full_name
FROM notifications n
JOIN owners o ON n.owner_id = o.id
ORDER BY n.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications - Admin</title>
<link rel="stylesheet" href="../css/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="action-bar" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <h2><i class="fas fa-bell"></i> Owner Notifications</h2>
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search notifications..." style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 300px;">
    </div>
</div>
<table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse;">
<thead>
<tr>
<th>ID</th>
<th>Owner</th>
<th>Title</th>
<th>Message</th>
<th>Status</th>
<th>Created At</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php if (!empty($notifications)): ?>
<?php foreach ($notifications as $n): ?>
<tr>
<td><?= $n['id'] ?></td>
<td><?= htmlspecialchars($n['full_name']) ?></td>
<td><?= htmlspecialchars($n['title']) ?></td>
<td><?= htmlspecialchars($n['message']) ?></td>
  <td>
<?= $n['is_read'] ? '<span style="color:green;">Read</span>'
: '<span style="color:red;">Unread</span>' ?>
</td>
<td><?= date('Y-m-d H:i', strtotime($n['created_at'])) ?></td>
 <td>
<?php if (!$n['is_read']): ?>
<a href="?mark_read=<?= $n['id'] ?>" style="color:green;">Mark Read</a>
<?php else: ?>
-
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="7" style="text-align:center;">No notifications found</td>
</tr>
<?php endif; ?>
</tbody>
</table>
<script src="js/notifications.js"></script>
</body>
</html>