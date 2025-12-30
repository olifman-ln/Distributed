<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: auth/login.php');
    exit();
}

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_settings = ['site_name', 'admin_email', 'currency', 'fine_base_amount', 'maintenance_mode'];
    
    foreach ($allowed_settings as $key) {
        if (isset($_POST[$key])) {
            $value = $_POST[$key];
            if ($key === 'maintenance_mode') {
                $value = isset($_POST['maintenance_mode']) ? '1' : '0';
            }
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            // Check if clean function exists in db.php, otherwise just use raw value but bound
            $clean_value = trim($value); 
            $stmt->bind_param("ss", $clean_value, $key);
            
            if (!$stmt->execute()) {
                $error = "Failed to update setting: $key";
            }
        } else {
             // Handle checkbox being unchecked (not sent in POST)
             if ($key === 'maintenance_mode') {
                 $stmt = $conn->prepare("UPDATE system_settings SET setting_value = '0' WHERE setting_key = 'maintenance_mode'");
                 $stmt->execute();
             }
        }
    }
    
    if (!$error) {
        $message = "System settings updated successfully ✅";
    }
}

// Fetch Settings
$settings = [];
$result = $conn->query("SELECT * FROM system_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Helper to get setting safely
function get_setting($key, $settings) {
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : '';
}

// Fetch Admin Profile for Navbar
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, profile_picture FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings - TrafficSense</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .settings-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .settings-header {
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-check {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .form-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            accent-color: #e74c3c;
        }
        .btn-save {
            background: #2ecc71;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-save:hover { background: #27ae60; }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
        <nav class="navbar">
            <div class="navbar-left">
                <h1>Configuration</h1>
                <div class="breadcrumb">
                    <a href="index.php">Dashboard</a> / <span class="active">Settings</span>
                </div>
            </div>
            <div class="navbar-right">
                <div class="user-menu">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                    <div class="user-avatar">
                        <?php if(!empty($admin['profile_picture']) && file_exists($admin['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($admin['profile_picture']) ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <div class="dashboard-content">
            <div class="settings-container">
                <?php if ($message): ?><div class="alert success"><?= $message ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>

                <div class="settings-card">
                    <div class="settings-header">
                        <h2><i class="fas fa-sliders-h"></i> System Parameters</h2>
                        <p style="color: #7f8c8d;">Manage global application settings and variables.</p>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-globe"></i> Site Name</label>
                            <input type="text" name="site_name" value="<?= get_setting('site_name', $settings) ?>" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Admin Email (System Notifications)</label>
                            <input type="email" name="admin_email" value="<?= get_setting('admin_email', $settings) ?>" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label><i class="fas fa-money-bill"></i> Currency Symbol</label>
                                <input type="text" name="currency" value="<?= get_setting('currency', $settings) ?>" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-gavel"></i> Base Violation Fine</label>
                                <input type="number" name="fine_base_amount" value="<?= get_setting('fine_base_amount', $settings) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="color: #e74c3c;"><i class="fas fa-ban"></i> Emergency Controls</label>
                            <div class="form-check">
                                <input type="checkbox" name="maintenance_mode" id="maintenance_mode" <?= get_setting('maintenance_mode', $settings) == '1' ? 'checked' : '' ?>>
                                <label for="maintenance_mode" style="margin: 0; cursor: pointer;">
                                    <strong>Enable Maintenance Mode</strong><br>
                                    <span style="font-size: 0.9em; color: #7f8c8d; font-weight: normal;">
                                        If enabled, only administrators will be able to access the system.
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div style="margin-top: 30px; text-align: right;">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
