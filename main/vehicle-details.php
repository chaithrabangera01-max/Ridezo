<?php
require '../app/config.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_role = $is_logged_in ? $_SESSION['role'] : null;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$vehicle_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT v.*, u.name as seller_name, u.phone as seller_phone 
    FROM vehicles v 
    JOIN users u ON v.seller_id = u.user_id 
    WHERE v.vehicle_id = ? AND v.status = 'Available'
");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    showAlert("Vehicle not found or currently unavailable.", "error");
    header("Location: index.php");
    exit();
}

$vehicle = $result->fetch_assoc();
$stmt->close();

$image_path = (strpos($vehicle['image_url'], 'http') === 0) ? $vehicle['image_url'] : '../app/' . $vehicle['image_url'];
if (!file_exists($image_path)) {
    $image_path = '../suzuki_baleno.png';
}

// Check if saved
$is_saved = false;
if ($is_logged_in && $user_role === 'Customer') {
    $check_saved = $conn->prepare("SELECT id FROM saved_vehicles WHERE customer_id = ? AND vehicle_id = ?");
    $check_saved->bind_param("ii", $_SESSION['user_id'], $vehicle_id);
    $check_saved->execute();
    if ($check_saved->get_result()->num_rows > 0) {
        $is_saved = true;
    }
    $check_saved->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $vehicle['brand'] . ' ' . $vehicle['model']; ?> | Ridezo</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8fafc; }
        .product-container {
            max-width: 1200px;
            margin: 100px auto 40px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        .product-gallery {
            background: white;
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-gray);
        }
        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 16px;
        }
        .product-info {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-gray);
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        .brand-badge {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .vehicle-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0 0 10px 0;
            line-height: 1.2;
        }
        .price-display {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: baseline;
            gap: 5px;
            margin: 20px 0;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-gray);
        }
        .price-display span {
            font-size: 1rem;
            color: var(--text-light);
            font-weight: 500;
        }
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        .btn-giant {
            padding: 16px;
            font-size: 1.1rem;
            text-align: center;
            justify-content: center;
            width: 100%;
        }
        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 30px 0;
        }
        .spec-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid var(--border-gray);
        }
        .spec-item i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        .spec-content { display: flex; flex-direction: column; }
        .spec-label { font-size: 0.8rem; color: var(--text-light); text-transform: uppercase; font-weight: 700; }
        .spec-value { font-size: 1rem; font-weight: 700; color: var(--text-dark); }
        
        .seller-card {
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid #e2e8f0;
        }
        .seller-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .description-box {
            margin-top: 30px;
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-gray);
        }
        
        @media (max-width: 992px) {
            .product-container { grid-template-columns: 1fr; }
            .product-info { position: static; }
        }
    
        /* Global Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main, var(--bg, #020617)); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color, var(--accent, #22c55e)); }

</style>
</head>
<body>

    <nav class="navbar glass-nav">
        <div class="container nav-container">
            <div class="logo">
                <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 8px; color: var(--primary-color);">
                    <img src="../assets/ridezo_logo.png" alt="Ridezo Logo" class="logo-img" style="height: 30px;">
                    <span>Ridezo</span>
                </a>
            </div>
            <div class="nav-actions">
                <button type="button" class="btn btn-secondary" onclick="history.back()"><i class="fas fa-arrow-left"></i> Back</button>
            </div>
        </div>
    </nav>

    <div class="product-container reveal">
        <!-- Left Side: Images & Details -->
        <div>
            <div class="product-gallery float-anim">
                <img src="<?php echo $image_path; ?>" alt="<?php echo $vehicle['brand'] . ' ' . $vehicle['model']; ?>" class="main-image">
            </div>

            <div class="description-box">
                <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 15px;"><i class="fas fa-info-circle"></i> Vehicle Description</h3>
                <p style="color: var(--text-light); line-height: 1.8; font-size: 1.05rem;">
                    <?php echo nl2br(htmlspecialchars($vehicle['description'] ?? 'No additional description provided by the seller.')); ?>
                </p>
                
                <h3 style="font-size: 1.25rem; font-weight: 800; margin-top: 30px; margin-bottom: 15px;"><i class="fas fa-map-marker-alt"></i> Location Information</h3>
                <p style="color: var(--text-light); font-size: 1.05rem;"><strong>Area:</strong> <?php echo htmlspecialchars($vehicle['area']); ?></p>
                <p style="color: var(--text-light); font-size: 1.05rem;"><strong>State:</strong> <?php echo htmlspecialchars($vehicle['state']); ?></p>
                <p style="color: var(--text-light); font-size: 1.05rem;"><strong>Pincode:</strong> <?php echo htmlspecialchars($vehicle['pincode']); ?></p>
            </div>
        </div>

        <!-- Right Side: Sticky Checkout / Action Card -->
        <div class="product-info">
            <div class="brand-badge"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></div>
            <h1 class="vehicle-title"><?php echo htmlspecialchars($vehicle['brand']) . ' ' . htmlspecialchars($vehicle['model']); ?></h1>
            <p style="color: var(--text-light); font-size: 1.1rem;"><i class="far fa-calendar-alt"></i> Model Year: <?php echo $vehicle['year']; ?></p>
            
            <div class="price-display">
                ₹<?php echo number_format($vehicle['rental_price_per_day'], 0); ?> <span>/ day</span>
            </div>

            <div class="specs-grid">
                <div class="spec-item">
                    <i class="fas fa-users"></i>
                    <div class="spec-content">
                        <span class="spec-label">Capacity</span>
                        <span class="spec-value"><?php echo $vehicle['seats']; ?> Seats</span>
                    </div>
                </div>
                <div class="spec-item">
                    <i class="fas fa-gas-pump"></i>
                    <div class="spec-content">
                        <span class="spec-label">Fuel</span>
                        <span class="spec-value"><?php echo $vehicle['fuel_type']; ?></span>
                    </div>
                </div>
                <div class="spec-item">
                    <i class="fas fa-cog"></i>
                    <div class="spec-content">
                        <span class="spec-label">Transmission</span>
                        <span class="spec-value"><?php echo $vehicle['transmission']; ?></span>
                    </div>
                </div>
                <div class="spec-item">
                    <i class="fas fa-road"></i>
                    <div class="spec-content">
                        <span class="spec-label">Status</span>
                        <span class="spec-value" style="color: #10b981;">Available</span>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <?php if ($is_logged_in): ?>
                    <a href="../booking/booking.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn-primary btn-giant pulse-anim">
                        <i class="fas fa-bolt"></i> Book Now
                    </a>
                    
                    <?php if ($user_role === 'Customer'): ?>
                        <?php if ($is_saved): ?>
                            <a href="../app/customer/remove-saved.php?id=<?php echo $vehicle['vehicle_id']; ?>&vehicle=1" class="btn-secondary btn-giant" style="color: var(--primary-color); border-color: var(--primary-color); background: var(--primary-light);">
                                <i class="fas fa-bookmark"></i> Saved
                            </a>
                        <?php else: ?>
                            <a href="../app/customer/save-vehicle.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn-secondary btn-giant">
                                <i class="far fa-bookmark"></i> Save
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <a href="../app/login.php?msg=unauthorized" class="btn-primary btn-giant" style="grid-column: 1/-1;">
                        <i class="fas fa-sign-in-alt"></i> Login to Book
                    </a>
                <?php endif; ?>
            </div>

            <div class="seller-card">
                <div class="seller-avatar">
                    <?php echo substr($vehicle['seller_name'], 0, 1); ?>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-dark);">Verified Host</h4>
                    <p style="margin: 5px 0 0; color: var(--text-light); font-size: 0.9rem;">
                        <i class="fas fa-user-check" style="color: #10b981;"></i> <?php echo htmlspecialchars($vehicle['seller_name']); ?>
                    </p>
                </div>
            </div>
            
            <div style="margin-top: 20px; font-size: 0.85rem; color: var(--text-muted); text-align: center;">
                <i class="fas fa-shield-alt"></i> Secure checkout powered by Ridezo
            </div>
        </div>
    </div>

</body>
</html>
