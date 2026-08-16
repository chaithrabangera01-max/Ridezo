<?php
require '../app/config.php';
checkUserRole('Admin');

$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch vehicle data
$stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vehicle) {
    header("Location: vehicles.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $brand         = sanitize($_POST['brand']);
    $model         = sanitize($_POST['model']);
    $year          = intval($_POST['year']);
    $vehicle_type  = sanitize($_POST['vehicle_type']);
    $license_plate = sanitize($_POST['license_plate']);
    $rental_price  = floatval($_POST['rental_price_per_day']);
    $fuel_type     = sanitize($_POST['fuel_type']);
    $transmission  = sanitize($_POST['transmission']);
    $seats         = intval($_POST['seats']);
    $description   = sanitize($_POST['description']);
    $status        = sanitize($_POST['status']);
    $state         = sanitize($_POST['state']);
    $area          = sanitize($_POST['area']);
    $pincode       = sanitize($_POST['pincode']);
    $pickup_location = sanitize($_POST['pickup_location']);
    $available_from  = sanitize($_POST['available_from']);
    $available_until = sanitize($_POST['available_until']);

    $errors = [];

    if (empty($brand))         $errors[] = "Brand is required";
    if (empty($model))         $errors[] = "Model is required";
    if ($rental_price <= 0)    $errors[] = "Valid rental price is required";

    if (empty($errors)) {
        // Handle image update if new one uploaded
        $image_url = $vehicle['image_url'];
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] == 0) {
            $file_name = time() . '_' . $_FILES['vehicle_image']['name'];
            if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], UPLOAD_VEHICLES_DIR . $file_name)) {
                $image_url = 'uploads/vehicles/' . $file_name;
            }
        }

        $sql = "UPDATE vehicles SET 
                brand = ?, model = ?, year = ?, vehicle_type = ?, license_plate = ?, 
                rental_price_per_day = ?, fuel_type = ?, transmission = ?, seats = ?, 
                description = ?, image_url = ?, status = ?, state = ?, area = ?, pincode = ?,
                pickup_location = ?, available_from = ?, available_until = ?
                WHERE vehicle_id = ?";

        $params = [
            $brand, $model, $year, $vehicle_type, $license_plate,
            $rental_price, $fuel_type, $transmission, $seats, $description, $image_url, $status,
            $state, $area, $pincode, $pickup_location, $available_from, $available_until, $vehicle_id
        ];

        if ($conn->execute_query($sql, $params)) {
            showAlert("Vehicle updated successfully!", "success");
            header("Location: vehicles.php");
            exit();
        } else {
            $errors[] = "Update failed: " . $conn->error;
        }
    }

    if (!empty($errors)) {
        showAlert(implode("<br>", $errors), "error");
    }
}

