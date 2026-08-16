<?php
require '../app/config.php';
$res = $conn->query("SHOW COLUMNS FROM bookings");
while($r = $res->fetch_assoc()) {
    echo $r['Field'] . " - " . $r['Type'] . "\n";
}
echo "\nPayments:\n";
$res2 = $conn->query("SHOW COLUMNS FROM payments");
if ($res2) {
    while($r = $res2->fetch_assoc()) {
        echo $r['Field'] . " - " . $r['Type'] . "\n";
    }
} else {
    echo "No payments table\n";
}
