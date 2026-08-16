<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../app/config.php';

$alert = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $message = sanitize($_POST['message']);

    // Create table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS feedbacks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $conn->prepare("INSERT INTO feedbacks (name, email, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $message);
    if ($stmt->execute()) {
        $alert = ['message' => 'Thank you for your feedback!', 'type' => 'success'];
    } else {
        $alert = ['message' => 'Failed to submit feedback. Please try again.', 'type' => 'error'];
    }
    $stmt->close();
}
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
$user_photo = $_SESSION['profile_photo'] ?? $_SESSION['user_photo'] ?? null;

// Determine dashboard link
$dashboard_link = "../app/login.php?msg=unauthorized";
if ($is_logged_in) {
    if ($user_role === 'Admin') $dashboard_link = "../admin/dashboard.php";
    elseif ($user_role === 'Seller') $dashboard_link = "../app/seller/dashboard.php";
    else $dashboard_link = "../app/customer/dashboard.php";
}

$bookings_link = $is_logged_in ? ($user_role === 'Customer' ? '../app/customer/bookings.php' : ($user_role === 'Seller' ? '../app/seller/bookings.php' : '../admin/bookings.php')) : '../app/login.php?msg=unauthorized';

// Fetch Vehicles by Category
$featured_cars = $conn->query("SELECT v.*, u.name as seller_name FROM vehicles v JOIN users u ON v.seller_id = u.user_id WHERE v.status = 'Available' AND v.vehicle_id NOT IN (SELECT vehicle_id FROM bookings WHERE status IN ('Pending', 'Confirmed', 'Active')) AND (v.vehicle_type = 'Car' OR v.vehicle_type = 'Sedan' OR v.vehicle_type = 'Hatchback') ORDER BY v.created_at DESC LIMIT 4");
$jeeps = $conn->query("SELECT v.*, u.name as seller_name FROM vehicles v JOIN users u ON v.seller_id = u.user_id WHERE v.status = 'Available' AND v.vehicle_id NOT IN (SELECT vehicle_id FROM bookings WHERE status IN ('Pending', 'Confirmed', 'Active')) AND (v.vehicle_type = 'Jeep' OR v.vehicle_type = 'SUV') ORDER BY v.created_at DESC LIMIT 4");
$bikes = $conn->query("SELECT v.*, u.name as seller_name FROM vehicles v JOIN users u ON v.seller_id = u.user_id WHERE v.status = 'Available' AND v.vehicle_id NOT IN (SELECT vehicle_id FROM bookings WHERE status IN ('Pending', 'Confirmed', 'Active')) AND v.vehicle_type = 'Bike' ORDER BY v.created_at DESC LIMIT 4");

