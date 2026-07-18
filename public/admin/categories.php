<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

$msg = '';
if (isset($_POST['add'])) {
    $n = trim($_POST['name']);
    if (!empty($n)) { $s = $conn->prepare("INSERT INTO categories (name) VALUES (?)"); $s->execute([$n]); $msg = "Category added."; }
}
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id']; $n = trim($_POST['name']);
    if (!empty($n)) { $s = $conn->prepare("UPDATE categories SET name = ? WHERE category_id = ?"); $s->execute([$n, $id]); $msg = "Category updated."; }
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM categories WHERE category_id = ?")->execute([$id]);
    $msg = "Category deleted.";
}

$edit_row = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $r = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
    $r->execute([$eid]);
    $edit_row = $r->fetch();
}

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'id';
$order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND c.name LIKE ?';
    $params[] = '%' . $search . '%';
}
$sort_map = ['name' => 'c.name', 'id' => 'c.category_id', 'usage' => 'usage_count'];
$order_col = $sort_map[$sort] ?? 'c.name';

$result = $conn->prepare("SELECT c.*, (SELECT COUNT(*) FROM recipe_categories WHERE category_id = c.category_id) AS usage_count FROM categories c WHERE $where ORDER BY $order_col $order");
$result->execute($params);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Admin — Categories</title>
<style>
:root{--bg:#282828;--bg-dim:#1d2021;--card:rgba(50,48,47,0.7);--gold:#d79921;--muted:#a89984;--teal:#458589;--teal-h:#83a598;--border:rgba(60,56,54,0.6);--font:system-ui,-apple-system,sans-serif;--red:#cc241d;--red-h:#fb4934}
[data-theme="light"]{--bg:#d5c4a1;--bg-dim:#c9b99a;--card:#ebdbb2;--gold:#b57614;--muted:#7c6f64;--teal:#458588;--teal-h:#83a598;--border:#bdae93;--red:#9d0006;--red-h:#cc241d}
*{box-sizing:border-box;font-family:var(--font);margin:0;padding:0}
body{background:var(--bg-dim);color:var(--muted);-webkit-font-smoothing:antialiased}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh}
.admin-layout{display:flex;flex:1;min-height:0}
.main-content{flex:1;padding:40px 24px}
.container{--gold:#d79921;width:100%;max-width:900px;margin:0 auto;background:var(--card);padding:30px;border-radius:12px;border:1px solid var(--border);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,.4)}
h2{color:var(--gold);font-weight:800;margin-bottom:20px}
.alert{padding:12px;background:rgba(69,133,137,.15);color:var(--teal-h);border:1px solid rgba(69,133,137,.3);border-radius:6px;margin-bottom:20px;font-weight:700;font-size:14px}
.inline-form{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:end}
.inline-form input{padding:9px 12px;border-radius:6px;border:1px solid var(--border);background:rgba(29,32,33,.6);color:var(--gold);font-size:13px;outline:none;min-width:220px}
.inline-form input:focus{border-color:var(--teal-h)}
.filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:end;padding:14px;background:rgba(29,32,33,0.3);border-radius:8px;border:1px solid var(--border);}
.filter-bar .fg{display:flex;flex-direction:column;gap:2px;}
.filter-bar .fg label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:.4px;}
.filter-bar input,.filter-bar select{padding:7px 10px;border-radius:5px;border:1px solid var(--border);background:rgba(29,32,33,0.6);color:var(--gold);font-size:12px;outline:none;}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--teal-h)}
.filter-bar input{min-width:160px;}
.btn-filter{padding:7px 14px;border-radius:5px;border:none;font-weight:700;font-size:12px;cursor:pointer;background:var(--teal);color:var(--bg-dim);transition:all .15s}
.btn-filter:hover{background:var(--teal-h)}
.btn-order{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:5px;border:1px solid var(--border);background:rgba(29,32,33,0.6);color:var(--muted);cursor:pointer;font-size:16px;transition:all .15s;text-decoration:none;align-self:end}
.btn-order:hover{border-color:var(--teal-h);color:var(--teal-h)}
.btn-clear{padding:7px 14px;border-radius:5px;border:1px solid var(--border);font-weight:600;font-size:12px;cursor:pointer;background:transparent;color:var(--muted);text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;align-self:end}
.btn-clear:hover{border-color:var(--teal-h);color:var(--gold)}
.btn{display:inline-flex;align-items:center;padding:9px 16px;border-radius:6px;font-weight:700;font-size:13px;cursor:pointer;border:none;text-decoration:none;transition:all .15s}
.btn-p{background:var(--teal);color:var(--bg-dim)}.btn-p:hover{background:var(--teal-h)}
.btn-xs{padding:5px 10px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;display:inline-block}
.btn-e{background:var(--teal);color:var(--bg-dim)}.btn-e:hover{background:var(--teal-h)}
.btn-d{background:rgba(204,36,29,.15);color:var(--red-h);border:1px solid rgba(204,36,29,.3)}.btn-d:hover{background:rgba(251,73,52,.25)}
.btn-cancel{background:transparent;color:var(--muted);border:1px solid var(--border)}.btn-cancel:hover{border-color:var(--border-hover);color:var(--gold)}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--border);font-size:13px}
th{background:rgba(40,40,40,.6);color:var(--gold);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px}
td{color:var(--muted)}
tr:hover{background:rgba(60,56,54,.3)}
.badge{display:inline-block;padding:2px 10px;border-radius:8px;font-size:11px;font-weight:700;background:rgba(69,133,137,.15);color:var(--teal-h)}
</style>
</head>
<body>
<div class="page-wrapper">
<?php include '../../includes/navbar.php'; ?>
<div class="admin-layout"><?php include 'admin_sidebar.php'; ?>
<main class="main-content">
<div class="container">
<h2><span class="material-icons" style="vertical-align:middle;margin-right:4px;">label</span> Categories</h2>
<?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg); ?></div><?php endif; ?>

