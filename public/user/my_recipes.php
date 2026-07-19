<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'created';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$is_admin = $_SESSION['role'] === 'Admin';
$uid = (int)$_SESSION['user_id'];

$where = $is_admin ? '1=1' : 'r.user_id = ?';
$params = $is_admin ? [] : [$uid];

if ($search !== '') {
    $where .= ' AND r.name LIKE ?';
    $params[] = '%' . $search . '%';
}

$order_map = ['created' => 'r.created_at', 'name' => 'r.name', 'rating' => 'avg_rating'];
$order_col = $order_map[$sort] ?? 'r.created_at';

$sql = "SELECT r.recipe_id, r.name, r.user_id, r.prep_time_minutes, r.cook_time_minutes, r.created_at,
               r.image_url, c.name AS country_name,
               COALESCE(AVG(rev.rating), 0) AS avg_rating, COUNT(rev.review_id) AS review_count,
               u.username AS owner_name
        FROM recipes r
        JOIN countries c ON r.country_id = c.country_id
        LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id
        LEFT JOIN users u ON r.user_id = u.user_id
        WHERE $where
        GROUP BY r.recipe_id
        ORDER BY $order_col $order";

$count_sql = "SELECT COUNT(*) AS cnt FROM ($sql) sub";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetch()['cnt'];

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;
$total_pages = max(1, (int)ceil($total / $per_page));

