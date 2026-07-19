<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Content Collector')) {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = "Invalid request.";
    header('Location: index.php');
    exit;
}

$recipe_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT user_id FROM recipes WHERE recipe_id = ?");
$stmt->execute([$recipe_id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    $_SESSION['message'] = "Recipe not found.";
    header('Location: index.php');
    exit;
}

if ($_SESSION['role'] !== 'Admin' && $recipe['user_id'] !== $_SESSION['user_id']) {
    $_SESSION['message'] = "You can only delete your own recipes.";
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("DELETE FROM recipes WHERE recipe_id = ?");
$stmt->execute([$recipe_id]);

$_SESSION['message'] = "Recipe deleted successfully.";
header('Location: index.php');
exit;
