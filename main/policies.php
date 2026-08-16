\<?php
require '../app/config.php';
// Dynamic default tab selection
$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'terms' ? 'terms' : 'privacy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policies & Terms | Ridezo</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #020617;
            color: #f8fafc;
            font-family: 'Outfit', 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }

        .policy-container {
            max-width: 900px;
            margin: 140px auto 80px;
            padding: 0 24px;
        }

        .glass-card {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8), rgba(2, 6, 23, 0.9));
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 48px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            backdrop-filter: blur(20px);
        }

        .policy-title {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -1px;
            color: #fff;
            margin-bottom: 8px;
            text-align: center;
        }

        .policy-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 500;
            text-align: center;
            margin-bottom: 40px;
        }

        /* Tabs styling */
        .tab-wrapper {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 40px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 24px;
        }

        .tab-btn {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 12px 28px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: #fff;
            border-color: rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.06);
        }

        .tab-btn.active {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(34, 197, 94, 0.2);
        }

        /* Policy content typography */
        .policy-section {
            display: none;
            animation: fadeIn 0.5s ease-out forwards;
        }

        .policy-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #fff;
            margin-top: 36px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 8px;
        }

        h2 i {
            color: var(--accent);
            font-size: 1.1rem;
        }

        p {
            font-size: 0.95rem;
            line-height: 1.7;
            color: rgba(255,255,255,0.8);
            margin-bottom: 20px;
            font-weight: 400;
        }

        ul {
            padding-left: 20px;
            margin-bottom: 24px;
        }

        li {
            font-size: 0.95rem;
            line-height: 1.7;
            color: rgba(255,255,255,0.8);
            margin-bottom: 10px;
        }

        .accent-bullet {
            color: var(--accent);
            font-weight: bold;
            margin-right: 6px;
        }

        /* Top Bar Navigation */
        .top-nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 76px;
            background: rgba(2, 6, 23, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
        }

        .logo-box {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #fff;
        }

        .logo-box span {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .btn-home {
            background: var(--accent);
            color: #020617;
            padding: 10px 24px;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 800;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px var(--accent-glow);
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--accent-glow);
        }
    
        /* Global Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main, var(--bg, #020617)); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color, var(--accent, #22c55e)); }

</style>
</head>
<body>

    <!-- TOP NAVIGATION BAR -->
    <nav class="top-nav">
        <a href="index.php" class="logo-box">
            <img src="../assets/ridezo_logo.png" alt="Ridezo" class=""  style="color: var(--accent); font-size: 1.6rem; filter: drop-shadow(0 0 6px var(--accent-glow));" style="height: 1.5em; vertical-align: middle; border-radius: 4px; object-fit: contain;">
            <span>Ridezo</span>
        </a>
        <a href="index.php" class="btn-home"><i class="fas fa-arrow-left" style="margin-right: 8px;"></i>Back to Home</a>
    </nav>

    <!-- POLICY CONTAINER -->
    <div class="policy-container">
        <div class="glass-card">
            
            <h1 class="policy-title">Ridezo Policies</h1>
            <p class="policy-subtitle">Last Updated: May 17, 2026</p>

            <!-- Interactive Tabs -->
            <div class="tab-wrapper">
                <button class="tab-btn <?php echo $active_tab === 'privacy' ? 'active' : ''; ?>" onclick="switchTab('privacy')">
                    <i class="fas fa-shield-halved"></i> Privacy Policy
                </button>
                <button class="tab-btn <?php echo $active_tab === 'terms' ? 'active' : ''; ?>" onclick="switchTab('terms')">
                    <i class="fas fa-file-contract"></i> Terms of Service
                </button>
            </div>

            <!-- PRIVACY POLICY CONTENT -->
            <div id="content-privacy" class="policy-section <?php echo $active_tab === 'privacy' ? 'active' : ''; ?>">
                <p>Welcome to Ridezo. Your privacy is critically important to us. This Privacy Policy outlines how we collect, use, store, and protect your personal information, especially concerning vehicle rentals and live location telemetry.</p>
                
                <h2><i class="fas fa-circle-info"></i> 1. Information We Collect</h2>
                <p>We collect essential information to provide seamless rental services:</p>
                <ul>
                    <li><span class="accent-bullet">•</span> <strong>Account Profiles:</strong> Full name, Email, Phone number, Profile Photo, and Driver's License details.</li>
                    <li><span class="accent-bullet">•</span> <strong>Verification Data:</strong> Official government credentials and driver license files to ensure safety across our marketplace.</li>
                    <li><span class="accent-bullet">•</span> <strong>Location Telemetry:</strong> While actively renting a vehicle, GPS data from the vehicle is collected to enable tracking for both host and guest security.</li>
                </ul>

                <h2><i class="fas fa-location-crosshairs"></i> 2. Telemetry and GPS Tracking</h2>
                <p>For rental security, Ridezo uses Leaflet and OpenStreetMap integrations to track active rides:</p>
                <ul>
                    <li><span class="accent-bullet">•</span> Tracking is only active during the approved booking start and end windows.</li>
                    <li><span class="accent-bullet">•</span> Customers must enable their tracking option in the dashboard to initiate active telemetry.</li>
                    <li><span class="accent-bullet">•</span> Telemetry metrics remain strictly locked inside India boundaries and are never shared outside the platform.</li>
                </ul>

                <h2><i class="fas fa-lock"></i> 3. Data Protection and Storage</h2>
                <p>All sensitive credentials, including password hashes (BCRYPT) and official document files, are safely encrypted and hosted in our private SQL clusters. We strictly restrict unauthorized administrative accesses to secure bookings logs.</p>

                <h2><i class="fas fa-bullhorn"></i> 4. Updates to This Policy</h2>
                <p>Ridezo reserves the right to adjust these terms at any time. Changes will be posted instantly on this portal. Continued utilization of the Ridezo ecosystem signals your binding approval of the modified policy guidelines.</p>
            </div>

            <!-- TERMS OF SERVICE CONTENT -->
            <div id="content-terms" class="policy-section <?php echo $active_tab === 'terms' ? 'active' : ''; ?>">
                <p>By registering, accessing, or listing on the Ridezo platform, you enter into a legally binding contract with Ridezo Premium Rental Network. Please read these terms carefully before starting.</p>

                <h2><i class="fas fa-user-check"></i> 1. Registration and Eligibility</h2>
                <p>We maintain strict safety benchmarks for all hosts and customers:</p>
                <ul>
                    <li><span class="accent-bullet">•</span> <strong>Customers (Renters):</strong> Must be at least 18 years old and possess a clean, active Indian Driver's License.</li>
                    <li><span class="accent-bullet">•</span> <strong>Sellers (Hosts/Owners):</strong> Must be at least 22 years old and hold clear ownership/legitimate leasing rights of the listed vehicles.</li>
                </ul>

                <h2><i class="fas fa-car"></i> 2. Vehicle Listing Terms</h2>
                <p>Hosts must supply accurate coordinates, pickup locations, and premium vehicle specifications. All uploads undergo automatic Indian state/city highlighters validation before showing live on our explorer.</p>

                <h2><i class="fas fa-coins"></i> 3. Security Deposits & Bookings</h2>
                <p>All renters are bound to complete the booking via the official checkout page. Late return penalties, fuel thresholds, and security deposits will be collected dynamically in accordance with standard telemetry logs.</p>

                <h2><i class="fas fa-circle-exclamation"></i> 4. Liability and Telemetry Consent</h2>
                <p>Sellers agree to activate standard OSM trackers. Disabling safety tracking arrays while renting out active fleet vehicles constitutes a fundamental breach of system agreements and is subject to immediate suspension.</p>

                <h2><i class="fas fa-circle-xmark"></i> 5. Account Termination</h2>
                <p>Failure to adhere to safety protocols, geocoder guidelines, or presenting false credentials will lead to instant termination of Ridezo account access, holding back subsequent listing privileges indefinitely.</p>
            </div>

        </div>
    </div>

    <!-- Interactive JS Tab Switcher -->
    <script>
        function switchTab(tabName) {
            // Remove active classes
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.policy-section').forEach(sec => sec.classList.remove('active'));

            // Find matching button and section
            const targetBtn = Array.from(document.querySelectorAll('.tab-btn')).find(btn => btn.innerHTML.includes(tabName === 'privacy' ? 'Privacy' : 'Terms'));
            const targetSection = document.getElementById('content-' + tabName);

            if (targetBtn) targetBtn.classList.add('active');
            if (targetSection) targetSection.classList.add('active');

            // Update URL parameters without page reload
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabName;
            window.history.pushState({ path: newUrl }, '', newUrl);
        }
    </script>
    
    <script>
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
</body>
</html>
