<?php
require '../app/config.php';

// Add columns to bookings table
$queries = [
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS tracking_active TINYINT(1) DEFAULT 0",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) DEFAULT NULL",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) DEFAULT NULL"
];

foreach ($queries as $query) {
    if ($conn->query($query)) {
        echo "Success: $query\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
?>