// Fetch Featured Vehicles (all Available, from both admin & sellers)
$featured_vehicles = $conn->query("SELECT v.*, u.name as seller_name FROM vehicles v JOIN users u ON v.seller_id = u.user_id WHERE v.status = 'Available' AND v.vehicle_id NOT IN (SELECT vehicle_id FROM bookings WHERE status IN ('Pending', 'Confirmed', 'Active')) ORDER BY v.created_at DESC LIMIT 10");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ridezo | Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- TOP NAVIGATION -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-left">
                <a href="index.php" class="logo">
                    <img src="../assets/ridezo_logo.png" alt="Ridezo Logo" class="logo-img">
                    <span>Ridezo</span>
                </a>
            </div>
            <div class="nav-right">
                <a href="explore.php" class="nav-link">Explore Vehicles</a>
                <a href="<?php echo $bookings_link; ?>" class="nav-link">My Bookings</a>
                <a href="<?php echo $dashboard_link; ?>" class="nav-link">Dashboard</a>
                <?php if ($is_logged_in): ?>
                    <div style="display: flex; align-items: center; gap: 16px; margin-left: 10px; background: rgba(255,255,255,0.03); padding: 6px 12px; border-radius: 100px; border: 1px solid var(--border);">
                        <a href="../app/profile.php" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
                            <div style="width: 8px; height: 8px; background: var(--accent); border-radius: 50%; box-shadow: 0 0 10px var(--accent-glow);"></div>
                            <span style="font-weight: 700; color: #fff; font-size: 0.85rem; letter-spacing: 0.5px;"><?php echo explode(' ', $user_name)[0]; ?></span>
                        </a>
                        <div style="width: 1px; height: 20px; background: var(--border);"></div>
                        <a href="../app/logout.php" class="nav-link" style="color: var(--accent); font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="../app/login.php" class="nav-link btn-login" style="padding: 10px 24px;">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-wrapper no-sidebar">
        <header style="padding: 160px 40px 120px; position: relative; overflow: hidden; border-bottom: 1px solid var(--border); background-image: linear-gradient(to bottom, rgba(2, 6, 23, 0.3) 0%, rgba(2, 6, 23, 0.7) 50%, rgba(2, 6, 23, 0.95) 100%), url('../assets/stering_img.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat;">
            <div style="position: relative; z-index: 10; max-width: 1000px; margin: 0 auto; text-align: center;">
                <?php if ($is_logged_in): ?>
                    <div style="display: inline-block; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); padding: 8px 24px; border-radius: 100px; margin-bottom: 48px; animation: slideInUp 0.6s ease-out;">
                        <span style="font-weight: 800; color: #fff; font-size: 0.95rem; letter-spacing: 0.5px;">Welcome back, <?php echo explode(' ', $user_name)[0]; ?>!</span>
                        <span style="margin: 0 12px; color: var(--text-muted);">|</span>
                        <span style="font-size: 0.85rem; color: var(--accent); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">You are a <?php echo $user_role; ?></span>
                    </div>
                <?php endif; ?>
                <h1 class="reveal reveal-up" style="font-size: 3rem; font-weight: 850; margin-top: 30px; margin-bottom: 12px; color: #fff; letter-spacing: -1.5px; text-shadow: 0 4px 15px rgba(0,0,0,0.7);">Find the right <span class="text-gradient-anim">wheels</span> for you.</h1>
                <p class="reveal reveal-up" style="opacity: 0.9; font-size: 1.1rem; margin-bottom: 44px; color: #fff; font-weight: 600; text-shadow: 0 2px 10px rgba(0,0,0,0.6); transition-delay: 0.2s;">Rent from verified owners in minutes.</p>
                
                <form action="explore.php" method="GET" class="search-form-premium reveal reveal-pop" style="max-width: 900px; margin: 0 auto; transition-delay: 0.3s;">
                    <div class="input-group-premium" style="flex: 2;">
                        <label>Location</label>
                        <div class="input-wrapper-premium">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" name="location" placeholder="Where to?" required>
                        </div>
                    </div>
                    <div class="input-group-premium">
                        <label>Pickup</label>
                        <div class="input-wrapper-premium">
                                                        <i class="fas fa-calendar-alt"></i>
                            <input type="date" name="pickup_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="input-group-premium">
                        <label>Return</label>
                        <div class="input-wrapper-premium">
                                                        <i class="fas fa-calendar-check"></i>
                            <input type="date" name="return_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="input-group-premium" style="justify-content: flex-end;">
                        <button type="submit" class="btn-search-premium hover-glow" style="height: 52px; width: 100%;">Search</button>
                    </div>
                </form>
            </div>
        </header>

        <!-- QUICK CATEGORY NAVIGATION SELECTOR -->
        <div style="background: rgba(15, 23, 42, 0.6); border-bottom: 1px solid rgba(34, 197, 94, 0.25); padding: 36px 40px; backdrop-filter: blur(10px); position: relative; z-index: 20;">
            <div style="max-width: 1000px; margin: 0 auto; display: flex; justify-content: center; align-items: center; gap: 24px 32px; flex-wrap: wrap;" class="reveal-stagger">
                
                <!-- Car Category -->
                <a href="explore.php?type=Car" title="Browse Cars" class="hover-lift" style="width: 72px; height: 72px; display: flex; align-items: center; justify-content: center; background: rgba(34, 197, 94, 0.08); border: 2px solid rgba(34, 197, 94, 0.35); border-radius: 50%; color: #22c55e; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 20px rgba(0,0,0,0.2);" 
                   onmouseover="this.style.background='rgba(34, 197, 94, 0.2)'; this.style.borderColor='#22c55e'; this.style.boxShadow='0 0 25px rgba(34, 197, 94, 0.5)'; this.style.transform='translateY(-6px)'" 
                   onmouseout="this.style.background='rgba(34, 197, 94, 0.08)'; this.style.borderColor='rgba(34, 197, 94, 0.35)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.2)'; this.style.transform='none'">
                    <i class="fas fa-car floating-icon" style="font-size: 1.8rem; color: #22c55e !important;"></i>
                </a>

                <!-- Bike Category -->
                <a href="explore.php?type=Bike" title="Browse Bikes" class="hover-lift" style="width: 72px; height: 72px; display: flex; align-items: center; justify-content: center; background: rgba(34, 197, 94, 0.08); border: 2px solid rgba(34, 197, 94, 0.35); border-radius: 50%; color: #22c55e; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 20px rgba(0,0,0,0.2);" 
                   onmouseover="this.style.background='rgba(34, 197, 94, 0.2)'; this.style.borderColor='#22c55e'; this.style.boxShadow='0 0 25px rgba(34, 197, 94, 0.5)'; this.style.transform='translateY(-6px)'" 
                   onmouseout="this.style.background='rgba(34, 197, 94, 0.08)'; this.style.borderColor='rgba(34, 197, 94, 0.35)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.2)'; this.style.transform='none'">
                    <i class="fas fa-motorcycle floating-icon" style="font-size: 1.8rem; color: #22c55e !important; animation-delay: 0.5s;"></i>
                </a>

                <!-- Scooty Category -->
                <a href="explore.php?type=Scooty" title="Browse Scooties" class="hover-lift" style="width: 72px; height: 72px; display: flex; align-items: center; justify-content: center; background: rgba(34, 197, 94, 0.08); border: 2px solid rgba(34, 197, 94, 0.35); border-radius: 50%; color: #22c55e; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 20px rgba(0,0,0,0.2);" 
                   onmouseover="this.style.background='rgba(34, 197, 94, 0.2)'; this.style.borderColor='#22c55e'; this.style.boxShadow='0 0 25px rgba(34, 197, 94, 0.5)'; this.style.transform='translateY(-6px)'" 
                   onmouseout="this.style.background='rgba(34, 197, 94, 0.08)'; this.style.borderColor='rgba(34, 197, 94, 0.35)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.2)'; this.style.transform='none'">
                    <i class="fas fa-motorcycle floating-icon" style="font-size: 1.8rem; color: #22c55e !important; transform: scaleX(-1); display: inline-block; animation-delay: 1s;"></i>
                </a>

                <!-- Jeep/SUV Category -->
                <a href="explore.php?type=Jeep" title="Browse Jeeps & SUVs" class="hover-lift" style="width: 72px; height: 72px; display: flex; align-items: center; justify-content: center; background: rgba(34, 197, 94, 0.08); border: 2px solid rgba(34, 197, 94, 0.35); border-radius: 50%; color: #22c55e; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 20px rgba(0,0,0,0.2);" 
                   onmouseover="this.style.background='rgba(34, 197, 94, 0.2)'; this.style.borderColor='#22c55e'; this.style.boxShadow='0 0 25px rgba(34, 197, 94, 0.5)'; this.style.transform='translateY(-6px)'" 
                   onmouseout="this.style.background='rgba(34, 197, 94, 0.08)'; this.style.borderColor='rgba(34, 197, 94, 0.35)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.2)'; this.style.transform='none'">
                    <i class="fas fa-truck-pickup floating-icon" style="font-size: 1.8rem; color: #22c55e !important; animation-delay: 1.5s;"></i>
                </a>
            </div>
        </div>

        <!-- FEATURED VEHICLES SECTION -->
        <?php if ($featured_vehicles && $featured_vehicles->num_rows > 0): ?>
        <div style="padding: 64px 40px 48px; background: var(--bg); position: relative;">
            <!-- Section Header -->
            <div style="max-width: 1400px; margin: 0 auto 36px; display: flex; justify-content: space-between; align-items: flex-end;" class="reveal reveal-left">
                <div>
                    <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2); padding: 6px 16px; border-radius: 100px; margin-bottom: 14px;">
                        <span style="width: 7px; height: 7px; background: var(--accent); border-radius: 50%; box-shadow: 0 0 8px var(--accent-glow); display: inline-block;"></span>
                        <span style="font-size: 0.75rem; font-weight: 800; color: var(--accent); text-transform: uppercase; letter-spacing: 1px;">Live Fleet</span>
                    </div>
                    <h2 style="font-size: 2rem; font-weight: 800; color: #fff; letter-spacing: -0.8px; margin: 0;">Featured <span class="text-gradient-anim">Vehicles</span></h2>
                    <p style="color: var(--text-muted); font-weight: 500; margin-top: 8px; font-size: 0.95rem;">Handpicked rides from our verified fleet — ready to book now.</p>
                </div>
                <a href="explore.php" class="hover-glow" style="display: inline-flex; align-items: center; gap: 8px; color: var(--accent); font-weight: 800; font-size: 0.9rem; text-decoration: none; border: 1px solid rgba(34,197,94,0.3); padding: 10px 22px; border-radius: 100px; transition: all 0.3s; white-space: nowrap;" onmouseover="this.style.background='rgba(34,197,94,0.08)'; this.style.borderColor='var(--accent)';" onmouseout="this.style.background='transparent'; this.style.borderColor='rgba(34,197,94,0.3)';">
                    View All <i class="fas fa-arrow-right" style="font-size: 0.8rem;"></i>
                </a>
            </div>

                <!-- Horizontal Scroll Track -->
                <div style="max-width: 1400px; margin: 0 auto; position: relative;">
                    <div id="featuredTrack" class="reveal-stagger" style="display: flex; gap: 24px; overflow-x: auto; padding-bottom: 20px; scroll-behavior: smooth; scrollbar-width: none; -ms-overflow-style: none;">
                        <?php while ($fv = $featured_vehicles->fetch_assoc()):
                            $img = strpos($fv['image_url'], 'http') === 0 ? $fv['image_url'] : '../app/' . $fv['image_url'];
                        ?>
                        <div class="feat-card hover-lift" style="flex: 0 0 280px; background: linear-gradient(145deg, #0f172a, #030712); border-radius: 24px; border: 1px solid rgba(255,255,255,0.06); overflow: hidden; position: relative; cursor: pointer;">
                            <!-- Vehicle Image -->
                            <div style="position: relative; height: 180px; overflow: hidden;">
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($fv['brand'].' '.$fv['model']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease;" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'">
                                <!-- ... -->
                                <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(3,7,18,0.85) 0%, transparent 55%);"></div>
                                <div style="position: absolute; top: 12px; right: 12px; background: rgba(3,7,18,0.8); backdrop-filter: blur(8px); border: 1px solid rgba(34,197,94,0.3); color: var(--accent); padding: 5px 12px; border-radius: 20px; font-weight: 800; font-size: 0.8rem;">
                                    ₹<?php echo number_format($fv['rental_price_per_day'], 0); ?>/day
                                </div>
                            </div>
                            <!-- Card Content -->
                            <div style="padding: 18px 20px 20px;">
                                <p style="font-size: 0.7rem; font-weight: 800; color: var(--accent); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; opacity: 0.8;">Premium <?php echo htmlspecialchars($fv['vehicle_type']); ?></p>
                                <h3 style="font-size: 1.1rem; font-weight: 800; color: #fff; margin: 0 0 10px; letter-spacing: -0.3px;"><?php echo htmlspecialchars($fv['brand'].' '.$fv['model']); ?></h3>
                                <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-users" style="color: var(--accent); font-size: 0.7rem;"></i> <?php echo $fv['seats']; ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-gas-pump" style="color: var(--accent); font-size: 0.7rem;"></i> <?php echo htmlspecialchars($fv['fuel_type']); ?>
                                    </span>
                                </div>
                                <?php if ($user_role !== 'Admin'): ?>
                                <a href="../booking/booking.php?id=<?php echo $fv['vehicle_id']; ?>" class="btn-book" style="display: block; text-align: center; padding: 11px; border-radius: 14px; text-decoration: none;">
                                    <i class="fas fa-bolt" style="margin-right: 6px;"></i> Rent Now
                                </a>
                                <?php endif; ?>

                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                <!-- Scroll arrows -->
                <button onclick="document.getElementById('featuredTrack').scrollBy({left:-320,behavior:'smooth'})" style="position: absolute; left: -20px; top: 50%; transform: translateY(-60%); width: 44px; height: 44px; background: rgba(15,23,42,0.9); border: 1px solid rgba(34,197,94,0.3); border-radius: 50%; color: var(--accent); font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; z-index: 10;" onmouseover="this.style.background='rgba(34,197,94,0.15)'; this.style.borderColor='var(--accent)';" onmouseout="this.style.background='rgba(15,23,42,0.9)'; this.style.borderColor='rgba(34,197,94,0.3)';">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button onclick="document.getElementById('featuredTrack').scrollBy({left:320,behavior:'smooth'})" style="position: absolute; right: -20px; top: 50%; transform: translateY(-60%); width: 44px; height: 44px; background: rgba(15,23,42,0.9); border: 1px solid rgba(34,197,94,0.3); border-radius: 50%; color: var(--accent); font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; z-index: 10;" onmouseover="this.style.background='rgba(34,197,94,0.15)'; this.style.borderColor='var(--accent)';" onmouseout="this.style.background='rgba(15,23,42,0.9)'; this.style.borderColor='rgba(34,197,94,0.3)';">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <style>#featuredTrack::-webkit-scrollbar { display: none; }
        /* Global Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main, var(--bg, #020617)); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color, var(--accent, #22c55e)); }

</style>
        </div>
        <?php endif; ?>

        <!-- BECOME A SELLER QUICK CALL-TO-ACTION -->
        <div style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(2, 6, 23, 0.95)); border-bottom: 1px solid var(--border); padding: 40px; text-align: center; position: relative; z-index: 19; overflow: hidden;">
            <div style="position: absolute; top: -50%; left: -20%; width: 300px; height: 300px; background: rgba(34, 197, 94, 0.05); border-radius: 50%; filter: blur(80px); pointer-events: none;"></div>
            <div style="position: absolute; bottom: -50%; right: -20%; width: 300px; height: 300px; background: rgba(34, 197, 94, 0.03); border-radius: 50%; filter: blur(80px); pointer-events: none;"></div>
            
            <div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 20px; position: relative; z-index: 10;" class="reveal reveal-zoom">
                <?php if ($is_logged_in && $user_role === 'Seller'): ?>
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -0.5px;">
                        You are a <span class="text-gradient-anim">Ridezo Seller!</span>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500; max-width: 600px; margin: 0 auto;">
                        Manage your active fleet, add new vehicles, and track your daily bookings effortlessly.
                    </p>
                    <a href="../app/seller/dashboard.php" class="btn-search-premium hover-glow" style="margin-top: 10px; text-decoration: none; padding: 14px 36px; border-radius: 100px; font-weight: 800; font-size: 0.9rem;">
                        <i class="fas fa-chart-line" style="margin-right: 8px;"></i>Go to Seller Dashboard
                    </a>
                <?php else: ?>
                    <h2 style="font-size: 1.6rem; font-weight: 800; color: #fff; letter-spacing: -0.8px;">
                        Have a vehicle? <span class="text-gradient-anim">Start Earning Daily!</span>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500; max-width: 600px; margin: 0 auto;">
                        List your car, bike, or SUV on Ridezo and turn your idle vehicle into passive daily income with verified customers.
                    </p>
                    <a href="../app/signup.php?role=seller" class="btn-search-premium hover-glow" style="margin-top: 10px; text-decoration: none; padding: 14px 36px; border-radius: 100px; font-weight: 800; font-size: 0.9rem; background: var(--accent); color: #020617; box-shadow: 0 0 20px var(--accent-glow);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 0 25px var(--accent-glow)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 0 20px var(--accent-glow)';">
                        <i class="fas fa-hand-holding-dollar" style="margin-right: 8px;"></i>Become a Ridezo Seller
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div style="max-width: 1400px; margin: 0 auto; padding: 60px 40px;">
            <?php
            function renderRow($title, $result, $type) {
                global $user_role;
                if ($result && $result->num_rows > 0) {
                    echo '
                    <section class="reveal" style="margin-bottom: 80px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
                            <div>
                                <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; margin-bottom: 8px;">'.$title.'</h2>
                                <div style="width: 30px; height: 3px; background: var(--primary); border-radius: 2px;"></div>
                            </div>
                            <a href="explore.php?type='.$type.'" style="color: var(--accent); font-weight: 700; font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                View All <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="vehicle-grid reveal-stagger">
                    ';
                    while ($v = $result->fetch_assoc()) {
                        $img = $v['image_url'] ? '../app/'.$v['image_url'] : 'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?auto=format&fit=crop&q=80&w=400&h=250';
                        
                        $v_type = strtolower($v['vehicle_type']);
                        $subtitle = "Premium Car";
                        $specs_text = "";
                        
                        if (strpos($v_type, 'bike') !== false) {
                            $subtitle = "Premium Bike";
                            $specs_text = "🏍️ Sport Edition &bull; " . $v['fuel_type'];
                        } elseif (strpos($v_type, 'scooty') !== false || strpos($v_type, 'scooter') !== false) {
                            $subtitle = "Premium Scooty";
                            $specs_text = "⚡ Smart Drive &bull; " . $v['fuel_type'];
                        } elseif (strpos($v_type, 'suv') !== false || strpos($v_type, 'jeep') !== false) {
                            $subtitle = "Adventure SUV";
                            $specs_text = "🛞 4x4 &bull; " . $v['seats'] . " Seats &bull; " . $v['transmission'];
                        } else {
                            $subtitle = "Premium " . $v['vehicle_type'];
                            $specs_text = "👥 " . $v['seats'] . " Seats &bull; " . $v['transmission'] . " &bull; " . $v['fuel_type'];
                        }

                        echo '
                        <div class="vehicle-card">
                            <div class="card-img-box">
                                <img src="'.$img.'" alt="'.$v['brand'].'">
                                <span style="position: absolute; top: 12px; left: 12px; background: rgba(34, 197, 94, 0.95); backdrop-filter: blur(8px); color: #020617; padding: 6px 12px; border-radius: 100px; font-weight: 800; font-size: 0.68rem; display: flex; align-items: center; gap: 5px; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4); z-index: 5;"><span style="width: 5px; height: 5px; background: #020617; border-radius: 50%; display: inline-block; animation: blinker 1.8s linear infinite;"></span>Available Now</span>
                                <div class="price-chip">₹'.number_format($v['rental_price_per_day']).'/d</div>
                            </div>
                            <div class="card-content">
                                <span class="vehicle-subtitle">'.$subtitle.'</span>
                                <h3 class="vehicle-name">'.$v['brand'].' '.$v['model'].'</h3>
                                <div class="vehicle-specs">
                                    <span>'.$specs_text.'</span>
                                </div>';
                                
                                if ($user_role !== 'Admin') {
                                    echo '<a href="../booking/booking.php?id='.$v['vehicle_id'].'" class="btn-book">Rent Now</a>';
                                }

                                echo '
                            </div>
                        </div>';
                    }
                    echo '</div></section>';
                }
            }

            renderRow('Premium Bikes', $bikes, 'Bike');
            ?>

            <!-- REVIEWS -->
            <section class="reveal reviews-section" style="margin: 0 -40px; padding: 80px 40px;">
                <div style="text-align: center; margin-bottom: 40px;">
                    <span style="display: inline-block; padding: 4px 10px; background: var(--primary-light); color: var(--primary); border-radius: 8px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 1px;">Reviews</span>
                    <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--dark); letter-spacing: -1px;">What our users say</h2>
                </div>
                <div class="review-grid reveal-stagger">
                    <div class="review-card">
                        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        <p class="review-text">"Seamless booking experience. The Thar I rented was in excellent condition. Highly recommended!"</p>
                        <div class="reviewer">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=100&h=100" alt="User">
                            <div class="reviewer-info">
                                <div class="name">Arjun Sharma</div>
                                <div class="status">Verified Customer</div>
                            </div>
                        </div>
                    </div>
                    <div class="review-card">
                        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        <p class="review-text">"Great way to earn passive income. Listing my bike was simple and the support team is very helpful."</p>
                        <div class="reviewer">
                            <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=100&h=100" alt="User">
                            <div class="reviewer-info">
                                <div class="name">Priya Nair</div>
                                <div class="status">Vehicle Owner</div>
                            </div>
                        </div>
                    </div>
                    <div class="review-card">
                        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        <p class="review-text">"Rented a scooty for my weekend trip. Very affordable and well-maintained. 5 stars!"</p>
                        <div class="reviewer">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=100&h=100" alt="User">
                            <div class="reviewer-info">
                                <div class="name">Rahul Verma</div>
                                <div class="status">Verified Customer</div>
                            </div>
                        </div>
                    </div>
                    <div class="review-card">
                        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        <p class="review-text">"The premium sedan I booked for the event was perfect. Excellent service by the team."</p>
                        <div class="reviewer">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=100&h=100" alt="User">
                            <div class="reviewer-info">
                                <div class="name">Anjali Gupta</div>
                                <div class="status">Verified Customer</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

            <!-- FEEDBACK & CONTACT FORM SECTION -->
            <section style="padding: 100px 40px; background: linear-gradient(to bottom, var(--bg), #0f172a); border-top: 1px solid var(--border); position: relative; overflow: hidden;">
                <div style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 100%; height: 100%; background: radial-gradient(circle at 50% 50%, rgba(34, 197, 94, 0.05) 0%, transparent 70%); pointer-events: none;"></div>
                
                <div style="max-width: 1400px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center;">
                    
                    <!-- Left Side: Content -->
                    <div class="reveal reveal-left">
                        <div style="display: inline-flex; align-items: center; gap: 10px; background: var(--accent-soft); padding: 8px 20px; border-radius: 100px; margin-bottom: 24px; border: 1px solid rgba(34, 197, 94, 0.2);">
                            <i class="fas fa-comment-dots" style="color: var(--accent);"></i>
                            <span style="font-size: 0.8rem; font-weight: 800; color: var(--accent); text-transform: uppercase;">Get in Touch</span>
                        </div>
                        <h2 style="font-size: 3rem; font-weight: 850; color: #fff; letter-spacing: -1.5px; line-height: 1.1; margin-bottom: 24px;">Have questions? <br><span class="text-gradient-anim">We're here to help.</span></h2>
                        <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.7; font-weight: 500; margin-bottom: 40px;">Whether you're a partner looking to list your fleet or a traveler seeking the perfect ride, our support team is available 24/7 to ensure your Ridezo experience is flawless.</p>
                        
                        <div style="display: grid; gap: 24px;">
                            <div style="display: flex; align-items: center; gap: 20px; background: rgba(255,255,255,0.02); padding: 20px; border-radius: 20px; border: 1px solid var(--border);" class="hover-lift">
                                <div style="width: 50px; height: 50px; background: var(--accent-soft); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 1.2rem;"><i class="fas fa-headset"></i></div>
                                <div><h4 style="color: #fff; font-weight: 800; font-size: 1rem; margin-bottom: 4px;">Premium Support</h4><p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">Avg. response time: 15 minutes</p></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 20px; background: rgba(255,255,255,0.02); padding: 20px; border-radius: 20px; border: 1px solid var(--border);" class="hover-lift">
                                <div style="width: 50px; height: 50px; background: var(--accent-soft); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 1.2rem;"><i class="fas fa-shield-alt"></i></div>
                                <div><h4 style="color: #fff; font-weight: 800; font-size: 1rem; margin-bottom: 4px;">Verified Safety</h4><p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">Every vehicle & host is pre-screened</p></div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Feedback Form -->
                    <div class="reveal reveal-right">
                        <?php if ($alert): ?>
                            <div style="margin-bottom: 24px; padding: 16px; border-radius: 12px; background: <?php echo $alert['type'] == 'success' ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; border: 1px solid <?php echo $alert['type'] == 'success' ? 'rgba(34, 197, 94, 0.3)' : 'rgba(239, 68, 68, 0.3)'; ?>; color: <?php echo $alert['type'] == 'success' ? '#22c55e' : '#ef4444'; ?>; font-weight: 700; text-align: center;">
                                <?php echo $alert['message']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(20px); border: 1px solid var(--border); padding: 40px; border-radius: 32px; box-shadow: 0 30px 60px rgba(0,0,0,0.5);">
                            <form action="index.php" method="POST" class="reveal-stagger" style="display: grid; gap: 20px;">
                                <div class="input-group-premium">
                                    <label>Full Name</label>
                                    <div class="input-wrapper-premium"><i class="fas fa-user"></i><input type="text" name="name" placeholder="John Doe" required></div>
                                </div>
                                <div class="input-group-premium">
                                    <label>Email Address</label>
                                    <div class="input-wrapper-premium"><i class="fas fa-envelope"></i><input type="email" name="email" placeholder="john@example.com" required></div>
                                </div>
                                <div class="input-group-premium">
                                    <label>Your Message / Feedback</label>
                                    <textarea name="message" placeholder="Tell us how we can help..." required style="width: 100%; height: 120px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 14px; padding: 16px; color: var(--text); font-family: inherit; font-weight: 600; font-size: 0.95rem; resize: none; outline: none; transition: var(--transition);" onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 4px var(--accent-soft)'" onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'"></textarea>
                                </div>
                                <input type="hidden" name="submit_feedback" value="1">
                                <button type="submit" class="btn-search-premium hover-glow" style="margin-top: 10px; height: 56px;">Send Message <i class="fas fa-paper-plane" style="margin-left: 8px;"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- PREMIUM FOOTER -->
        <footer style="background: rgba(15, 23, 42, 0.95); border-top: 1px solid var(--border); padding: 80px 40px 40px; margin-top: 60px; position: relative; z-index: 10; backdrop-filter: blur(25px);">
            <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 48px; margin-bottom: 60px;">
                
                <!-- Column 1: Brand Info -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <a href="index.php" class="logo" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff;">
                        <img src="../assets/ridezo_logo.png" alt="Ridezo Logo" class="logo-img">
                        <span style="font-size: 1.6rem; font-weight: 800; letter-spacing: -1.2px; color: #fff;">Ridezo</span>
                    </a>
                    <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.6; font-weight: 500;">
                        Vehicle rental simplified. Connecting verified vehicle owners and renters across Karnataka with state-of-the-art telemetry and zero hidden costs.
                    </p>
                    <div style="display: flex; gap: 12px; margin-top: 10px;">
                        <a href="#" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 50%; color: var(--text-muted); text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)'; this.style.background='var(--accent-soft)'; this.style.transform='translateY(-3px)';" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-muted)'; this.style.background='rgba(255,255,255,0.03)'; this.style.transform='none';"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 50%; color: var(--text-muted); text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)'; this.style.background='var(--accent-soft)'; this.style.transform='translateY(-3px)';" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-muted)'; this.style.background='rgba(255,255,255,0.03)'; this.style.transform='none';"><i class="fab fa-twitter"></i></a>
                        <a href="#" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 50%; color: var(--text-muted); text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)'; this.style.background='var(--accent-soft)'; this.style.transform='translateY(-3px)';" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-muted)'; this.style.background='rgba(255,255,255,0.03)'; this.style.transform='none';"><i class="fab fa-instagram"></i></a>
                        <a href="#" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 50%; color: var(--text-muted); text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)'; this.style.background='var(--accent-soft)'; this.style.transform='translateY(-3px)';" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-muted)'; this.style.background='rgba(255,255,255,0.03)'; this.style.transform='none';"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <!-- Column 2: Vehicles -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <h3 style="font-size: 0.95rem; font-weight: 800; text-transform: uppercase; color: #fff; letter-spacing: 0.8px;">Browse Vehicles</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="explore.php?type=Car" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='var(--accent)'; this.style.paddingLeft='5px';" onmouseout="this.style.color='var(--text-muted)'; this.style.paddingLeft='0';">Sedan & Hatchback Cars</a>
                        <a href="explore.php?type=Bike" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='var(--accent)'; this.style.paddingLeft='5px';" onmouseout="this.style.color='var(--text-muted)'; this.style.paddingLeft='0';">Premium Bikes</a>
                        <a href="explore.php?type=Scooty" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='var(--accent)'; this.style.paddingLeft='5px';" onmouseout="this.style.color='var(--text-muted)'; this.style.paddingLeft='0';">Smart Scooties</a>
                        <a href="explore.php?type=Jeep" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='var(--accent)'; this.style.paddingLeft='5px';" onmouseout="this.style.color='var(--text-muted)'; this.style.paddingLeft='0';">Adventurous Jeeps & SUVs</a>
                    </div>
                </div>

                <!-- Column 3: Quick Links -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <h3 style="font-size: 0.95rem; font-weight: 800; text-transform: uppercase; color: #fff; letter-spacing: 0.8px;">Quick Links</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="index.php" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='var(--accent)'; this.style.paddingLeft='5px';" onmouseout="this.style.color='var(--text-muted)'; this.style.paddingLeft='0';">Home Page</a>
                        <a href="explore.php" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='var(--accent)'; this.style.paddingLeft='5px';" onmouseout="this.style.color='var(--text-muted)'; this.style.paddingLeft='0';">Explore Fleet</a>
                        <a href="../app/signup.php?role=seller" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='var(--accent)'; this.style.paddingLeft='5px';" onmouseout="this.style.color='var(--text-muted)'; this.style.paddingLeft='0';">Become a Seller</a>
                        <a href="<?php echo $bookings_link; ?>" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='var(--accent)'; this.style.paddingLeft='5px';" onmouseout="this.style.color='var(--text-muted)'; this.style.paddingLeft='0';">My Rental Bookings</a>
                    </div>
                </div>

                <!-- Column 4: Contact Support -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <h3 style="font-size: 0.95rem; font-weight: 800; text-transform: uppercase; color: #fff; letter-spacing: 0.8px;">Contact Support</h3>
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-phone" style="color: var(--accent); font-size: 0.95rem; width: 20px;"></i>
                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">+91 98765 43210</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-envelope" style="color: var(--accent); font-size: 0.95rem; width: 20px;"></i>
                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">support@ridezo.com</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-map-marker-alt" style="color: var(--accent); font-size: 0.95rem; width: 20px;"></i>
                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">Bangalore, Karnataka, India</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Divider -->
            <div style="width: 100%; height: 1px; background: var(--border); margin-bottom: 30px;"></div>

            <!-- Footer Bottom -->
            <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <p style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;">
                    &copy; <?php echo date('Y'); ?> Ridezo Premium. All rights reserved.
                </p>
                <div style="display: flex; gap: 24px;">
                    <a href="policies.php?tab=privacy" style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600; text-decoration: none; transition: var(--transition);" onmouseover="this.style.color='var(--accent)';" onmouseout="this.style.color='var(--text-muted)';">Privacy Policy</a>
                    <a href="policies.php?tab=terms" style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600; text-decoration: none; transition: var(--transition);" onmouseover="this.style.color='var(--accent)';" onmouseout="this.style.color='var(--text-muted)';">Terms of Service</a>
                </div>
            </div>
        </footer>

        </div>
    </main>

    <script>
        // Slideshow Logic
        const slides = document.querySelectorAll('.slide');
        if (slides.length > 1) {
            let currentSlide = 0;
            function nextSlide() {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }
            setInterval(nextSlide, 3500);
        }

        // Advanced Reveal Animation
        function reveal() {
            // Navbar Scroll Logic
            const nav = document.querySelector('.top-nav');
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }

            var reveals = document.querySelectorAll(".reveal, .reveal-stagger");
            for (var i = 0; i < reveals.length; i++) {
                var windowHeight = window.innerHeight;
                var elementTop = reveals[i].getBoundingClientRect().top;
                var elementVisible = 50;
                
                if (elementTop < windowHeight - elementVisible) {
                    reveals[i].classList.add("active");
                } else if (elementTop > windowHeight) {
                    // Optional: Remove active class if user scrolls back up significantly
                    // reveals[i].classList.remove("active");
                }
            }
        }
        window.addEventListener("scroll", reveal);
        document.addEventListener("DOMContentLoaded", reveal);
    </script>
</body>
</html>