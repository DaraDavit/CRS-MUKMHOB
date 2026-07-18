<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_GET['remove'])) {
    $stmt = $conn->prepare("DELETE FROM favorite_recipes WHERE user_id = ? AND recipe_id = ?");
    $stmt->execute([$_SESSION['user_id'], (int)$_GET['remove']]);
    header('Location: my_favorites.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$food_type_id = (int)($_GET['food_type_id'] ?? 0);
$sort = $_GET['sort'] ?? 'created';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$where = 'WHERE f.user_id = ?';
$params = [$_SESSION['user_id']];

if ($search !== '') {
    $where .= ' AND r.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($food_type_id > 0) {
    $where .= ' AND ft.food_type_id = ?';
    $params[] = $food_type_id;
}

$sort_map = [
    'name' => 'r.name',
    'rating' => 'avg_rating',
    'created' => 'f.recipe_id',
];
$order_col = $sort_map[$sort] ?? 'f.recipe_id';

$sql = "SELECT r.recipe_id, r.name, r.image_url, r.prep_time_minutes, r.cook_time_minutes, r.created_at, c.name AS country_name, ft.name AS food_type_name, reg.name AS region_name, COALESCE(AVG(rev.rating), 0) AS avg_rating, COUNT(rev.review_id) AS review_count FROM favorite_recipes f JOIN recipes r ON f.recipe_id = r.recipe_id JOIN countries c ON r.country_id = c.country_id JOIN regions reg ON c.region_id = reg.region_id JOIN food_types ft ON reg.food_type_id = ft.food_type_id LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id $where GROUP BY r.recipe_id ORDER BY $order_col $order";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$favorites = $stmt->fetchAll();

$food_types = $conn->query("SELECT * FROM food_types ORDER BY name")->fetchAll();

$opposite_order = $order === 'DESC' ? 'ASC' : 'DESC';
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>My Favorites</title>
<style>

*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg-dim);color:var(--text-muted);font-family:var(--font-stack);-webkit-font-smoothing:antialiased;}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh;}
.main-content{flex:1;padding:40px 24px;}
.container{width:100%;max-width:1200px;margin:0 auto;background:var(--card-bg);padding:30px;border-radius:12px;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,.4);}
h2{color:var(--text-main);font-weight:800;margin-bottom:20px;}

