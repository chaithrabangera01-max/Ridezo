<?php
require '../app/config.php';
checkUserRole('Admin');

$user_name = $_SESSION['name'] ?? 'Admin';
$user_photo = $_SESSION['profile_photo'] ?? null;

// Fetch ALL vehicles with booking info and pickup location
$query = "
    SELECT v.vehicle_id, v.brand, v.model, v.license_plate, v.vehicle_type, v.image_url,
           v.pickup_location, v.area, v.state, v.status as vehicle_status,
           u.name as seller_name,
           b.booking_id, b.status as booking_status, b.latitude, b.longitude,
           b.tracking_active, b.payment_status,
           cu.name as customer_name, cu.phone as customer_phone
    FROM vehicles v
    JOIN users u ON v.seller_id = u.user_id
    LEFT JOIN bookings b ON b.vehicle_id = v.vehicle_id AND b.status IN ('Confirmed','Active')
    LEFT JOIN users cu ON b.customer_id = cu.user_id
    WHERE v.status = 'Available'
    ORDER BY v.vehicle_id ASC
";
$result = $conn->query($query);
$vehicles = [];
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}

// Build dummy location data for each vehicle
// Base: Bangalore (12.9716, 77.5946)
// Use the pickup_location/area field as seed for deterministic placement
function pseudoRandOffset($seed, $range = 0.05) {
    $hash = crc32($seed);
    return (($hash % 10000) / 100000) * $range * 2 - $range;
}