<form method="POST" class="inline-form">
    <?php if ($edit_row): ?><input type="hidden" name="id" value="<?= $edit_row['category_id']; ?>"><?php endif; ?>
    <input type="text" name="name" required placeholder="Category name" value="<?= $edit_row ? htmlspecialchars($edit_row['name']) : ''; ?>">
    <button type="submit" name="<?= $edit_row ? 'edit' : 'add'; ?>" class="btn btn-p"><?= $edit_row ? 'Update' : 'Add'; ?></button>
    <?php if ($edit_row): ?><a href="categories.php" class="btn btn-cancel">Cancel</a><?php endif; ?>
</form>

<form method="GET" class="filter-bar">
    <div class="fg">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search categories..." value="<?= htmlspecialchars($search); ?>">
    </div>
    <div class="fg">
        <label>Sort</label>
        <select name="sort">
            <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name</option>
            <option value="id" <?= $sort === 'id' ? 'selected' : ''; ?>>ID</option>
            <option value="usage" <?= $sort === 'usage' ? 'selected' : ''; ?>>Usage</option>
        </select>
    </div>
    <a href="?sort=<?= $sort ?>&order=<?= $opposite_order ?>&search=<?= urlencode($search) ?>" class="btn-order" title="Toggle order">
        <span class="material-icons"><?= $order === 'ASC' ? 'arrow_upward' : 'arrow_downward'; ?></span>
    </a>
    <button type="submit" class="btn-filter">Filter</button>
    <a href="categories.php" class="btn-clear">Clear</a>
</form>

<table>
    <thead><tr><th>ID</th><th>Name</th><th>Used In</th><th>Actions</th></tr></thead>
    <tbody>
        <?php while ($r = $result->fetch()): ?>
        <tr><td><?= $r['category_id']; ?></td><td><strong style="color:var(--teal-h);"><?= htmlspecialchars($r['name']); ?></strong></td><td><span class="badge"><?= $r['usage_count']; ?> recipes</span></td><td><a href="categories.php?edit=<?= $r['category_id']; ?>" class="btn-xs btn-e">Edit</a> <a href="categories.php?delete=<?= $r['category_id']; ?>" class="btn-xs btn-d" onclick="return confirm('Delete?')">Delete</a></td></tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>
</main>
</div></div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
