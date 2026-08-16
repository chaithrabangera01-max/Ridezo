<?php
session_start();
require '../app/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../app/login.php?msg=unauthorized');
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_POST['pickup']) || !isset($_POST['return'])) {
    header('Location: ../main/index.php');
    exit();
}

$vehicle_id = intval($_GET['id']);
$pickup = $_POST['pickup'];
$return = $_POST['return'];

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

$start_date = new DateTime($pickup);
$end_date = new DateTime($return);
$days = $start_date->diff($end_date)->days;
if ($days < 1) $days = 1;

$subtotal = $days * $vehicle['rental_price_per_day'];
$gst = $subtotal * 0.18;
$total = $subtotal + $gst;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ridezo | Secure Payment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020617;
            --surface: #0f172a;
            --surface-2: #1e293b;
            --border: rgba(255,255,255,0.08);
            --accent: #22c55e;
            --accent-soft: rgba(34,197,94,0.1);
            --accent-glow: rgba(34,197,94,0.25);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .bg-glow-1 { position:fixed; top:-150px; left:-150px; width:500px; height:500px; background:radial-gradient(circle,rgba(34,197,94,0.08) 0%,transparent 70%); filter:blur(60px); z-index:0; pointer-events:none; }
        .bg-glow-2 { position:fixed; bottom:-150px; right:-150px; width:600px; height:600px; background:radial-gradient(circle,rgba(59,130,246,0.05) 0%,transparent 70%); filter:blur(60px); z-index:0; pointer-events:none; }
        .top-nav { position: fixed; top: 0; width: 100%; z-index: 100; background: rgba(2,6,23,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); }
        .nav-inner { max-width: 1200px; margin: 0 auto; padding: 0 32px; height: 70px; display: flex; align-items: center; justify-content: space-between; }
        .nav-logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .nav-logo i { font-size:1.8rem; color:var(--accent); filter:drop-shadow(0 0 10px var(--accent-glow)); }
        .nav-logo span { font-size:1.5rem; font-weight:800; color:#fff; letter-spacing:-0.5px; }
        .nav-link { color:var(--text-muted); text-decoration:none; font-weight:600; font-size:0.9rem; transition:color 0.25s; }
        .nav-link:hover { color:var(--accent); }
        .page-wrapper { max-width:1200px; margin:0 auto; padding:110px 24px 60px; position:relative; z-index:1; }
        .step-bar { display:flex; align-items:center; gap:10px; font-size:13px; font-weight:700; margin-bottom:40px; }
        .step-dot { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
        .step-done { background:var(--accent); color:#020617; }
        .step-active { background:var(--accent); color:#020617; box-shadow:0 0 0 4px var(--accent-soft); }
        .step-line { flex:1; max-width:40px; height:2px; background:var(--accent); border-radius:2px; }
        .step-label { color:var(--text-muted); font-size:13px; font-weight:600; }
        .step-label.active { color:var(--accent); }
        .pay-grid { display:grid; grid-template-columns:1fr 420px; gap:28px; align-items:start; }
        @media(max-width:900px) { .pay-grid { grid-template-columns:1fr; } }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 28px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .card-sticky { position: sticky; top: 90px; }
        .section-label { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:var(--text-muted); margin-bottom:20px; }
        .vehicle-img { width:100%; height:160px; border-radius:16px; overflow:hidden; position:relative; margin-bottom:18px; }
        .vehicle-img img { width:100%; height:100%; object-fit:cover; }
        .vehicle-img-overlay { position:absolute; inset:0; background:linear-gradient(to top,rgba(2,6,23,0.7),transparent); }
        .vehicle-img-label { position:absolute; bottom:12px; left:14px; }
        .vehicle-img-label p { margin:0; }
        .vehicle-badge { position:absolute; top:10px; left:10px; background:var(--accent); color:#020617; font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px; }
        .info-row { display:flex; align-items:center; gap:12px; padding:12px; background:var(--surface-2); border-radius:12px; border:1px solid var(--border); margin-bottom:10px; }
        .info-icon { width:36px; height:36px; border-radius:10px; background:var(--accent-soft); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .info-icon i { color:var(--accent); font-size:13px; }
        .info-sub { font-size:11px; color:var(--text-muted); font-weight:700; margin-bottom:2px; }
        .info-val { font-size:13px; color:var(--text); font-weight:700; }
        .date-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px; }
        .date-cell { padding:12px; background:var(--surface-2); border-radius:12px; border:1px solid var(--border); }
        .price-divider { border:none; border-top:1px solid var(--border); margin:16px 0; }
        .price-row { display:flex; justify-content:space-between; font-size:13px; margin-bottom:10px; }
        .price-row span:first-child { color:var(--text-muted); font-weight:600; }
        .price-row span:last-child { color:var(--text); font-weight:700; }
        .price-total { display:flex; justify-content:space-between; align-items:center; padding-top:14px; border-top:1px solid var(--border); }
        .price-total-label { font-size:15px; font-weight:800; color:var(--text); }
        .price-total-amount { font-size:26px; font-weight:800; color:var(--accent); text-shadow:0 0 20px var(--accent-glow); }
        .trust-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; text-align:center; margin-top:18px; padding-top:18px; border-top:1px solid var(--border); }
        .trust-item i { display:block; margin-bottom:4px; color:var(--accent); }
        .trust-item p { font-size:11px; color:var(--text-muted); font-weight:600; margin:0; line-height:1.3; }
        .method-tabs { display:flex; gap:10px; margin-bottom:28px; }
        .method-tab { flex:1; display:flex; align-items:center; justify-content:center; gap:7px; padding:12px 10px; border-radius:14px; font-weight:700; font-size:13px; border:1.5px solid var(--border); color:var(--text-muted); cursor:pointer; background:var(--surface-2); transition:var(--transition); }
        .method-tab:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-soft); }
        .method-tab.active { border-color:var(--accent); color:var(--accent); background:var(--accent-soft); box-shadow:0 0 0 3px var(--accent-glow); }
        .method-panel { display:none; }
        .method-panel.active { display:block; animation:fadeUp 0.3s ease; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        @keyframes spin { to{transform:rotate(360deg)} }
        @keyframes popIn { from{opacity:0;transform:scale(0.8)} to{opacity:1;transform:scale(1)} }
        @keyframes checkDraw { from{stroke-dashoffset:60} to{stroke-dashoffset:0} }
        .cc-preview { background:linear-gradient(135deg,#0f2540 0%,#0f3460 60%,#16213e 100%); border-radius:18px; padding:24px; position:relative; overflow:hidden; box-shadow:0 20px 50px rgba(0,0,0,0.5); margin-bottom:20px; border:1px solid rgba(34,197,94,0.2); }
        .cc-preview::before { content:''; position:absolute; top:-40px; right:-40px; width:160px; height:160px; border-radius:50%; background:rgba(34,197,94,0.05); }
        .cc-preview::after { content:''; position:absolute; bottom:-50px; right:30px; width:100px; height:100px; border-radius:50%; background:rgba(34,197,94,0.03); }
        .field-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:6px; display:block; }
        .field-input { width:100%; padding:13px 16px; border-radius:12px; border:1.5px solid var(--border); font-family:'Plus Jakarta Sans',sans-serif; font-size:14px; font-weight:600; color:var(--text); background:var(--surface-2); transition:all 0.2s; outline:none; }
        .field-input:focus { border-color:var(--accent); background:rgba(15,23,42,0.9); box-shadow:0 0 0 3px var(--accent-soft); }
        .field-input.has-icon { padding-left:44px; }
        .field-input::placeholder { color:#334155; font-weight:500; }
        .input-wrap { position:relative; }
        .input-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#475569; font-size:14px; pointer-events:none; }
        .upi-chips { display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap; }
        .upi-chip { padding:7px 14px; border-radius:20px; font-size:12px; font-weight:700; border:1.5px solid var(--border); color:var(--text-muted); background:var(--surface-2); cursor:pointer; transition:all 0.2s; }
        .upi-chip:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-soft); }
        .cash-box { background:var(--accent-soft); border:1px solid rgba(34,197,94,0.25); border-radius:16px; padding:28px; text-align:center; }
        .cash-icon-wrap { width:56px; height:56px; background:rgba(34,197,94,0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; border:1.5px solid rgba(34,197,94,0.3); }
        .cash-icon-wrap i { font-size:24px; color:var(--accent); }
        .cash-box h3 { font-size:16px; font-weight:800; color:var(--text); margin-bottom:8px; }
        .cash-box p { font-size:13px; color:var(--text-muted); line-height:1.6; }
        .pay-btn { width:100%; padding:16px; border-radius:14px; font-size:15px; font-weight:800; border:none; cursor:pointer; background:linear-gradient(90deg,var(--accent) 0%,#10b981 50%,var(--accent) 100%); background-size:200% auto; color:#020617; transition:var(--transition); box-shadow:0 8px 25px rgba(34,197,94,0.3); font-family:'Plus Jakarta Sans',sans-serif; animation:shineFlow 3s linear infinite; }
        @keyframes shineFlow { 0%{background-position:-200% center} 100%{background-position:200% center} }
        .pay-btn:hover { transform:translateY(-3px); box-shadow:0 12px 35px rgba(34,197,94,0.5); }
        .pay-btn:disabled { opacity:0.6; cursor:not-allowed; transform:none; animation:none; }
        .spinner { width:18px; height:18px; border:2.5px solid rgba(2,6,23,0.3); border-top-color:#020617; border-radius:50%; animation:spin 0.8s linear infinite; display:inline-block; vertical-align:middle; margin-right:6px; }
        .alert-error { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); border-radius:14px; padding:14px 16px; display:none; align-items:center; gap:10px; margin-bottom:20px; }
        .alert-error.show { display:flex; animation:fadeUp 0.3s ease; }
        .alert-error span { color:#fca5a5; font-size:13px; font-weight:600; }
        #successScreen { display:none; max-width:520px; margin:60px auto 0; }
        #successScreen.show { display:block; animation:popIn 0.5s cubic-bezier(0.34,1.56,0.64,1) both; }
        .success-card { background:var(--surface); border:1px solid rgba(34,197,94,0.2); border-radius:28px; padding:48px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.4), 0 0 0 1px var(--border); }
        .success-icon-wrap { width:90px; height:90px; border-radius:50%; background:var(--accent-soft); border:2px solid rgba(34,197,94,0.3); display:flex; align-items:center; justify-content:center; margin:0 auto 24px; box-shadow:0 0 40px var(--accent-glow); }
        .success-heading { font-size:26px; font-weight:800; color:var(--text); margin-bottom:8px; }
        .success-sub { color:var(--text-muted); font-weight:600; font-size:14px; margin-bottom:28px; }
        .txn-box { background:var(--surface-2); border:1px solid rgba(34,197,94,0.2); border-radius:14px; padding:16px 20px; margin-bottom:16px; text-align:left; }
        .txn-label { color:var(--text-muted); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:4px; }
        .txn-value { color:var(--accent); font-weight:800; font-size:16px; letter-spacing:2px; font-family:monospace; }
        .details-box { background:var(--surface-2); border:1px solid var(--border); border-radius:14px; padding:16px 20px; margin-bottom:28px; text-align:left; }
        .detail-row { display:flex; justify-content:space-between; font-size:14px; margin-bottom:10px; }
        .detail-row span:first-child { color:var(--text-muted); font-weight:600; }
        .detail-row span:last-child { color:var(--text); font-weight:700; }
        .detail-total { display:flex; justify-content:space-between; font-size:14px; padding-top:10px; border-top:1px solid var(--border); }
        .detail-total span:first-child { color:var(--text); font-weight:800; }
        .detail-total span:last-child { color:var(--accent); font-weight:800; font-size:18px; }
        .btn-go-bookings { display:block; width:100%; padding:14px; border-radius:14px; background:var(--accent); color:#020617; font-weight:800; font-size:14px; border:none; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif; text-decoration:none; text-align:center; transition:var(--transition); box-shadow:0 6px 20px rgba(34,197,94,0.3); }
        .btn-go-bookings:hover { transform:translateY(-2px); box-shadow:0 10px 30px rgba(34,197,94,0.4); }
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
    <div class="nav-inner">
        <a href="../main/index.php" class="nav-logo">
            <img src="../assets/ridezo_logo.png" alt="Ridezo Logo" class="logo-img" style="height: 34px; filter: drop-shadow(0 0 10px var(--accent-glow));">
            <span>Ridezo</span>
        </a>
        <div style="display:flex;gap:28px;align-items:center;">
            <a href="../main/explore.php" class="nav-link">Explore</a>
            <a href="../app/customer/bookings.php" class="nav-link">My Bookings</a>
            <a href="javascript:history.back()" class="nav-link"><i class="fas fa-arrow-left" style="margin-right:4px;"></i>Back</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="../app/logout.php" style="padding:8px 20px;background:var(--accent-soft);color:var(--accent);border:1px solid rgba(34,197,94,0.3);border-radius:12px;font-weight:800;font-size:13px;text-decoration:none;">
                    <i class="fas fa-sign-out-alt" style="margin-right:4px;"></i>Logout
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div id="successScreen">
    <div class="success-card">
        <div class="success-icon-wrap">
            <svg width="44" height="44" viewBox="0 0 44 44" fill="none">
                <circle cx="22" cy="22" r="22" fill="#22c55e" opacity="0.15"/>
                <path d="M13 22l7 7 12-13" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="60" style="animation:checkDraw 0.6s 0.3s ease forwards;stroke-dashoffset:60"/>
            </svg>
        </div>
        <h2 id="successHeading" class="success-heading">Payment Successful! 🎉</h2>
        <p class="success-sub">Your <strong style="color:var(--accent);"><?php echo htmlspecialchars($vehicle['brand'].' '.$vehicle['model']); ?></strong> is booked and confirmed!</p>
        <div class="txn-box">
            <div class="txn-label">Booking / Transaction ID</div>
            <div id="txnIdDisplay" class="txn-value"></div>
        </div>
        <div class="details-box">
            <div class="detail-row">
                <span>Vehicle</span>
                <span><?php echo htmlspecialchars($vehicle['brand'].' '.$vehicle['model'].' ('.$vehicle['year'].')'); ?></span>
            </div>
            <div class="detail-row">
                <span>Duration</span>
                <span><?php echo $days; ?> Days</span>
            </div>
            <div class="detail-row" style="margin-bottom:0;">
                <span>Payment</span>
                <span id="successMethod"></span>
            </div>
            <div class="detail-total">
                <span>Amount</span>
                <span>₹<?php echo number_format($total, 2); ?></span>
            </div>
        </div>
        <div style="display:flex;gap:12px;margin-top:10px;">
            <a href="../app/customer/bookings.php" class="btn-go-bookings" style="flex:1;">
                <i class="fas fa-calendar-check" style="margin-right:6px;"></i> View My Bookings
            </a>
            <a href="#" id="btnDownloadReceipt" target="_blank" class="btn-go-bookings" style="flex:1;background:var(--surface-2);color:var(--text);border:1px solid var(--border);">
                <i class="fas fa-file-invoice" style="margin-right:6px;"></i> Download Receipt
            </a>
        </div>
        <p style="color:var(--text-muted);font-size:12px;margin-top:14px;">Confirmation has been saved to your account.</p>
    </div>
</div>

<div id="paymentForm" class="page-wrapper">
    <div class="step-bar">
        <div class="step-dot step-done"><i class="fas fa-check" style="font-size:11px;"></i></div>
        <span class="step-label">Select Car</span>
        <div class="step-line"></div>
        <div class="step-dot step-done"><i class="fas fa-check" style="font-size:11px;"></i></div>
        <span class="step-label">Booking Details</span>
        <div class="step-line"></div>
        <div class="step-dot step-active">3</div>
        <span class="step-label active">Payment</span>
    </div>

    <div class="pay-grid">
        <div class="card card-sticky">
            <p class="section-label">Booking Summary</p>
            <div class="vehicle-img">
                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($vehicle['brand'].' '.$vehicle['model']); ?>">
                <div class="vehicle-img-overlay"></div>
                <div class="vehicle-img-label">
                    <p style="color:#fff;font-weight:800;font-size:16px;"><?php echo htmlspecialchars($vehicle['brand'].' '.$vehicle['model']); ?></p>
                    <p style="color:var(--accent);font-size:12px;font-weight:600;"><?php echo htmlspecialchars($vehicle['vehicle_type'].' · '.$vehicle['year']); ?></p>
                </div>
                <span class="vehicle-badge">Available</span>
            </div>
            <div class="info-row">
                <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div>
                    <div class="info-sub">Pickup Location</div>
                    <div class="info-val"><?php echo htmlspecialchars($vehicle['area']); ?></div>
                </div>
            </div>
            <div class="date-grid">
                <div class="date-cell">
                    <div class="info-sub">Pick-up</div>
                    <div class="info-val"><?php echo date('M d, Y', strtotime($pickup)); ?></div>
                </div>
                <div class="date-cell">
                    <div class="info-sub">Return</div>
                    <div class="info-val"><?php echo date('M d, Y', strtotime($return)); ?></div>
                </div>
            </div>
            <hr class="price-divider">
            <div class="price-row">
                <span>Rental Rate</span>
                <span>₹<?php echo number_format($vehicle['rental_price_per_day'], 0); ?> × <?php echo $days; ?> days</span>
            </div>
            <div class="price-row">
                <span>Subtotal</span>
                <span>₹<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="price-row" style="margin-bottom:0;">
                <span>GST (18%)</span>
                <span>₹<?php echo number_format($gst, 2); ?></span>
            </div>
            <div class="price-total">
                <span class="price-total-label">Total Amount</span>
                <span class="price-total-amount">₹<?php echo number_format($total, 2); ?></span>
            </div>
            <div class="trust-grid">
                <div class="trust-item"><i class="fas fa-shield-alt"></i><p>SSL Secure</p></div>
                <div class="trust-item"><i class="fas fa-bolt"></i><p>Instant Confirm</p></div>
                <div class="trust-item"><i class="fas fa-headset"></i><p>24/7 Support</p></div>
            </div>
        </div>

        <div class="card">
            <p class="section-label">Choose Payment Method</p>
            <div class="method-tabs">
                <div class="method-tab active" onclick="switchMethod('card', this)"><i class="fas fa-credit-card"></i> Card</div>
                <div class="method-tab" onclick="switchMethod('upi', this)"><i class="fas fa-mobile-alt"></i> UPI</div>
                <div class="method-tab" onclick="switchMethod('cash', this)"><i class="fas fa-money-bill-wave"></i> Cash</div>
            </div>
            <div id="alertError" class="alert-error">
                <i class="fas fa-exclamation-circle" style="color:#ef4444;"></i>
                <span id="errorText"></span>
            </div>

            <div id="cardPanel" class="method-panel active">
                <div class="cc-preview">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:36px;">
                        <img src="https://raw.githubusercontent.com/muhammederdem/credit-card-form/master/src/assets/images/chip.png" style="width:42px;">
                        <i class="fab fa-cc-visa" style="color:rgba(255,255,255,0.7);font-size:30px;"></i>
                    </div>
                    <div id="cardNumPreview" style="color:#fff;font-size:19px;letter-spacing:3px;font-weight:700;margin-bottom:22px;font-family:monospace;">#### #### #### ####</div>
                    <div style="display:flex;justify-content:space-between;color:#fff;">
                        <div>
                            <p style="font-size:9px;text-transform:uppercase;opacity:0.5;margin-bottom:3px;font-weight:700;">Card Holder</p>
                            <p id="cardNamePreview" style="font-size:13px;font-weight:700;text-transform:uppercase;">FULL NAME</p>
                        </div>
                        <div style="text-align:right;">
                            <p style="font-size:9px;text-transform:uppercase;opacity:0.5;margin-bottom:3px;font-weight:700;">Expires</p>
                            <p id="cardExpPreview" style="font-size:13px;font-weight:700;">MM/YY</p>
                        </div>
                    </div>
                </div>

                <div style="display:grid;gap:16px;">
                    <div>
                        <label class="field-label">Card Holder Name</label>
                        <div class="input-wrap">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" id="cardName" class="field-input has-icon" placeholder="e.g. John Doe" oninput="updateCardPreview()">
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Card Number</label>
                        <div class="input-wrap">
                            <i class="fas fa-credit-card input-icon"></i>
                            <input type="text" id="cardNo" class="field-input has-icon" placeholder="0000 0000 0000 0000" maxlength="19" oninput="formatCardNo(this);updateCardPreview()">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="field-label">Expiry</label>
                            <input type="text" id="cardExp" class="field-input" placeholder="MM/YY" maxlength="5" oninput="formatExp(this);updateCardPreview()">
                        </div>
                        <div>
                            <label class="field-label">CVV</label>
                            <input type="password" id="cardCvv" class="field-input" placeholder="***" maxlength="3">
                        </div>
                    </div>
                </div>
            </div>

            <div id="upiPanel" class="method-panel">
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;font-weight:600;">Select your UPI app or enter ID:</p>
                <div class="upi-chips">
                    <div class="upi-chip" onclick="setUpi('@okaxis')"><i class="fas fa-mobile-alt" style="margin-right:4px;"></i>Google Pay</div>
                    <div class="upi-chip" onclick="setUpi('@ybl')"><i class="fas fa-mobile-alt" style="margin-right:4px;"></i>PhonePe</div>
                    <div class="upi-chip" onclick="setUpi('@paytm')"><i class="fas fa-mobile-alt" style="margin-right:4px;"></i>Paytm</div>
                </div>
                <label class="field-label">UPI ID</label>
                <div class="input-wrap">
                    <i class="fas fa-at input-icon"></i>
                    <input type="text" id="upiId" class="field-input has-icon" placeholder="username@upi">
                </div>
            </div>

            <div id="cashPanel" class="method-panel">
                <div class="cash-box">
                    <div class="cash-icon-wrap">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h3>Rent by Cash</h3>
                    <p>You have selected to pay by cash. Please have the exact amount of <strong style="color:var(--accent);">₹<?php echo number_format($total, 2); ?></strong> ready at the time of vehicle pickup.</p>
                </div>
            </div>

            <div style="margin-top:28px;">
                <button id="payBtn" class="pay-btn" onclick="processPayment()">
                    <i class="fas fa-lock" style="margin-right:6px;"></i>Pay ₹<?php echo number_format($total, 2); ?> Securely
                </button>
                <p style="text-align:center;color:var(--text-muted);font-size:12px;margin-top:14px;font-weight:600;">
                    <i class="fas fa-shield-alt" style="margin-right:4px;color:var(--accent);"></i>256-bit AES Encrypted Connection
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    let currentMethod = 'card';

    function switchMethod(method, el) {
        currentMethod = method;
        document.querySelectorAll('.method-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.method-panel').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
        document.getElementById(method + 'Panel').classList.add('active');
        document.getElementById('alertError').classList.remove('show');

        const btn = document.getElementById('payBtn');
        if (method === 'cash') {
            btn.innerHTML = '<i class="fas fa-check-circle" style="margin-right:6px;"></i>Confirm Booking';
        } else {
            btn.innerHTML = '<i class="fas fa-lock" style="margin-right:6px;"></i>Pay ₹<?php echo number_format($total, 2); ?> Securely';
        }
    }

    function formatCardNo(el) {
        let v = el.value.replace(/\D/g,'').match(/.{1,4}/g);
        el.value = v ? v.join(' ') : '';
    }
    function isValidLuhn(cardNumber) {
        let sum = 0;
        let shouldDouble = false;
        for (let i = cardNumber.length - 1; i >= 0; i--) {
            let digit = parseInt(cardNumber.charAt(i), 10);
            if (shouldDouble) {
                digit *= 2;
                if (digit > 9) digit -= 9;
            }
            sum += digit;
            shouldDouble = !shouldDouble;
        }
        return sum % 10 === 0;
    }

    function formatExp(el) {
        let v = el.value.replace(/\D/g,'');
        el.value = v.length > 2 ? v.slice(0,2)+'/'+v.slice(2,4) : v;
        const err = document.getElementById('alertError');
        const errTxt = document.getElementById('errorText');
        if (el.value.length === 5) {
            let match = el.value.match(/^(0[1-9]|1[0-2])\/([0-9]{2})$/);
            if (!match) {
                errTxt.innerText = 'Invalid expiry month.';
                err.classList.add('show');
                el.style.borderColor = '#ef4444';
            } else {
                let expMonth = parseInt(match[1], 10);
                let expYear = parseInt('20' + match[2], 10);
                let currentDate = new Date();
                let currentMonth = currentDate.getMonth() + 1;
                let currentYear = currentDate.getFullYear();
                if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
                    errTxt.innerText = 'Card has expired.';
                    err.classList.add('show');
                    el.style.borderColor = '#ef4444';
                } else {
                    err.classList.remove('show');
                    el.style.borderColor = '';
                }
            }
        } else {
            err.classList.remove('show');
            el.style.borderColor = '';
        }
    }
    function updateCardPreview() {
        document.getElementById('cardNumPreview').innerText = document.getElementById('cardNo').value || '#### #### #### ####';
        document.getElementById('cardNamePreview').innerText = document.getElementById('cardName').value || 'FULL NAME';
        document.getElementById('cardExpPreview').innerText = document.getElementById('cardExp').value || 'MM/YY';
    }
    function setUpi(suffix) {
        document.getElementById('upiId').value = 'user' + suffix;
    }

    function processPayment() {
        const btn = document.getElementById('payBtn');
        const err = document.getElementById('alertError');
        const errTxt = document.getElementById('errorText');

        if (currentMethod !== 'cash') {
            if (currentMethod === 'card') {
                const cardName = document.getElementById('cardName').value.trim();
                const cardNo = document.getElementById('cardNo').value.replace(/\s+/g, '');
                const cardExp = document.getElementById('cardExp').value.trim();
                const cardCvv = document.getElementById('cardCvv').value.trim();

                if (!cardName || !/^[a-zA-Z\s]{3,50}$/.test(cardName)) {
                    errTxt.innerText = 'Please enter a valid card holder name.';
                    err.classList.add('show');
                    return;
                }
                if (!cardNo || !/^\d{16}$/.test(cardNo) || /^0+$/.test(cardNo) || !isValidLuhn(cardNo)) {
                    errTxt.innerText = 'Please enter a valid 16-digit card number.';
                    err.classList.add('show');
                    return;
                }
                let expMatch = cardExp.match(/^(0[1-9]|1[0-2])\/([0-9]{2})$/);
                if (!expMatch) {
                    errTxt.innerText = 'Please enter a valid expiry date (MM/YY).';
                    err.classList.add('show');
                    return;
                }
                let expMonth = parseInt(expMatch[1], 10);
                let expYear = parseInt('20' + expMatch[2], 10);
                let currentDate = new Date();
                let currentMonth = currentDate.getMonth() + 1;
                let currentYear = currentDate.getFullYear();
                if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
                    errTxt.innerText = 'The card has expired.';
                    err.classList.add('show');
                    return;
                }
                if (!cardCvv || !/^\d{3}$/.test(cardCvv)) {
                    errTxt.innerText = 'Please enter a valid 3-digit CVV.';
                    err.classList.add('show');
                    return;
                }
            } else if (currentMethod === 'upi') {
                const upiId = document.getElementById('upiId').value.trim();
                if (/[A-Z]/.test(upiId)) {
                    errTxt.innerText = 'UPI ID must not contain capital letters.';
                    err.classList.add('show');
                    return;
                }
                if (!upiId || !/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/.test(upiId)) {
                    errTxt.innerText = 'Please enter a valid UPI ID (e.g. name@bank).';
                    err.classList.add('show');
                    return;
                }
            }
        }

        err.classList.remove('show');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> ' + (currentMethod === 'cash' ? 'Confirming...' : 'Processing...');

        const payload = {
            vehicle_id: <?php echo $vehicle_id; ?>,
            pickup: '<?php echo $pickup; ?>',
            return_date: '<?php echo $return; ?>',
            total_amount: <?php echo $total; ?>,
            payment_method: currentMethod
        };

        if (currentMethod === 'card') {
            payload.card_name = document.getElementById('cardName').value.trim();
            payload.card_no = document.getElementById('cardNo').value.replace(/\s+/g, '');
            payload.card_exp = document.getElementById('cardExp').value.trim();
            payload.card_cvv = document.getElementById('cardCvv').value.trim();
        } else if (currentMethod === 'upi') {
            payload.upi_id = document.getElementById('upiId').value.trim();
        }

        fetch('process_payment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('paymentForm').style.display = 'none';
                const screen = document.getElementById('successScreen');
                screen.classList.add('show');
                document.getElementById('txnIdDisplay').innerText = data.txId;
                document.getElementById('successMethod').innerText = currentMethod === 'cash' ? 'Cash (Pay at Pickup)' : currentMethod.toUpperCase();
                if (currentMethod === 'cash') {
                    document.getElementById('successHeading').innerText = 'Booking Confirmed! 🎉';
                }
                document.getElementById('btnDownloadReceipt').href = 'receipt.php?id=' + data.booking_id;
                window.scrollTo({top: 0, behavior: 'smooth'});
            } else {
                errTxt.innerText = data.message || 'Something went wrong. Please try again.';
                err.classList.add('show');
                btn.disabled = false;
                btn.innerHTML = currentMethod === 'cash'
                    ? '<i class="fas fa-check-circle" style="margin-right:6px;"></i>Confirm Booking'
                    : '<i class="fas fa-lock" style="margin-right:6px;"></i>Pay ₹<?php echo number_format($total, 2); ?> Securely';
            }
        })
        .catch(() => {
            errTxt.innerText = 'Network error. Please check your connection.';
            err.classList.add('show');
            btn.disabled = false;
            btn.innerHTML = currentMethod === 'cash'
                ? '<i class="fas fa-check-circle" style="margin-right:6px;"></i>Confirm Booking'
                : '<i class="fas fa-lock" style="margin-right:6px;"></i>Pay ₹<?php echo number_format($total, 2); ?> Securely';
        });
    }
</script>
</body>
</html>