$vehicleData = [];
foreach ($vehicles as $idx => $v) {
    $seed = $v['vehicle_id'] . $v['license_plate'];
    $baseLat = 12.9716 + pseudoRandOffset($seed . 'lat', 0.08);
    $baseLng = 77.5946 + pseudoRandOffset($seed . 'lng', 0.08);

    // Determine state: if vehicle has active booking → moving, else → parked at pickup
    $isMoving = !empty($v['booking_id']) && in_array($v['booking_status'], ['Confirmed', 'Active']);
    $vehicleData[] = [
        'vehicle_id'    => $v['vehicle_id'],
        'brand'         => $v['brand'],
        'model'         => $v['model'],
        'license_plate' => $v['license_plate'],
        'vehicle_type'  => $v['vehicle_type'],
        'image_url'     => $v['image_url'],
        'seller_name'   => $v['seller_name'],
        'pickup_area'   => $v['area'],
        'booking_id'    => $v['booking_id'],
        'booking_status'=> $v['booking_status'],
        'is_moving'     => $isMoving,
        'customer_name' => $v['customer_name'],
        'customer_phone'=> $v['customer_phone'],
        'payment_status'=> $v['payment_status'],
        'base_lat'      => $baseLat,
        'base_lng'      => $baseLng,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Live Tracking | Ridezo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root {
            --sidebar-width: 280px;
            --panel-width: 380px;
            --primary: #0f172a;
            --accent: #22c55e;
            --accent-soft: rgba(34,197,94,0.1);
            --accent-glow: rgba(34,197,94,0.25);
            --bg: #020617;
            --surface: #0f172a;
            --surface-light: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.08);
            --transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
            --moving: #22c55e;
            --parked: #3b82f6;
            --offline: #64748b;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); display:flex; height:100vh; overflow:hidden; }

        /* ─── SIDEBAR ─── */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(2,6,23,0.95);
            backdrop-filter: blur(25px);
            height: 100vh;
            position: fixed; left:0; top:0; z-index:1000;
            display:flex; flex-direction:column;
            border-right: 1px solid var(--border);
        }
        .sidebar-header { padding:28px 24px; display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--border); }
        .logo-box { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; }
        .logo-text { font-size:1.5rem; font-weight:800; letter-spacing:-1px; color:#fff; }
        .nav-menu { flex-grow:1; padding:20px 14px; overflow-y:auto; }
        .nav-item { display:flex; align-items:center; gap:14px; padding:12px 14px; color:var(--text-muted); text-decoration:none; border-radius:12px; font-weight:600; margin-bottom:6px; transition:var(--transition); font-size:0.9rem; }
        .nav-item:hover, .nav-item.active { background:var(--accent-soft); color:var(--accent); }
        .nav-item.active i { color:var(--accent); }
        .sidebar-footer { padding:20px; border-top:1px solid var(--border); }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px; background:var(--surface-light); border-radius:14px; margin-bottom:14px; border:1px solid var(--border); }
        .user-pill img { width:36px; height:36px; border-radius:10px; object-fit:cover; }
        .user-pill .name { font-weight:700; font-size:0.85rem; }
        .user-pill .role { font-size:0.7rem; color:var(--text-muted); }
        .btn-logout { display:flex; align-items:center; gap:8px; color:#ef4444; text-decoration:none; font-weight:700; font-size:0.85rem; padding:6px; transition:var(--transition); }
        .btn-logout:hover { transform:translateX(4px); }

        /* ─── MAIN LAYOUT ─── */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ─── MAP ─── */
        #map {
            flex-grow: 1;
            height: 100vh;
            z-index: 1;
        }
        /* Dark map tint */
        .leaflet-layer,
        .leaflet-control-zoom-in,
        .leaflet-control-zoom-out,
        .leaflet-control-attribution {
            filter: invert(100%) hue-rotate(180deg) brightness(90%) contrast(85%);
        }

        /* ─── RIGHT PANEL ─── */
        .tracking-panel {
            width: var(--panel-width);
            background: rgba(2,6,23,0.97);
            backdrop-filter: blur(20px);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 10;
            overflow: hidden;
        }
        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panel-title { font-size: 1rem; font-weight: 800; }
        .live-badge {
            display: inline-flex; align-items:center; gap:6px;
            background: rgba(34,197,94,0.1); color:var(--accent);
            padding: 4px 10px; border-radius:100px;
            font-size: 0.7rem; font-weight:800; text-transform:uppercase;
            border: 1px solid rgba(34,197,94,0.2);
        }
        .live-dot {
            width: 6px; height: 6px; background: var(--accent);
            border-radius: 50%; box-shadow: 0 0 8px var(--accent);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.5)} }

        /* Stats bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border);
            border-bottom: 1px solid var(--border);
        }
        .stat-cell {
            background: var(--surface);
            padding: 14px;
            text-align: center;
        }
        .stat-val { font-size: 1.4rem; font-weight: 800; line-height: 1; }
        .stat-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-top: 4px; }
        .stat-moving { color: var(--moving); }
        .stat-parked { color: var(--parked); }
        .stat-total { color: #f59e0b; }

        /* Vehicle list */
        .vehicle-list { flex-grow:1; overflow-y:auto; }
        .vehicle-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
        }
        .vehicle-item:hover, .vehicle-item.selected {
            background: rgba(34,197,94,0.06);
        }
        .vehicle-item.selected { border-left: 3px solid var(--accent); }
        .v-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items:center; justify-content:center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .v-icon.moving { background: rgba(34,197,94,0.15); color: var(--moving); border: 1px solid rgba(34,197,94,0.3); }
        .v-icon.parked { background: rgba(59,130,246,0.15); color: var(--parked); border: 1px solid rgba(59,130,246,0.3); }
        .v-name { font-weight: 800; font-size: 0.88rem; color: var(--text); }
        .v-plate { font-size: 0.72rem; color: #f59e0b; font-family: monospace; font-weight:700; background: rgba(245,158,11,0.1); padding: 1px 6px; border-radius:5px; margin-top:2px; display:inline-block; }
        .v-status-badge {
            margin-left: auto; padding: 4px 10px; border-radius:8px;
            font-size: 0.65rem; font-weight:800; text-transform:uppercase; white-space:nowrap; flex-shrink:0;
        }
        .badge-moving { background:rgba(34,197,94,0.15); color:var(--moving); border:1px solid rgba(34,197,94,0.3); }
        .badge-parked { background:rgba(59,130,246,0.15); color:var(--parked); border:1px solid rgba(59,130,246,0.3); }

        /* Detail panel */
        .detail-panel {
            background: var(--surface-light);
            border-top: 1px solid var(--border);
            padding: 18px 20px;
            display: none;
        }
        .detail-panel.open { display: block; }
        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px; }
        .detail-metric {
            background: var(--surface); padding:12px; border-radius:12px;
            border: 1px solid var(--border); text-align:center;
        }
        .d-icon { font-size:1.2rem; margin-bottom:6px; }
        .d-val { font-size:1.3rem; font-weight:800; }
        .d-label { font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; }

        /* Track-live btn */
        .btn-track-live {
            display:flex; align-items:center; justify-content:center; gap:8px;
            width:100%; padding:10px; margin-top:12px;
            background: var(--accent); color: var(--primary);
            border-radius:10px; font-weight:800; text-decoration:none;
            font-size:0.85rem; transition:var(--transition);
        }
        .btn-track-live:hover { transform:scale(1.02); box-shadow: 0 4px 20px var(--accent-glow); }

        /* Custom markers */
        .map-marker-moving {
            width:42px;height:42px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;
            background:rgba(34,197,94,0.2);
            border:2.5px solid var(--moving);
            box-shadow:0 0 18px rgba(34,197,94,0.5);
            color:var(--moving);font-size:17px;
            animation: markerPulse 2s infinite;
        }
        .map-marker-parked {
            width:38px;height:38px;border-radius:10px;
            display:flex;align-items:center;justify-content:center;
            background:rgba(59,130,246,0.2);
            border:2px solid var(--parked);
            color:var(--parked);font-size:15px;
        }
        @keyframes markerPulse {
            0%,100%{box-shadow:0 0 0 0 rgba(34,197,94,0.4)}
            50%{box-shadow:0 0 0 12px rgba(34,197,94,0)}
        }

        ::-webkit-scrollbar { width:6px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.1); border-radius:3px; }

        @media(max-width:1200px){
            .tracking-panel { width:320px; }
        }
    </style>