.filter-bar{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;align-items:end;}
.filter-group{display:flex;flex-direction:column;gap:2px;}
.filter-group label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.4px;}
.filter-group input,.filter-group select{padding:8px 10px;border-radius:6px;border:1px solid var(--border-color);color:var(--text-main);font-size:13px;outline:none;}
.filter-group input:focus,.filter-group select:focus{border-color:var(--primary-hover);}
.filter-group input{min-width:160px;}
.btn-filter{padding:8px 14px;border-radius:6px;border:none;font-weight:700;font-size:13px;cursor:pointer;background:var(--primary);color:#fff;transition:all .15s;}
.btn-filter:hover{background:var(--primary-hover);}
.btn-order{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:6px;border:1px solid var(--border-color);color:var(--text-muted);cursor:pointer;font-size:16px;transition:all .15s;text-decoration:none;align-self:end;}
.btn-order:hover{border-color:var(--primary-hover);color:var(--primary-hover);}
.btn-clear{padding:8px 14px;border-radius:6px;border:1px solid var(--border-color);font-weight:600;font-size:13px;cursor:pointer;background:transparent;color:var(--text-muted);text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;align-self:end;}
.btn-clear:hover{border-color:var(--primary-hover);color:var(--primary-hover);}

.card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;}
.card{background:var(--card-bg);border:1px solid var(--border-color);border-radius:14px;overflow:hidden;transition:all .25s;text-decoration:none;color:inherit;display:flex;flex-direction:column;}
.card:hover{transform:translateY(-4px);box-shadow:0 12px 32px -8px rgba(0,0,0,0.3);border-color:var(--primary);}
.card-img{height:170px;background:var(--bg-dim);overflow:hidden;position:relative;}
.card-img img{width:100%;height:100%;object-fit:cover;display:block;}
.card-img .placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;background:linear-gradient(135deg,rgba(214,93,60,0.1),rgba(230,126,82,0.1));}
.card-body{padding:16px 18px 18px;flex:1;display:flex;flex-direction:column;}
.card-body h3{font-size:16px;font-weight:700;color:var(--text-main);margin-bottom:4px;}
.card-body .cuisine{font-size:12px;color:var(--text-muted);margin-bottom:8px;}
.card-body .meta{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:12px;color:var(--text-muted);}
.card-body .badges{display:flex;gap:6px;margin-top:auto;flex-wrap:wrap;}
.card-body .badges span{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;background:rgba(214,93,60,0.1);color:var(--primary);}
.btn-remove{position:absolute;top:8px;right:8px;z-index:2;padding:5px 10px;background:rgba(204,36,29,0.2);color:#fff;border:1px solid rgba(204,36,29,0.3);border-radius:6px;text-decoration:none;font-size:11px;font-weight:600;transition:all .15s;backdrop-filter:blur(4px);cursor:pointer;opacity:0;}
.card:hover .btn-remove{opacity:1;}
.btn-remove:hover{background:rgba(251,73,52,0.35);opacity:1;}
.empty-state{text-align:center;padding:80px 20px;}
.empty-state .icon{font-size:64px;color:var(--text-muted);margin-bottom:16px;}
.empty-state h3{font-size:20px;color:var(--text-main);font-weight:700;margin-bottom:8px;}
.empty-state p{font-size:14px;color:var(--text-muted);margin-bottom:20px;}
.empty-state a{display:inline-flex;align-items:center;gap:6px;padding:12px 24px;border-radius:10px;font-weight:700;font-size:15px;background:var(--primary);color:#fff;text-decoration:none;transition:all .2s;}
.empty-state a:hover{background:var(--primary-hover);transform:translateY(-2px);}
@media(max-width:700px){.card-grid{grid-template-columns:1fr;}.filter-bar{flex-direction:column;}.filter-group input{min-width:100%;}}
</style>
</head>
<body>
<div class="page-wrapper"><?php include '../../includes/navbar.php'; ?><main class="main-content"><div class="container"><h2>My Favorites</h2>

<?php if (count($favorites) === 0 && $search === '' && !$food_type_id): ?>
<div class="empty-state">
    <div class="icon"><span class="material-icons" style="font-size:64px;">favorite</span></div>
    <h3>No favorites yet</h3>
    <p>Start exploring recipes and save your favorites here!</p>
    <a href="../crs_app/index.php"><span class="material-icons">restaurant</span> Browse Recipes</a>
</div>
<?php else: ?>
<form method="GET" class="filter-bar">
    <div class="filter-group">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search favorites..." value="<?= htmlspecialchars($search); ?>">
    </div>
    <div class="filter-group">
        <label>Food Type</label>
        <select name="food_type_id">
            <option value="">All</option>
            <?php foreach ($food_types as $ft): ?>
            <option value="<?= $ft['food_type_id']; ?>" <?= $food_type_id === (int)$ft['food_type_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($ft['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <a href="?order=<?= $opposite_order ?>&search=<?= urlencode($search) ?>&food_type_id=<?= $food_type_id ?>" class="btn-order" title="Toggle order">
        <span class="material-icons"><?= $order === 'DESC' ? 'arrow_downward' : 'arrow_upward'; ?></span>
    </a>
    <button type="submit" class="btn-filter">Filter</button>
    <a href="my_favorites.php" class="btn-clear">Clear</a>
</form>

<?php if (count($favorites) === 0): ?>
<div class="empty-state" style="padding:40px 20px;">
    <div class="icon"><span class="material-icons" style="font-size:48px;">search_off</span></div>
    <h3>No results</h3>
    <p>Try adjusting your search or filters.</p>
    <a href="my_favorites.php" class="btn-clear" style="display:inline-flex;padding:10px 20px;font-size:14px;">Clear Filters</a>
</div>
<?php else: ?>
<div class="card-grid">
    <?php foreach ($favorites as $r): ?>
    <a href="../crs_app/view.php?id=<?= $r['recipe_id']; ?>" class="card">
        <div class="card-img">
            <?php if (!empty($r['image_url'])): ?>
                <img src="<?= htmlspecialchars($r['image_url']); ?>" alt="">
            <?php else: ?>
                <div class="placeholder"><span class="material-icons" style="font-size:48px;">restaurant</span></div>
            <?php endif; ?>
            <button class="btn-remove" onclick="window.location='my_favorites.php?remove=<?= $r['recipe_id']; ?>'">Remove</button>
        </div>
        <div class="card-body">
            <h3><?= htmlspecialchars($r['name']); ?></h3>
            <div class="cuisine"><?= htmlspecialchars($r['food_type_name']); ?> · <?= htmlspecialchars($r['country_name']); ?></div>
            <div class="meta">
                <span style="color:#d79921;"><?php $s = round($r['avg_rating']); for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:14px;vertical-align:middle;"><?= $i < $s ? 'star' : 'star_outline'; ?></span><?php endfor; ?></span>
                <span>(<?= $r['review_count']; ?>)</span>
            </div>
            <div class="badges">
                <?php if ($r['prep_time_minutes']): ?><span><span class="material-icons" style="font-size:11px;vertical-align:middle;">schedule</span> <?= $r['prep_time_minutes']; ?>m</span><?php endif; ?>
                <?php if ($r['cook_time_minutes']): ?><span><span class="material-icons" style="font-size:11px;vertical-align:middle;">restaurant_menu</span> <?= $r['cook_time_minutes']; ?>m</span><?php endif; ?>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</div></main></div><?php include '../../includes/footer.php'; ?></body>
</html>
