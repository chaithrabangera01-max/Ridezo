<?php
require 'd:/xaamp/htdocs/Ridezo/app/config.php';
$res = $conn->query("SELECT vehicle_id, status, available_from, available_until, pickup_location FROM vehicles");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
