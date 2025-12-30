<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$message = "";
$error = "";

// 1. Add to Watchlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_watchlist'])) {
    $plate = strtoupper(trim($_POST['plate_number']));
    $reason = trim($_POST['reason']);
    $severity = $_POST['severity'];

    try {
        $stmt = $conn->prepare("INSERT INTO watchlist (plate_number, reason, severity) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $plate, $reason, $severity);
        if ($stmt->execute()) {
            $message = "Vehicle " . htmlspecialchars($plate) . " added to watchlist.";
        }
    } catch (Exception $e) {
        $error = "Error adding vehicle: " . $e->getMessage();
    }
}

// 2. Remove from Watchlist
if (isset($_GET['remove'])) {
    $id = (int) $_GET['remove'];
    $stmt = $conn->prepare("DELETE FROM watchlist WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Vehicle removed from watchlist.";
    }
}

// 3. Fetch Watchlist
$result = $conn->query("SELECT * FROM watchlist ORDER BY added_at DESC");
$watchlist = $result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watchlist Management - TrafficSense</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .watchlist-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }

        .severity-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .severity-high { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .severity-medium { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
        .severity-low { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }

        .plate-box {
            background: #1e293b;
            color: #fff;
            padding: 5px 12px;
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
            border-radius: 4px;
            border: 2px solid #334155;
            display: inline-block;
        }

        .watchlist-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
        }

        .btn-add {
            background: #1e293b;
            color: #fff;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s;
        }

        .btn-add:hover {
            background: #334155;
            transform: translateY(-2px);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th, .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
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
                    <li><a href="watchlist.php" class="active"><i class="fas fa-list-ul"></i> Watchlist</a></li>
                    <li><a href="traffic_data.php"><i class="fas fa-chart-line"></i> Traffic Data</a></li>
                    <li><a href="accidents.php"><i class="fas fa-car-crash"></i> Accidents</a></li>
                    <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> Violations</a></li>
                    <li style="margin: 15px 20px; height: 1px; background: #334155;"></li>
                    <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </aside>

        <main class="main-content">
            <nav class="navbar">
                <div class="navbar-left">
                    <h1>Watchlist Management</h1>
                    <div class="breadcrumb">
                        <a href="index.php">Dashboard</a> / <span class="active">Watchlist</span>
                    </div>
                </div>
            </nav>

            <div class="dashboard-content">
                <?php if ($message): ?>
                    <div style="background: #ecfdf5; color: #059669; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #d1fae5;">
                        <i class="fas fa-check-circle"></i> <?= $message ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div style="background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #fecaca;">
                        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="watchlist-container">
                    <!-- Left: Add Form -->
                    <div class="watchlist-card">
                        <h3 style="margin-bottom: 20px;"><i class="fas fa-plus-circle"></i> Flag New Plate</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>License Plate Number</label>
                                <input type="text" name="plate_number" required placeholder="e.g. ABC-1234" style="text-transform: uppercase;">
                            </div>
                            <div class="form-group">
                                <label>Severity Level</label>
                                <select name="severity">
                                    <option value="low">Low - Monitor Only</option>
                                    <option value="medium">Medium - Suspicious</option>
                                    <option value="high">High - Wanted / Stolen</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Reason / Notes</label>
                                <textarea name="reason" rows="4" required placeholder="Reason for flagging this vehicle..."></textarea>
                            </div>
                            <button type="submit" name="add_to_watchlist" class="btn-add">
                                <i class="fas fa-save"></i> Add to Watchlist
                            </button>
                        </form>
                    </div>

                    <!-- Right: List -->
                    <div class="watchlist-card">
                        <h3 style="margin-bottom: 20px;"><i class="fas fa-shield-alt"></i> Active Watchlist</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Plate Number</th>
                                    <th>Severity</th>
                                    <th>Reason</th>
                                    <th>Flagged On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($watchlist)): ?>
                                    <?php foreach ($watchlist as $item): ?>
                                        <tr>
                                            <td><span class="plate-box"><?= htmlspecialchars($item['plate_number']) ?></span></td>
                                            <td>
                                                <span class="severity-badge severity-<?= $item['severity'] ?>">
                                                    <?= $item['severity'] ?>
                                                </span>
                                            </td>
                                            <td style="max-width: 250px; font-size: 0.9rem; color: #64748b;">
                                                <?= htmlspecialchars($item['reason']) ?>
                                            </td>
                                            <td style="font-size: 0.85rem; color: #94a3b8;">
                                                <?= date('M d, Y', strtotime($item['added_at'])) ?>
                                            </td>
                                            <td>
                                                <a href="?remove=<?= $item['id'] ?>" 
                                                   style="color: #ef4444; font-size: 1.1rem;" 
                                                   onclick="return confirm('Remove this vehicle from watchlist?')" 
                                                   title="Remove">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 50px; color: #94a3b8;">
                                            <i class="fas fa-info-circle fa-2x" style="margin-bottom: 15px;"></i>
                                            <p>No vehicles currently in watchlist.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
