<?php
$conn = mysqli_connect("localhost", "root", "", "ridezo");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$email = 'admin@ridezo.com';
$result = $conn->query("SELECT * FROM users WHERE email = '$email'");

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "User found:\n";
    print_r($user);
} else {
    echo "User not found for email: $email\n";
    
    echo "\nAll users in table:\n";
    $all = $conn->query("SELECT user_id, name, email, role, is_active FROM users");
    while($row = $all->fetch_assoc()) {
        print_r($row);
    }
}

$conn->close();
?>