$sql .= " LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>My Recipes</title>
<style>
:root{--bg-color:#282828;--bg-dim:#1d2021;--card-bg:rgba(50,48,47,0.7);--text-main:#d79921;--text-muted:#a89984;--primary:#458589;--primary-hover:#83a598;--border-color:rgba(60,56,54,0.6);--font-stack:system-ui,-apple-system,sans-serif;--danger:#cc241d;--danger-hover:#fb4934;--gold:#d79921}
[data-theme="light"]{--bg-color:#d5c4a1;--bg-dim:#c9b99a;--card-bg:#ebdbb2;--text-main:#3c3836;--text-muted:#7c6f64;--primary:#458588;--primary-hover:#83a598;--border-color:#bdae93;--danger:#9d0006;--danger-hover:#cc241d;--gold:#b57614}
*{box-sizing:border-box;font-family:var(--font-stack);margin:0;padding:0}
body{background:var(--bg-dim);color:var(--text-muted);-webkit-font-smoothing:antialiased}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh}
.main-content{flex:1;padding:40px 24px}
.container{width:100%;max-width:1100px;margin:0 auto;background:var(--card-bg);padding:30px;border-radius:12px;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,.4)}
h2{color:var(--text-main);font-weight:800;margin-bottom:4px;font-size:22px}
.sub{color:var(--text-muted);font-size:13px;margin-bottom:20px}
.filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:end;padding:14px;background:rgba(29,32,33,.3);border-radius:8px;border:1px solid var(--border-color)}
.filter-bar .fg{display:flex;flex-direction:column;gap:2px}
.filter-bar .fg label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.4px}
.filter-bar input,.filter-bar select{padding:7px 10px;border-radius:5px;border:1px solid var(--border-color);background:rgba(29,32,33,.6);color:var(--text-main);font-size:12px;outline:none}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--primary-hover)}
.filter-bar input{min-width:160px}
.btn-filter{padding:7px 14px;border-radius:5px;border:none;font-weight:700;font-size:12px;cursor:pointer;background:var(--primary);color:var(--bg-dim);transition:all .15s}
.btn-filter:hover{background:var(--primary-hover)}
.btn-order{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:5px;border:1px solid var(--border-color);background:rgba(29,32,33,.6);color:var(--text-muted);cursor:pointer;font-size:16px;transition:all .15s;text-decoration:none;align-self:end}
.btn-order:hover{border-color:var(--primary-hover);color:var(--primary-hover)}
.btn-clear{padding:7px 14px;border-radius:5px;border:1px solid var(--border-color);font-weight:600;font-size:12px;cursor:pointer;background:transparent;color:var(--text-muted);text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;align-self:end}
.btn-clear:hover{border-color:var(--primary-hover);color:var(--primary-hover)}
.btn{display:inline-flex;align-items:center;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer;border:none;font-size:14px;text-decoration:none;transition:all .15s}
.btn-p{background:var(--primary);color:var(--bg-dim)}
.btn-p:hover{background:var(--primary-hover)}
.btn-sm{padding:6px 12px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all .15s;display:inline-block}
.btn-v{background:var(--primary);color:var(--bg-dim)}
.btn-v:hover{background:var(--primary-hover)}
.btn-e{background:var(--gold);color:var(--bg-dim)}
.btn-e:hover{background:#fabd2f}
.btn-d{background:rgba(204,36,29,.15);color:var(--danger-hover);border:1px solid rgba(204,36,29,.3)}
.btn-d:hover{background:rgba(251,73,52,.25)}
.actions{white-space:nowrap;display:flex;gap:4px}
.empty{padding:40px;text-align:center;color:var(--text-muted);font-size:14px}
.rating{color:var(--gold)}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 14px;text-align:left;border-bottom:1px solid var(--border-color);font-size:14px}
th{background:rgba(40,40,40,.6);color:var(--text-main);font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px}
td{color:var(--text-muted)}
tr:hover{background:rgba(60,56,54,.3)}
a{color:var(--text-muted);text-decoration:none}
a:hover{color:var(--primary-hover)}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:20px;flex-wrap:wrap}
.page-link{padding:8px 14px;border-radius:6px;border:1px solid var(--border-color);font-size:13px;font-weight:600;transition:all .15s;color:var(--text-muted);background:rgba(29,32,33,.4)}
.page-link:hover{border-color:var(--primary-hover);color:var(--primary-hover)}
.page-link.active{background:var(--primary);color:var(--bg-dim);border-color:var(--primary)}
@media(max-width:700px){.main-content{padding:20px 12px}.container{padding:16px}table,thead,tbody,th,td,tr{display:block}thead{display:none}td{padding:8px 12px;border:none;position:relative;padding-left:50%}td:before{content:attr(data-label);position:absolute;left:12px;font-weight:700;font-size:12px;color:var(--text-muted);text-transform:uppercase}tr{border-bottom:1px solid var(--border-color);padding:8px 0}tr:hover{background:none}.actions{gap:2px}.btn-sm{font-size:11px;padding:4px 8px}}
</style>
</head>
<body>
<div class="page-wrapper"><?php include '../../includes/navbar.php'; ?><main class="main-content"><div class="container">
<h2><span class="material-icons" style="vertical-align:middle;font-size:22px;margin-right:4px;">description</span> My Recipes</h2>
<p class="sub"><?= $is_admin ? 'All recipes in the system' : 'Recipes you have created'; ?> · <?= $total; ?> total</p>

<form method="GET" class="filter-bar">
    <div class="fg"><label>Search</label><input type="text" name="search" placeholder="Search recipes..." value="<?= htmlspecialchars($search); ?>"></div>
    <div class="fg"><label>Sort</label>
        <select name="sort">
            <option value="created" <?= $sort==='created'?'selected':''; ?>>Created</option>
            <option value="name" <?= $sort==='name'?'selected':''; ?>>Name</option>
            <option value="rating" <?= $sort==='rating'?'selected':''; ?>>Rating</option>
        </select>
    </div>
    <a href="?sort=<?= $sort ?>&order=<?= $opposite_order ?>&search=<?= urlencode($search) ?>" class="btn-order" title="Toggle order"><span class="material-icons"><?= $order==='ASC'?'arrow_upward':'arrow_downward'; ?></span></a>
    <button type="submit" class="btn-filter">Filter</button>
    <a href="my_recipes.php" class="btn-clear">Clear</a>
