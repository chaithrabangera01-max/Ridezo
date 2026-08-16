<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
require '../app/config.php';
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

// Filter Logic
$loc_filter = isset($_GET['location']) ? sanitize($_GET['location']) : '';
$brand_filter = isset($_GET['brand']) ? sanitize($_GET['brand']) : '';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$seats_filter = isset($_GET['seats']) ? intval($_GET['seats']) : 0;
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : 0;

$status_condition = "v.status = 'Available' AND v.vehicle_id NOT IN (SELECT vehicle_id FROM bookings WHERE status IN ('Pending', 'Confirmed', 'Active'))";
$sql = "SELECT v.*, u.name as seller_name, u.role as seller_role 
        FROM vehicles v 
        JOIN users u ON v.seller_id = u.user_id 
        WHERE $status_condition";
$params = [];

if (!empty($loc_filter) && strtolower($loc_filter) !== 'select location') {
    if (strtolower($loc_filter) === 'bangalore') {
        $sql .= " AND (v.brand LIKE ? OR v.model LIKE ? OR v.vehicle_type LIKE ? OR v.area LIKE ? OR v.pickup_location LIKE ? OR v.state LIKE ? OR v.description LIKE ? OR u.name LIKE ? OR u.role LIKE ? OR v.state = 'Karnataka')";
    } else {
        $sql .= " AND (v.brand LIKE ? OR v.model LIKE ? OR v.vehicle_type LIKE ? OR v.area LIKE ? OR v.pickup_location LIKE ? OR v.state LIKE ? OR v.description LIKE ? OR u.name LIKE ? OR u.role LIKE ?)";
    }    
    $search_term = "%$loc_filter%";
    for($i=0; $i<9; $i++) $params[] = $search_term;
}

if (!empty($brand_filter)) {
    $sql .= " AND v.brand = ?";
    $params[] = $brand_filter;
}

if (!empty($type_filter)) {
    if ($type_filter === 'Car') {
        $sql .= " AND (v.vehicle_type = 'Car' OR v.vehicle_type = 'Sedan' OR v.vehicle_type = 'Hatchback')";
    } elseif ($type_filter === 'Jeep') {
        $sql .= " AND (v.vehicle_type = 'Jeep' OR v.vehicle_type = 'SUV')";
    } else {
        $sql .= " AND v.vehicle_type = ?";
        $params[] = $type_filter;
    }
}

if ($seats_filter > 0) {
    $sql .= " AND v.seats >= ?";
    $params[] = $seats_filter;
}

if ($min_price > 0) {
    $sql .= " AND v.rental_price_per_day >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $sql .= " AND v.rental_price_per_day <= ?";
    $params[] = $max_price;
}

$sql .= " ORDER BY v.created_at DESC";
$result = $conn->execute_query($sql, $params);