</head>
<body>

<!-- ─── SIDEBAR ─── -->
<div class="sidebar" id="sidebar">
    <a href="../main/index.php" style="text-decoration:none;color:inherit;">
        <div class="sidebar-header">
            <div class="logo-box"><img src="../assets/ridezo_logo.png" alt="Ridezo" style="height:32px;width:auto;filter:drop-shadow(0 0 10px var(--accent-glow));"></div>
            <div class="logo-text">Ridezo</div>
        </div>
    </a>
    <nav class="nav-menu">
        <a href="javascript:history.back()" class="nav-item"><i class="fas fa-arrow-left"></i><span>Back</span></a>
        <a href="../main/index.php" class="nav-item"><i class="fas fa-house"></i><span>Home</span></a>
        <a href="dashboard.php" class="nav-item"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a>
        <a href="vehicles.php" class="nav-item"><i class="fas fa-car"></i><span>All Vehicles</span></a>
        <a href="fleet-tracking.php" class="nav-item active"><i class="fas fa-location-crosshairs"></i><span>Fleet Tracking</span></a>
        <a href="users.php" class="nav-item"><i class="fas fa-users-gear"></i><span>User Management</span></a>
        <a href="bookings.php" class="nav-item"><i class="fas fa-calendar-check"></i><span>Booking Logs</span></a>
        <a href="transactions.php" class="nav-item"><i class="fas fa-wallet"></i><span>Transactions</span></a>
        <a href="feedbacks.php" class="nav-item"><i class="fas fa-comments"></i><span>Feedback</span></a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-pill">
            <?php if(!empty($user_photo)): ?>
                <img src="../app/<?php echo $user_photo; ?>" alt="Profile">
            <?php else: ?>
                <div style="width:36px;height:36px;background:rgba(255,255,255,0.1);border-radius:10px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-user"></i></div>
            <?php endif; ?>
            <div><div class="name">Admin</div><div class="role">Administrator</div></div>
        </div>
        <a href="../app/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i><span>Sign Out</span></a>
    </div>
