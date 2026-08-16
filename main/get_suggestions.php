<?php
require '../app/config.php';

$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$suggestions = [];

if (strlen($query) >= 2) {
    $search = "%$query%";
    
    // Search for Brands
    $stmt = $conn->prepare("SELECT DISTINCT brand FROM vehicles WHERE brand LIKE ? AND status = 'Available' LIMIT 3");
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $suggestions[] = ['text' => $row['brand'], 'type' => 'Brand'];
    }

    // Search for Models
    $stmt = $conn->prepare("SELECT DISTINCT model FROM vehicles WHERE model LIKE ? AND status = 'Available' LIMIT 3");
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $suggestions[] = ['text' => $row['model'], 'type' => 'Model'];
    }

    // Search for Locations
    $stmt = $conn->prepare("SELECT DISTINCT area FROM vehicles WHERE area LIKE ? AND status = 'Available' LIMIT 3");
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $suggestions[] = ['text' => $row['area'], 'type' => 'Location'];
    }
}

header('Content-Type: application/json');
echo json_encode($suggestions);