// Fetch unique values for filters
$brands_res = $conn->query("SELECT DISTINCT brand FROM vehicles WHERE status = 'Available' ORDER BY brand ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore | Ridezo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* Make dropdown options readable on dark theme */
        select option {
            background-color: #0f172a;
            color: #ffffff;
        }
    
        /* Global Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main, var(--bg, #020617)); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color, var(--accent, #22c55e)); }

</style>
</head>
<body>

    <!-- TOP NAVIGATION -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-left" style="display: flex; align-items: center; gap: 20px;">
                <a href="javascript:history.back()" class="nav-link" style="color: var(--accent);"><i class="fas fa-arrow-left" style="margin-right: 6px;"></i> Back</a>
                <a href="index.php" class="logo">
                    <img src="../assets/ridezo_logo.png" alt="Ridezo Logo" class="logo-img">
                    <span>Ridezo</span>
                </a>
            </div>
            <div class="nav-right">
                <a href="index.php" class="nav-link">Home</a>
                <a href="explore.php" class="nav-link active">Explore Vehicles</a>
                <a href="<?php echo $bookings_link; ?>" class="nav-link">My Bookings</a>
                <a href="<?php echo $dashboard_link; ?>" class="nav-link">Dashboard</a>
                <?php if ($is_logged_in): ?>
                    <a href="../app/logout.php" class="nav-link btn-login">Logout</a>
                <?php else: ?>
                    <a href="../app/login.php" class="nav-link btn-login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="main-wrapper no-sidebar" style="margin-left: 0 !important;">
        <div style="max-width: 1400px; margin: 0 auto; padding: 20px 40px;">
            
            <!-- HORIZONTAL FILTER BAR (Full Width) -->
            <form action="explore.php" method="GET" class="search-form-premium reveal reveal-down" style="margin-top: 20px; margin-bottom: 40px; padding: 12px; border-radius: 12px; border: 1px solid var(--border);">
                <div class="input-group-premium" style="flex: 1.5;">
                    <label>Keyword</label>
                    <div class="input-wrapper-premium">
                        <i class="fas fa-search"></i>
                        <input type="text" name="location" placeholder="Brand, Model or Area..." value="<?php echo htmlspecialchars($loc_filter); ?>">
                    </div>
                </div>
                <div class="input-group-premium">
                    <label>Brand</label>
                    <div class="input-wrapper-premium">
                        <i class="fas fa-building"></i>
                        <select name="brand" style="width:100%; border:none; background:transparent; font-family:inherit; font-weight:600; font-size:0.95rem; outline:none; color:var(--text);">
                            <option value="">All Brands</option>
                            <?php while($b = $brands_res->fetch_assoc()): ?>
                                <option value="<?php echo $b['brand']; ?>" <?php echo $brand_filter == $b['brand'] ? 'selected' : ''; ?>><?php echo $b['brand']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="input-group-premium">
                    <label>Type</label>
                    <div class="input-wrapper-premium">
                        <i class="fas fa-car"></i>
                        <select name="type" style="width:100%; border:none; background:transparent; font-family:inherit; font-weight:600; font-size:0.95rem; outline:none; color:var(--text);">
                            <option value="">All Types</option>
                            <option value="Car" <?php echo $type_filter == 'Car' ? 'selected' : ''; ?>>Car</option>
                            <option value="Bike" <?php echo $type_filter == 'Bike' ? 'selected' : ''; ?>>Bike</option>
                            <option value="Scooty" <?php echo $type_filter == 'Scooty' ? 'selected' : ''; ?>>Scooty</option>
                            <option value="Jeep" <?php echo $type_filter == 'Jeep' ? 'selected' : ''; ?>>SUV/Jeep</option>
                        </select>
                    </div>
                </div>
                <div class="input-group-premium">
                    <label>Capacity</label>
                    <div class="input-wrapper-premium">
                        <i class="fas fa-users"></i>
                        <select name="seats" style="width:100%; border:none; background:transparent; font-family:inherit; font-weight:600; font-size:0.95rem; outline:none; color:var(--text);">
                            <option value="0">Any Seats</option>
                            <option value="2" <?php echo $seats_filter == 2 ? 'selected' : ''; ?>>2+ Seats</option>
                            <option value="4" <?php echo $seats_filter == 4 ? 'selected' : ''; ?>>4+ Seats</option>
                            <option value="5" <?php echo $seats_filter == 5 ? 'selected' : ''; ?>>5+ Seats</option>
                        </select>
                    </div>
                </div>
                <div class="input-group-premium">
                    <label>Min Price</label>
                    <div class="input-wrapper-premium">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" name="min_price" placeholder="₹ Min" min="0" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" style="width:100%; border:none; background:transparent; font-family:inherit; font-weight:600; font-size:0.95rem; outline:none; color:var(--text);">
                    </div>
                </div>
                <div class="input-group-premium">
                    <label>Max Price</label>
                    <div class="input-wrapper-premium">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" name="max_price" placeholder="₹ Max" min="0" value="<?php echo $max_price > 0 ? $max_price : ''; ?>" style="width:100%; border:none; background:transparent; font-family:inherit; font-weight:600; font-size:0.95rem; outline:none; color:var(--text);">
                    </div>
                </div>
                <button type="submit" class="btn-search-premium pulse-anim" style="padding: 0 40px;">Filter Vehicles</button>
            </form>

            <div class="explore-sections">
                <?php
                $admin_vehicles = [];
                $seller_vehicles = [];
                
                if ($result->num_rows > 0) {
                    while ($car = $result->fetch_assoc()) {
                        if ($car['seller_role'] === 'Admin') {
                            $admin_vehicles[] = $car;
                        } else {
                            $seller_vehicles[] = $car;
                        }
                    }
                }

                function renderVehicleGrid($title, $subtitle, $vehicles, $user_role, $badge_text, $badge_class) {
                    if (empty($vehicles)) return '';
                    $html = '<section class="fleet-section" style="margin-bottom: 60px;">';
                    $html .= '<div class="section-header-premium reveal reveal-up" style="margin-bottom: 30px;">';
                    $html .= '<h2 style="font-size: 1.8rem; font-weight: 800; color: #fff; letter-spacing: -0.5px;">'.$title.' <span style="background: var(--accent-soft); color: var(--accent); font-size: 0.8rem; padding: 4px 12px; border-radius: 100px; margin-left: 10px; vertical-align: middle;">' . count($vehicles) . ' Available</span></h2>';
                    $html .= '<p style="color: var(--text-muted); font-weight: 500;">'.$subtitle.'</p>';
                    $html .= '</div>';
                    $html .= '<div class="vehicle-grid reveal-stagger">';
                    
                    foreach ($vehicles as $car) {
                        $image_path = strpos($car['image_url'], 'http') === 0 ? $car['image_url'] : '../app/' . $car['image_url'];
                        
                        $v_type = strtolower($car['vehicle_type']);
                        $card_subtitle = "Premium Car";
                        $specs_text = "";
                        
                        if (strpos($v_type, 'bike') !== false) {
                            $card_subtitle = "Premium Bike";
                            $specs_text = "🏍️ Sport Edition &bull; " . $car['fuel_type'];
                        } elseif (strpos($v_type, 'scooty') !== false || strpos($v_type, 'scooter') !== false) {
                            $card_subtitle = "Premium Scooty";
                            $specs_text = "⚡ Smart Drive &bull; " . $car['fuel_type'];
                        } elseif (strpos($v_type, 'suv') !== false || strpos($v_type, 'jeep') !== false) {
                            $card_subtitle = "Adventure SUV";
                            $specs_text = "🛞 4x4 &bull; " . $car['seats'] . " Seats &bull; " . $car['transmission'];
                        } else {
                            $card_subtitle = "Premium " . $car['vehicle_type'];
                            $specs_text = "👥 " . $car['seats'] . " Seats &bull; " . $car['transmission'] . " &bull; " . $car['fuel_type'];
                        }

                        $html .= '<div class="vehicle-card">';
                        $html .= '<div class="card-img-box">';
                        $html .= '<img src="'.$image_path.'" alt="'.$car['brand'].' '.$car['model'].'">';
                        $html .= '<div class="host-badge '.$badge_class.'"><i class="fas fa-certificate"></i> '.$badge_text.'</div>';
                        $html .= '<div class="price-chip">₹' . number_format($car['rental_price_per_day'], 0) . '/d</div>';
                        $html .= '</div>';
                        $html .= '<div class="card-content">';
                        $html .= '<span class="vehicle-subtitle">'.$card_subtitle.'</span>';
                        $html .= '<h3 class="vehicle-name">'.$car['brand'].' '.$car['model'].'</h3>';
                        $html .= '<div class="vehicle-specs" style="margin-bottom: 8px;"><span>'.$specs_text.'</span></div>';
                        $html .= '<div style="display: flex; justify-content: flex-end; align-items: center; font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 10px; margin-top: 4px; margin-bottom: 16px;">';
                        $html .= '<span><i class="fas fa-map-marker-alt" style="color: var(--accent); margin-right: 4px;"></i>' . $car['area'] . '</span>';
                        $html .= '</div>';
                        
                        if ($user_role !== 'Admin') {
                            $html .= '<a href="../booking/booking.php?id=' . $car['vehicle_id'] . '" class="btn-book">Rent Now</a>';
                        }
                        
                        $html .= '</div></div>';
                    }
                    $html .= '</div></section>';
                    return $html;
                }

                // Render Sections
                $has_results = false;
                if (!empty($admin_vehicles)) {
                    echo renderVehicleGrid("Official Ridezo Fleet", "Directly managed and verified by Ridezo.", $admin_vehicles, $user_role, "Available", "badge-admin");
                    $has_results = true;
                }
                
                if (!empty($seller_vehicles)) {
                    echo renderVehicleGrid("Verified Partner Fleet", "Quality vehicles from our community of verified hosts.", $seller_vehicles, $user_role, "Available", "badge-seller");
                    $has_results = true;
                }

                if (!$has_results): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 100px 0;">
                        <i class="fas fa-car-rear" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px;"></i>
                        <h2>No Vehicles Found</h2>
                        <p style="color: var(--text-muted)">Try adjusting your filters or search keywords.</p>
                        <a href="explore.php" style="color: var(--primary); font-weight: 700; text-decoration: none; display: inline-block; margin-top: 20px;">Clear All Filters</a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <footer class="main-footer" style="padding: 40px 0;">
        <div class="footer-bottom">
            <p>&copy; 2024 Ridezo Technologies. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function reveal() {
            // Navbar Scroll Logic
            const nav = document.querySelector('.top-nav');
            if (nav) {
                if (window.scrollY > 50) {
                    nav.classList.add('scrolled');
                } else {
                    nav.classList.remove('scrolled');
                }
            }

            var reveals = document.querySelectorAll(".reveal, .reveal-stagger");
            for (var i = 0; i < reveals.length; i++) {
                var windowHeight = window.innerHeight;
                var elementTop = reveals[i].getBoundingClientRect().top;
                var elementVisible = 50; // Lower threshold for faster reveal
                if (elementTop < windowHeight - elementVisible) {
                    reveals[i].classList.add("active");
                }
            }
        }
        window.addEventListener("scroll", reveal);
        document.addEventListener("DOMContentLoaded", reveal);
    </script>
</body>
</html>
