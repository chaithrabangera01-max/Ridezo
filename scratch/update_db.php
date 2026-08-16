<?php
require 'd:/xaamp/htdocs/Ridezo/app/config.php';
$queries = [
    "ALTER TABLE users MODIFY COLUMN role ENUM('Admin', 'Seller', 'Customer') NOT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT AFTER phone",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(50) AFTER address",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(50) AFTER city",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS pincode VARCHAR(10) AFTER state",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(100) AFTER pincode",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20) AFTER emergency_contact_name",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS license_expiry_date DATE AFTER license_number"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Success: $q\n";
    } else {
        echo "Error: " . $conn->error . " | Query: $q\n";
    }
}
?>
