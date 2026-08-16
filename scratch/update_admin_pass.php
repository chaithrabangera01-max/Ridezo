<?php
$conn = mysqli_connect("localhost", "root", "", "ridezo");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$email = 'admin@ridezo.com';
$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hash, $email);

if ($stmt->execute()) {
    echo "Password updated successfully for $email\n";
} else {
    echo "Error updating password: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
