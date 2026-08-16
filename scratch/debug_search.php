<?php
require '../app/config.php';
$res = $conn->query("SELECT COUNT(*) as count FROM vehicles");
$row = $res->fetch_assoc();
echo "Total vehicles: " . $row['count'] . "\n";

$res = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status = 'Available'");
$row = $res->fetch_assoc();
echo "Available vehicles: " . $row['count'] . "\n";

$res = $conn->query("SELECT * FROM vehicles");
while($v = $res->fetch_assoc()) {
    echo "ID: {$v['vehicle_id']} | Brand: {$v['brand']} | Model: {$v['model']} | Type: {$v['vehicle_type']} | Status: {$v['status']} | Area: {$v['area']} | Loc: {$v['pickup_location']} | Available: {$v['available_from']} to {$v['available_until']}\n";
}
?>