$alert = getAlert();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vehicle | Ridezo Admin</title>
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
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            display: flex; 
            min-height: 100vh;
            overflow-x: hidden;
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

        /* Main Content */
        .main-wrapper { margin-left: var(--sidebar-width); flex-grow: 1; padding: 40px; width: calc(100% - var(--sidebar-width)); }
        .page-header { margin-bottom: 30px; display: flex; align-items: center; gap: 20px; }
        .btn-back { background: var(--surface-light); color: var(--text); padding: 10px 16px; border-radius: 12px; text-decoration: none; font-weight: 700; border: 1px solid var(--border); transition: var(--transition); display: flex; align-items: center; gap: 8px; }
        .btn-back:hover { background: var(--border); }
        .header-title h1 { font-size: 2rem; font-weight: 800; color: var(--text); margin-bottom: 4px; }
        
        .content-box { background: var(--surface); border-radius: 24px; border: 1px solid var(--border); padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }

        /* Forms */
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%; padding: 14px 16px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: inherit; font-size: 0.95rem; font-weight: 500; transition: var(--transition); outline: none;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        
        .btn-primary { background: var(--accent); color: var(--primary); padding: 14px 28px; border-radius: 12px; border: none; font-weight: 800; font-size: 1rem; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: #16a34a; transform: translateY(-2px); box-shadow: 0 10px 20px var(--accent-soft); }
        
        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

        .image-preview { width: 100%; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 16px; object-fit: cover; }

        .mobile-header { display: none; background: var(--surface); padding: 16px 24px; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 900; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-bottom: 1px solid var(--border); }

        @media (max-width: 1024px) {
            .sidebar { left: -280px; }
            .sidebar.open { left: 0; }
            .main-wrapper { margin-left: 0; width: 100%; padding: 24px; }
            .mobile-header { display: flex !important; }
            .grid-layout { grid-template-columns: 1fr !important; }
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
            <a href="vehicles.php" class="btn-back"><i class="fas fa-arrow-left"></i></a>
            <div class="header-title">
                <h1>Edit Vehicle</h1>
                <p style="color: var(--text-muted); font-weight: 500;">Modifying: <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></p>
            </div>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?php echo $alert['type']; ?>">
                <i class="fas fa-<?php echo $alert['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $alert['message']; ?></span>
            </div>
        <?php endif; ?>

        <div class="content-box">
            <form method="POST" enctype="multipart/form-data">
                <div class="grid-layout" style="display: grid; grid-template-columns: 320px 1fr; gap: 40px;">
                    <!-- Left: Image Preview -->
                    <div>
                        <div style="background: var(--surface-light); padding: 20px; border-radius: 20px; border: 1px solid var(--border); position: sticky; top: 40px;">
                            <label>Current Image</label>
                            <img src="../app/<?php echo $vehicle['image_url']; ?>" class="image-preview">
                            <div style="margin-top: 16px;">
                                <label>Upload New Image (Optional)</label>
                                <input type="file" name="vehicle_image" accept="image/*" style="padding: 10px; font-size: 0.8rem; background: var(--bg);">
                            </div>
                        </div>
                    </div>

                    <!-- Right: Form Fields -->
                    <div>
                        <h3 style="margin-bottom: 24px; color: #fff; font-size: 1.2rem; display: flex; align-items: center; gap: 8px;"><i class="fas fa-info-circle" style="color: var(--accent);"></i> Basic Info</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group"><label>Brand</label><input type="text" name="brand" value="<?php echo htmlspecialchars($vehicle['brand']); ?>" required></div>
                            <div class="form-group"><label>Model</label><input type="text" name="model" value="<?php echo htmlspecialchars($vehicle['model']); ?>" required></div>
                            <div class="form-group"><label>Year</label><input type="number" name="year" value="<?php echo $vehicle['year']; ?>" required></div>
                            <div class="form-group"><label>License Plate</label><input type="text" name="license_plate" value="<?php echo htmlspecialchars($vehicle['license_plate']); ?>" required pattern="^[A-Za-z]{2}[0-9]{1,2}[A-Za-z]{1,3}[0-9]{1,4}$" style="text-transform:uppercase;" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');"></div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Vehicle Type</label>
                                <select name="vehicle_type">
                                    <?php $types = ['Car', 'Bike', 'Scooty', 'Van', 'Electric']; foreach($types as $t) { echo "<option value='$t' ".($vehicle['vehicle_type'] == $t ? 'selected' : '').">$t</option>"; } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fuel Type</label>
                                <select name="fuel_type">
                                    <?php $fuels = ['Petrol', 'Diesel', 'Electric', 'Hybrid']; foreach($fuels as $f) { echo "<option value='$f' ".($vehicle['fuel_type'] == $f ? 'selected' : '').">$f</option>"; } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Transmission</label>
                                <select name="transmission">
                                    <option value="Manual" <?php if($vehicle['transmission'] == 'Manual') echo 'selected'; ?>>Manual</option>
                                    <option value="Automatic" <?php if($vehicle['transmission'] == 'Automatic') echo 'selected'; ?>>Automatic</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            <div class="form-group"><label>Seats</label><input type="number" name="seats" value="<?php echo $vehicle['seats']; ?>" required min="1" max="15"></div>
                            <div class="form-group"><label>Rental Price / Day (₹)</label><input type="number" name="rental_price_per_day" value="<?php echo $vehicle['rental_price_per_day']; ?>" min="1" step="0.01" required></div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" style="border-color: var(--accent); color: var(--accent); font-weight: 700; background: rgba(34, 197, 94, 0.05);">
                                    <option value="Available" <?php if($vehicle['status'] == 'Available') echo 'selected'; ?>>Available (Live)</option>
                                    <option value="Pending" <?php if($vehicle['status'] == 'Pending') echo 'selected'; ?>>Pending Approval</option>
                                    <option value="Maintenance" <?php if($vehicle['status'] == 'Maintenance') echo 'selected'; ?>>Maintenance</option>
                                    <option value="Rejected" <?php if($vehicle['status'] == 'Rejected') echo 'selected'; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="4"><?php echo htmlspecialchars($vehicle['description']); ?></textarea>
                        </div>

                        <h3 style="margin: 32px 0 24px; color: #fff; font-size: 1.2rem; display: flex; align-items: center; gap: 8px; border-top: 1px solid var(--border); padding-top: 32px;"><i class="fas fa-map-marker-alt" style="color: #38bdf8;"></i> Location Details</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>State</label>
                                <select name="state">
                                    <?php $states = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi']; foreach($states as $s) { echo "<option value='$s' ".($vehicle['state'] == $s ? 'selected' : '').">$s</option>"; } ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Area / City</label><input type="text" name="area" value="<?php echo htmlspecialchars($vehicle['area']); ?>" required></div>
                            <div class="form-group"><label>Pincode</label><input type="text" name="pincode" value="<?php echo htmlspecialchars($vehicle['pincode']); ?>" required pattern="[0-9]{6}"></div>
                            <div class="form-group"><label>Specific Pickup Spot</label><input type="text" name="pickup_location" value="<?php echo htmlspecialchars($vehicle['pickup_location']); ?>" required></div>
                        </div>

                        <h3 style="margin: 32px 0 24px; color: #fff; font-size: 1.2rem; display: flex; align-items: center; gap: 8px; border-top: 1px solid var(--border); padding-top: 32px;"><i class="fas fa-calendar-check" style="color: #f59e0b;"></i> Availability Range</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group"><label>Available From</label><input type="date" name="available_from" value="<?php echo $vehicle['available_from']; ?>" required></div>
                            <div class="form-group"><label>Available Until</label><input type="date" name="available_until" value="<?php echo $vehicle['available_until']; ?>" required></div>
                        </div>

                        <div style="margin-top: 40px; display: flex; gap: 16px;">
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
    </script>
</body>
</html>