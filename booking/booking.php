<?php
session_start();
require '../app/config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../main/index.php");
    exit();
}

$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;

$dashboard_link = "../app/login.php?msg=unauthorized";
if ($is_logged_in) {
    if ($user_role === 'Admin') $dashboard_link = "../admin/dashboard.php";
    elseif ($user_role === 'Seller') $dashboard_link = "../app/seller/dashboard.php";
    else $dashboard_link = "../app/customer/dashboard.php";
}

$bookings_link = $is_logged_in ? ($user_role === 'Customer' ? '../app/customer/bookings.php' : ($user_role === 'Seller' ? '../app/seller/bookings.php' : '../admin/bookings.php')) : '../app/login.php?msg=unauthorized';

$vehicle_id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT v.*, u.name as seller_name FROM vehicles v JOIN users u ON v.seller_id = u.user_id WHERE v.vehicle_id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../main/index.php");
    exit();
}
$vehicle = $result->fetch_assoc();
$stmt->close();

$image_path = strpos($vehicle['image_url'], 'http') === 0 ? $vehicle['image_url'] : '../app/' . $vehicle['image_url'];

$image_path = strpos($vehicle['image_url'], 'http') === 0 ? $vehicle['image_url'] : '../app/' . $vehicle['image_url'];

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
    <title>Book <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?> | Ridezo</title>
    <link rel="stylesheet" href="../main/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Background Animations */
        .bg-glow-1 { position: fixed; top: -100px; left: -100px; width: 500px; height: 500px; background: radial-gradient(circle, rgba(34,197,94,0.1) 0%, transparent 70%); filter: blur(50px); z-index: -1; pointer-events: none; animation: floatGlow 10s ease-in-out infinite alternate; }
        .bg-glow-2 { position: fixed; bottom: -100px; right: -100px; width: 600px; height: 600px; background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 70%); filter: blur(50px); z-index: -1; pointer-events: none; animation: floatGlow 12s ease-in-out infinite alternate-reverse; }
        
        @keyframes floatGlow { 0% { transform: translateY(0) scale(1); } 100% { transform: translateY(-30px) scale(1.1); } }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleInGlow { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes shineFlow { 0% { background-position: -200% center; } 100% { background-position: 200% center; } }
        
        .anim-1 { animation: slideUpFade 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both; }
        .anim-2 { animation: slideUpFade 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both; }
        .anim-3 { animation: slideUpFade 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.3s both; }
        
        .product-gallery {
            background: linear-gradient(145deg, rgba(15,23,42,0.6), rgba(2,6,23,0.8));
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.05);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 300px;
        }
        .main-image {
            max-width: 100%; 
            height: auto; 
            max-height: 500px; 
            object-fit: contain; 
            border-radius: 12px;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: transform;
        }
        .product-gallery:hover .main-image { transform: scale(1.02); }
        
        .product-info {
            background: linear-gradient(145deg, rgba(15,23,42,0.95), rgba(2,6,23,0.98));
            backdrop-filter: blur(20px);
            border-radius: 24px; padding: 30px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.5), inset 0 0 20px rgba(34,197,94,0.05);
            border: 1px solid rgba(34,197,94,0.2);
            position: sticky; top: 100px; height: fit-content;
            animation: scaleInGlow 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.4s both;
            overflow: hidden;
        }
        .product-info::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent); opacity: 0.8;
        }
        
        .vehicle-title { font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0 0 10px 0; line-height: 1.2; letter-spacing: -0.5px; }
        .price-display { font-size: 2.5rem; font-weight: 800; color: var(--accent); display: flex; align-items: baseline; gap: 5px; margin: 10px 0 20px 0; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-shadow: 0 0 20px rgba(34,197,94,0.3); }
        .price-display span { font-size: 1rem; color: var(--text-muted); font-weight: 600; text-shadow: none; }
        
        .description-box {
            margin-top: 30px;
            background: linear-gradient(145deg, rgba(15,23,42,0.8), rgba(2,6,23,0.9));
            backdrop-filter: blur(10px);
            border-radius: 24px; padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .product-container {
            max-width: 1200px;
            margin: 140px auto 40px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        

        
        .specs-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 30px 0; }
        .spec-item {
            display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
            padding: 18px 10px; background: rgba(2,6,23,0.5); border-radius: 16px; border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); position: relative; overflow: hidden;
        }
        .spec-item::before { content:''; position:absolute; inset:0; background:radial-gradient(circle, rgba(34,197,94,0.1) 0%, transparent 70%); opacity:0; transition:opacity 0.4s; }
        .spec-item:hover { transform: translateY(-5px); border-color: rgba(34,197,94,0.3); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .spec-item:hover::before { opacity:1; }
        .spec-item i { font-size: 1.6rem; color: var(--accent); margin-bottom: 10px; transition: transform 0.4s; }
        .spec-item:hover i { transform: scale(1.2); filter: drop-shadow(0 0 8px rgba(34,197,94,0.5)); }
        .spec-value { font-size: 0.85rem; font-weight: 700; color: #fff; z-index: 1; }
        
        .form-control {
            width: 100%; padding: 14px 16px; border-radius: 14px; border: 1.5px solid rgba(255,255,255,0.08);
            font-family: inherit; font-size: 1rem; font-weight: 600; background: rgba(2,6,23,0.6); color: #fff; outline: none; transition: all 0.3s;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(34,197,94,0.1); background: rgba(15,23,42,0.9); }
        .form-control[type="date"]::-webkit-calendar-picker-indicator { filter: invert(61%) sepia(85%) saturate(382%) hue-rotate(89deg) brightness(94%) contrast(94%); cursor: pointer; }
        
        .btn-giant {
            padding: 16px; font-size: 1.1rem; text-align: center; justify-content: center; width: 100%; margin-top: 15px;
            background: linear-gradient(90deg, var(--accent) 0%, #10b981 50%, var(--accent) 100%); background-size: 200% auto;
            color: #020617; border: none; border-radius: 16px; font-weight: 800; cursor: pointer;
            transition: all 0.4s; box-shadow: 0 8px 25px rgba(34,197,94,0.3); animation: shineFlow 3s linear infinite; display: flex; align-items: center; gap: 8px;
        }
        .btn-giant:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(34,197,94,0.5); letter-spacing: 0.5px; }
        
        .features-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .feature-item { display: flex; align-items: center; gap: 10px; color: #fff; font-weight: 600; padding: 12px 16px; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid rgba(255,255,255,0.03); transition: all 0.3s; }
        .feature-item:hover { background: rgba(34,197,94,0.05); border-color: rgba(34,197,94,0.2); transform: translateX(5px); }
        .feature-item i { color: var(--accent); font-size: 1.1rem; filter: drop-shadow(0 0 5px rgba(34,197,94,0.4)); }
        
        .error-message { display: none; background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        @media (max-width: 992px) {
            .product-container { grid-template-columns: 1fr; }
            .product-info { position: static; }
            .specs-grid { grid-template-columns: repeat(2, 1fr); }
        }
    
        /* Global Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main, var(--bg, #020617)); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color, var(--accent, #22c55e)); }

</style>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <nav class="top-nav">
        <div class="nav-container" style="max-width: 1400px; margin: 0 auto; width: 100%; padding: 0 40px; display: flex; justify-content: space-between; align-items: center;">
            <div class="nav-left">
                <a href="../main/index.php" class="logo" style="text-decoration: none; display: flex; align-items: center; gap: 12px; color: #fff;">
                    <img src="../assets/ridezo_logo.png" alt="Ridezo Logo" class="logo-img" style="height: 34px; filter: drop-shadow(0 0 10px var(--accent-glow));">
                    <span style="font-size: 1.6rem; font-weight: 800; letter-spacing: -1.2px;">Ridezo</span>
                </a>
            </div>
            <div class="nav-right" style="display: flex; align-items: center; gap: 32px;">
                <a href="../main/explore.php" class="nav-link" style="text-decoration: none; font-weight: 600; font-size: 0.95rem; color: var(--text-muted); transition: all 0.4s;">Explore Vehicles</a>
                <a href="<?php echo $bookings_link; ?>" class="nav-link" style="text-decoration: none; font-weight: 600; font-size: 0.95rem; color: var(--text-muted); transition: all 0.4s;">My Bookings</a>
                <a href="<?php echo $dashboard_link; ?>" class="nav-link" style="text-decoration: none; font-weight: 600; font-size: 0.95rem; color: var(--text-muted); transition: all 0.4s;">Dashboard</a>
                <a href="javascript:history.back()" class="nav-link" style="text-decoration: none; font-weight: 600; font-size: 0.95rem; color: var(--accent); transition: all 0.4s;"><i class="fas fa-arrow-left"></i> Back to Details</a>
                <?php if ($is_logged_in): ?>
                    <div style="display: flex; align-items: center; gap: 16px; margin-left: 10px; background: rgba(255,255,255,0.03); padding: 6px 12px; border-radius: 100px; border: 1px solid var(--border);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 8px; height: 8px; background: var(--accent); border-radius: 50%; box-shadow: 0 0 10px var(--accent-glow);"></div>
                            <span style="font-weight: 700; color: #fff; font-size: 0.85rem; letter-spacing: 0.5px;"><?php echo explode(' ', $user_name)[0]; ?></span>
                        </div>
                        <div style="width: 1px; height: 20px; background: var(--border);"></div>
                        <a href="../app/logout.php" class="nav-link" style="text-decoration: none; color: var(--accent); font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="../app/login.php" class="nav-link btn-login" style="text-decoration: none; padding: 10px 24px; background: var(--accent); color: var(--primary) !important; border-radius: 14px; font-weight: 800; transition: all 0.4s; box-shadow: 0 8px 20px rgba(34, 197, 94, 0.2);">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="product-container">
        <!-- Left Side: Images & Details -->
        <div>
            <div class="product-gallery anim-1">
                <img src="<?php echo $image_path; ?>" alt="<?php echo $vehicle['brand'] . ' ' . $vehicle['model']; ?>" class="main-image">
            </div>

            <div class="description-box anim-2">
                <h1 class="vehicle-title"><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></h1>
                <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 30px;"><i class="far fa-calendar-alt"></i> Model Year: <?php echo htmlspecialchars($vehicle['year']); ?> &bull; <?php echo htmlspecialchars($vehicle['vehicle_type']); ?></p>

                <div class="specs-grid">
                    <div class="spec-item">
                        <i class="fas fa-users"></i>
                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['seats'] ?? 4); ?> Seats</span>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-gas-pump"></i>
                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'Petrol'); ?></span>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-cog"></i>
                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['transmission'] ?? 'Automatic'); ?></span>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['area'] ?? 'City'); ?></span>
                    </div>
                </div>

                <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 15px;"><i class="fas fa-info-circle"></i> Description</h3>
                <p style="color: var(--text-muted); line-height: 1.8; font-size: 1.05rem; margin-bottom: 0;">
                    <?php echo nl2br(htmlspecialchars($vehicle['description'] ?? 'No description provided by the seller.')); ?>
                </p>


            </div>
        </div>

        <!-- Right Side: Sticky Checkout Card -->
        <div class="product-info">
            <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 5px;">Reserve Your Ride</h2>
            
            <div class="price-display">
                ₹<?php echo number_format($vehicle['rental_price_per_day'], 0); ?> <span>/ day</span>
            </div>

            <form id="bookingForm" action="pay.php?id=<?php echo $vehicle_id; ?>" method="POST">
                <div id="dateError" class="error-message">
                    <i class="fas fa-exclamation-circle"></i> Please select a valid return date that is after the pickup date.
                </div>
                
                <div class="form-group">
                    <label>Pickup Date</label>
                    <input type="date" id="pickup" name="pickup" min="<?php echo date('Y-m-d'); ?>" required class="form-control">
                </div>

                <div class="form-group">
                    <label>Return Date</label>
                    <input type="date" id="return_date" name="return" min="<?php echo date('Y-m-d'); ?>" required class="form-control">
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <button type="submit" class="btn-primary btn-giant pulse-anim">
                        <i class="fas fa-arrow-right"></i> Book Now
                    </button>
                    
                    <?php if ($user_role === 'Customer'): ?>
                        <?php if ($is_saved): ?>
                            <a href="../app/customer/remove-saved.php?id=<?php echo $vehicle['vehicle_id']; ?>&vehicle=1" class="btn-giant" style="display: flex; margin-top: 15px; background: rgba(34,197,94,0.1); border: 1px solid var(--accent); color: var(--accent); text-decoration: none; animation: none;">
                                <i class="fas fa-bookmark"></i> Saved to Favorites
                            </a>
                        <?php else: ?>
                            <a href="../app/customer/save-vehicle.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn-giant" style="display: flex; margin-top: 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; text-decoration: none; animation: none;">
                                <i class="far fa-bookmark"></i> Save Vehicle
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="../app/login.php?msg=unauthorized" class="btn-primary btn-giant" style="display: block; text-decoration: none;">
                        <i class="fas fa-sign-in-alt"></i> Login to Book
                    </a>
                <?php endif; ?>
            </form>
            
            <p style="text-align: center; font-size: 0.85rem; color: var(--text-muted); margin-top: 20px;">
                <i class="fas fa-shield-alt"></i> Secure checkout powered by Ridezo
            </p>
            
            <script>
                const pickupInput = document.getElementById('pickup');
                const returnInput = document.getElementById('return_date');
                const form = document.getElementById('bookingForm');
                const errorMsg = document.getElementById('dateError');

                pickupInput.addEventListener('change', function() {
                    returnInput.min = this.value;
                    if (returnInput.value && returnInput.value < this.value) {
                        returnInput.value = this.value;
                    }
                });

                form.addEventListener('submit', function(e) {
                    if (returnInput.value < pickupInput.value) {
                        e.preventDefault();
                        errorMsg.style.display = 'block';
                    } else {
                        errorMsg.style.display = 'none';
                    }
                });
                
                // Navbar scroll effect
                window.addEventListener('scroll', () => {
                    const nav = document.querySelector('.top-nav');
                    if (window.scrollY > 50) {
                        nav.classList.add('scrolled');
                    } else {
                        nav.classList.remove('scrolled');
                    }
                });
            </script>
        </div>
    </div>

</body>
</html>
