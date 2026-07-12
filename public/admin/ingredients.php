<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $cal = !empty($_POST['calories_per_100g']) ? (int)$_POST['calories_per_100g'] : null;
    $gram = !empty($_POST['grams_per_unit']) ? (float)$_POST['grams_per_unit'] : null;
    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE ingredients SET name = ?, calories_per_100g = ?, grams_per_unit = ? WHERE ingredient_id = ?");
        $stmt->execute([$name, $cal, $gram, $id]);
        $_SESSION['message'] = "Ingredient updated.";
        header("Location: ingredients.php");
        exit;
    }
}
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $r = $conn->prepare("SELECT * FROM ingredients WHERE ingredient_id = ?");
    $r->execute([$eid]);
    $editing = $r->fetch();
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $r = $conn->prepare("SELECT COUNT(*) AS c FROM recipe_ingredients WHERE ingredient_id = ?");
    $r->execute([$id]);
    $row = $r->fetch();
    if ($row['c'] > 0) {
        $_SESSION['message'] = "Cannot delete — used in {$row['c']} recipe(s).";
    } else {
        $conn->prepare("DELETE FROM ingredients WHERE ingredient_id = ?")->execute([$id]);
        $_SESSION['message'] = "Ingredient deleted.";
    }
    header("Location: ingredients.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'id';
$order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND i.name LIKE ?';
    $params[] = '%' . $search . '%';
}
$sort_map = ['name' => 'i.name', 'id' => 'i.ingredient_id', 'usage' => 'usage_count'];
$order_col = $sort_map[$sort] ?? 'i.name';

$result = $conn->prepare("SELECT i.*, (SELECT COUNT(*) FROM recipe_ingredients WHERE ingredient_id = i.ingredient_id) AS usage_count FROM ingredients i WHERE $where ORDER BY $order_col $order");
$result->execute($params);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Admin — Ingredients</title>
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
.form-inline input[type=text]{padding:10px 14px;border-radius:8px;border:1px solid var(--border-color);color:var(--text-main);font-size:14px;outline:none;min-width:250px;}
.form-inline input:focus{border-color:var(--primary-hover);}
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
.btn-clear{padding:7px 14px;border-radius:5px;border:1px solid var(--border);font-weight:600;font-size:12px;cursor:pointer;background:transparent;color:var(--text-muted);text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;align-self:end;}
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
<h2><?= isset($editing) ? 'Edit' : ''; ?> Ingredients</h2>
<?php if (isset($_SESSION['message'])): ?><div class="alert"><?= htmlspecialchars($_SESSION['message']); ?><?php unset($_SESSION['message']); ?></div><?php endif; ?>

<?php if (isset($editing) && $editing): ?>
<form method="POST" class="form-inline" style="gap:6px;">
    <input type="hidden" name="id" value="<?= $editing['ingredient_id']; ?>">
    <input type="text" name="name" required value="<?= htmlspecialchars($editing['name']); ?>" style="flex:1;min-width:160px;">
    <input type="number" name="calories_per_100g" placeholder="Cal/100g" value="<?= $editing['calories_per_100g']; ?>" style="width:80px;padding:10px 8px;border-radius:8px;border:1px solid var(--border-color);background:rgba(29,32,33,0.6);color:var(--text-main);font-size:13px;outline:none;">
    <input type="number" step="0.01" name="grams_per_unit" placeholder="Grams/unit" value="<?= $editing['grams_per_unit']; ?>" style="width:100px;padding:10px 8px;border-radius:8px;border:1px solid var(--border-color);background:rgba(29,32,33,0.6);color:var(--text-main);font-size:13px;outline:none;">
    <button type="submit" name="edit" class="btn btn-add">Update</button>
    <a href="ingredients.php" class="btn btn-cancel">Cancel</a>
</form>
<?php endif; ?>

<h2>All Ingredients</h2>

<form method="GET" class="filter-bar">
    <div class="fg">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search ingredients..." value="<?= htmlspecialchars($search); ?>">
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
    <a href="ingredients.php" class="btn-clear">Clear</a>
</form>

<table>
    <thead><tr><th>ID</th><th>Name</th><th>Cal/100g</th><th>Grams/Unit</th><th>Used in</th><th>Actions</th></tr></thead>
    <tbody>
        <?php while ($ing = $result->fetch()): ?>
        <tr>
            <td><?= $ing['ingredient_id']; ?></td>
            <td><strong style="color:#458589;"><?= htmlspecialchars($ing['name']); ?></strong></td>
            <td><span style="color:<?= $ing['calories_per_100g'] ? 'var(--gold)' : 'var(--text-muted)'; ?>;"><?= $ing['calories_per_100g'] ?? '—'; ?></span></td>
            <td><span style="color:<?= $ing['grams_per_unit'] ? 'var(--gold)' : 'var(--text-muted)'; ?>;"><?= $ing['grams_per_unit'] ? rtrim(rtrim(number_format($ing['grams_per_unit'], 2), '0'), '.') : '—'; ?></span></td>
            <td><?= $ing['usage_count']; ?></td>
            <td style="white-space:nowrap;">
                <a href="ingredients.php?edit=<?= $ing['ingredient_id']; ?>" class="btn-sm btn-edit">Edit</a>
                <a href="ingredients.php?delete=<?= $ing['ingredient_id']; ?>" class="btn-sm btn-del" onclick="return confirm('Delete <?= htmlspecialchars($ing['name']); ?>?')">Delete</a>
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
