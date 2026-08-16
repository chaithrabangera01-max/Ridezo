<?php
require 'app/config.php';
$res = $conn->query("SELECT v.vehicle_id, v.brand, v.model, v.status, u.name, u.role FROM vehicles v JOIN users u ON v.seller_id = u.user_id");
echo "Vehicles in DB:\n";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
