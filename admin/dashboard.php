<?php
require '../app/config.php';
checkUserRole('Admin');

$alert = getAlert();
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'Admin';
$user_photo = $_SESSION['profile_photo'] ?? $_SESSION['user_photo'] ?? null;

// Get admin statistics
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM vehicles WHERE status = 'Pending') as pending_vehicles,
        (SELECT COUNT(*) FROM vehicles WHERE status = 'Available') as available_vehicles,
        (SELECT COUNT(*) FROM vehicles WHERE status = 'Rejected') as rejected_vehicles,
        (SELECT COUNT(*) FROM users WHERE role = 'Seller') as total_sellers,
        (SELECT COUNT(*) FROM bookings) as total_bookings
")->fetch_assoc();

// Handle vehicle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $vehicle_id = intval($_POST['vehicle_id']);
        $action = sanitize($_POST['action']);
        $rejection_reason = isset($_POST['rejection_reason']) ? sanitize($_POST['rejection_reason']) : null;
        $admin_id = $_SESSION['user_id'];

        if ($action === 'approve') {
            $update = $conn->prepare("UPDATE vehicles SET status = 'Available' WHERE vehicle_id = ?");
            $update->bind_param("i", $vehicle_id);
            $update->execute();
            $update->close();

            $log = $conn->prepare("INSERT INTO audit_logs (admin_id, vehicle_id, action, old_status, new_status) VALUES (?, ?, 'Approve', 'Pending', 'Available')");
            $log->bind_param("ii", $admin_id, $vehicle_id);
            $log->execute();
            $log->close();

            $sellerQuery = $conn->query("SELECT seller_id, brand, model FROM vehicles WHERE vehicle_id = $vehicle_id")->fetch_assoc();
            if ($sellerQuery) {
                $msg = "Your vehicle " . $sellerQuery['brand'] . " " . $sellerQuery['model'] . " has been approved!";
                $conn->query("INSERT INTO notifications (user_id, message, type) VALUES (" . $sellerQuery['seller_id'] . ", '" . $conn->real_escape_string($msg) . "', 'Approval')");
            }
            showAlert("Vehicle approved successfully!", "success");
        } else if ($action === 'reject') {
            $update = $conn->prepare("UPDATE vehicles SET status = 'Rejected', rejection_reason = ? WHERE vehicle_id = ?");
            $update->bind_param("si", $rejection_reason, $vehicle_id);
            $update->execute();
            $update->close();

            $log = $conn->prepare("INSERT INTO audit_logs (admin_id, vehicle_id, action, old_status, new_status, comment) VALUES (?, ?, 'Reject', 'Pending', 'Rejected', ?)");
            $log->bind_param("iis", $admin_id, $vehicle_id, $rejection_reason);
            $log->execute();
            $log->close();

            $sellerQuery = $conn->query("SELECT seller_id, brand, model FROM vehicles WHERE vehicle_id = $vehicle_id")->fetch_assoc();
            if ($sellerQuery) {
                $msg = "Your vehicle " . $sellerQuery['brand'] . " " . $sellerQuery['model'] . " was rejected. Reason: $rejection_reason";
                $conn->query("INSERT INTO notifications (user_id, message, type) VALUES (" . $sellerQuery['seller_id'] . ", '" . $conn->real_escape_string($msg) . "', 'Rejection')");
            }
            showAlert("Vehicle rejected and seller notified.", "success");
        }
        header("Location: dashboard.php");
        exit();
    }
}

