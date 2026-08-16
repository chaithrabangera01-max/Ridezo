<?php
require '../app/config.php';
checkUserRole('Admin');

$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'Admin';
$user_photo = $_SESSION['profile_photo'] ?? $_SESSION['user_photo'] ?? null;
$alert = getAlert();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $vehicle_id = intval($_POST['vehicle_id']);

    if ($_POST['action'] == 'delete') {
        $delete = $conn->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
        $delete->bind_param("i", $vehicle_id);
        if ($delete->execute()) {
            showAlert("Vehicle deleted successfully.", "success");
        }
        $delete->close();

    } elseif ($_POST['action'] == 'approve') {
        $upd = $conn->prepare("UPDATE vehicles SET status = 'Available' WHERE vehicle_id = ?");
        $upd->bind_param("i", $vehicle_id);
        if ($upd->execute()) {
            showAlert("Vehicle approved and is now live!", "success");
        }
        $upd->close();

    } elseif ($_POST['action'] == 'reject') {
        $reason = sanitize($_POST['rejection_reason'] ?? 'Does not meet requirements.');
        $upd = $conn->prepare("UPDATE vehicles SET status = 'Rejected', rejection_reason = ? WHERE vehicle_id = ?");
        $upd->bind_param("si", $reason, $vehicle_id);
        if ($upd->execute()) {
            $sellerQuery = $conn->query("SELECT seller_id, brand, model FROM vehicles WHERE vehicle_id = $vehicle_id")->fetch_assoc();
            if ($sellerQuery) {
                $msg = "Your vehicle " . $sellerQuery['brand'] . " " . $sellerQuery['model'] . " was rejected. Reason: $reason";
                $conn->query("INSERT INTO notifications (user_id, message, type) VALUES (" . $sellerQuery['seller_id'] . ", '" . $conn->real_escape_string($msg) . "', 'Rejection')");
            }
            showAlert("Vehicle rejected and seller notified.", "success");
        }
        $upd->close();
    }

    header("Location: vehicles.php");
    exit();
}

// Search Logic
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sql = "SELECT v.*, u.name as seller_name, u.email as seller_email, u.role as seller_role,
        (SELECT booking_id FROM bookings b WHERE b.vehicle_id = v.vehicle_id AND b.status IN ('Confirmed', 'Active') LIMIT 1) as active_booking_id
        FROM vehicles v 
        JOIN users u ON v.seller_id = u.user_id";

$params = [];
if (!empty($search)) {
    $sql .= " WHERE v.brand LIKE ? OR v.model LIKE ? OR v.license_plate LIKE ? OR u.name LIKE ?";
    $st = "%$search%";
    $params = [$st, $st, $st, $st];
}

