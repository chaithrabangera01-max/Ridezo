<?php
$conn = mysqli_connect("localhost", "root", "", "ridezo");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$name = 'System Admin';
$email = 'admin@ridezo.com';
$password = 'Admin@123';
$role = 'Admin';
$hash = password_hash($password, PASSWORD_BCRYPT);

// Check if table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'users'");
if ($checkTable->num_rows == 0) {
    die("Table 'users' does not exist. Please import database.sql first.");
}

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, TRUE)");
$stmt->bind_param("ssss", $name, $email, $hash, $role);

if ($stmt->execute()) {
    echo "Admin user created successfully!\n";
} else {
    echo "Error creating admin user: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
