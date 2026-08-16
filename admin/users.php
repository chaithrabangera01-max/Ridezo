<?php
require '../app/config.php';
checkUserRole('Admin');

$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'Admin';
$user_photo = $_SESSION['profile_photo'] ?? $_SESSION['user_photo'] ?? null;
$alert = getAlert();

// Handle User Actions (e.g. Delete/Ban if needed)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $target_user_id = intval($_POST['target_user_id']);
    // Check if user has active bookings
    $check = $conn->query("SELECT * FROM bookings WHERE (customer_id = $target_user_id OR seller_id = $target_user_id) AND status IN ('Pending', 'Confirmed', 'Active')");
    if ($check->num_rows > 0) {
        showAlert("Cannot delete user. They have active or pending bookings.", "error");
    } else {
        $conn->query("DELETE FROM users WHERE user_id = $target_user_id");
        showAlert("User removed successfully.", "success");
    }
    header("Location: users.php");
    exit();
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

// Fetch data based on tab
if ($tab === 'tracking') {
    // Tracking active rentals
    $tracking_query = "
        SELECT b.booking_id, b.status as booking_status, b.payment_status, 
               b.start_datetime, b.end_datetime, b.total_amount,
               b.latitude, b.longitude, b.tracking_active,
               u.name as customer_name, u.phone as customer_phone,
               v.brand, v.model, v.license_plate,
               s.name as seller_name, s.phone as seller_phone
        FROM bookings b
        JOIN users u ON b.customer_id = u.user_id
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        JOIN users s ON b.seller_id = s.user_id
        WHERE b.status IN ('Confirmed', 'Active', 'Pending')
        ORDER BY b.start_datetime ASC
    ";
    $tracking_data = $conn->query($tracking_query);
} else {
    // All users
    $users_query = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM vehicles WHERE seller_id = u.user_id) as total_vehicles,
               (SELECT COUNT(*) FROM bookings WHERE customer_id = u.user_id) as total_bookings
        FROM users u 
        WHERE u.role != 'Admin' 
        ORDER BY u.created_at DESC
    ";
    $users_data = $conn->query($users_query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management & Tracking | Ridezo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Leaflet.js -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        /* Tracking custom grid layout */
        .tracking-grid {
            display: grid;
            grid-template-columns: 1.2fr 1.8fr;
            gap: 32px;
            align-items: start;
        }
        @media (max-width: 1200px) {
            .tracking-grid {
                grid-template-columns: 1fr;
            }
        }
        #admin-tracking-map {
            height: 600px;
            border-radius: 28px;
            border: 1px solid var(--border);
            z-index: 1;
        }
        .status-online { background: rgba(34, 197, 94, 0.1); color: var(--accent); border: 1px solid var(--accent-glow); }
        .status-offline { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-track {
            padding: 6px 12px;
            background: var(--accent-soft);
            color: var(--accent);
            border: 1px solid var(--accent-glow);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 800;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-track:hover {
            background: var(--accent);
            color: var(--primary);
        }
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
        .page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 40px; }
        .header-title h1 { font-size: 2rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
        .btn-back-page { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 14px; background: var(--surface-light); color: var(--text-muted); border: 1px solid var(--border); text-decoration: none; transition: var(--transition); flex-shrink: 0; }
        .btn-back-page:hover { background: var(--accent); color: var(--primary); border-color: var(--accent); }

        .filter-tabs { display: flex; gap: 12px; margin-bottom: 32px; flex-wrap: wrap; }
        .tab-btn {
            padding: 10px 24px; border-radius: 12px; text-decoration: none;
            font-weight: 700; font-size: 0.85rem; transition: var(--transition);
            background: var(--surface); color: var(--text-muted); border: 1px solid var(--border);
        }
        .tab-btn.active { background: var(--accent); color: var(--primary); border-color: var(--accent); box-shadow: 0 4px 12px var(--accent-glow); }
        .tab-btn:hover:not(.active) { background: var(--surface-light); color: var(--text); border-color: var(--text-muted); }


        .content-box { background: var(--surface); border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 32px; background: var(--surface-light); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); }
        td { padding: 20px 32px; border-bottom: 1px solid var(--border); vertical-align: middle; }

        .status-badge { padding: 6px 12px; border-radius: 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-paid { background: rgba(34, 197, 94, 0.1); color: var(--accent); border: 1px solid rgba(34, 197, 94, 0.2); }
        .status-unpaid { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-confirmed { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
        .status-active { background: rgba(34, 197, 94, 0.1); color: var(--accent); border: 1px solid rgba(34, 197, 94, 0.2); }
        .status-pending { background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.2); }

        .btn-action { width: 36px; height: 36px; border-radius: 10px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); text-decoration: none; }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-delete:hover { background: #ef4444; color: white; }

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
            <a href="users.php" class="nav-item active">
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
                <h1>Users &amp; Tracking</h1>
                <p style="color: var(--text-muted); font-weight: 500;">Manage accounts and track live vehicle rentals.</p>
            </div>
        </div>

        <?php if ($alert): ?>
            <div style="background: <?php echo $alert['type'] == 'success' ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo $alert['type'] == 'success' ? '#22c55e' : '#ef4444'; ?>; border: 1px solid <?php echo $alert['type'] == 'success' ? 'rgba(34, 197, 94, 0.2)' : 'rgba(239, 68, 68, 0.2)'; ?>; padding: 16px; border-radius: 16px; margin-bottom: 32px; font-weight: 700;">
                <?php echo $alert['message']; ?>
            </div>
        <?php endif; ?>

        <div class="filter-tabs">
            <a href="users.php?tab=users" class="tab-btn <?php echo $tab === 'users' ? 'active' : ''; ?>">All Users</a>
            <a href="users.php?tab=tracking" class="tab-btn <?php echo $tab === 'tracking' ? 'active' : ''; ?>"><i class="fas fa-location-crosshairs" style="margin-right: 6px;"></i> Active Vehicle Tracking</a>
        </div>

        <?php if ($tab === 'tracking'): ?>
            <div class="tracking-grid">
                <div class="content-box" style="padding: 12px; height: 600px; overflow-y: auto;">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($tracking_data && $tracking_data->num_rows > 0): ?>
                                    <?php while($row = $tracking_data->fetch_assoc()): ?>
                                        <tr id="booking-row-<?php echo $row['booking_id']; ?>">
                                            <td>
                                                <div style="font-weight: 800; color: var(--text);"><?php echo $row['brand'] . ' ' . $row['model']; ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $row['license_plate']; ?></div>
                                                <div style="font-size: 0.75rem; margin-top: 4px; color: var(--accent);">Renter: <?php echo $row['customer_name']; ?></div>
                                            </td>
                                            <td>
                                                <?php if ($row['tracking_active'] == 1): ?>
                                                    <span class="status-badge status-online"><i class="fas fa-circle-dot fa-fade" style="color:var(--accent); font-size: 8px;"></i> Live</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-offline">Offline</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($row['payment_status']); ?>">
                                                    <?php echo $row['payment_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($row['tracking_active'] == 1): ?>
                                                    <button class="btn-track" onclick="focusVehicle(<?php echo $row['booking_id']; ?>, <?php echo $row['latitude']; ?>, <?php echo $row['longitude']; ?>)">
                                                        <i class="fas fa-location-arrow"></i> Track
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-track" style="opacity: 0.5; cursor: not-allowed;" disabled>
                                                        <i class="fas fa-location-arrow"></i> Track
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align: center; padding: 60px; color: var(--text-muted);">No active rentals found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="content-box">
                    <div id="admin-tracking-map"></div>
                </div>
            </div>
        <?php else: ?>
            <div class="content-box">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Activity Overview</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_data && $users_data->num_rows > 0): ?>
                                <?php while($row = $users_data->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <?php if(!empty($row['profile_photo'])): ?>
                                                    <img src="../app/<?php echo $row['profile_photo']; ?>" style="width: 40px; height: 40px; border-radius: 12px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; border-radius: 12px; background: var(--surface-light); display: flex; align-items: center; justify-content: center; color: var(--text-muted);"><i class="fas fa-user"></i></div>
                                                <?php endif; ?>
                                                <div style="font-weight: 800; color: var(--text);"><?php echo $row['name']; ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; font-size: 0.85rem;"><?php echo $row['email']; ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $row['phone']; ?></div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $row['role'] == 'Seller' ? 'active' : 'confirmed'; ?>"><?php echo $row['role']; ?></span>
                                        </td>
                                        <td>
                                            <?php if($row['role'] == 'Seller'): ?>
                                                <div style="font-size: 0.85rem; font-weight: 600;"><i class="fas fa-car" style="color:var(--text-muted); margin-right:4px;"></i> <?php echo $row['total_vehicles']; ?> Listed</div>
                                            <?php endif; ?>
                                            <?php if($row['role'] == 'Customer'): ?>
                                                <div style="font-size: 0.85rem; font-weight: 600;"><i class="fas fa-book" style="color:var(--text-muted); margin-right:4px;"></i> <?php echo $row['total_bookings']; ?> Bookings</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="target_user_id" value="<?php echo $row['user_id']; ?>">
                                                <button type="submit" class="btn-action btn-delete" title="Remove User"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; padding: 60px; color: var(--text-muted);">No users found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

        <?php if ($tab === 'tracking'): ?>
        let map = null;
        let markers = {};

        function initAdminMap() {
            // Center map at Bangalore by default
            map = L.map('admin-tracking-map').setView([12.9716, 77.5946], 12);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            
            // Run initial poll
            pollLocations();
            
            // Poll every 4 seconds
            setInterval(pollLocations, 4000);
        }

        function pollLocations() {
            fetch('get-active-locations.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const activeIds = new Set();
                        
                        data.locations.forEach(loc => {
                            activeIds.add(loc.booking_id);
                            
                            // Live update the table row to Active if it was offline
                            const row = document.getElementById('booking-row-' + loc.booking_id);
                            if (row) {
                                const badgeCell = row.cells[1];
                                badgeCell.innerHTML = '<span class="status-badge status-online"><i class="fas fa-circle-dot fa-fade" style="color:var(--accent); font-size:8px;"></i> Live</span>';
                                
                                const actionCell = row.cells[3];
                                actionCell.innerHTML = `<button class="btn-track" onclick="focusVehicle(${loc.booking_id}, ${loc.latitude}, ${loc.longitude})"><i class="fas fa-location-arrow"></i> Track</button>`;
                            }
                            
                            const popupContent = `
                                <div style="font-family:'Plus Jakarta Sans', sans-serif; color:#0f172a; padding:4px; min-width: 180px;">
                                    <h4 style="margin:0 0 6px 0; font-weight:800; font-size:0.95rem; color: #0f172a;">${loc.brand} ${loc.model}</h4>
                                    <div style="font-size:0.75rem; font-weight:700; color:#64748b; margin-bottom:8px;">Plate: ${loc.license_plate}</div>
                                    <div style="font-size:0.8rem; margin-bottom:4px; color: #1e293b;"><b>Renter:</b> ${loc.customer_name}</div>
                                    <div style="font-size:0.8rem; margin-bottom:8px; color: #1e293b;"><b>Payment:</b> <span style="color:${loc.payment_status === 'Paid' ? '#16a34a' : '#dc2626'}; font-weight:800;">${loc.payment_status}</span></div>
                                    <a href="bookings.php" style="display:inline-block; font-size:0.75rem; color:#3b82f6; font-weight:700; text-decoration:none;">View booking logs ➔</a>
                                </div>
                            `;
                            
                            if (markers[loc.booking_id]) {
                                // Update existing marker coordinate
                                markers[loc.booking_id].setLatLng([loc.latitude, loc.longitude]);
                                markers[loc.booking_id].getPopup().setContent(popupContent);
                            } else {
                                // Create new vehicle marker with electric green color scheme
                                const carIcon = L.divIcon({
                                    html: '<div style="background: rgba(34, 197, 94, 0.2); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #22c55e; box-shadow: 0 0 12px #22c55e;"><i class="fas fa-car" style="color: #22c55e; font-size: 18px;"></i></div>',
                                    className: 'custom-admin-marker',
                                    iconSize: [44, 44],
                                    iconAnchor: [22, 22]
                                });
                                
                                const marker = L.marker([loc.latitude, loc.longitude], {icon: carIcon})
                                    .addTo(map)
                                    .bindPopup(popupContent);
                                
                                markers[loc.booking_id] = marker;
                            }
                        });
                        
                        // Clean up offline markers
                        Object.keys(markers).forEach(id => {
                            const bId = parseInt(id);
                            if (!activeIds.has(bId)) {
                                map.removeLayer(markers[bId]);
                                delete markers[bId];
                                
                                // Live update the table row to Offline
                                const row = document.getElementById('booking-row-' + bId);
                                if (row) {
                                    const badgeCell = row.cells[1];
                                    badgeCell.innerHTML = '<span class="status-badge status-offline">Offline</span>';
                                    
                                    const actionCell = row.cells[3];
                                    actionCell.innerHTML = `<button class="btn-track" style="opacity: 0.5; cursor: not-allowed;" disabled><i class="fas fa-location-arrow"></i> Track</button>`;
                                }
                            }
                        });
                    }
                })
                .catch(err => console.error('Error polling locations:', err));
        }

        function focusVehicle(bookingId, lat, lng) {
            if (map && markers[bookingId]) {
                map.setView([lat, lng], 15, { animate: true, duration: 1.5 });
                markers[bookingId].openPopup();
            }
        }

        // Initialize map when page finishes loading
        document.addEventListener("DOMContentLoaded", initAdminMap);
        <?php endif; ?>
    </script>
</body>
</html>