</div>

<!-- ─── MAIN ─── -->
<div class="main-wrapper">
    <!-- MAP -->
    <div id="map"></div>

    <!-- RIGHT PANEL -->
    <div class="tracking-panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">🛰️ Fleet Tracking</div>
                <div style="font-size:0.7rem;color:var(--text-muted);margin-top:3px;">Live vehicle positions</div>
            </div>
            <div class="live-badge"><div class="live-dot"></div> LIVE</div>
        </div>

        <div class="stats-bar">
            <div class="stat-cell">
                <div class="stat-val stat-moving" id="count-moving">0</div>
                <div class="stat-label">Moving</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val stat-parked" id="count-parked">0</div>
                <div class="stat-label">Parked</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val stat-total" id="count-total">0</div>
                <div class="stat-label">Total</div>
            </div>
        </div>

        <div class="vehicle-list" id="vehicleList"></div>

        <!-- Detail panel for selected vehicle -->
        <div class="detail-panel" id="detailPanel">
            <div style="display:flex;align-items:center;gap:10px;">
                <img id="d-img" src="" style="width:56px;height:42px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                <div>
                    <div id="d-name" style="font-weight:800;font-size:0.95rem;"></div>
                    <div id="d-plate" style="font-size:0.72rem;color:#f59e0b;font-family:monospace;font-weight:700;"></div>
                </div>
            </div>
            <div class="detail-grid" id="d-metrics"></div>
            <a id="d-track-btn" href="#" class="btn-track-live" style="display:none;">
                <i class="fas fa-location-arrow"></i> Open Full Telematics
            </a>
            <div id="d-customer" style="margin-top:12px;font-size:0.78rem;color:var(--text-muted);"></div>
        </div>
    </div>
</div>

<script>
// ─── Vehicle data from PHP ───
const VEHICLES = <?php echo json_encode($vehicleData); ?>;

// ─── Simulated speed per vehicle (km/h) ───
const vehicleSpeeds = {};
const vehicleHeadings = ['N','NE','E','SE','S','SW','W','NW'];
const vehicleCurrent = {}; // { lat, lng } — live positions

VEHICLES.forEach(v => {
    vehicleCurrent[v.vehicle_id] = {
        lat: v.base_lat,
        lng: v.base_lng,
        speed: v.is_moving ? (30 + Math.random() * 40) : 0,
        fuel: 60 + Math.random() * 38,
        heading: Math.floor(Math.random() * 8),
    };
});

// ─── Map init ───
const map = L.map('map').setView([12.9716, 77.5946], 13);
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// ─── Markers ───
const markers = {};

function getVehicleIcon(v) {
    const type = v.is_moving ? 'moving' : 'parked';
    const icon = v.vehicle_type?.toLowerCase().includes('bike') || v.vehicle_type?.toLowerCase().includes('scooty')
        ? 'fa-motorcycle' : 'fa-car';
    const html = type === 'moving'
        ? `<div class="map-marker-moving"><i class="fas ${icon}"></i></div>`
        : `<div class="map-marker-parked"><i class="fas ${icon}"></i></div>`;
    return L.divIcon({ className:'', html, iconSize:[44,44], iconAnchor:[22,22], popupAnchor:[0,-24] });
}

