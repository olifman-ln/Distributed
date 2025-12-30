<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: auth/login.php');
    exit();
}

require_once 'db.php';

$message = '';
$error = '';
$admin_id = $_SESSION['user_id'];

// Fetch current admin data
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info']) || isset($_FILES['profile_picture'])) {
        $username = clean($_POST['username']);
        $email = clean($_POST['email']);

        // Check if username/email already taken by another admin
        $check = $conn->prepare("SELECT id FROM admin WHERE (username = ? OR email = ?) AND id != ?");
        $check->bind_param("ssi", $username, $email, $admin_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Username or Email already exists.";
        } else {
            // Handle Profile Picture
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_picture']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $newFilename = "admin_" . $admin_id . "_" . time() . "." . $ext;
                    if (!file_exists('uploads/avatars')) {
                        mkdir('uploads/avatars', 0777, true);
                    }
                    $targetPath = "uploads/avatars/" . $newFilename;
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                        $stmt = $conn->prepare("UPDATE admin SET profile_picture = ? WHERE id = ?");
                        $stmt->bind_param("si", $targetPath, $admin_id);
                        $stmt->execute();
                        $admin['profile_picture'] = $targetPath;
                    }
                }
            }

            $update = $conn->prepare("UPDATE admin SET username = ?, email = ? WHERE id = ?");
            $update->bind_param("ssi", $username, $email, $admin_id);
            if ($update->execute()) {
                $message = "Profile updated successfully.";
                $_SESSION['username'] = $username; 
                // Refresh data
                $admin['username'] = $username;
                $admin['email'] = $email;
            } else {
                $error = "Error updating profile.";
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (!password_verify($current, $admin['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } elseif (strlen($new) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed, $admin_id);
            if ($update->execute()) {
                $message = "Password changed successfully.";
            } else {
                $error = "Error updating password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile - TrafficSense</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo"><h2>TrafficSense</h2></div>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="vehicle.php"><i class="fas fa-car"></i> Vehicles</a></li>
                <li><a href="owners.php"><i class="fas fa-users"></i> Owners</a></li>
                <li><a href="nodes.php"><i class="fas fa-satellite-dish"></i> Nodes</a></li>
                <li><a href="cameras.php"><i class="fas fa-video"></i> Cameras</a></li>
<li><a href="cameras_live.php"><i class="fas fa-broadcast-tower"></i> Live Surveillance</a></li>
<li><a href="watchlist.php"><i class="fas fa-list-ul"></i> Watchlist</a></li>
<li><a href="traffic_data.php"><i class="fas fa-chart-line"></i> Traffic Data</a></li>
                <li><a href="accidents.php"><i class="fas fa-car-crash"></i> Accidents</a></li>
                <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> Violations</a></li>
                <li style="margin: 15px 20px; height: 1px; background: #334155;"></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
        <nav class="navbar">
            <h1>My Profile</h1>
            <div class="navbar-right">
                <div class="user-menu" style="display: flex; align-items: center;">
                    <div class="user-avatar" style="width: 35px; height: 35px; border-radius: 50%; overflow: hidden; margin-right: 10px; border: 2px solid #3498db;">
                        <?php if(!empty($admin['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($admin['profile_picture']) ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle" style="font-size: 35px; color: #ccc;"></i>
                        <?php endif; ?>
                    </div>
                    <span><?php echo htmlspecialchars($admin['username']); ?> (Admin)</span>
                    <a href="logout.php" style="margin-left: 15px; color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </nav>

        <?php if ($message): ?><div class="alert success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?php echo $error; ?></div><?php endif; ?>

        <div class="form-section">
            <h3><i class="fas fa-user-edit"></i> Edit Information</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group" style="text-align: center;">
                    <div style="position: relative; display: inline-block;">
                        <?php 
                        $pic = !empty($admin['profile_picture']) ? $admin['profile_picture'] : 'https://via.placeholder.com/150';
                        ?>
                        <img src="<?php echo htmlspecialchars($pic); ?>" alt="Profile Picture" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #3498db; margin-bottom: 10px;">
                        <input type="file" name="profile_picture" id="profile_picture" style="display: none;" onchange="this.form.submit()">
                        <label for="profile_picture" style="display: block; cursor: pointer; color: #3498db; font-weight: 600;">
                            <i class="fas fa-camera"></i> Change Photo
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                </div>
                <button type="submit" name="update_info" class="btn-primary">Update Info</button>
            </form>
        </div>

        <div class="form-section">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn-primary">Change Password</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
