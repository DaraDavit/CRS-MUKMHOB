<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$stmt = $conn->prepare("SELECT rev.*, r.name AS recipe_name FROM reviews rev JOIN recipes r ON rev.recipe_id = r.recipe_id WHERE rev.user_id = ? ORDER BY rev.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>My Reviews</title>
<style>

*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg-dim);color:var(--text-muted);font-family:var(--font-stack);-webkit-font-smoothing:antialiased;}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh;}
.main-content{flex:1;padding:40px 24px;}
.container{width:100%;max-width:1000px;margin:0 auto;background:var(--card-bg);padding:30px;border-radius:12px;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,.4);}
h2{color:var(--text-main);font-weight:800;margin-bottom:20px;}
.review-item{padding:16px;border:1px solid var(--border-color);border-radius:8px;margin-bottom:12px;background:rgba(29,32,33,.3);}
.review-item h3{color:var(--text-main);font-size:16px;font-weight:700;}
.review-item p{font-size:13px;color:var(--text-muted);margin-top:4px;}
</style>
</head>
<body>
<div class="page-wrapper"><?php include '../../includes/navbar.php'; ?><main class="main-content"><div class="container"><h2>My Reviews</h2>
<?php while ($r = $stmt->fetch()): ?>
<div class="review-item"><h3><a href="../crs_app/view.php?id=<?= $r['recipe_id']; ?>" style="color:var(--primary-hover);text-decoration:none;"><?= htmlspecialchars($r['recipe_name']); ?></a></h3><p><?php for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:14px;color:#d79921;vertical-align:middle;"><?= $i < $r['rating'] ? 'star' : 'star_outline'; ?></span><?php endfor; ?> · <?= $r['created_at']; ?></p><?php if ($r['comment']): ?><p><?= nl2br(htmlspecialchars($r['comment'])); ?></p><?php endif; ?></div>
<?php endwhile; ?>
</div></main></div><?php include '../../includes/footer.php'; ?></body>
</html>
