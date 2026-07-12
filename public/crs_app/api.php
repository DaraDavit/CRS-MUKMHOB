<?php
require '../../includes/db.php';
header('Content-Type: application/json');

if (isset($_GET['regions']) && isset($_GET['food_type_id'])) {
    $stmt = $conn->prepare("SELECT region_id, name FROM regions WHERE food_type_id = ? ORDER BY name");
    $stmt->execute([(int) $_GET['food_type_id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if (isset($_GET['countries']) && isset($_GET['region_id'])) {
    $stmt = $conn->prepare("SELECT country_id, name FROM countries WHERE region_id = ? ORDER BY name");
    $stmt->execute([(int) $_GET['region_id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if (isset($_GET['ingredients']) && isset($_GET['q'])) {
    $stmt = $conn->prepare("SELECT ingredient_id, name FROM ingredients WHERE name LIKE ? ORDER BY name LIMIT 10");
    $stmt->execute(['%' . $_GET['q'] . '%']);
    echo json_encode($stmt->fetchAll());
    exit;
}

echo json_encode([]);
