<?php
session_start();
require '../../includes/db.php';

$where = [];
$params = [];

if (!empty($_GET['category_id'])) {
    $where[] = 'rc.category_id = ?';
    $params[] = (int)$_GET['category_id'];
}
if (!empty($_GET['food_type_id'])) {
    $where[] = 'ft.food_type_id = ?';
    $params[] = (int)$_GET['food_type_id'];
}
if (!empty($_GET['region_id'])) {
    $where[] = 'reg.region_id = ?';
    $params[] = (int)$_GET['region_id'];
}
if (!empty($_GET['country_id'])) {
    $where[] = 'c.country_id = ?';
    $params[] = (int)$_GET['country_id'];
}
if (!empty($_GET['search'])) {
    $where[] = 'r.name LIKE ?';
    $params[] = '%' . $_GET['search'] . '%';
}

$sort = $_GET['sort'] ?? 'created';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$sort_map = ['created' => 'r.created_at', 'name' => 'r.name', 'rating' => 'avg_rating', 'prep' => 'r.prep_time_minutes'];
$order_col = $sort_map[$sort] ?? 'r.created_at';

$sql = "SELECT r.recipe_id, r.name, r.prep_time_minutes, r.cook_time_minutes, r.created_at, r.image_url,
               c.name AS country_name, reg.name AS region_name, ft.name AS food_type_name,
               COALESCE(AVG(rev.rating), 0) AS avg_rating, COUNT(rev.review_id) AS review_count
        FROM recipes r
        JOIN countries c ON r.country_id = c.country_id
        JOIN regions reg ON c.region_id = reg.region_id
        JOIN food_types ft ON reg.food_type_id = ft.food_type_id
        LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id";

$has_cat_filter = !empty($_GET['category_id']);
if ($has_cat_filter) $sql .= " JOIN recipe_categories rc ON r.recipe_id = rc.recipe_id";

if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= " GROUP BY r.recipe_id ORDER BY $order_col $order";

$count_sql = "SELECT COUNT(*) AS cnt FROM ($sql) sub";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetch()['cnt'];

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;
$total_pages = max(1, (int)ceil($total / $per_page));

$sql .= " LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);

$food_types = $conn->query("SELECT * FROM food_types ORDER BY name")->fetchAll();

$selected_regions = [];
$selected_countries = [];
if (!empty($_GET['food_type_id'])) {
    $r = $conn->prepare("SELECT region_id, name FROM regions WHERE food_type_id = ? ORDER BY name");
    $r->execute([(int)$_GET['food_type_id']]);
    $selected_regions = $r->fetchAll();
}
if (!empty($_GET['region_id'])) {
    $r = $conn->prepare("SELECT country_id, name FROM countries WHERE region_id = ? ORDER BY name");
    $r->execute([(int)$_GET['region_id']]);
    $selected_countries = $r->fetchAll();
}

$all_cats = $conn->query("SELECT rc.recipe_id, c.name FROM recipe_categories rc JOIN categories c ON rc.category_id = c.category_id");
$recipe_cats_map = [];
while ($rc = $all_cats->fetch()) $recipe_cats_map[$rc['recipe_id']][] = $rc['name'];

