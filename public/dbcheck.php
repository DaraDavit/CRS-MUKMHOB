<?php
header('Content-Type: text/plain');
echo "DB_HOST env: " . var_export(getenv('DB_HOST'), true) . "\n";
echo "DB_NAME env: " . var_export(getenv('DB_NAME'), true) . "\n";
echo "DB_USER env: " . var_export(getenv('DB_USER'), true) . "\n";
echo "DB_PASS env: " . var_export(getenv('DB_PASS'), true) . "\n";
echo "All env vars:\n";
echo shell_exec('printenv | grep -i -E "^(DB_|MYSQL|RAILWAY)"') ?: "(none found)\n";
echo "\n--- Testing PDO ---\n";
try {
    require '../includes/db.php';
    echo "PDO connected OK\n";
    $r = $conn->query("SELECT COUNT(*) FROM food_types")->fetchColumn();
    echo "food_types count: $r\n";
    $r2 = $conn->query("SELECT name FROM food_types ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    echo "food_types names: " . implode(', ', $r2) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
