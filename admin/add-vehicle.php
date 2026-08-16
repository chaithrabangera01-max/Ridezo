<?php
require '../app/config.php';
checkUserRole('Admin');

$admin_id = $_SESSION['user_id'];

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
    $state         = sanitize($_POST['state']);
    $area          = sanitize($_POST['area']);
    $pincode       = sanitize($_POST['pincode']);
    $pickup_location = sanitize($_POST['pickup_location']);
    $available_from  = sanitize($_POST['available_from']);
    $available_until = sanitize($_POST['available_until']);

    $errors = [];

    if (empty($brand))         $errors[] = "Brand is required";
    if (empty($model))         $errors[] = "Model is required";
    if (empty($year) || $year < 1900 || $year > date('Y') + 1) $errors[] = "Valid year is required";
    if (empty($license_plate)) $errors[] = "License plate is required";
    if ($rental_price <= 0)    $errors[] = "Valid rental price is required";

    // Check duplicate license plate
    $plate_check = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate = ?");
    $plate_check->bind_param("s", $license_plate);
    $plate_check->execute();
    if ($plate_check->get_result()->num_rows > 0) {
        $errors[] = "This license plate ($license_plate) is already registered.";
    }
    $plate_check->close();

    // Helper function for file uploads
    function uploadDoc($fileKey, $prefix, $admin_id) {
        global $errors;
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
            $file_size = $_FILES[$fileKey]['size'];
            $file_tmp  = $_FILES[$fileKey]['tmp_name'];
            $file_name = $_FILES[$fileKey]['name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if ($file_size > MAX_FILE_SIZE) {
                $errors[] = "File size must not exceed 5MB for " . $prefix;
                return null;
            }

            $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            if (!in_array($file_ext, $allowed_types)) {
                $errors[] = "Only JPG, JPEG, PNG, WebP, and PDF files are allowed for " . $prefix;
                return null;
            }

            if (!is_dir(UPLOAD_VEHICLES_DIR)) {
                mkdir(UPLOAD_VEHICLES_DIR, 0755, true);
            }

            $new_filename = $prefix . '_admin_' . $admin_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $upload_path  = UPLOAD_VEHICLES_DIR . $new_filename;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                return 'uploads/vehicles/' . $new_filename;
            } else {
                $errors[] = "Failed to upload " . $prefix;
            }
        }
        return null;
    }

    $image_url = uploadDoc('vehicle_image', 'vehicle', $admin_id);
    $registration_url = uploadDoc('registration_doc', 'reg', $admin_id);
    $insurance_url = uploadDoc('insurance_doc', 'ins', $admin_id);

    if (!$image_url) $errors[] = "Vehicle image is required";
    if (!$registration_url) $errors[] = "Registration document is required";
    if (!$insurance_url) $errors[] = "Insurance document is required";
    if (empty($description)) $errors[] = "Description is required";
    
    if (empty($errors)) {
        // Admin adds vehicle directly as 'Available'
        $sql = "INSERT INTO vehicles 
                (seller_id, brand, model, year, vehicle_type, license_plate, rental_price_per_day, 
                 fuel_type, transmission, seats, description, image_url, registration_doc_url, insurance_doc_url, status, state, area, pincode, pickup_location, available_from, available_until) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', ?, ?, ?, ?, ?, ?)";

        $params = [
            $admin_id, $brand, $model, $year, $vehicle_type, $license_plate,
            $rental_price, $fuel_type, $transmission, $seats, $description, $image_url, $registration_url, $insurance_url,
            $state, $area, $pincode, $pickup_location, $available_from, $available_until
        ];

        if ($conn->execute_query($sql, $params)) {
            showAlert("Vehicle added successfully and is now LIVE!", "success");
            header("Location: vehicles.php");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
    }
    
    if (!empty($errors)) {
        $error_msg = implode("<br>", $errors);
        showAlert($error_msg, "error");
    }
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vehicle | Ridezo Admin</title>
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

        .page-header { display: flex; align-items: center; margin-bottom: 32px; gap: 16px; }
        .btn-back-page { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 14px; background: var(--surface-light); color: var(--text-muted); border: 1px solid var(--border); text-decoration: none; transition: var(--transition); flex-shrink: 0; }
        .btn-back-page:hover { background: var(--accent); color: var(--primary); border-color: var(--accent); }

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

        /* Forms Styling */
        .form-container {
            background: var(--surface);
            border-radius: 28px;
            padding: 40px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%;
            padding: 14px 18px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            color: var(--text);
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            outline: none;
            transition: var(--transition);
        }

        input[type="text"]:-webkit-autofill,
        input[type="number"]:-webkit-autofill,
        input[type="date"]:-webkit-autofill,
        select:-webkit-autofill,
        textarea:-webkit-autofill,
        input[type="text"]:-webkit-autofill:hover, 
        input[type="number"]:-webkit-autofill:hover,
        input[type="date"]:-webkit-autofill:hover,
        select:-webkit-autofill:hover,
        textarea:-webkit-autofill:hover,
        input[type="text"]:-webkit-autofill:focus, 
        input[type="number"]:-webkit-autofill:focus,
        input[type="date"]:-webkit-autofill:focus,
        select:-webkit-autofill:focus,
        textarea:-webkit-autofill:focus,
        input[type="text"]:-webkit-autofill:active,
        input[type="number"]:-webkit-autofill:active,
        input[type="date"]:-webkit-autofill:active,
        select:-webkit-autofill:active,
        textarea:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px var(--input-bg) inset !important;
            -webkit-text-fill-color: var(--text) !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        input[type="file"] {
            width: 100%;
            padding: 12px 16px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 14px;
            color: var(--text-muted);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: var(--transition);
            cursor: pointer;
        }

        input[type="file"]:hover {
            border-color: var(--accent);
            color: var(--text);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
            font-weight: 700;
            padding: 16px 32px;
            border-radius: 14px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--text-muted);
            transform: translateY(-2px);
        }

        input[type="text"]:focus, input[type="number"]:focus, input[type="date"]:focus, select:focus, textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 15px var(--accent-glow);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .form-row.full { grid-template-columns: 1fr; }

        .image-upload-box {
            border: 2px dashed var(--border);
            border-radius: 18px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: var(--input-bg);
        }

        .image-upload-box:hover,
        .image-upload-box.active {
            border-color: var(--accent);
            background: var(--accent-soft);
        }

        .image-upload-box i {
            font-size: 2.5rem;
            color: var(--accent);
            margin-bottom: 12px;
            filter: drop-shadow(0 0 8px var(--accent-glow));
        }

        .image-preview {
            margin-top: 20px;
            position: relative;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .remove-image {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .remove-image:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        #vehicle_image { display: none; }

        .file-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 8px;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--primary);
            font-weight: 800;
            padding: 16px 32px;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--accent-glow);
        }

        .alert {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 16px 24px;
            border-radius: 16px;
            margin-bottom: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: var(--accent-soft);
            color: var(--accent);
            border: 1px solid var(--accent-glow);
        }

        @media (max-width: 1024px) {
            .sidebar { left: -280px; }
            .sidebar.open { left: 0; }
            .main-wrapper { margin-left: 0; width: 100%; padding: 24px; }
            .mobile-header { display: flex !important; }
        }

        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
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
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-chart-pie"></i>
                <span>System Dashboard</span>
            </a>
            <a href="vehicles.php" class="nav-item">
                <i class="fas fa-car"></i>
                <span>All Vehicles</span>
            </a>
            <a href="add-vehicle.php" class="nav-item active">
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
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sign Out</span>
            </a>
        </div>
    </div>

    <div class="mobile-header">
        <div onclick="document.getElementById('sidebar').classList.toggle('open')" style="font-size: 1.5rem; cursor: pointer;"><i class="fas fa-bars"></i></div>
        <div class="logo-text" style="color: #fff">Ridezo</div>
        <div></div>
    </div>

    <main class="main-wrapper">
        <div class="page-header">
            <a href="javascript:history.back()" class="btn-back-page" title="Go Back"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 style="font-size: 2rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 8px;">Add New Vehicle</h1>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Manually add a vehicle to the platform</p>
            </div>
        </div>

        <?php if (isset($alert) && $alert): ?>
            <div class="alert alert-<?php echo $alert['type']; ?>">
                <i class="fas fa-<?php echo $alert['type'] == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <span><?php echo $alert['message']; ?></span>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <!-- Images & Docs -->
                <div style="margin-bottom: 32px;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">
                        <i class="fas fa-camera"></i> Images & Documents
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Vehicle Image (Primary) <span style="color: var(--danger-color);">*</span></label>
                            <div class="image-upload-box" id="uploadBox">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 4px;">Click or drag image here</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">Max size: 5MB (JPG, PNG, WebP)</div>
                            </div>
                            <input type="file" id="vehicle_image" name="vehicle_image" accept="image/*" required>
                            <div class="image-preview" id="imagePreview" style="display: none;">
                                <img id="previewImg" src="" alt="Preview">
                                <button type="button" class="remove-image" onclick="removeImage()"><i class="fas fa-times"></i></button>
                                <div class="file-info" id="fileInfo"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div style="margin-bottom: 24px;">
                                <label for="registration_doc">Registration Doc (RC) <span style="color: var(--danger-color);">*</span></label>
                                <input type="file" id="registration_doc" name="registration_doc" accept="image/*,.pdf" required>
                            </div>
                            <div>
                                <label for="insurance_doc">Insurance Policy <span style="color: var(--danger-color);">*</span></label>
                                <input type="file" id="insurance_doc" name="insurance_doc" accept="image/*,.pdf" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Basic Info -->
                <div style="margin-bottom: 32px;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="brand">Brand <span style="color: var(--danger-color);">*</span></label>
                            <input type="text" id="brand" name="brand" required placeholder="e.g. Toyota">
                        </div>
                        <div class="form-group">
                            <label for="model">Model <span style="color: var(--danger-color);">*</span></label>
                            <input type="text" id="model" name="model" required placeholder="e.g. Fortuner">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="year">Manufacturing Year <span style="color: var(--danger-color);">*</span></label>
                            <input type="number" id="year" name="year" required min="1900" max="<?php echo date('Y') + 1; ?>" value="<?php echo date('Y'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Type <span style="color: var(--danger-color);">*</span></label>
                            <select id="vehicle_type" name="vehicle_type" required>
                                <option value="">Select Type</option>
                                <option value="Car">Car</option>
                                <option value="Bike">Bike</option>
                                <option value="Scooty">Scooty</option>
                                <option value="Van">Van</option>
                                <option value="Electric">Electric</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="license_plate">License Plate <span style="color: var(--danger-color);">*</span></label>
                            <input type="text" id="license_plate" name="license_plate" required placeholder="e.g. MH12AB1234" pattern="^[A-Za-z]{2}[0-9]{1,2}[A-Za-z]{1,3}[0-9]{1,4}$" title="Enter valid Indian Vehicle License Plate (e.g. MH12AB1234)" style="text-transform:uppercase;" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');">
                        </div>
                        <div class="form-group">
                            <label for="seats">Seating Capacity <span style="color: var(--danger-color);">*</span></label>
                            <input type="number" id="seats" name="seats" required min="1" max="60" value="5">
                        </div>
                    </div>
                </div>

                <!-- Location Details -->
                <div style="margin-bottom: 32px;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">
                        <i class="fas fa-map-marker-alt"></i> Location Details
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="state">State <span style="color: var(--danger-color);">*</span></label>
                            <select id="state" name="state" required onchange="handleStateInput()">
                                <option value="">Select State</option>
                                    <option value="Andhra Pradesh">Andhra Pradesh</option>
                                    <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                    <option value="Assam">Assam</option>
                                    <option value="Bihar">Bihar</option>
                                    <option value="Chhattisgarh">Chhattisgarh</option>
                                    <option value="Goa">Goa</option>
                                    <option value="Gujarat">Gujarat</option>
                                    <option value="Haryana">Haryana</option>
                                    <option value="Himachal Pradesh">Himachal Pradesh</option>
                                    <option value="Jharkhand">Jharkhand</option>
                                    <option value="Karnataka">Karnataka</option>
                                    <option value="Kerala">Kerala</option>
                                    <option value="Madhya Pradesh">Madhya Pradesh</option>
                                    <option value="Maharashtra">Maharashtra</option>
                                    <option value="Manipur">Manipur</option>
                                    <option value="Meghalaya">Meghalaya</option>
                                    <option value="Mizoram">Mizoram</option>
                                    <option value="Andhra Pradesh">Andhra Pradesh</option>
                                    <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                    <option value="Assam">Assam</option>
                                    <option value="Bihar">Bihar</option>
                                    <option value="Chhattisgarh">Chhattisgarh</option>
                                    <option value="Goa">Goa</option>
                                    <option value="Gujarat">Gujarat</option>
                                    <option value="Haryana">Haryana</option>
                                    <option value="Himachal Pradesh">Himachal Pradesh</option>
                                    <option value="Jharkhand">Jharkhand</option>
                                    <option value="Karnataka">Karnataka</option>
                                    <option value="Kerala">Kerala</option>
                                    <option value="Madhya Pradesh">Madhya Pradesh</option>
                                    <option value="Maharashtra">Maharashtra</option>
                                    <option value="Manipur">Manipur</option>
                                    <option value="Meghalaya">Meghalaya</option>
                                    <option value="Mizoram">Mizoram</option>
                                    <option value="Nagaland">Nagaland</option>
                                    <option value="Odisha">Odisha</option>
                                    <option value="Punjab">Punjab</option>
                                    <option value="Rajasthan">Rajasthan</option>
                                    <option value="Sikkim">Sikkim</option>
                                    <option value="Tamil Nadu">Tamil Nadu</option>
                                    <option value="Telangana">Telangana</option>
                                    <option value="Tripura">Tripura</option>
                                    <option value="Uttar Pradesh">Uttar Pradesh</option>
                                    <option value="Uttarakhand">Uttarakhand</option>
                                    <option value="West Bengal">West Bengal</option>
                                    <option value="Delhi">Delhi</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="area">Area / City <span style="color: var(--danger-color);">*</span></label>
                                <input type="text" id="area" name="area" required placeholder="e.g. Andheri, Mumbai" oninput="handleAreaInput()">
                                <div id="area-highlight" style="margin-top: 8px; font-size: 0.8rem; font-weight: 700;"></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="pincode">Pincode <span style="color: var(--danger-color);">*</span></label>
                                <input type="text" id="pincode" name="pincode" required placeholder="400001" pattern="[0-9]{6}" title="Please enter 6-digit pincode">
                            </div>
                            <div class="form-group">
                                <label for="pickup_location">Specific Pickup Spot <span style="color: var(--danger-color);">*</span></label>
                                <input type="text" id="pickup_location" name="pickup_location" required placeholder="e.g. Terminal 1, International Airport, Mumbai" oninput="handlePickupInput()">
                                <div id="pickup-location-highlight" style="margin-top: 8px; font-size: 0.8rem; font-weight: 700;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Availability range -->
                    <div style="margin-top: 32px;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">
                            <i class="fas fa-calendar-check"></i> Availability Range
                        </h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="available_from">Available From <span style="color: var(--danger-color);">*</span></label>
                                <input type="date" id="available_from" name="available_from" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="available_until">Available Until <span style="color: var(--danger-color);">*</span></label>
                                <input type="date" id="available_until" name="available_until" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Specifications -->
                    <div style="margin-top: 32px;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">
                            <i class="fas fa-cog"></i> Specifications
                        </h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="fuel_type">Fuel Type <span style="color: var(--danger-color);">*</span></label>
                                <select id="fuel_type" name="fuel_type" required>
                                    <option value="">Select Fuel Type</option>
                                    <option value="Petrol">Petrol</option>
                                    <option value="Diesel">Diesel</option>
                                    <option value="Hybrid">Hybrid</option>
                                    <option value="Electric">Electric</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="transmission">Transmission <span style="color: var(--danger-color);">*</span></label>
                                <select id="transmission" name="transmission" required>
                                    <option value="">Select Transmission</option>
                                    <option value="Manual">Manual</option>
                                    <option value="Automatic">Automatic</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label for="rental_price_per_day">Rental Price per Day (₹) <span style="color: var(--danger-color);">*</span></label>
                                <input type="number" id="rental_price_per_day" name="rental_price_per_day" required placeholder="1500" min="1" step="0.01">
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div style="margin-top: 32px;">
                        <div class="form-group">
                            <label for="description">Description <span style="color: var(--danger-color);">*</span></label>
                            <textarea id="description" name="description" placeholder="Describe your vehicle..." required></textarea>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div style="display: flex; gap: 12px; margin-top: 32px;">
                        <button type="button" class="btn-secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus"></i> Add Vehicle Directly
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const uploadBox   = document.getElementById('uploadBox');
        const fileInput   = document.getElementById('vehicle_image');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg  = document.getElementById('previewImg');
        const fileInfo    = document.getElementById('fileInfo');

        uploadBox.addEventListener('click', () => fileInput.click());

        uploadBox.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadBox.classList.add('active');
        });

        uploadBox.addEventListener('dragleave', () => uploadBox.classList.remove('active'));

        uploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadBox.classList.remove('active');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                    uploadBox.style.display = 'none';
                    fileInfo.innerHTML = `<strong>${file.name}</strong> (${fileSize}MB)`;
                };
                reader.readAsDataURL(file);
            }
        }

        function removeImage() {
            fileInput.value = '';
            imagePreview.style.display = 'none';
            uploadBox.style.display = 'block';
            fileInfo.innerHTML = '';
        }

        function validateForm() {
            if (!fileInput.files[0]) {
                alert('Please select a vehicle image');
                return false;
            }
            return true;
        }

        // Cities and States detection for Real-time Highlight
        const CITIES_AND_STATES = [
            // States
            "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", 
            "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", 
            "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", 
            "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", 
            "Uttarakhand", "West Bengal", "Delhi",
            // Major Cities
            "Mumbai", "New Delhi", "Bangalore", "Bengaluru", "Chennai", "Kolkata", "Hyderabad", 
            "Pune", "Ahmedabad", "Jaipur", "Surat", "Lucknow", "Kanpur", "Nagpur", "Indore", 
            "Thane", "Bhopal", "Visakhapatnam", "Pimpri-Chinchwad", "Patna", "Vadodara", 
            "Ghaziabad", "Ludhiana", "Agra", "Nashik", "Faridabad", "Meerut", "Rajkot", 
            "Kalyan-Dombivli", "Vasai-Virar", "Varanasi", "Srinagar", "Aurangabad", "Dhanbad", 
            "Amritsar", "Navi Mumbai", "Allahabad", "Ranchi", "Howrah", "Coimbatore", 
            "Jabalpur", "Gwalior", "Vijayawada", "Jodhpur", "Madurai", "Raipur", "Kota", 
            "Guwahati", "Chandigarh", "Solapur", "Hubli-Dharwad", "Bareilly", "Moradabad", 
            "Mysore", "Gurgaon", "Aligarh", "Jalandhar", "Tiruchirappalli", "Bhubaneswar", 
            "Salem", "Mira-Bhayandar", "Warangal", "Guntur", "Bhiwandi", "Saharanpur"
        ];

        function scanAndHighlightLocation(inputElement, highlightContainer) {
            const text = inputElement.value;
            if (!text.trim()) {
                highlightContainer.innerHTML = '';
                return;
            }
            
            let detected = [];
            const lowerText = text.toLowerCase();
            
            CITIES_AND_STATES.forEach(loc => {
                const lowerLoc = loc.toLowerCase();
                if (lowerText.includes(lowerLoc)) {
                    detected.push(loc);
                }
            });
            
            if (detected.length > 0) {
                highlightContainer.innerHTML = `<span style="color: #22c55e; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); padding: 6px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(34, 197, 94, 0.1);"><i class="fas fa-circle-check"></i> Linked to Location: ${detected.join(', ')}</span>`;
            } else {
                highlightContainer.innerHTML = `<span style="color: #ea580c; background: rgba(234, 88, 12, 0.1); border: 1px solid rgba(234, 88, 12, 0.2); padding: 6px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;"><i class="fas fa-circle-exclamation"></i> Tip: Include a city or state name (e.g. Mumbai, Delhi) so GPS maps it perfectly!</span>`;
            }
        }

        function handleAreaInput() {
            scanAndHighlightLocation(document.getElementById('area'), document.getElementById('area-highlight'));
        }

        function handlePickupInput() {
            scanAndHighlightLocation(document.getElementById('pickup_location'), document.getElementById('pickup-location-highlight'));
        }
    </script>
</body>
</html>
<?php
exit();
?>
