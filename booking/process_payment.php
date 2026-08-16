<?php
session_start();
require '../app/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$vehicle_id     = intval($data['vehicle_id']);
$pickup         = $data['pickup'];
$return_date    = $data['return_date'];
$total_amount   = floatval($data['total_amount']);
$payment_method = isset($data['payment_method']) ? $data['payment_method'] : 'card';

if (!$vehicle_id || !$pickup || !$return_date) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

if ($payment_method === 'card') {
    $card_name = isset($data['card_name']) ? trim($data['card_name']) : '';
    $card_no = isset($data['card_no']) ? str_replace(' ', '', $data['card_no']) : '';
    $card_exp = isset($data['card_exp']) ? trim($data['card_exp']) : '';
    $card_cvv = isset($data['card_cvv']) ? trim($data['card_cvv']) : '';

    if (empty($card_name) || !preg_match('/^[a-zA-Z\s]{3,50}$/', $card_name)) {
        echo json_encode(['success' => false, 'message' => 'Invalid card holder name.']);
        exit();
    }

    if (empty($card_no) || !preg_match('/^\d{16}$/', $card_no)) {
        echo json_encode(['success' => false, 'message' => 'Invalid card number.']);
        exit();
    }

    // Luhn Algorithm check
    $sum = 0;
    $shouldDouble = false;
    for ($i = strlen($card_no) - 1; $i >= 0; $i--) {
        $digit = intval($card_no[$i]);
        if ($shouldDouble) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $shouldDouble = !$shouldDouble;
    }
    if ($sum % 10 !== 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid card number (Luhn check failed).']);
        exit();
    }

    if (empty($card_exp) || !preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $card_exp, $matches)) {
        echo json_encode(['success' => false, 'message' => 'Invalid expiry date format. Use MM/YY.']);
        exit();
    }
    
    $exp_month = intval($matches[1]);
    $exp_year = intval("20" . $matches[2]);
    $current_year = intval(date('Y'));
    $current_month = intval(date('m'));
    
    if ($exp_year < $current_year || ($exp_year === $current_year && $exp_month < $current_month)) {
        echo json_encode(['success' => false, 'message' => 'The card has expired.']);
        exit();
    }

    if (empty($card_cvv) || !preg_match('/^\d{3}$/', $card_cvv)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CVV.']);
        exit();
    }

} else if ($payment_method === 'upi') {
    $upi_id = isset($data['upi_id']) ? trim($data['upi_id']) : '';

    if (preg_match('/[A-Z]/', $upi_id)) {
        echo json_encode(['success' => false, 'message' => 'UPI ID must not contain capital letters.']);
        exit();
    }

    if (empty($upi_id) || !preg_match('/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/', $upi_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid UPI ID format.']);
        exit();
    }
}


$customer_id = $_SESSION['user_id'];

// Get vehicle info
$stmt = $conn->prepare("SELECT v.seller_id, v.rental_price_per_day, u.role FROM vehicles v JOIN users u ON v.seller_id = u.user_id WHERE v.vehicle_id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vehicle) {
    echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
    exit();
}

if ($vehicle['role'] === 'Seller') {
    $commission     = $total_amount * 0.10;
    $seller_earning = $total_amount * 0.90;
} else {
    $commission     = 0;
    $seller_earning = 0;
}
$current_time   = date('H:i:s');
$start_datetime = $pickup . " " . $current_time;
$end_datetime   = $return_date . " " . $current_time;

// Cash payments are Unpaid until picked up; online payments are Paid immediately
$payment_status = ($payment_method === 'cash') ? 'Unpaid' : 'Paid';

$stmt = $conn->prepare("INSERT INTO bookings (vehicle_id, seller_id, customer_id, start_datetime, end_datetime, total_amount, commission, seller_earning, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?)");
$stmt->bind_param("iiissddds", $vehicle_id, $vehicle['seller_id'], $customer_id, $start_datetime, $end_datetime, $total_amount, $commission, $seller_earning, $payment_status);

if ($stmt->execute()) {
    $booking_id = $conn->insert_id;
    $prefix = ($payment_method === 'cash') ? 'BKG' : 'TXN';
    $txId = $prefix . strtoupper(uniqid());
    echo json_encode(['success' => true, 'txId' => $txId, 'booking_id' => $booking_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();
?>
