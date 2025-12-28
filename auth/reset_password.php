<?php
session_start();
require '../db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$validToken = false;
$userType = '';

if (!$token) {
    header("Location: login.php");
    exit();
}

// Validate Token
$stmt = $conn->prepare("SELECT id, 'admin' as type FROM admin WHERE reset_token = ? AND reset_expires > NOW() UNION SELECT id, 'owners' as type FROM owners WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->bind_param("ss", $token, $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $validToken = true;
    $userType = $user['type'];
} else {
    $error = "Invalid or expired password reset link.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
      
        
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $update = $conn->prepare("UPDATE $userType SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $update->bind_param("si", $hashed, $user['id']);
        
        if ($update->execute()) {
            $_SESSION['start_login_success'] = "Password successfully reset! Please login."; 
            header("Location: login.php");
            exit();
        } else {
            $error = "Failed to update password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - TrafficSense</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <h2><i class="fas fa-traffic-light"></i> TrafficSense</h2>
            <p>Set New Password</p>
        </div>

        <?php if ($error): ?>
            <div class="demo-message error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <div class="login-links" style="margin-top: 15px;">
                <a href="forgot_password.php">Request new link</a>
            </div>
        <?php endif; ?>

        <?php if ($validToken && !$success): ?>
        <form method="POST">
            <div class="input-group">
                <label for="password"><i class="fas fa-lock"></i> New Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" placeholder="Enter new password" required>
                    <button type="button" class="toggle-password" onclick="toggleInput('password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="input-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    <button type="button" class="toggle-password" onclick="toggleInput('confirm_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-btn">
                <i class="fas fa-save"></i> Reset Password
            </button>
        </form>
        <?php endif; ?>
        
        <script>
        function toggleInput(id, btn) {
            const input = document.getElementById(id);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            btn.querySelector('i').classList.toggle('fa-eye');
            btn.querySelector('i').classList.toggle('fa-eye-slash');
        }
        </script>

        <div class="footer">
            <p>© <?= date('Y') ?> Traffic Monitoring System</p>
        </div>
    </div>
</body>
</html>