function buildPopup(v, pos) {
    const stateColor = v.is_moving ? '#22c55e' : '#3b82f6';
    const stateLabel = v.is_moving ? '🚗 Moving' : '🅿️ Parked';
    return `<div style="font-family:'Plus Jakarta Sans',sans-serif;min-width:200px;padding:4px;">
        <b style="font-size:0.95rem;">${v.brand} ${v.model}</b>
        <div style="font-size:0.72rem;color:#64748b;margin-bottom:8px;">${v.license_plate}</div>
        <div style="display:inline-block;background:${stateColor}22;color:${stateColor};border:1px solid ${stateColor}44;padding:2px 8px;border-radius:6px;font-size:0.7rem;font-weight:800;margin-bottom:8px;">${stateLabel}</div>
        <div style="font-size:0.78rem;color:#334155;"><b>Seller:</b> ${v.seller_name}</div>
        ${v.customer_name ? `<div style="font-size:0.78rem;color:#334155;"><b>Rented by:</b> ${v.customer_name}</div>` : ''}
        <div style="font-size:0.75rem;color:#64748b;margin-top:6px;">📍 ${pos.lat.toFixed(4)}, ${pos.lng.toFixed(4)}</div>
    </div>`;
}

function initMarkers() {
    VEHICLES.forEach(v => {
        const pos = vehicleCurrent[v.vehicle_id];
        const marker = L.marker([pos.lat, pos.lng], { icon: getVehicleIcon(v) })
            .addTo(map)
            .bindPopup(buildPopup(v, pos));
        markers[v.vehicle_id] = marker;
        marker.on('click', () => selectVehicle(v.vehicle_id));
    });
}

// ─── Simulate movement ───
function simulate() {
    VEHICLES.forEach(v => {
        const pos = vehicleCurrent[v.vehicle_id];
        if (v.is_moving) {
            // Random walk, slightly biased in one direction
            pos.lat += (Math.random() - 0.45) * 0.0003;
            pos.lng += (Math.random() - 0.45) * 0.0003;
            pos.speed = Math.max(5, Math.min(85, pos.speed + (Math.random() * 10 - 5)));
            pos.fuel = Math.max(0, pos.fuel - Math.random() * 0.05);
            pos.heading = Math.floor(Math.random() * 8);
        }
        // Update marker
        if (markers[v.vehicle_id]) {
            markers[v.vehicle_id].setLatLng([pos.lat, pos.lng]);
            markers[v.vehicle_id].setPopupContent(buildPopup(v, pos));
        }
    });
    updatePanel();
}

// ─── Panel ───
let selectedId = null;

function buildVehicleList() {
    const list = document.getElementById('vehicleList');
    list.innerHTML = '';
    VEHICLES.forEach(v => {
        const div = document.createElement('div');
        div.className = 'vehicle-item' + (selectedId === v.vehicle_id ? ' selected' : '');
        div.id = 'vi-' + v.vehicle_id;
        const iconClass = v.is_moving ? 'moving' : 'parked';
        const badgeClass = v.is_moving ? 'badge-moving' : 'badge-parked';
        const badgeLabel = v.is_moving ? '▶ Moving' : '■ Parked';
        const faIcon = v.vehicle_type?.toLowerCase().includes('bike') || v.vehicle_type?.toLowerCase().includes('scooty') ? 'fa-motorcycle' : 'fa-car';
        div.innerHTML = `
            <div class="v-icon ${iconClass}"><i class="fas ${faIcon}"></i></div>
            <div style="flex-grow:1;min-width:0;">
                <div class="v-name">${v.brand} ${v.model}</div>
                <span class="v-plate">${v.license_plate}</span>
            </div>
            <span class="v-status-badge ${badgeClass}">${badgeLabel}</span>
        `;
        div.addEventListener('click', () => selectVehicle(v.vehicle_id));
        list.appendChild(div);
    });
}

