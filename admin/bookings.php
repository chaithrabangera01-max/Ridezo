<?php
require '../app/config.php';
checkUserRole('Admin');

$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'Admin';
$user_photo = $_SESSION['profile_photo'] ?? $_SESSION['user_photo'] ?? null;
$alert = getAlert();

// Fetch transactions with commission calculations
$query = "
    SELECT b.*, v.brand, v.model, u.name as customer_name, s.name as seller_name
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN users u ON b.customer_id = u.user_id
    JOIN users s ON b.seller_id = s.user_id
    ORDER BY b.created_at DESC
";
$transactions = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Logs | Ridezo Admin</title>
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; overflow-x: hidden; }

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
            display: flex; flex-direction: column; color: var(--text); transition: var(--transition);
            border-right: 1px solid var(--border);
        }

        .sidebar-header { padding: 32px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border); }
        .logo-box { background: transparent; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--accent); filter: drop-shadow(0 0 10px var(--accent-glow)); }
        .logo-text { font-size: 1.6rem; font-weight: 800; letter-spacing: -1.2px; color: #fff; }

        .nav-menu { flex-grow: 1; padding: 24px 16px; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 16px; padding: 14px 16px; color: var(--text-muted); text-decoration: none; border-radius: 14px; font-weight: 600; margin-bottom: 8px; transition: var(--transition); }
        .nav-item i { font-size: 1.2rem; transition: var(--transition); }
        .nav-item:hover, .nav-item.active { background: var(--accent-soft); color: var(--accent); }
        .nav-item.active i { color: var(--accent); }

        .sidebar-footer { padding: 24px; border-top: 1px solid var(--border); }
        .user-pill { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--surface-light); border-radius: 16px; margin-bottom: 16px; border: 1px solid var(--border); }
        .user-pill img { width: 40px; height: 40px; border-radius: 12px; object-fit: cover; }
        .user-pill .info { flex-grow: 1; }
        .user-pill .name { font-weight: 700; font-size: 0.9rem; color: var(--text); }
        .user-pill .role { font-size: 0.75rem; color: var(--text-muted); }
        .btn-logout { display: flex; align-items: center; gap: 10px; color: #ef4444; text-decoration: none; font-weight: 700; font-size: 0.9rem; padding: 8px; transition: var(--transition); }
        .btn-logout:hover { transform: translateX(5px); color: #f87171; }

        /* Main Content Layout */
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; padding: 40px; width: calc(100% - var(--sidebar-width)); }
        .page-header { margin-bottom: 40px; display: flex; align-items: center; gap: 16px; }
        .header-title h1 { font-size: 2rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
        .btn-back-page { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 14px; background: var(--surface-light); color: var(--text-muted); border: 1px solid var(--border); text-decoration: none; transition: var(--transition); flex-shrink: 0; }
        .btn-back-page:hover { background: var(--accent); color: var(--primary); border-color: var(--accent); }

        .content-box { background: var(--surface); border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 32px; background: var(--surface-light); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); }
        td { padding: 20px 32px; border-bottom: 1px solid var(--border); vertical-align: middle; }

        .status-badge { padding: 6px 12px; border-radius: 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-confirmed { background: rgba(34, 197, 94, 0.1); color: var(--accent); border: 1px solid rgba(34, 197, 94, 0.2); }
        .status-pending { background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.2); }
        .status-cancelled { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

        .amount-positive { color: var(--accent); font-weight: 800; }
        .amount-primary { color: #38bdf8; font-weight: 800; }

        @media (max-width: 1024px) {
            .sidebar { left: -280px; }
            .sidebar.open { left: 0; }
            .main-wrapper { margin-left: 0; width: 100%; padding: 24px; }
            .mobile-header { display: flex !important; }
        }

        .mobile-header { display: none; background: var(--surface); padding: 16px 24px; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 900; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-bottom: 1px solid var(--border); }
    
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
            <a href="javascript:history.back()" class="nav-item">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
            <a href="../main/index.php" class="nav-item">
                <i class="fas fa-house"></i>
                <span>Home</span>
            </a>
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-chart-pie"></i>
                <span>System Dashboard</span>
            </a>
            <a href="vehicles.php" class="nav-item">
                <i class="fas fa-car"></i>
                <span>All Vehicles</span>
            </a>
            <a href="add-vehicle.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Vehicle</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users-gear"></i>
                <span>User Management</span>
            </a>
            <a href="bookings.php" class="nav-item active">
                <i class="fas fa-calendar-check"></i>
                <span>Booking Logs</span>
            </a>
            <a href="transactions.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>Transactions</span>            <a href="feedbacks.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Customer Feedback</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-pill">
                <?php if (!empty($user_photo)): ?>
                    <img src="../app/<?php echo $user_photo; ?>" alt="Profile">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-user"></i></div>
                <?php endif; ?>
                <div class="info"><div class="name">Admin</div><div class="role">Administrator</div></div>
            </div>
            <a href="../app/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i><span>Sign Out</span></a>
        </div>
    </div>

    <div class="mobile-header">
        <div onclick="toggleSidebar()" style="font-size: 1.5rem; cursor: pointer;"><i class="fas fa-bars"></i></div>
        <div class="logo-text" style="color: #fff">Ridezo</div>
        <div></div>
    </div>

    <main class="main-wrapper">
        <div class="page-header">
            <a href="javascript:history.back()" class="btn-back-page" title="Go Back"><i class="fas fa-arrow-left"></i></a>
            <div class="header-title">
                <h1>Booking Logs</h1>
                <p style="color: var(--text-muted); font-weight: 500;">Monitor all vehicle rentals across the platform.</p>
            </div>
        </div>

        <div class="content-box">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Vehicle</th>
                            <th>Customer</th>
                            <th>Vehicle Added By</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($transactions->num_rows > 0): ?>
                            <?php while($row = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 800; color: var(--text);">#<?php echo $row['booking_id']; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 800;"><?php echo $row['brand']; ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $row['model']; ?></div>
                                    </td>
                                    <td style="font-weight: 600;"><?php echo $row['customer_name']; ?></td>
                                    <td style="font-weight: 600;"><?php echo $row['seller_name']; ?></td>
                                    <td style="font-weight: 600;"><?php echo date('M d, Y H:i', strtotime($row['start_datetime'])); ?></td>
                                    <td style="font-weight: 600;"><?php echo date('M d, Y H:i', strtotime($row['end_datetime'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span>
                                        <?php if (in_array($row['status'], ['Confirmed', 'Active'])): ?>
                                            <div style="margin-top: 10px;">
                                                <a href="../app/track-vehicle.php?id=<?php echo $row['booking_id']; ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; font-size: 0.75rem; font-weight: 800; text-decoration: none; text-transform: uppercase;">
                                                    <i class="fas fa-location-arrow"></i> Track Live
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; padding: 60px; color: var(--text-muted);">No booking records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
    </script>
</body>
</html>
