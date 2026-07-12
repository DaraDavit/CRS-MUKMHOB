<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$stmt = $conn->prepare("SELECT r.*, c.name AS country_name, COALESCE(AVG(rev.rating), 0) AS avg_rating FROM recipes r JOIN countries c ON r.country_id = c.country_id LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id WHERE r.user_id = ? GROUP BY r.recipe_id ORDER BY r.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>My Recipes</title>
<style>
:root{--bg-color:#282828;--bg-dim:#1d2021;--card-bg:rgba(50,48,47,0.7);--text-main:#ebdbb2;--text-muted:#a89984;--primary:#d65d3c;--primary-hover:#e67e52;--border-color:rgba(60,56,54,0.6);--font-stack:system-ui,-apple-system,sans-serif;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg-dim);color:var(--text-muted);font-family:var(--font-stack);-webkit-font-smoothing:antialiased;}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh;}
.main-content{flex:1;padding:40px 24px;}
.container{width:100%;max-width:1000px;margin:0 auto;background:var(--card-bg);padding:30px;border-radius:12px;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,.4);}
h2{color:var(--text-main);font-weight:800;margin-bottom:20px;}
.recipe-item{padding:16px;border:1px solid var(--border-color);border-radius:8px;margin-bottom:12px;background:rgba(29,32,33,.3);}
.recipe-item h3{color:var(--text-main);font-size:16px;font-weight:700;}
.recipe-item p{font-size:13px;color:var(--text-muted);margin-top:4px;}
</style>
</head>
<body>
<div class="page-wrapper"><?php include '../../includes/navbar.php'; ?><main class="main-content"><div class="container"><h2>My Recipes</h2>
<?php while ($r = $stmt->fetch()): ?>
<div class="recipe-item"><h3><?= htmlspecialchars($r['name']); ?></h3><p><?= htmlspecialchars($r['country_name']); ?> · <span class="material-icons" style="font-size:14px;color:#d79921;vertical-align:middle;">star</span> <?= round($r['avg_rating'], 1); ?></p></div>
<?php endwhile; ?>
</div></main></div><?php include '../../includes/footer.php'; ?></body>
</html>