function selectVehicle(vid) {
    selectedId = vid;
    const v = VEHICLES.find(x => x.vehicle_id == vid);
    const pos = vehicleCurrent[vid];
    if (!v || !pos) return;

    // Highlight in list
    document.querySelectorAll('.vehicle-item').forEach(el => el.classList.remove('selected'));
    const item = document.getElementById('vi-' + vid);
    if (item) item.classList.add('selected');

    // Fly to marker
    map.flyTo([pos.lat, pos.lng], 15, { animate:true, duration:1.2 });
    if (markers[vid]) markers[vid].openPopup();

    // Detail panel
    const panel = document.getElementById('detailPanel');
    panel.classList.add('open');

    document.getElementById('d-img').src = '../app/' + v.image_url;
    document.getElementById('d-name').textContent = v.brand + ' ' + v.model;
    document.getElementById('d-plate').textContent = v.license_plate;

    const metrics = document.getElementById('d-metrics');
    const headings = ['N','NE','E','SE','S','SW','W','NW'];
    metrics.innerHTML = `
        <div class="detail-metric">
            <div class="d-icon" style="color:#22c55e;"><i class="fas fa-tachometer-alt"></i></div>
            <div class="d-val" style="color:#22c55e;" id="dm-speed-${vid}">${Math.round(pos.speed)}</div>
            <div class="d-label">km/h</div>
        </div>
        <div class="detail-metric">
            <div class="d-icon" style="color:#38bdf8;"><i class="fas fa-gas-pump"></i></div>
            <div class="d-val" style="color:#38bdf8;" id="dm-fuel-${vid}">${pos.fuel.toFixed(1)}</div>
            <div class="d-label">% Fuel</div>
        </div>
        <div class="detail-metric">
            <div class="d-icon" style="color:#f59e0b;"><i class="fas fa-map-marker-alt"></i></div>
            <div class="d-val" style="font-size:0.85rem;color:#f59e0b;" id="dm-lat-${vid}">${pos.lat.toFixed(4)}</div>
            <div class="d-label">Latitude</div>
        </div>
        <div class="detail-metric">
            <div class="d-icon" style="color:#a855f7;"><i class="fas fa-compass"></i></div>
            <div class="d-val" style="color:#a855f7;" id="dm-hdg-${vid}">${v.is_moving ? headings[pos.heading] : 'IDLE'}</div>
            <div class="d-label">Heading</div>
        </div>
    `;

    // Show telematics link for booked vehicles
    const trackBtn = document.getElementById('d-track-btn');
    if (v.booking_id) {
        trackBtn.href = '../app/track-vehicle.php?id=' + v.booking_id;
        trackBtn.style.display = 'flex';
    } else {
        trackBtn.style.display = 'none';
    }

    const customerDiv = document.getElementById('d-customer');
    if (v.customer_name) {
        customerDiv.innerHTML = `<i class="fas fa-user" style="margin-right:6px;color:var(--accent);"></i>Rented by: <b style="color:var(--text);">${v.customer_name}</b>`;
    } else {
        customerDiv.innerHTML = `<i class="fas fa-map-marker-alt" style="margin-right:6px;color:var(--parked);"></i>Parked at: <b style="color:var(--text);">${v.pickup_area || 'Pickup Location'}</b>`;
    }
}

function updatePanel() {
    if (selectedId) {
        const pos = vehicleCurrent[selectedId];
        const v = VEHICLES.find(x => x.vehicle_id == selectedId);
        const headings = ['N','NE','E','SE','S','SW','W','NW'];
        const speedEl = document.getElementById('dm-speed-' + selectedId);
        const fuelEl = document.getElementById('dm-fuel-' + selectedId);
        const latEl = document.getElementById('dm-lat-' + selectedId);
        const hdgEl = document.getElementById('dm-hdg-' + selectedId);
        if (speedEl) speedEl.textContent = Math.round(pos.speed);
        if (fuelEl) fuelEl.textContent = pos.fuel.toFixed(1);
        if (latEl) latEl.textContent = pos.lat.toFixed(4);
        if (hdgEl) hdgEl.textContent = v.is_moving ? headings[pos.heading] : 'IDLE';
    }
    // Update counters
    const moving = VEHICLES.filter(v => v.is_moving).length;
    const parked = VEHICLES.length - moving;
    document.getElementById('count-moving').textContent = moving;
    document.getElementById('count-parked').textContent = parked;
    document.getElementById('count-total').textContent = VEHICLES.length;
}

// ─── Init ───
initMarkers();
buildVehicleList();
updatePanel();

// Run simulation every 2.5 seconds
setInterval(simulate, 2500);
</script>
</body>
</html>
