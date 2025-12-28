<?php
session_start();
require '../db.php';

$message = '';
$error = '';
$simulationLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email']);

  if (!$email) {
    $error = "Please enter your email address.";
  } else {
    // Check both tables
    $stmt = $conn->prepare("SELECT id, 'admin' as type FROM admin WHERE email = ? UNION SELECT id, 'owners' as type FROM owners WHERE email = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
      $token = bin2hex(random_bytes(32));
      $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
      $table = $user['type'];
      
      $update = $conn->prepare("UPDATE $table SET reset_token = ?, reset_expires = ? WHERE id = ?");
      $update->bind_param("ssi", $token, $expires, $user['id']);
      
      if ($update->execute()) {
        $message = "We have sent a password reset link to your email.";
        // SIMULATION FOR DEMO PURPOSES
        $simulationLink = "reset_password.php?token=" . $token;
      } else {
        $error = "Something went wrong. Please try again.";
      }
    } else {
      // Security: Always return success message even if email not found to prevent enumeration
      $message = "If an account exists with this email, you will receive a reset link.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - TrafficSense</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="login-box">
    <div class="logo">
      <h2><i class="fas fa-traffic-light"></i> TrafficSense</h2>
      <p>Account Recovery</p>
    </div>

    <?php if ($error): ?>
      <div class="demo-message error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="demo-message success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
      </div>
      
      <?php if ($simulationLink): ?>
        <div style="margin-top: 15px; padding: 10px; background: #eff6ff; border: 1px dashed #2563eb; border-radius: 6px; font-size: 0.9em;">
          <strong><i class="fas fa-bug"></i> Debug Mode:</strong><br>
          <a href="<?= $simulationLink ?>" style="color: #2563eb; text-decoration: underline;">Click here to simulate email link</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$message || $simulationLink): ?>
    <form method="POST">
      <div class="input-group">
        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
        <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
      </div>

      <button type="submit" class="login-btn">
        <i class="fas fa-paper-plane"></i> Send Reset Link
      </button>
    </form>
    <?php endif; ?>

    <div class="login-links" style="margin-top: 20px; text-align: center;">
      <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>

    <div class="footer">
      <p>© <?= date('Y') ?> Traffic Monitoring System</p>
    </div>
  </div>
</body>
</html>