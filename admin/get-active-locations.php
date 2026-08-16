<?php
require '../app/config.php';
header('Content-Type: application/json');

// Ensure user is Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$query = "
    SELECT b.booking_id, b.latitude, b.longitude, b.tracking_active, b.payment_status,
           v.brand, v.model, v.license_plate, u.name as customer_name
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN users u ON b.customer_id = u.user_id
    WHERE b.status IN ('Confirmed', 'Active') AND b.tracking_active = 1
";

$res = $conn->query($query);
$locations = [];

if ($res) {
    while($row = $res->fetch_assoc()) {
        $locations[] = [
            'booking_id' => intval($row['booking_id']),
            'brand' => $row['brand'],
            'model' => $row['model'],
            'license_plate' => $row['license_plate'],
            'customer_name' => $row['customer_name'],
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'payment_status' => $row['payment_status']
        ];
    }
}

echo json_encode(['success' => true, 'locations' => $locations]);
?>