$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe Collection</title>
    <style>
        :root, [data-theme="light"] {
            --primary: #458589; --primary-hover: #83a598;
        }
        * { box-sizing:border-box; font-family:var(--font-stack); margin:0; padding:0; }
        body { background-color:var(--bg-dim); color:var(--text-muted); -webkit-font-smoothing:antialiased; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .main-content { flex:1; padding:40px 24px; }
        .container {
            width:100%; max-width:1200px; margin:0 auto;
            background:var(--card-bg); padding:30px; border-radius:12px;
            border:1px solid var(--border-color);
            backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
            box-shadow:0 20px 40px -10px rgba(0,0,0,0.4);
        }
        h2 { color:var(--text-main); font-weight:800; letter-spacing:-0.5px; margin-bottom:20px; }
        .alert { padding:12px; background:rgba(69,133,137,0.15); color:var(--primary-hover); border:1px solid rgba(69,133,137,0.3); border-radius:6px; margin-bottom:20px; font-weight:bold; font-size:14px; }

        .filter-toggle { display:none; }
        .header-bar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
        .btn {
            display:inline-flex; align-items:center; gap:6px; padding:10px 18px; border-radius:8px;
            font-weight:700; text-decoration:none; font-size:14px; cursor:pointer; border:none;
            transition:all 0.15s ease;
        }
        .btn-add { background:var(--text-main); color:var(--bg-dim); }
        .btn-add:hover { background:#fabd2f; transform:translateY(-1px); }

        .filters { display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap; align-items:end; }
        .filter-group { display:flex; flex-direction:column; gap:3px; }
        .filter-group label { font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
        .filter-group select, .filter-group input {
            padding:8px 12px; border-radius:6px; border:1px solid var(--border-color);
            font-size:13px; outline:none;
        }
        .filter-group select:focus, .filter-group input:focus { border-color:var(--primary-hover); }
        .btn-filter { background:var(--primary); color:var(--bg-dim); padding:8px 16px; border:none; border-radius:6px; font-weight:700; cursor:pointer; font-size:13px; }
        .btn-filter:hover { background:var(--primary-hover); }
        .btn-clear { background:transparent; color:var(--text-muted); padding:8px 16px; border:1px solid var(--border-color); border-radius:6px; font-weight:600; text-decoration:none; font-size:13px; }
        .btn-clear:hover { border-color:var(--border-hover); color:var(--text-main); }

        .recipe-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:20px; }
        .recipe-card {
            border:1px solid var(--border-color);
            border-radius:12px; padding:22px; transition:all 0.25s ease;
            display:flex; flex-direction:column;
        }
        .recipe-card:hover { border-color:var(--primary-hover); transform:translateY(-3px); box-shadow:0 10px 30px -8px rgba(0,0,0,0.4); }
        .recipe-card h3 { color:var(--text-main); font-size:18px; font-weight:700; margin-bottom:6px; }
        .recipe-card .breadcrumb { font-size:12px; color:var(--text-muted); margin-bottom:10px; }
        .recipe-card .breadcrumb span { color:var(--primary-hover); }
        .recipe-card .rating-row { display:flex; align-items:center; gap:8px; margin-bottom:12px; }
        .recipe-card .stars { color:#d79921; font-size:16px; }
        .recipe-card .review-count { font-size:12px; color:var(--text-muted); }
        .recipe-card .times { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
        .recipe-card .times .badge {
            display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:6px;
            font-size:12px; font-weight:600;
            background:rgba(69,133,137,0.12); color:var(--primary-hover);
        }
        .recipe-card .card-footer { margin-top:auto; display:flex; gap:8px; }
        .btn-card {
            display:inline-flex; align-items:center; justify-content:center;
            padding:8px 16px; border-radius:8px; font-weight:700; text-decoration:none;
            font-size:13px; transition:all 0.15s ease; border:none; cursor:pointer;
        }
        .btn-view { background:var(--primary); color:var(--bg-dim); flex:1; }
        .btn-view:hover { background:var(--primary-hover); }

        .empty-state { text-align:center; padding:80px 20px; color:var(--text-muted); }
        .empty-state p { font-size:16px; margin-bottom:16px; }
        .pagination { display:flex; justify-content:center; gap:6px; margin-top:24px; flex-wrap:wrap; }
        .page-link { display:inline-flex; align-items:center; justify-content:center; min-width:36px; padding:8px 14px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none; color:var(--text-muted); border:1px solid var(--border-color); transition:all 0.15s; }
        .page-link:hover { border-color:var(--primary-hover); color:var(--primary-hover); }
        .page-link.active { background:var(--primary); color:var(--bg-dim); border-color:var(--primary); }

        .cat-badge { display:inline-block; padding:2px 8px; border-radius:6px; font-size:10px; font-weight:700; background:rgba(69,133,137,0.12); color:var(--primary-hover); }
        @media (max-width:700px) {
            .recipe-grid { grid-template-columns:1fr; }
            .container { padding:16px; }
            .main-content { padding:20px 12px; }
            .recipe-card { padding:16px; }
            .filters { display:none; flex-direction:column; gap:8px; }
            .filters.open { display:flex; }
            .filter-group { width:100%; }
            .filter-group select, .filter-group input { width:100%; }
            .filter-group:last-child { flex-direction:row; align-self:stretch; }
            .filter-toggle { display:inline-flex; align-items:center; gap:6px; padding:10px 18px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; border:1px solid var(--border-color); background:var(--card-bg); color:var(--text-main); transition:all 0.15s ease; margin-bottom:16px; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../../includes/navbar.php'; ?>
    <main class="main-content">
        <div class="container">
            <div class="header-bar">
                <h2>Recipe Collection</h2>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert"><?= htmlspecialchars($_SESSION['message']); ?><?php unset($_SESSION['message']); ?></div>
            <?php endif; ?>

            <button class="filter-toggle" id="filterToggle"><span class="material-icons">filter_list</span> Filters</button>
            <form method="GET" class="filters" id="filterForm">
                <div class="filter-group">
                    <label>Food Type</label>
                    <select name="food_type_id" id="filter-food-type">
                        <option value="">All</option>
                        <?php foreach ($food_types as $ft): ?>
                            <option value="<?= $ft['food_type_id']; ?>" <?= (!empty($_GET['food_type_id']) && $_GET['food_type_id'] == $ft['food_type_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($ft['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Region</label>
                    <select name="region_id" id="filter-region">
                        <option value="">All</option>
                        <?php foreach ($selected_regions as $r): ?>
                            <option value="<?= $r['region_id']; ?>" <?= (!empty($_GET['region_id']) && $_GET['region_id'] == $r['region_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Country</label>
                    <select name="country_id" id="filter-country">
                        <option value="">All</option>
                        <?php foreach ($selected_countries as $c): ?>
                            <option value="<?= $c['country_id']; ?>" <?= (!empty($_GET['country_id']) && $_GET['country_id'] == $c['country_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id']; ?>" <?= (!empty($_GET['category_id']) && $_GET['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Name..." value="<?= htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="filter-group">
                    <label>Sort</label>
                    <select name="sort">
                        <option value="created" <?= $sort === 'created' ? 'selected' : ''; ?>>Newest</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : ''; ?>>Rating</option>
                        <option value="prep" <?= $sort === 'prep' ? 'selected' : ''; ?>>Prep Time</option>
                    </select>
                </div>
                <div class="filter-group" style="align-self:end;">
                    <button type="submit" class="btn-filter">Filter</button>
                    <a href="index.php" class="btn-clear" style="margin-left:4px;">Clear</a>
                </div>
            </form>

            <?php if ($stmt->rowCount() === 0): ?>
                <div class="empty-state">
                    <span class="material-icons" style="font-size:48px;color:var(--text-muted);">restaurant</span>
                    <p>No recipes found.</p>
                    <a href="../auth/login.php" class="btn btn-add">Sign In</a>
                </div>
            <?php else: ?>
                <div class="recipe-grid">
                    <?php while ($row = $stmt->fetch()): ?>
                    <div class="recipe-card">
                        <?php if (!empty($row['image_url'])): ?>
                        <div style="margin:-22px -22px 14px -22px;overflow:hidden;border-radius:12px 12px 0 0;height:160px;">
                            <img src="<?= htmlspecialchars($row['image_url']); ?>" alt="" style="width:100%;height:160px;object-fit:cover;display:block;">
                        </div>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($row['name']); ?></h3>
                        <div class="breadcrumb">
                            <?= htmlspecialchars($row['food_type_name']); ?> <span>·</span>
                            <?= htmlspecialchars($row['region_name']); ?> <span>·</span>
                            <?= htmlspecialchars($row['country_name']); ?>
                        </div>
                        <?php if (isset($recipe_cats_map[$row['recipe_id']])): ?>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;">
                            <?php foreach ($recipe_cats_map[$row['recipe_id']] as $cn): ?>
                            <span class="cat-badge"><?= htmlspecialchars($cn); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="rating-row">
                            <span class="stars"><?php $s = round($row['avg_rating']); for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:16px;color:#d79921;vertical-align:middle;"><?= $i < $s ? 'star' : 'star_outline'; ?></span><?php endfor; ?></span>
                            <span class="review-count">(<?= $row['review_count']; ?>)</span>
                        </div>
                        <div class="times">
                            <?php if ($row['prep_time_minutes']): ?>
                                <span class="badge"><span class="material-icons" style="font-size:12px;vertical-align:middle;">schedule</span> <?= $row['prep_time_minutes']; ?>m prep</span>
                            <?php endif; ?>
                            <?php if ($row['cook_time_minutes']): ?>
                                <span class="badge"><span class="material-icons" style="font-size:12px;vertical-align:middle;">restaurant_menu</span> <?= $row['cook_time_minutes']; ?>m cook</span>
                            <?php endif; ?>
                            <?php if (!$row['prep_time_minutes'] && !$row['cook_time_minutes']): ?>
                                <span class="badge">No cook</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="view.php?id=<?= $row['recipe_id']; ?>" class="btn-card btn-view">View Recipe</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1; ?>&sort=<?= $sort; ?>&order=<?= $order; ?><?= !empty($_GET['food_type_id']) ? '&food_type_id=' . (int)$_GET['food_type_id'] : ''; ?><?= !empty($_GET['region_id']) ? '&region_id=' . (int)$_GET['region_id'] : ''; ?><?= !empty($_GET['country_id']) ? '&country_id=' . (int)$_GET['country_id'] : ''; ?><?= !empty($_GET['category_id']) ? '&category_id=' . (int)$_GET['category_id'] : ''; ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="page-link">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i; ?>&sort=<?= $sort; ?>&order=<?= $order; ?><?= !empty($_GET['food_type_id']) ? '&food_type_id=' . (int)$_GET['food_type_id'] : ''; ?><?= !empty($_GET['region_id']) ? '&region_id=' . (int)$_GET['region_id'] : ''; ?><?= !empty($_GET['country_id']) ? '&country_id=' . (int)$_GET['country_id'] : ''; ?><?= !empty($_GET['category_id']) ? '&category_id=' . (int)$_GET['category_id'] : ''; ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="page-link <?= $i === $page ? 'active' : ''; ?>"><?= $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1; ?>&sort=<?= $sort; ?>&order=<?= $order; ?><?= !empty($_GET['food_type_id']) ? '&food_type_id=' . (int)$_GET['food_type_id'] : ''; ?><?= !empty($_GET['region_id']) ? '&region_id=' . (int)$_GET['region_id'] : ''; ?><?= !empty($_GET['country_id']) ? '&country_id=' . (int)$_GET['country_id'] : ''; ?><?= !empty($_GET['category_id']) ? '&category_id=' . (int)$_GET['category_id'] : ''; ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="page-link">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
const toggle = document.getElementById('filterToggle');
const filterForm = document.getElementById('filterForm');
if (toggle && filterForm) {
    toggle.addEventListener('click', function() {
        filterForm.classList.toggle('open');
        toggle.innerHTML = filterForm.classList.contains('open')
            ? '<span class="material-icons">close</span> Hide Filters'
            : '<span class="material-icons">filter_list</span> Filters';
    });
}

document.getElementById('filter-food-type').addEventListener('change', function() {
    const ftId = this.value;
    const regionSel = document.getElementById('filter-region');
    const countrySel = document.getElementById('filter-country');
    regionSel.innerHTML = '<option value="">All</option>';
    countrySel.innerHTML = '<option value="">All</option>';
    if (!ftId) return;
    fetch('api.php?regions&food_type_id=' + ftId).then(r=>r.json()).then(data => {
        data.forEach(r => { const o = document.createElement('option'); o.value = r.region_id; o.textContent = r.name; regionSel.appendChild(o); });
    });
});
document.getElementById('filter-region').addEventListener('change', function() {
    const regId = this.value;
    const countrySel = document.getElementById('filter-country');
    countrySel.innerHTML = '<option value="">All</option>';
    if (!regId) return;
    fetch('api.php?countries&region_id=' + regId).then(r=>r.json()).then(data => {
        data.forEach(c => { const o = document.createElement('option'); o.value = c.country_id; o.textContent = c.name; countrySel.appendChild(o); });
    });
});
</script>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
