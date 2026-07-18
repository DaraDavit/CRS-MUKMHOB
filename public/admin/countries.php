<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $region_id = (int)$_POST['region_id'];
    if (!empty($name) && $region_id) {
        $stmt = $conn->prepare("INSERT INTO countries (name, region_id) VALUES (?, ?)");
        $stmt->execute([$name, $region_id]);
        $_SESSION['message'] = "Country added.";
        header("Location: countries.php");
        exit;
    }
}

$editing = null;
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $region_id = (int)$_POST['region_id'];
    if (!empty($name) && $region_id) {
        $stmt = $conn->prepare("UPDATE countries SET name = ?, region_id = ? WHERE country_id = ?");
        $stmt->execute([$name, $region_id, $id]);
        $_SESSION['message'] = "Country updated.";
        header("Location: countries.php");
        exit;
    }
}
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $r = $conn->prepare("SELECT * FROM countries WHERE country_id = ?");
    $r->execute([$eid]);
    $editing = $r->fetch();
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $r = $conn->prepare("SELECT COUNT(*) AS c FROM recipes WHERE country_id = ?");
    $r->execute([$id]);
    $row = $r->fetch();
    if ($row['c'] > 0) {
        $_SESSION['message'] = "Cannot delete — {$row['c']} recipe(s) use this country.";
    } else {
        $conn->prepare("DELETE FROM countries WHERE country_id = ?")->execute([$id]);
        $_SESSION['message'] = "Country deleted.";
    }
    header("Location: countries.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$region_filter = (int)($_GET['region_id'] ?? 0);
$sort = $_GET['sort'] ?? 'id';
$order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND c.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($region_filter > 0) {
    $where .= ' AND c.region_id = ?';
    $params[] = $region_filter;
}
$sort_map = ['name' => 'c.name', 'region' => 'r.name', 'food_type' => 'ft.name', 'id' => 'c.country_id'];
$order_col = $sort_map[$sort] ?? 'c.country_id';

$result = $conn->prepare("SELECT c.*, r.name AS region_name, ft.name AS food_type_name FROM countries c JOIN regions r ON c.region_id = r.region_id JOIN food_types ft ON r.food_type_id = ft.food_type_id WHERE $where ORDER BY $order_col $order");
$result->execute($params);
$regions = $conn->query("SELECT reg.*, ft.name AS food_type_name FROM regions reg JOIN food_types ft ON reg.food_type_id = ft.food_type_id ORDER BY ft.name, reg.name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Admin — Countries</title>
<style>
:root{--bg-color:#282828;--bg-dim:#1d2021;--card-bg:rgba(50,48,47,0.7);--text-main:#d79921;--text-muted:#a89984;--primary:#458589;--primary-hover:#83a598;--border-color:rgba(60,56,54,0.6);--font-stack:system-ui,-apple-system,sans-serif;--danger:#cc241d;--danger-hover:#fb4934;}
[data-theme="light"]{--bg-color:#d5c4a1;--bg-dim:#c9b99a;--card-bg:#ebdbb2;--text-main:#3c3836;--text-muted:#7c6f64;--primary:#458588;--primary-hover:#83a598;--border-color:#bdae93;--font-stack:system-ui,-apple-system,sans-serif;--danger:#9d0006;--danger-hover:#cc241d;}
*{box-sizing:border-box;font-family:var(--font-stack);margin:0;padding:0;}
body{background-color:var(--bg-dim);color:var(--text-muted);-webkit-font-smoothing:antialiased;}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh;}
.main-content{flex:1;padding:40px 24px;}
.container{--text-main:#d79921;width:100%;max-width:1000px;margin:0 auto;background:var(--card-bg);padding:30px;border-radius:12px;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,0.4);}
h2{color:var(--text-main);font-weight:800;margin-bottom:20px;}
.alert{padding:12px;background:rgba(69,133,137,0.15);color:var(--primary-hover);border:1px solid rgba(69,133,137,0.3);border-radius:6px;margin-bottom:20px;font-weight:bold;font-size:14px;}
.form-inline{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:end;}
.form-inline input[type=text],.form-inline select{padding:10px 14px;border-radius:8px;border:1px solid var(--border-color);color:var(--text-main);font-size:14px;outline:none;}
.form-inline input{min-width:180px;}.form-inline select{min-width:200px;}
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
<h2><?= $editing ? 'Edit' : 'Add'; ?> Country</h2>
<?php if (isset($_SESSION['message'])): ?><div class="alert"><?= htmlspecialchars($_SESSION['message']); ?><?php unset($_SESSION['message']); ?></div><?php endif; ?>

<form method="POST" class="form-inline">
    <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= $editing['country_id']; ?>">
    <?php endif; ?>
    <input type="text" name="name" required placeholder="Country name" value="<?= $editing ? htmlspecialchars($editing['name']) : ''; ?>">
    <select name="region_id" required>
        <option value="">Region</option>
        <?php foreach ($regions as $reg): ?>
            <option value="<?= $reg['region_id']; ?>" <?= $editing && $editing['region_id'] == $reg['region_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($reg['food_type_name']) . ' / ' . htmlspecialchars($reg['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="<?= $editing ? 'edit' : 'add'; ?>" class="btn btn-add"><?= $editing ? 'Update' : 'Add'; ?></button>
    <?php if ($editing): ?><a href="countries.php" class="btn btn-cancel">Cancel</a><?php endif; ?>
</form>

<h2>All Countries</h2>

<form method="GET" class="filter-bar">
    <div class="fg">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search countries..." value="<?= htmlspecialchars($search); ?>">
    </div>
    <div class="fg">
        <label>Region</label>
        <select name="region_id">
            <option value="">All</option>
            <?php foreach ($regions as $reg): ?>
            <option value="<?= $reg['region_id']; ?>" <?= $region_filter === (int)$reg['region_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($reg['food_type_name']) . ' / ' . htmlspecialchars($reg['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fg">
        <label>Sort</label>
        <select name="sort">
            <option value="id" <?= $sort === 'id' ? 'selected' : ''; ?>>ID</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name</option>
            <option value="region" <?= $sort === 'region' ? 'selected' : ''; ?>>Region</option>
            <option value="food_type" <?= $sort === 'food_type' ? 'selected' : ''; ?>>Food Type</option>
        </select>
    </div>
    <a href="?sort=<?= $sort ?>&order=<?= $opposite_order ?>&search=<?= urlencode($search) ?>&region_id=<?= $region_filter ?>" class="btn-order" title="Toggle order">
        <span class="material-icons"><?= $order === 'ASC' ? 'arrow_upward' : 'arrow_downward'; ?></span>
    </a>
    <button type="submit" class="btn-filter">Filter</button>
    <a href="countries.php" class="btn-clear">Clear</a>
</form>

<table>
    <thead><tr><th>ID</th><th>Name</th><th>Region</th><th>Food Type</th><th>Actions</th></tr></thead>
    <tbody>
        <?php while ($c = $result->fetch()): ?>
        <tr>
            <td><?= $c['country_id']; ?></td>
            <td><strong style="color:#458589;"><?= htmlspecialchars($c['name']); ?></strong></td>
            <td><?= htmlspecialchars($c['region_name']); ?></td>
            <td><?= htmlspecialchars($c['food_type_name']); ?></td>
            <td style="white-space:nowrap;">
                <a href="countries.php?edit=<?= $c['country_id']; ?>" class="btn-sm btn-edit">Edit</a>
                <a href="countries.php?delete=<?= $c['country_id']; ?>" class="btn-sm btn-del" onclick="return confirm('Delete <?= htmlspecialchars($c['name']); ?>?')">Delete</a>
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
