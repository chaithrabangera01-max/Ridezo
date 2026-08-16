<?php
session_start();
require '../app/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../app/login.php');
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Receipt ID");
}

$booking_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get user role if available
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if (empty($role)) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) $role = $res['role'];
    $stmt->close();
}

// Fetch booking with vehicle and customer details
$sql = "
    SELECT b.*, 
           v.brand, v.model, v.year, v.license_plate, v.rental_price_per_day,
           u.name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN users u ON b.customer_id = u.user_id
    WHERE b.booking_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    die("Receipt not found.");
}

// Authorization check: Only Admin or the Customer who booked it can view the receipt
if ($role !== 'Admin' && $booking['customer_id'] != $user_id) {
    die("Unauthorized access to receipt.");
}

$start = new DateTime($booking['start_datetime']);
$end = new DateTime($booking['end_datetime']);
$days = $start->diff($end)->days;
if ($days < 1) $days = 1;

$subtotal = $booking['total_amount'] / 1.18;
$gst = $booking['total_amount'] - $subtotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo str_pad($booking_id, 6, "0", STR_PAD_LEFT); ?> | Ridezo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #22c55e;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-color: #f1f5f9;
            --surface: #ffffff;
            --border: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-color);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px;
        }
        
        .receipt-container {
            background: var(--surface);
            max-width: 800px;
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            position: relative;
        }
        
        .receipt-header {
            background: #020617;
            color: #fff;
            padding: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .brand img {
            height: 40px;
            filter: drop-shadow(0 0 10px rgba(34,197,94,0.5));
        }
        
        .brand span {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .receipt-title {
            text-align: right;
        }
        
        .receipt-title h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
            color: var(--primary);
        }
        
        .receipt-title p {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
        }
        
        .receipt-body {
            padding: 40px;
        }
        
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .info-block h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            margin-bottom: 12px;
            font-weight: 700;
        }
        
        .info-block p {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            line-height: 1.5;
        }
        
        .table-wrapper {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 40px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        
        th {
            background: #f8fafc;
            padding: 16px 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        
        td {
            padding: 20px;
            font-size: 14px;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .amount-col {
            text-align: right;
        }
        
        .totals-section {
            display: flex;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px dashed var(--border);
            margin-bottom: 40px;
        }
        
        .totals-grid {
            width: 300px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
        }
        
        .total-label {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 14px;
        }
        
        .total-val {
            font-weight: 700;
            font-size: 14px;
            text-align: right;
        }
        
        .grand-total {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
            padding-top: 16px;
            border-top: 1px solid var(--border);
            margin-top: 8px;
        }
        
        .grand-total-val {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            text-align: right;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            margin-top: 8px;
        }
        
        .receipt-footer {
            text-align: center;
            padding: 24px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .badge-paid { background: #dcfce7; color: #16a34a; }
        .badge-unpaid { background: #fef9c3; color: #ca8a04; }
        
        .actions {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--text-dark);
            color: #fff;
            border: none;
        }
        
        .btn-primary:hover { background: #0f172a; transform: translateY(-2px); }
        
        .btn-outline {
            background: transparent;
            color: var(--text-dark);
            border: 2px solid var(--border);
        }
        
        .btn-outline:hover { border-color: var(--text-muted); background: #f8fafc; }
        
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; border: none; width: 100%; max-width: 100%; }
            .actions { display: none; }
            .receipt-header { background: white; color: black; border-bottom: 2px solid black; padding: 20px 0; }
            .receipt-header * { color: black !important; filter: none !important; }
            .grand-total-val { color: black; }
        }
    
        /* Global Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main, var(--bg, #020617)); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color, var(--accent, #22c55e)); }

</style>
</head>
<body>

<div>
    <div class="receipt-container" id="receiptBox">
        <div class="receipt-header">
            <div class="brand">
                <img src="../assets/ridezo_logo.png" alt="Ridezo">
                <span>Ridezo</span>
            </div>
            <div class="receipt-title">
                <h1>RECEIPT</h1>
                <p>Date: <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></p>
                <p>Booking ID: #<?php echo str_pad($booking_id, 6, "0", STR_PAD_LEFT); ?></p>
            </div>
        </div>
        
        <div class="receipt-body">
            <div class="info-section">
                <div class="info-block">
                    <h3>Billed To</h3>
                    <p><?php echo htmlspecialchars($booking['customer_name']); ?></p>
                    <p style="color:var(--text-muted);"><?php echo htmlspecialchars($booking['customer_email']); ?></p>
                    <p style="color:var(--text-muted);"><?php echo htmlspecialchars($booking['customer_phone']); ?></p>
                </div>
                <div class="info-block" style="text-align: right;">
                    <h3>Booking Status</h3>
                    <p style="margin-bottom:12px;">
                        <span class="badge <?php echo $booking['payment_status'] === 'Paid' ? 'badge-paid' : 'badge-unpaid'; ?>">
                            <?php echo strtoupper($booking['payment_status']); ?>
                        </span>
                    </p>
                    <p>Method: <?php echo $booking['payment_status'] === 'Unpaid' ? 'Cash at Pickup' : 'Online / Card'; ?></p>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Vehicle Description</th>
                            <th>Rental Period</th>
                            <th class="amount-col">Rate</th>
                            <th class="amount-col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight: 800; font-size: 15px; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?> (<?php echo $booking['year']; ?>)
                                </div>
                                <div style="color: var(--text-muted); font-size: 12px;">
                                    Plate: <?php echo htmlspecialchars($booking['license_plate']); ?>
                                </div>
                            </td>
                            <td>
                                <div><?php echo date('M d, Y', strtotime($start->format('Y-m-d'))); ?></div>
                                <div style="color: var(--text-muted); font-size: 12px;">to <?php echo date('M d, Y', strtotime($end->format('Y-m-d'))); ?></div>
                                <div style="font-size: 12px; margin-top: 4px; font-weight: 800; color: var(--primary);">(<?php echo $days; ?> Days)</div>
                            </td>
                            <td class="amount-col">₹<?php echo number_format($booking['rental_price_per_day'], 2); ?> / day</td>
                            <td class="amount-col">₹<?php echo number_format($booking['rental_price_per_day'] * $days, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="totals-section">
                <div class="totals-grid">
                    <div class="total-label">Subtotal:</div>
                    <div class="total-val">₹<?php echo number_format($subtotal, 2); ?></div>
                    
                    <div class="total-label">GST (18%):</div>
                    <div class="total-val">₹<?php echo number_format($gst, 2); ?></div>
                    
                    <div class="total-label grand-total">Total Amount:</div>
                    <div class="total-val grand-total-val">₹<?php echo number_format($booking['total_amount'], 2); ?></div>
                </div>
            </div>
        </div>
        
        <div class="receipt-footer">
            Thank you for choosing Ridezo! For any support, contact us at support@ridezo.com
        </div>
    </div>
    
    <div class="actions">
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Receipt</button>
        <a href="../app/customer/bookings.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
    </div>
</div>

</body>
</html>