</form>

<?php if (isset($_SESSION['message'])): ?>
<div style="padding:12px;background:rgba(69,133,137,.15);color:var(--primary-hover);border:1px solid rgba(69,133,137,.3);border-radius:6px;margin-bottom:20px;font-weight:700;font-size:14px;"><?= htmlspecialchars($_SESSION['message']); ?><?php unset($_SESSION['message']); ?></div>
<?php endif; ?>

<?php if (count($rows) > 0): ?>
<table>
    <thead><tr>
        <th>Name</th>
        <th>Country</th>
        <th>Rating</th>
        <th>Prep/Cook</th>
        <th>Created</th>
        <?php if ($is_admin): ?><th>Owner</th><?php endif; ?>
        <th>Actions</th>
    </tr></thead>
    <tbody>
        <?php foreach ($rows as $r):
            $can_edit = $is_admin || $_SESSION['role'] === 'Content Collector' || (int)$r['user_id'] === $uid;
            $can_delete = $is_admin || (int)$r['user_id'] === $uid;
        ?>
        <tr>
            <td data-label="Name"><strong style="color:var(--primary-hover);"><?= htmlspecialchars($r['name']); ?></strong></td>
            <td data-label="Country"><?= htmlspecialchars($r['country_name']); ?></td>
            <td data-label="Rating"><span class="rating"><span class="material-icons" style="font-size:14px;vertical-align:middle;">star</span> <?= round($r['avg_rating'], 1); ?></span> <span style="font-size:12px;">(<?= $r['review_count']; ?>)</span></td>
            <td data-label="Prep/Cook"><?php if ($r['prep_time_minutes'] || $r['cook_time_minutes']): ?><?= $r['prep_time_minutes'] ? $r['prep_time_minutes'].'m' : '—'; ?> / <?= $r['cook_time_minutes'] ? $r['cook_time_minutes'].'m' : '—'; ?><?php else: ?>—<?php endif; ?></td>
            <td data-label="Created" style="font-size:13px;"><?= date('Y-m-d', strtotime($r['created_at'])); ?></td>
            <?php if ($is_admin): ?><td data-label="Owner"><?= htmlspecialchars($r['owner_name'] ?? '—'); ?></td><?php endif; ?>
            <td data-label="Actions"><div class="actions">
                <a href="../crs_app/view.php?id=<?= $r['recipe_id']; ?>" class="btn-sm btn-v">View</a>
                <?php if ($can_edit): ?><a href="../crs_app/edit.php?id=<?= $r['recipe_id']; ?>" class="btn-sm btn-e">Edit</a><?php endif; ?>
                <?php if ($can_delete): ?><a href="../crs_app/delete.php?id=<?= $r['recipe_id']; ?>&csrf_token=<?= $_SESSION['csrf_token']; ?>" class="btn-sm btn-d" onclick="return confirm('Delete <?= htmlspecialchars($r['name'], ENT_QUOTES); ?>?')">Delete</a><?php endif; ?>
            </div></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?page=<?= $page-1; ?>&sort=<?= $sort; ?>&order=<?= $order; ?>&search=<?= urlencode($search); ?>" class="page-link">&laquo; Prev</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <a href="?page=<?= $i; ?>&sort=<?= $sort; ?>&order=<?= $order; ?>&search=<?= urlencode($search); ?>" class="page-link <?= $i===$page?'active':''; ?>"><?= $i; ?></a>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
    <a href="?page=<?= $page+1; ?>&sort=<?= $sort; ?>&order=<?= $order; ?>&search=<?= urlencode($search); ?>" class="page-link">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php else: ?>
<div class="empty"><span class="material-icons" style="font-size:32px;display:block;margin-bottom:8px;">restaurant</span>No recipes found. <a href="../crs_app/create.php" style="color:var(--primary-hover);font-weight:600;">Create one</a>.</div>
<?php endif; ?>
</div></main></div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
