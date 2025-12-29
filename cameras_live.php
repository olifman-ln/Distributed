<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Fetch active cameras
$cameras_result = $conn->query("
    SELECT c.*, n.name AS node_name
    FROM cameras c
    LEFT JOIN nodes n ON c.node_id = n.id
    WHERE c.status = 'active'
    ORDER BY c.created_at DESC
");
$cameras = $cameras_result->fetch_all(MYSQLI_ASSOC);

// Map images to cameras
$images = [
    'highway.png',
    'intersection.png',
    'bridge.png'
];

// Helper to get a random image from the list if not set
function getCameraFeed($index, $images) {
    return 'assets/surveillance/' . $images[$index % count($images)];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Surveillance - TrafficSense</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .feed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .feed-card {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
            border: 2px solid #334155;
            aspect-ratio: 16 / 9;
        }

        .feed-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(20%) contrast(110%);
        }

        /* Surveillance Overlay */
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 15px;
            color: #fff;
            font-family: 'Courier New', Courier, monospace;
            text-shadow: 1px 1px 2px #000;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3) 0%, transparent 20%, transparent 80%, rgba(0,0,0,0.3) 100%);
        }

        .overlay-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .overlay-bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .rec-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            color: #ff4757;
        }

        .rec-dot {
            width: 10px;
            height: 10px;
            background: #ff4757;
            border-radius: 50%;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }

        .camera-id {
            background: rgba(0,0,0,0.6);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .scanline {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                rgba(18, 16, 16, 0) 50%,
                rgba(0, 0, 0, 0.1) 50%
            ), linear-gradient(
                90deg,
                rgba(255, 0, 0, 0.03),
                rgba(0, 255, 0, 0.01),
                rgba(0, 0, 255, 0.03)
            );
            background-size: 100% 4px, 3px 100%;
            z-index: 2;
        }

        .timestamp {
            font-size: 1.1rem;
            letter-spacing: 1px;
        }

        .node-name {
            font-size: 1.2rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .static-noise {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.03;
            z-index: 1;
            pointer-events: none;
        }

        .live-badge {
            background: #ff4757;
            color: #fff;
            padding: 2px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.8rem;
            margin-bottom: 5px;
            display: inline-block;
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
                    <li><a href="cameras_live.php" class="active"><i class="fas fa-broadcast-tower"></i> Live Surveillance</a></li>
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
                    <h1>Live Monitoring</h1>
                    <div class="breadcrumb">
                        <a href="index.php">Dashboard</a> / <span class="active">Surveillance Simulator</span>
                    </div>
                </div>
                <div class="navbar-right">
                    <div id="connection-status" style="margin-right: 20px; font-size: 0.9rem; color: #2ecc71;">
                        <i class="fas fa-circle"></i> System Connected
                    </div>
                    <button class="btn-theme-toggle" style="margin-right: 20px;"><i class="fas fa-moon"></i></button>
                    <div class="user-avatar" style="width: 35px; height: 35px; background: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </nav>

            <div class="dashboard-content">
                <div class="action-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <div class="stats-mini" style="display: flex; gap: 20px;">
                        <span style="background: #f1f5f9; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 500;">
                            <i class="fas fa-video" style="color: #3498db;"></i> <?= count($cameras) ?> Active Streams
                        </span>
                        <span style="background: #f1f5f9; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 500;">
                            <i class="fas fa-clock" style="color: #f39c12;"></i> Real-time Latency: <span id="latency">24ms</span>
                        </span>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="window.location.reload()"><i class="fas fa-sync-alt"></i> Refresh Feeds</button>
                    </div>
                </div>

                <div class="feed-grid">
                    <?php if (!empty($cameras)): ?>
                        <?php foreach ($cameras as $index => $camera): ?>
                            <div class="feed-card">
                                <canvas class="static-noise"></canvas>
                                <div class="scanline"></div>
                                <img src="<?= getCameraFeed($index, $images) ?>" alt="Surveillance Stream" class="feed-video">
                                
                                <div class="overlay">
                                    <div class="overlay-top">
                                        <div class="node-info">
                                            <div class="live-badge">LIVE</div>
                                            <div class="node-name"><?= htmlspecialchars($camera['node_name'] ?? 'STATION-'.$camera['id']) ?></div>
                                            <div class="camera-id">CAM_<?= str_pad($camera['id'], 2, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($camera['direction'] ?? 'N/A') ?></div>
                                        </div>
                                        <div class="rec-indicator">
                                            <div class="rec-dot"></div>
                                            <span>REC</span>
                                        </div>
                                    </div>
                                    <div class="overlay-bottom">
                                        <div class="timestamp" id="timestamp-<?= $camera['id'] ?>">
                                            <?= date('Y-m-d H:i:s') ?>
                                        </div>
                                        <div style="font-size: 0.8rem; text-align: right;">
                                            COORD: <?= rand(20, 30) ?>.<?= rand(1000, 9999) ?>N <br>
                                            <?= rand(70, 80) ?>.<?= rand(1000, 9999) ?>E
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 100px; background: #fff; border-radius: 12px; border: 2px dashed #cbd5e1;">
                            <i class="fas fa-video-slash fa-4x" style="color: #cbd5e1; margin-bottom: 20px;"></i>
                            <h3>No Active Camera Feeds</h3>
                            <p>Go to <a href="cameras.php">Camera Management</a> to add and activate cameras.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Real-time clock for each camera feed
        function updateTimestamps() {
            const now = new Date();
            const dateStr = now.getFullYear() + '-' + 
                           String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(now.getDate()).padStart(2, '0');
            const timeStr = String(now.getHours()).padStart(2, '0') + ':' + 
                           String(now.getMinutes()).padStart(2, '0') + ':' + 
                           String(now.getSeconds()).padStart(2, '0');
            
            document.querySelectorAll('.timestamp').forEach(el => {
                el.innerText = dateStr + ' ' + timeStr;
            });
        }

        setInterval(updateTimestamps, 1000);

        // Random jitter for latency
        setInterval(() => {
            const lat = Math.floor(Math.random() * (45 - 18) + 18);
            const el = document.getElementById('latency');
            if (el) el.innerText = lat + 'ms';
        }, 3000);

        // Static noise simulation on canvas
        document.querySelectorAll('.static-noise').forEach(canvas => {
            const ctx = canvas.getContext('2d');
            function noise() {
                const w = canvas.width;
                const h = canvas.height;
                const idata = ctx.createImageData(w, h);
                const buffer32 = new Uint32Array(idata.data.buffer);
                const len = buffer32.length;
                
                for (let i = 0; i < len; i++) {
                    if (Math.random() < 0.5) buffer32[i] = 0xff000000;
                }
                
                ctx.putImageData(idata, 0, 0);
            }
            
            setInterval(noise, 100);
        });
    </script>
</body>
</html>
