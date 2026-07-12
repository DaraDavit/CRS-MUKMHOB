<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Content Collector')) {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$recipe_id = (int)$_GET['id'];

$stmt = $conn->prepare("DELETE FROM recipes WHERE recipe_id = ?");
$stmt->execute([$recipe_id]);

if ($stmt->rowCount() > 0) {
    $_SESSION['message'] = "Recipe deleted successfully.";
} else {
    $_SESSION['message'] = "Error deleting recipe.";
}

header('Location: index.php');
exit;