// Get pending vehicles
$pending_vehicles = $conn->query("
    SELECT v.*, u.name as seller_name, u.email as seller_email 
    FROM vehicles v 
    JOIN users u ON v.seller_id = u.user_id 
    WHERE v.status = 'Pending' 
    ORDER BY v.created_at DESC
");

// Get recent activity
$recent_activity = $conn->query("
    SELECT al.*, v.brand, v.model, u.name as admin_name 
    FROM audit_logs al 
    JOIN vehicles v ON al.vehicle_id = v.vehicle_id 
    JOIN users u ON al.admin_id = u.user_id 
    ORDER BY al.created_at DESC 
    LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Ridezo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --primary: #0f172a;
            --accent: #22c55e;
            --accent-soft: rgba(34, 197, 94, 0.1);
            --accent-glow: rgba(34, 197, 94, 0.25);
            --bg: #020617;
            --surface: #0f172a;
            --surface-light: #1e293b;
            --input-bg: #030712;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }

        body { 
            font-family: 'Sora', 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            display: flex; 
            min-height: 100vh; 
            overflow-x: hidden; 
            -webkit-font-smoothing: antialiased;
        }

        /* Sidebar Layout */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(2, 6, 23, 0.85);
            backdrop-filter: blur(25px);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            color: var(--text);
            transition: var(--transition);
            border-right: 1px solid var(--border);
        }

        .sidebar-header {
            padding: 32px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border);
        }

        .logo-box {
            background: transparent;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--accent);
            filter: drop-shadow(0 0 10px var(--accent-glow));
        }

        .logo-text { font-size: 1.6rem; font-weight: 800; letter-spacing: -1.2px; color: #fff; }

        .nav-menu { flex-grow: 1; padding: 24px 16px; overflow-y: auto; }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            transition: var(--transition);
            letter-spacing: -0.2px;
        }

        .nav-item i { font-size: 1.2rem; transition: var(--transition); }

        .nav-item:hover, .nav-item.active {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .nav-item.active i { color: var(--accent); }

        .sidebar-footer {
            padding: 24px;
            border-top: 1px solid var(--border);
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--surface-light);
            border-radius: 16px;
            margin-bottom: 16px;
            border: 1px solid var(--border);
        }

        .user-pill img { width: 40px; height: 40px; border-radius: 12px; object-fit: cover; }
        .user-pill .info { flex-grow: 1; }
        .user-pill .name { font-weight: 700; font-size: 0.9rem; color: var(--text); }
        .user-pill .role { font-size: 0.75rem; color: var(--text-muted); }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ef4444;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 8px;
            transition: var(--transition);
        }
        .btn-logout:hover { transform: translateX(5px); color: #f87171; }

        /* Main Content Layout */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 40px;
            width: calc(100% - var(--sidebar-width));
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            gap: 16px;
        }

        .btn-back-page { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 14px; background: var(--surface-light); color: var(--text-muted); border: 1px solid var(--border); text-decoration: none; transition: var(--transition); flex-shrink: 0; }
        .btn-back-page:hover { background: var(--accent); color: var(--primary); border-color: var(--accent); }

        .welcome-box h1 { font-size: 2rem; font-weight: 800; color: var(--text); margin-bottom: 8px; letter-spacing: -0.5px; }
        .welcome-box p { color: var(--text-muted); font-weight: 500; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--surface);
            padding: 24px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
        }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--accent); }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .icon-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
        .icon-green { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--accent-glow); }
        .icon-orange { background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.2); }
        .icon-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

        .stat-info .value { font-size: 1.25rem; font-weight: 800; color: var(--text); }
        .stat-info .label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }

        /* Sections Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 32px;
        }

        .content-box {
            background: var(--surface);
            border-radius: 28px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .box-header { padding: 24px 32px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .box-header h2 { font-size: 1.25rem; font-weight: 800; color: var(--text); }

        /* Pending Vehicle Cards */
        .pending-list { padding: 24px; }
        .pending-card {
            border: 1px solid var(--border);
            background: rgba(2, 6, 23, 0.3);
            border-radius: 20px;
            padding: 16px;
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
            transition: var(--transition);
        }
        .pending-card:hover { border-color: var(--accent); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }

        .pending-img { width: 100px; height: 100px; border-radius: 14px; object-fit: cover; border: 1px solid var(--border); }
        .pending-info { flex-grow: 1; }
        .pending-title { font-weight: 800; color: var(--text); font-size: 1rem; margin-bottom: 4px; }
        .pending-meta { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-bottom: 12px; }

        .action-btns { display: flex; gap: 8px; }
        .btn-sml {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 800;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-approve { background: rgba(34, 197, 94, 0.1); color: var(--accent); border: 1px solid var(--accent-glow); }
        .btn-approve:hover { background: var(--accent); color: var(--primary); }
        .btn-reject { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-reject:hover { background: #ef4444; color: white; }

        /* Activity Feed */
        .activity-feed { padding: 0 24px 24px; }
        .activity-item {
            display: flex;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 6px; flex-shrink: 0; box-shadow: 0 0 10px currentColor; }
        .dot-approve { color: var(--accent); background: var(--accent); }
        .dot-reject { color: #ef4444; background: #ef4444; }

        .activity-text { font-size: 0.85rem; line-height: 1.4; color: var(--text-muted); }
        .activity-text strong { color: var(--text); }
        .activity-time { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 4px; }

        @media (max-width: 1200px) { .dashboard-grid { grid-template-columns: 1fr; } }
        @media (max-width: 1024px) {
            .sidebar { left: -280px; }
            .sidebar.open { left: 0; }
            .main-wrapper { margin-left: 0; width: 100%; padding: 24px; }
            .mobile-header { display: flex !important; }
        }

        .mobile-header {
            display: none;
            background: var(--surface);
            padding: 16px 24px;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
            border-bottom: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        /* Animations */
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(40px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .main-wrapper > .page-header {
            animation: slideInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        
        .main-wrapper > *:not(.page-header) {
            animation: slideInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
            animation-delay: 0.1s;
        }

        .alert, div[style*="background: #dcfce7"], div[style*="rgba(34, 197, 94, 0.1)"], div[style*="rgba(239, 68, 68, 0.1)"] {
            animation: slideInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both !important;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(2, 6, 23, 0.8);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--surface);
            color: var(--text);
            padding: 32px;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }
    
        /* Global Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main, var(--bg, #020617)); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color, var(--accent, #22c55e)); }

</style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <a href="../main/index.php" style="text-decoration: none; color: inherit;">
            <div class="sidebar-header">
                <div class="logo-box"><img src="../assets/ridezo_logo.png" alt="Ridezo Logo" style="height: 34px; width: auto; filter: drop-shadow(0 0 10px var(--accent-glow));"></div>
                <div class="logo-text">Ridezo</div>
            </div>
        </a>

        <nav class="nav-menu">
            <a href="../main/index.php" class="nav-item">
                <i class="fas fa-house"></i>
                <span>Home</span>
            </a>
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-chart-pie"></i>
                <span>System Dashboard</span>
            </a>
            <a href="vehicles.php" class="nav-item">
                <i class="fas fa-car"></i>
                <span>All Vehicles</span>
            </a>
            <a href="fleet-tracking.php" class="nav-item" style="position:relative;">
                <i class="fas fa-location-crosshairs"></i>
                <span>Fleet Tracking</span>
                <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:#22c55e;color:#020617;font-size:0.55rem;font-weight:800;padding:2px 6px;border-radius:6px;text-transform:uppercase;">LIVE</span>
            </a>
            <a href="add-vehicle.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Vehicle</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users-gear"></i>
                <span>User Management</span>
            </a>
            <a href="bookings.php" class="nav-item">
                <i class="fas fa-calendar-check"></i>
                <span>Booking Logs</span>
            </a>
            <a href="transactions.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>Transactions</span>
            </a>
            <a href="feedbacks.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Customer Feedback</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-pill">
                <?php if (!empty($user_photo)): ?>
                    <img src="../app/<?php echo $user_photo; ?>" alt="Profile">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <div class="info">
                    <div class="name"><?php echo explode(' ', $user_name)[0]; ?></div>
                    <div class="role">Administrator</div>
                </div>
            </div>
            <a href="../app/logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sign Out</span>
            </a>
        </div>
    </div>

    <div class="mobile-header">
        <div onclick="toggleSidebar()" style="font-size: 1.5rem; cursor: pointer;"><i class="fas fa-bars"></i></div>
        <div class="logo-text" style="color: #fff">Ridezo Admin</div>
        <div></div>
    </div>

    <main class="main-wrapper">
        <div class="page-header">
            <a href="javascript:history.back()" class="btn-back-page" title="Go Back"><i class="fas fa-arrow-left"></i></a>
            <div class="welcome-box">
                <h1>Admin Control Center</h1>
                <p>Monitor system performance and approve new listings.</p>
            </div>
            <a href="add-vehicle.php" style="background: var(--accent); color: var(--primary); text-decoration: none; padding: 12px 24px; border-radius: 14px; font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; transition: var(--transition); box-shadow: 0 4px 12px var(--accent-glow);" onmouseover="this.style.filter='brightness(1.1)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.filter='none'; this.style.transform='none';">
                <i class="fas fa-plus-circle"></i> Add New Vehicle
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-info">
                    <div class="value"><?php echo $stats['pending_vehicles'] ?? 0; ?></div>
                    <div class="label">Pending Approval</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="value"><?php echo $stats['available_vehicles'] ?? 0; ?></div>
                    <div class="label">Active Vehicle</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-user-tie"></i></div>
                <div class="stat-info">
                    <div class="value"><?php echo $stats['total_sellers'] ?? 0; ?></div>
                    <div class="label">Total Sellers</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <div class="value"><?php echo $stats['total_bookings'] ?? 0; ?></div>
                    <div class="label">Total Bookings</div>
                </div>
            </div>
        </div>

        <!-- Fleet Tracking Quick Access -->
        <a href="fleet-tracking.php" style="display:flex;align-items:center;gap:20px;background:linear-gradient(135deg,rgba(34,197,94,0.12),rgba(34,197,94,0.04));border:1px solid rgba(34,197,94,0.25);border-radius:24px;padding:24px 32px;text-decoration:none;margin-bottom:32px;transition:all 0.3s;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 40px rgba(34,197,94,0.15)';" onmouseout="this.style.transform='none';this.style.boxShadow='none';">
            <div style="width:56px;height:56px;background:rgba(34,197,94,0.15);border-radius:18px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(34,197,94,0.3);flex-shrink:0;">
                <i class="fas fa-location-crosshairs" style="font-size:1.4rem;color:#22c55e;"></i>
            </div>
            <div style="flex-grow:1;">
                <div style="font-weight:800;font-size:1rem;color:#f8fafc;margin-bottom:4px;">Live Fleet Tracking <span style="background:#22c55e;color:#020617;font-size:0.6rem;font-weight:800;padding:2px 8px;border-radius:6px;text-transform:uppercase;vertical-align:middle;margin-left:8px;">LIVE</span></div>
                <div style="color:#94a3b8;font-size:0.85rem;font-weight:500;">View real-time positions of all vehicles on an interactive map</div>
            </div>
            <i class="fas fa-arrow-right" style="color:#22c55e;font-size:1.1rem;flex-shrink:0;"></i>
        </a>

        <div class="dashboard-grid">
            <div class="content-box">
                <div class="box-header">
                    <h2>Pending Approvals</h2>
                    <a href="vehicles.php?status=Pending" style="color: var(--accent); font-weight: 700; text-decoration: none; font-size: 0.9rem;">Review All</a>
                </div>
                <div class="pending-list">
                    <?php if ($pending_vehicles->num_rows > 0): ?>
                        <?php while ($v = $pending_vehicles->fetch_assoc()): ?>
                            <div class="pending-card">
                                <img src="../app/<?php echo $v['image_url']; ?>" class="pending-img" alt="Vehicle">
                                <div class="pending-info">
                                    <div class="pending-title"><?php echo $v['brand'].' '.$v['model']; ?></div>
                                    <div class="pending-meta">Seller: <?php echo $v['seller_name']; ?> &bull; ₹<?php echo number_format($v['rental_price_per_day'], 0); ?>/day</div>
                                    <div class="action-btns">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $v['vehicle_id']; ?>">
                                            <button type="submit" class="btn-sml btn-approve">Approve</button>
                                        </form>
                                        <button onclick="openRejectModal(<?php echo $v['vehicle_id']; ?>)" class="btn-sml btn-reject">Reject</button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--accent-glow); margin-bottom: 12px;"></i>
                            <p style="color: var(--text-muted); font-weight: 600;">Queue is empty!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-box">
                <div class="box-header">
                    <h2>Recent Audit Logs</h2>
                </div>
                <div class="activity-feed">
                    <?php if ($recent_activity->num_rows > 0): ?>
                        <?php while ($log = $recent_activity->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-dot dot-<?php echo strtolower($log['action']); ?>"></div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <strong><?php echo $log['admin_name']; ?></strong> 
                                        <?php echo strtolower($log['action']); ?>d 
                                        <strong><?php echo $log['brand'].' '.$log['model']; ?></strong>
                                    </div>
                                    <div class="activity-time"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; padding: 20px; color: var(--text-muted);">No logs available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="rejectModal">
        <div class="modal-box">
            <h2 style="margin-bottom: 16px;">Reject Listing</h2>
            <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">Please provide a reason. This will be sent to the seller.</p>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="vehicle_id" id="rejectId">
                <textarea name="rejection_reason" required style="width: 100%; min-height: 120px; padding: 16px; border-radius: 12px; border: 1px solid var(--border); background: var(--input-bg); color: var(--text); font-family: inherit; margin-bottom: 24px;"></textarea>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn-sml btn-reject" style="flex: 1; padding: 12px;">Confirm Reject</button>
                    <button type="button" onclick="closeRejectModal()" class="btn-sml" style="flex: 1; padding: 12px; background: rgba(255,255,255,0.05); color: var(--text); border: 1px solid var(--border);">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
        function openRejectModal(id) { document.getElementById('rejectId').value = id; document.getElementById('rejectModal').classList.add('active'); }
        function closeRejectModal() { document.getElementById('rejectModal').classList.remove('active'); }
    </script>
</body>
</html>
