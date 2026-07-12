<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $food_type_id = (int)$_POST['food_type_id'];
    if (!empty($name) && $food_type_id) {
        $stmt = $conn->prepare("INSERT INTO regions (name, food_type_id) VALUES (?, ?)");
        $stmt->execute([$name, $food_type_id]);
        $_SESSION['message'] = "Region added.";
        header("Location: regions.php");
        exit;
    }
}

$editing = null;
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $food_type_id = (int)$_POST['food_type_id'];
    if (!empty($name) && $food_type_id) {
        $stmt = $conn->prepare("UPDATE regions SET name = ?, food_type_id = ? WHERE region_id = ?");
        $stmt->execute([$name, $food_type_id, $id]);
        $_SESSION['message'] = "Region updated.";
        header("Location: regions.php");
        exit;
    }
}
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $r = $conn->prepare("SELECT * FROM regions WHERE region_id = ?");
    $r->execute([$eid]);
    $editing = $r->fetch();
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM regions WHERE region_id = ?")->execute([$id]);
    $_SESSION['message'] = "Region deleted.";
    header("Location: regions.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$food_type_filter = (int)($_GET['food_type_id'] ?? 0);
$sort = $_GET['sort'] ?? 'id';
$order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND reg.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($food_type_filter > 0) {
    $where .= ' AND reg.food_type_id = ?';
    $params[] = $food_type_filter;
}
$sort_map = ['name' => 'reg.name', 'food_type' => 'ft.name', 'countries' => 'country_count', 'id' => 'reg.region_id'];
$order_col = $sort_map[$sort] ?? 'reg.region_id';

$result = $conn->prepare("SELECT reg.*, ft.name AS food_type_name, (SELECT COUNT(*) FROM countries WHERE region_id = reg.region_id) AS country_count FROM regions reg JOIN food_types ft ON reg.food_type_id = ft.food_type_id WHERE $where ORDER BY $order_col $order");
$result->execute($params);
$food_types = $conn->query("SELECT * FROM food_types ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Admin — Regions</title>
<style>
:root{--bg-color:#282828;--bg-dim:#1d2021;--card-bg:rgba(50,48,47,0.7);--text-main:#d79921;--text-muted:#a89984;--primary:#458589;--primary-hover:#83a598;--border-color:rgba(60,56,54,0.6);--font-stack:system-ui,-apple-system,sans-serif;--danger:#cc241d;--danger-hover:#fb4934;}
*{box-sizing:border-box;font-family:var(--font-stack);margin:0;padding:0;}
body{background-color:var(--bg-dim);color:var(--text-muted);-webkit-font-smoothing:antialiased;}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh;}
.main-content{flex:1;padding:40px 24px;}
.container{--text-main:#d79921;width:100%;max-width:1000px;margin:0 auto;background:var(--card-bg);padding:30px;border-radius:12px;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,0.4);}
h2{color:var(--text-main);font-weight:800;margin-bottom:20px;}
.alert{padding:12px;background:rgba(69,133,137,0.15);color:var(--primary-hover);border:1px solid rgba(69,133,137,0.3);border-radius:6px;margin-bottom:20px;font-weight:bold;font-size:14px;}
.form-inline{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:end;}
.form-inline input[type=text],.form-inline select{padding:10px 14px;border-radius:8px;border:1px solid var(--border-color);color:var(--text-main);font-size:14px;outline:none;}
.form-inline input{min-width:180px;}.form-inline select{min-width:150px;}
.form-inline input:focus,.form-inline select:focus{border-color:var(--primary-hover);}
.filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:end;padding:14px;background:rgba(29,32,33,0.3);border-radius:8px;border:1px solid var(--border-color);}
.filter-bar .fg{display:flex;flex-direction:column;gap:2px;}
.filter-bar .fg label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.4px;}
.filter-bar input,.filter-bar select{padding:7px 10px;border-radius:5px;border:1px solid var(--border-color);background:rgba(29,32,33,0.6);color:var(--text-main);font-size:12px;outline:none;}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--primary-hover);}
.filter-bar input{min-width:160px;}
.btn-filter{padding:7px 14px;border-radius:5px;border:none;font-weight:700;font-size:12px;cursor:pointer;background:var(--primary);color:var(--bg-dim);transition:all .15s;}
.btn-filter:hover{background:var(--primary-hover);}
.btn-order{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:5px;border:1px solid var(--border-color);background:rgba(29,32,33,0.6);color:var(--text-muted);cursor:pointer;font-size:16px;transition:all .15s;text-decoration:none;align-self:end;}
.btn-order:hover{border-color:var(--primary-hover);color:var(--primary-hover);}
.btn-clear{padding:7px 14px;border-radius:5px;border:1px solid var(--border-color);font-weight:600;font-size:12px;cursor:pointer;background:transparent;color:var(--text-muted);text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;align-self:end;}
.btn-clear:hover{border-color:var(--primary-hover);color:var(--primary-hover);}
.btn{display:inline-flex;align-items:center;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer;border:none;font-size:14px;text-decoration:none;transition:all 0.15s ease;}
.btn-add{background:var(--text-main);color:var(--bg-dim);}.btn-add:hover{background:#fabd2f;}
.btn-sm{padding:6px 12px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all 0.15s ease;}
.btn-edit{background:var(--primary);color:var(--bg-dim);}.btn-edit:hover{background:var(--primary-hover);}
.btn-del{background:rgba(204,36,29,0.15);color:var(--danger-hover);border:1px solid rgba(204,36,29,0.3);}.btn-del:hover{background:rgba(251,73,52,0.25);}
.btn-cancel{background:transparent;color:var(--text-muted);border:1px solid var(--border-color);}.btn-cancel:hover{border-color:var(--border-hover);color:var(--text-main);}
table{width:100%;border-collapse:collapse;}
th,td{padding:12px 14px;text-align:left;border-bottom:1px solid var(--border-color);font-size:14px;}
th{background:rgba(40,40,40,0.6);color:var(--text-main);font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;}
td{color:var(--text-muted);}
tr:hover{background:rgba(60,56,54,0.3);}
</style>
</head>
<body>
<div class="page-wrapper">
<?php include '../../includes/navbar.php'; ?>
<div class="admin-layout">
<?php include 'admin_sidebar.php'; ?>
<main class="admin-content">
<div class="container">
<h2><?= $editing ? 'Edit' : 'Add'; ?> Region</h2>
<?php if (isset($_SESSION['message'])): ?><div class="alert"><?= htmlspecialchars($_SESSION['message']); ?><?php unset($_SESSION['message']); ?></div><?php endif; ?>

<form method="POST" class="form-inline">
    <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= $editing['region_id']; ?>">
    <?php endif; ?>
    <input type="text" name="name" required placeholder="Region name" value="<?= $editing ? htmlspecialchars($editing['name']) : ''; ?>">
    <select name="food_type_id" required>
        <option value="">Food Type</option>
        <?php foreach ($food_types as $ft): ?>
            <option value="<?= $ft['food_type_id']; ?>" <?= $editing && $editing['food_type_id'] == $ft['food_type_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($ft['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="<?= $editing ? 'edit' : 'add'; ?>" class="btn btn-add"><?= $editing ? 'Update' : 'Add'; ?></button>
    <?php if ($editing): ?><a href="regions.php" class="btn btn-cancel">Cancel</a><?php endif; ?>
</form>

<h2>All Regions</h2>

<form method="GET" class="filter-bar">
    <div class="fg">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search regions..." value="<?= htmlspecialchars($search); ?>">
    </div>
    <div class="fg">
        <label>Food Type</label>
        <select name="food_type_id">
            <option value="">All</option>
            <?php foreach ($food_types as $ft): ?>
            <option value="<?= $ft['food_type_id']; ?>" <?= $food_type_filter === (int)$ft['food_type_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($ft['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fg">
        <label>Sort</label>
        <select name="sort">
            <option value="id" <?= $sort === 'id' ? 'selected' : ''; ?>>ID</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name</option>
            <option value="food_type" <?= $sort === 'food_type' ? 'selected' : ''; ?>>Food Type</option>
            <option value="countries" <?= $sort === 'countries' ? 'selected' : ''; ?>>Countries</option>
        </select>
    </div>
    <a href="?sort=<?= $sort ?>&order=<?= $opposite_order ?>&search=<?= urlencode($search) ?>&food_type_id=<?= $food_type_filter ?>" class="btn-order" title="Toggle order">
        <span class="material-icons"><?= $order === 'ASC' ? 'arrow_upward' : 'arrow_downward'; ?></span>
    </a>
    <button type="submit" class="btn-filter">Filter</button>
    <a href="regions.php" class="btn-clear">Clear</a>
</form>

<table>
    <thead><tr><th>ID</th><th>Name</th><th>Food Type</th><th>Countries</th><th>Actions</th></tr></thead>
    <tbody>
        <?php while ($r = $result->fetch()): ?>
        <tr>
            <td><?= $r['region_id']; ?></td>
            <td><strong style="color:#458589;"><?= htmlspecialchars($r['name']); ?></strong></td>
            <td><?= htmlspecialchars($r['food_type_name']); ?></td>
            <td><?= $r['country_count']; ?></td>
            <td style="white-space:nowrap;">
                <a href="regions.php?edit=<?= $r['region_id']; ?>" class="btn-sm btn-edit">Edit</a>
                <a href="regions.php?delete=<?= $r['region_id']; ?>" class="btn-sm btn-del" onclick="return confirm('Delete <?= htmlspecialchars($r['name']); ?>?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
        </div>
    </main>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
