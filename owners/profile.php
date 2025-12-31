<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $conn->prepare("SELECT * FROM owners WHERE id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();

if (isset($_POST['update_profile']) || isset($_FILES['profile_picture'])) {
    $full_name = trim($_POST['full_name'] ?? $owner['full_name']);
    $phone = trim($_POST['phone'] ?? $owner['phone']);
    $email = trim($_POST['email'] ?? $owner['email']);
    $address = trim($_POST['address'] ?? $owner['address']);
    
    // Handle Profile Picture
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $newFilename = "owner_" . $owner_id . "_" . time() . "." . $ext;
            if (!file_exists('../uploads/avatars')) {
                mkdir('../uploads/avatars', 0777, true);
            }
            $targetPath = "../uploads/avatars/" . $newFilename;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                $dbPath = "uploads/avatars/" . $newFilename;
                $stmt = $conn->prepare("UPDATE owners SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $dbPath, $owner_id);
                $stmt->execute();
            }
        }
    }

    $stmt = $conn->prepare("UPDATE owners SET full_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $full_name, $phone, $email, $address, $owner_id);
    if ($stmt->execute()) {
        $message = "Profile updated successfully ✅";
        // Refresh owner data
        $stmt = $conn->prepare("SELECT * FROM owners WHERE id = ?");
        $stmt->bind_param("i", $owner_id);
        $stmt->execute();
        $owner = $stmt->get_result()->fetch_assoc();
    } else {
        $error = "Failed to update profile ❌";
    }
}

if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Check if password column exists and is hashed. 
    // If password_verify fails because of plain text (initially), handle it.
    if (!password_verify($current, $owner['password'] ?? '')) {
        $error = "Current password is incorrect ❌";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match ❌";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE owners SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $owner_id);
        if ($stmt->execute()) {
            $message = "Password changed successfully 🔐";
        } else {
            $error = "Failed to change password ❌";
        }
    }
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

$userName = htmlspecialchars($owner['full_name'] ?? $_SESSION['username'] ?? 'Owner');
$userRole = 'Vehicle Owner';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Traffic Monitoring System</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }
        @media (max-width: 992px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        .form-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-family: inherit;
        }
        .form-group input:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }
        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }
        .submit-btn:hover { background: var(--primary-dark); }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
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
                    <li class="active"><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> My Violations <span class="badge badge-warning"><?= $unpaidViolations ?></span></a></li>
                    <li><a href="accident.php"><i class="fas fa-car-crash"></i> My Accidents</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </aside>
<!-- Main Content -->
        <main class="main-content">
        <nav class="navbar">
        <div class="navbar-left">
        <h1>Profile Settings</h1>
        <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        <i class="fas fa-chevron-right"></i>
        <span class="active">Profile</span>
        </div>
        </div>
        <div class="navbar-right">
        <div class="user-menu">
        <div class="user-info">
        <span class="user-name"><?= $userName ?></span>
        <span class="user-role"><?= $userRole ?></span>
     </div>
     <div class="user-avatar">
    <?php if(!empty($owner['profile_picture'])): ?>
     <img src="../<?= htmlspecialchars($owner['profile_picture']) ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
     <?php else: ?>
    <i class="fas fa-user-circle"></i>
    <?php endif; ?>
    </div>
    </div>
    <button class="btn-theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
    </div>
    </nav>
<?php if ($message): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div>
<?php endif; ?>
<div class="profile-grid">
<!-- Personal Info -->
<div class="form-card">
<h3><i class="fas fa-user"></i> Personal Information</h3>
<form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
<div class="form-group" style="text-align: center;">
<div style="position: relative; display: inline-block;">
<?php 
$pic = !empty($owner['profile_picture']) ? '../'.$owner['profile_picture'] : 'https://placehold.co/150x150?text=Profile';
 ?>
  <img src="<?php echo htmlspecialchars($pic); ?>" alt="Profile Picture" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); margin-bottom: 10px;">
<input type="file" name="profile_picture" id="profile_picture" style="display: none;" onchange="this.form.submit()">
<label for="profile_picture" style="display: block; cursor: pointer; color: var(--primary-color); font-weight: 600; font-size: 0.9rem;">
<i class="fas fa-camera"></i> Change Photo
</label>
</div>
</div>
<input type="hidden" name="update_profile" value="1">
<div class="form-group">
<label>Full Name</label>
<input type="text" name="full_name" value="<?= htmlspecialchars($owner['full_name']) ?>" required>
</d>
<div class="form-group">
<label>Username</label>
<input type="text" value="<?= htmlspecialchars($owner['username'] ?? $_SESSION['username']) ?>" disabled>
</div>
<div class="form-group">
<label>Email Address</label>
<input type="email" name="email" value="<?= htmlspecialchars($owner['email'] ?? '') ?>">
</div>
<div class="form-group">
<label>Phone Number</label>
<input type="text" name="phone" value="<?= htmlspecialchars($owner['phone'] ?? '') ?>">
</div>
<div class="form-group">
<label>Address</label>
<textarea name="address" rows="3"><?= htmlspecialchars($owner['address'] ?? '') ?></textarea>
</div>
 <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Save Changes</button>
    </form>
    </div>
<!-- Change Password -->
<div class="form-card">
<h3><i class="fas fa-key"></i> Change Password</h3>
<form method="post" style="margin-top: 20px;">
<input type="hidden" name="change_password" value="1">
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
<button type="submit" class="submit-btn"><i class="fas fa-shield-alt"></i> Update Password</button>
</form>
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