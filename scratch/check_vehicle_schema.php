<?php
$conn = mysqli_connect("localhost", "root", "", "ridezo");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$result = $conn->query("DESCRIBE vehicles");
while($row = $result->fetch_assoc()) {
    print_r($row);
}

$conn->close();
?>