$sql .= " ORDER BY v.created_at DESC";
$vehicles = $conn->execute_query($sql, $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Management | Ridezo Admin</title>
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
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; gap: 16px; }
        .btn-back-page { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 14px; background: var(--surface-light); color: var(--text-muted); border: 1px solid var(--border); text-decoration: none; transition: var(--transition); flex-shrink: 0; }
        .btn-back-page:hover { background: var(--accent); color: var(--primary); border-color: var(--accent); }
        .header-title h1 { font-size: 2rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }

        .content-box { background: var(--surface); border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 32px; background: var(--surface-light); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); }
        td { padding: 20px 32px; border-bottom: 1px solid var(--border); vertical-align: middle; }

        .status-badge { padding: 6px 12px; border-radius: 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-available { background: rgba(34, 197, 94, 0.1); color: var(--accent); border: 1px solid rgba(34, 197, 94, 0.2); }
        .status-pending { background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.2); }
        .status-rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

        .btn-action { width: 36px; height: 36px; border-radius: 10px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); text-decoration: none; }
        .btn-edit { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
        .btn-edit:hover { background: #3b82f6; color: white; }
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

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(2, 6, 23, 0.8); backdrop-filter: blur(5px); z-index: 2000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 32px; width: 90%; max-width: 850px; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .close-modal { position: absolute; top: 24px; right: 24px; background: transparent; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: var(--transition); }
        .close-modal:hover { color: #ef4444; }
        .modal-header h2 { font-size: 1.5rem; font-weight: 800; color: var(--text); margin-bottom: 4px; }
        .modal-header p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }
        .modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px; }
        .modal-section { background: var(--surface-light); padding: 24px; border-radius: 16px; border: 1px solid var(--border); }
        .modal-section h4 { color: var(--accent); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-size: 1rem; }
        .detail-row { display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 12px; }
        .detail-row:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
        .detail-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; }
        .detail-value { font-size: 0.9rem; font-weight: 700; color: var(--text); text-align: right; }
        .modal-img { width: 100%; height: 200px; object-fit: cover; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 16px; }
        .doc-link { display: flex; align-items: center; gap: 10px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 14px 16px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 0.9rem; border: 1px solid rgba(59, 130, 246, 0.2); transition: var(--transition); margin-bottom: 12px; }
        .doc-link:hover { background: #3b82f6; color: white; transform: translateY(-2px); }
        .modal-actions { display: flex; gap: 16px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border); }
        .modal-actions button { flex: 1; padding: 14px; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: var(--transition); border: none; }
        .btn-modal-approve { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); }
        .btn-modal-approve:hover { background: #22c55e; color: #020617; }
        .btn-modal-reject { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-modal-reject:hover { background: #ef4444; color: white; }
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
            <a href="vehicles.php" class="nav-item active">
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
            <a href="bookings.php" class="nav-item">
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
                <h1>Fleet Control</h1>
                <p style="color: var(--text-muted); font-weight: 500;">Manage all vehicles listed on the platform.</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <form action="vehicles.php" method="GET" style="display: flex; gap: 8px;">
                    <input type="text" name="search" placeholder="Search fleet..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 12px; background: var(--input-bg); color: var(--text); border: 1px solid var(--border); border-radius: 12px; font-weight: 600; outline: none;">
                    <button type="submit" style="background: var(--accent); color: var(--primary); border: none; padding: 0 20px; border-radius: 12px; cursor: pointer; font-weight: 800;">Search</button>
                </form>
            </div>
        </div>

        <?php if ($alert): ?>
            <div style="background: #dcfce7; color: #166534; padding: 16px; border-radius: 16px; margin-bottom: 32px; font-weight: 700;"><?php echo $alert['message']; ?></div>
        <?php endif; ?>

        <div class="content-box">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Seller</th>
                            <th>Status</th>
                            <th>Pricing</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($vehicles->num_rows > 0): ?>
                            <?php while ($v = $vehicles->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <img src="../app/<?php echo $v['image_url']; ?>" style="width: 50px; height: 50px; border-radius: 10px; object-fit: cover; border: 1px solid var(--border);">
                                            <div>
                                                <div style="font-weight: 800; color: var(--text);"><?php echo $v['brand'].' '.$v['model']; ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $v['license_plate']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700;"><?php echo $v['seller_name']; ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $v['seller_email']; ?></div>
                                    </td>
                                    <td><span class="status-badge status-<?php echo strtolower($v['status']); ?>"><?php echo $v['status']; ?></span></td>
                                    <td style="font-weight: 800;">₹<?php echo number_format($v['rental_price_per_day'], 0); ?>/d</td>
                                    <td>
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <!-- Approve button for Pending vehicles -->
                                            <?php if ($v['status'] === 'Pending'): ?>
                                                <button type="button" class="btn-action" title="View Details" style="background: rgba(59,130,246,0.12); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); width: auto; padding: 0 12px; gap: 4px; font-size: 0.75rem; font-weight: 800;" onmouseover="this.style.background='#3b82f6'; this.style.color='white';" onmouseout="this.style.background='rgba(59,130,246,0.12)'; this.style.color='#3b82f6';" onclick='showVehicleDetails(<?php echo htmlspecialchars(json_encode($v), ENT_QUOTES, "UTF-8"); ?>)'>
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <form method="POST" style="display:contents;">
                                                    <input type="hidden" name="vehicle_id" value="<?php echo $v['vehicle_id']; ?>">
                                                    <button type="submit" class="btn-action" title="Approve" style="background: rgba(34,197,94,0.12); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); width: auto; padding: 0 12px; gap: 4px; font-size: 0.75rem; font-weight: 800;" onmouseover="this.style.background='#22c55e'; this.style.color='#020617';" onmouseout="this.style.background='rgba(34,197,94,0.12)'; this.style.color='#22c55e';">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <button type="button" class="btn-action" title="Reject" style="background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); width: auto; padding: 0 12px; font-size: 0.75rem; font-weight: 800;" onmouseover="this.style.background='#ef4444'; this.style.color='white';" onmouseout="this.style.background='rgba(239,68,68,0.12)'; this.style.color='#ef4444';" onclick="openRejectModal(<?php echo $v['vehicle_id']; ?>)">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php elseif ($v['seller_role'] === 'Admin'): ?>
                                                <a href="edit-vehicle.php?id=<?php echo $v['vehicle_id']; ?>" class="btn-action btn-edit" title="Edit"><i class="fas fa-edit"></i></a>
                                            <?php else: ?>
                                                <span class="btn-action" style="opacity: 0.3; cursor: not-allowed; background: var(--surface-light); color: var(--text-muted);" title="Already processed"><i class="fas fa-check-double"></i></span>
                                            <?php endif; ?>

                                            <?php if (!empty($v['active_booking_id'])): ?>
                                                <a href="../app/track-vehicle.php?id=<?php echo $v['active_booking_id']; ?>" class="btn-action" title="Track Live" style="background: rgba(34,197,94,0.12); color: #22c55e; border: 1px solid rgba(34,197,94,0.3);" onmouseover="this.style.background='#22c55e'; this.style.color='#020617';" onmouseout="this.style.background='rgba(34,197,94,0.12)'; this.style.color='#22c55e';">
                                                    <i class="fas fa-location-arrow"></i>
                                                </a>
                                            <?php endif; ?>

                                            <form method="POST" onsubmit="return confirm('Delete this vehicle?');" style="display:contents;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="vehicle_id" value="<?php echo $v['vehicle_id']; ?>">
                                                <button type="submit" class="btn-action btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 60px; color: var(--text-muted);">No vehicles found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Vehicle Details Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeVehicleDetails()"><i class="fas fa-times"></i></button>
            <div class="modal-header">
                <h2 id="modal-brand-model">Brand Model</h2>
                <p id="modal-plate-status">License Plate • Status</p>
            </div>
            
            <div class="modal-grid">
                <!-- Left Column -->
                <div>
                    <img id="modal-img" src="" alt="Vehicle Image" class="modal-img">
                    
                    <div class="modal-section">
                        <h4><i class="fas fa-file-alt"></i> Documents</h4>
                        <a id="modal-rc" href="#" class="doc-link">
                            <i class="fas fa-file-invoice"></i> Registration (RC)
                        </a>
                        <a id="modal-insurance" href="#" class="doc-link">
                            <i class="fas fa-shield-alt"></i> Insurance Policy
                        </a>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <div class="modal-section" style="margin-bottom: 24px;">
                        <h4><i class="fas fa-info-circle"></i> Specifications</h4>
                        <div class="detail-row"><span class="detail-label">Vehicle Type</span><span class="detail-value" id="modal-type"></span></div>
                        <div class="detail-row"><span class="detail-label">Fuel</span><span class="detail-value" id="modal-fuel"></span></div>
                        <div class="detail-row"><span class="detail-label">Transmission</span><span class="detail-value" id="modal-transmission"></span></div>
                        <div class="detail-row"><span class="detail-label">Seats</span><span class="detail-value" id="modal-seats"></span></div>
                        <div class="detail-row"><span class="detail-label">Rental Price</span><span class="detail-value" id="modal-price" style="color: var(--accent);"></span></div>
                    </div>
                    
                    <div class="modal-section">
                        <h4><i class="fas fa-map-marker-alt"></i> Location & Dates</h4>
                        <div class="detail-row"><span class="detail-label">Location</span><span class="detail-value" id="modal-location" style="text-align: right; max-width: 200px;"></span></div>
                        <div class="detail-row"><span class="detail-label">Available From</span><span class="detail-value" id="modal-from"></span></div>
                        <div class="detail-row"><span class="detail-label">Available Until</span><span class="detail-value" id="modal-until"></span></div>
                        <div style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px;">
                            <span class="detail-label">Description</span>
                            <p id="modal-desc" style="font-size: 0.85rem; color: var(--text); margin-top: 4px; line-height: 1.5;"></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <form method="POST" style="flex: 1;" onsubmit="return confirm('Approve this vehicle?');">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="vehicle_id" id="modal-approve-id">
                    <button type="submit" class="btn-modal-approve" style="width: 100%;"><i class="fas fa-check"></i> Approve Vehicle</button>
                </form>
                <input type="hidden" id="modal-reject-id">
                <button type="button" class="btn-modal-reject" style="flex: 1; width: 100%; padding: 14px; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: var(--transition); border: 1px solid rgba(239, 68, 68, 0.2);" onclick="closeVehicleDetails(); openRejectModal(document.getElementById('modal-reject-id').value);"><i class="fas fa-times"></i> Reject Vehicle</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="rejectModal">
        <div class="modal-content" style="max-width: 500px; padding: 32px;">
            <h2 style="margin-bottom: 16px;">Reject Listing</h2>
            <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">Please provide a reason. This will be sent to the seller.</p>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="vehicle_id" id="rejectId">
                <textarea name="rejection_reason" required style="width: 100%; min-height: 120px; padding: 16px; border-radius: 12px; border: 1px solid var(--border); background: var(--input-bg); color: var(--text); font-family: inherit; margin-bottom: 24px;"></textarea>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn-modal-reject" style="flex: 1; padding: 12px; font-weight: 800; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.2); cursor: pointer; transition: all 0.4s;">Confirm Reject</button>
                    <button type="button" onclick="closeRejectModal()" style="flex: 1; padding: 12px; background: rgba(255,255,255,0.05); color: var(--text); border: 1px solid var(--border); border-radius: 12px; font-weight: 800; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
        
        function showVehicleDetails(v) {
            document.getElementById('modal-brand-model').textContent = v.brand + ' ' + v.model + ' (' + v.year + ')';
            document.getElementById('modal-plate-status').textContent = v.license_plate + ' • ' + v.status;
            
            document.getElementById('modal-img').src = '../app/' + v.image_url;
            document.getElementById('modal-rc').href = 'view-document.php?file=' + encodeURIComponent(v.registration_doc_url) + '&title=Registration Document (RC)';
            document.getElementById('modal-insurance').href = 'view-document.php?file=' + encodeURIComponent(v.insurance_doc_url) + '&title=Insurance Policy';
            
            document.getElementById('modal-type').textContent = v.vehicle_type;
            document.getElementById('modal-fuel').textContent = v.fuel_type;
            document.getElementById('modal-transmission').textContent = v.transmission;
            document.getElementById('modal-seats').textContent = v.seats;
            document.getElementById('modal-price').textContent = '₹' + parseInt(v.rental_price_per_day) + '/day';
            
            document.getElementById('modal-location').textContent = v.pickup_location + ', ' + v.area + ', ' + v.state + ' - ' + v.pincode;
            document.getElementById('modal-from').textContent = v.available_from;
            document.getElementById('modal-until').textContent = v.available_until;
            document.getElementById('modal-desc').textContent = v.description || 'No description provided.';
            
            document.getElementById('modal-approve-id').value = v.vehicle_id;
            document.getElementById('modal-reject-id').value = v.vehicle_id;
            
            // Only show actions if status is Pending
            const actionsDiv = document.querySelector('.modal-actions');
            if (v.status === 'Pending') {
                actionsDiv.style.display = 'flex';
            } else {
                actionsDiv.style.display = 'none';
            }
            
            document.getElementById('detailsModal').classList.add('active');
        }
        
        function closeVehicleDetails() {
            document.getElementById('detailsModal').classList.remove('active');
        }

        function openRejectModal(id) { document.getElementById('rejectId').value = id; document.getElementById('rejectModal').classList.add('active'); }
        function closeRejectModal() { document.getElementById('rejectModal').classList.remove('active'); }
    </script>
</body>
</html>
