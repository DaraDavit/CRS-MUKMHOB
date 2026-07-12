<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

$toast_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_create'])) {
    $name = trim($_POST['name']);
    $country_id = (int)$_POST['country_id'];
    $description = trim($_POST['description'] ?? '');
    $instructions = trim($_POST['instructions']);
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $prep_time = !empty($_POST['prep_time_minutes']) ? (int)$_POST['prep_time_minutes'] : null;
    $cook_time = !empty($_POST['cook_time_minutes']) ? (int)$_POST['cook_time_minutes'] : null;
    $uid = $_SESSION['user_id'];

    if (empty($name) || empty($instructions) || empty($country_id)) {
        $toast_msg = "Name, instructions, and country are required.";
    } else {
        $conn->beginTransaction();
        try {
            $s = $conn->prepare("INSERT INTO recipes (name, user_id, country_id, description, instructions, youtube_url, image_url, prep_time_minutes, cook_time_minutes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $s->execute([$name, $uid, $country_id, $description, $instructions, $youtube_url, $image_url, $prep_time, $cook_time]);
            $rid = $conn->lastInsertId();

            $names = $_POST['ingredient_name'] ?? [];

            $fi = $conn->prepare("SELECT ingredient_id FROM ingredients WHERE name = ?");
            $ci = $conn->prepare("INSERT INTO ingredients (name) VALUES (?)");
            $li = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)");

            for ($i = 0; $i < count($names); $i++) {
                $in = trim($names[$i] ?? '');
                $amt = trim($_POST['amount'][$i] ?? '');
                if (empty($in)) continue;
                $q = null; $u = $amt;
                if (preg_match('/^([\d\.\/\s]+)\s+(.+)$/', $amt, $m)) { $q = $m[1]; $u = $m[2]; }
                $fi->execute([$in]);
                $row = $fi->fetch();
                if ($row) { $iid = $row['ingredient_id']; }
                else { $ci->execute([$in]); $iid = $conn->lastInsertId(); }
                $li->execute([$rid, $iid, $q, $u]);
            }
            if (isset($_POST['categories'])) {
                $cs = $conn->prepare("INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)");
                foreach ($_POST['categories'] as $cid) { $cs->execute([$rid, (int)$cid]); }
            }
            $conn->commit();
            $toast_msg = "Recipe '$name' created!";
        } catch (Exception $e) {
            $conn->rollBack();
            $toast_msg = "Error: " . $e->getMessage();
        }
    }
}

$search = trim($_GET['search'] ?? '');
$food_type_filter = (int)($_GET['food_type_id'] ?? 0);
$sort = $_GET['sort'] ?? 'created';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$where = '1=1';
$params = [];
$extra_joins = '';

if ($search !== '') {
    $where .= ' AND r.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($food_type_filter > 0) {
    $extra_joins = ' JOIN regions regx ON c.region_id = regx.region_id';
    $where .= ' AND regx.food_type_id = ?';
    $params[] = $food_type_filter;
}

$order_map = ['created' => 'r.created_at', 'name' => 'r.name', 'rating' => 'avg_rating'];
$order_col = $order_map[$sort] ?? 'r.created_at';

$sql = "SELECT r.recipe_id, r.name, r.created_at, u.username, c.name AS country_name,
               COALESCE(AVG(rev.rating), 0) AS avg_rating
        FROM recipes r
        LEFT JOIN users u ON r.user_id = u.user_id
        JOIN countries c ON r.country_id = c.country_id
        $extra_joins
        LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id
        WHERE $where
        GROUP BY r.recipe_id
        ORDER BY $order_col $order";

if (empty($params)) {
    $result = $conn->query($sql);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt;
}

$food_types = $conn->query("SELECT * FROM food_types ORDER BY name")->fetchAll();
$categories_q = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin — Recipes</title>
<style>
:root{--bg:#282828;--bg-dim:#1d2021;--card:rgba(50,48,47,0.7);--gold:#d79921;--muted:#a89984;--teal:#458589;--teal-h:#83a598;--border:rgba(60,56,54,0.6);--font:system-ui,-apple-system,sans-serif;--danger:#cc241d;--danger-hover:#fb4934;--green:#b8bb26}
*{box-sizing:border-box;font-family:var(--font);margin:0;padding:0}
body{background:var(--bg-dim);color:var(--muted);-webkit-font-smoothing:antialiased}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh}
.admin-layout{display:flex;flex:1;min-height:0}
.main-content{flex:1;padding:40px 24px}
.container{--gold:#d79921;width:100%;max-width:1200px;margin:0 auto;background:var(--card);padding:30px;border-radius:12px;border:1px solid var(--border);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,.4)}
h2{color:var(--gold);font-weight:800;margin-bottom:20px}
.alert{padding:12px;background:rgba(69,133,137,.15);color:var(--teal-h);border:1px solid rgba(69,133,137,.3);border-radius:6px;margin-bottom:20px;font-weight:700;font-size:14px}
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
.btn-add{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-weight:700;font-size:14px;border:none;cursor:pointer;background:var(--gold);color:var(--bg-dim);transition:all .15s;margin-bottom:20px}
.btn-add:hover{background:#fabd2f}
.btn-sm{display:inline-block;padding:6px 12px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;border:none;cursor:pointer}
.btn-view{background:var(--teal);color:var(--bg-dim)}.btn-view:hover{background:var(--teal-h)}
.btn-del{background:rgba(204,36,29,.15);color:var(--danger-hover);border:1px solid rgba(204,36,29,.3)}.btn-del:hover{background:rgba(251,73,52,.25)}
.rating{color:var(--gold)}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 14px;text-align:left;border-bottom:1px solid var(--border);font-size:14px}
th{background:rgba(40,40,40,.6);color:var(--gold);font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px}
td{color:var(--muted)}
tr:hover{background:rgba(60,56,54,.3)}

/* Modal */
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:999;align-items:center;justify-content:center;opacity:0;transition:opacity .2s}
.modal-overlay.show{display:flex;opacity:1}
.modal-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 25px 50px -12px rgba(0,0,0,.5);transform:scale(.92) translateY(10px);transition:transform .25s}
.show .modal-card{transform:scale(1) translateY(0)}
.modal-card h3{color:var(--gold);font-size:20px;font-weight:800;margin-bottom:2px}
.modal-card .sub{font-size:13px;color:var(--muted);margin-bottom:16px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.form-grid .full{grid-column:span 2}
@media(max-width:700px){.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:span 1}}
.fg{display:flex;flex-direction:column;gap:2px}
.fg label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:.4px}
.fg label .req{color:var(--danger-hover)}
.fg input,.fg select,.fg textarea{padding:9px 11px;border-radius:6px;border:1px solid var(--border);background:rgba(29,32,33,.6);color:var(--gold);font-size:13px;outline:none;transition:all .2s}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--teal-h);box-shadow:0 0 0 3px rgba(69,133,136,.15)}
.fg textarea{min-height:50px;resize:vertical}
.ing-row{display:flex;gap:5px;margin-bottom:4px;align-items:center}
.ing-row input{padding:7px 9px;border-radius:5px;border:1px solid var(--border);background:rgba(29,32,33,.6);color:var(--gold);font-size:12px;outline:none;flex:1}
.ing-row input:focus{border-color:var(--teal-h)}
.ing-row input[name="amount[]"]{max-width:130px;flex:none}
.ing-row .rm-ing{background:rgba(204,36,29,.15);color:var(--danger-hover);border:1px solid rgba(204,36,29,.3);border-radius:4px;width:26px;height:26px;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.ing-row .rm-ing:hover{background:rgba(251,73,52,.25)}
.btn-add-ing{background:transparent;color:var(--teal-h);border:1px dashed var(--border);border-radius:5px;padding:5px;font-size:11px;font-weight:600;cursor:pointer;width:100%;margin-top:4px}
.btn-add-ing:hover{border-color:var(--teal-h)}
.modal-actions{display:flex;gap:8px;margin-top:16px}
.modal-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;border:none;transition:all .15s}
.modal-btn-primary{background:var(--teal);color:var(--bg-dim);flex:1}.modal-btn-primary:hover{background:var(--teal-h)}
.modal-btn-primary:disabled{opacity:.5;cursor:not-allowed}
.modal-btn-close{background:transparent;color:var(--muted);border:1px solid var(--border)}.modal-btn-close:hover{border-color:var(--border-hover);color:var(--gold)}
.modal-err{padding:8px 12px;border-radius:6px;font-size:12px;font-weight:600;margin-bottom:12px;background:rgba(251,73,52,.1);color:var(--danger-hover);border:1px solid rgba(251,73,52,.2);display:none}
@keyframes spin{to{transform:rotate(360deg)}}
.spinner{display:none;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite}.spinner.show{display:inline-block}
#toast{position:fixed;top:20px;right:20px;z-index:9999;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:700;background:rgba(40,40,40,.95);border:1px solid var(--border);backdrop-filter:blur(12px);color:var(--green);transform:translateX(120%);opacity:0;transition:all .35s cubic-bezier(.4,0,.2,1);box-shadow:0 10px 30px -8px rgba(0,0,0,.5);display:flex;align-items:center;gap:10px}
#toast.show{transform:translateX(0);opacity:1}
#toast.err{color:var(--danger-hover)}
@media(max-width:600px){.modal-card{max-width:100%;margin:0 10px;padding:20px}}
</style>
</head>
<body>
<div class="page-wrapper">
<?php include '../../includes/navbar.php'; ?>
<div class="admin-layout">
<?php include 'admin_sidebar.php'; ?>
<main class="admin-content">
<div class="container">
<h2>All Recipes</h2>
<button class="btn-add" onclick="openRecipeModal()">+ Create Recipe</button>

<form method="GET" class="filter-bar">
    <div class="fg">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search recipes..." value="<?= htmlspecialchars($search); ?>">
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
            <option value="created" <?= $sort === 'created' ? 'selected' : ''; ?>>Date</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name</option>
            <option value="rating" <?= $sort === 'rating' ? 'selected' : ''; ?>>Rating</option>
        </select>
    </div>
    <a href="?sort=<?= $sort ?>&order=<?= $opposite_order ?>&search=<?= urlencode($search) ?>&food_type_id=<?= $food_type_filter ?>" class="btn-order" title="Toggle order">
        <span class="material-icons"><?= $order === 'ASC' ? 'arrow_upward' : 'arrow_downward'; ?></span>
    </a>
    <button type="submit" class="btn-filter">Filter</button>
    <a href="recipes.php" class="btn-clear">Clear</a>
</form>

<div class="table-responsive">
<table>
<thead><tr><th>Name</th><th>Author</th><th>Country</th><th>Rating</th><th>Created</th><th>Actions</th></tr></thead>
<tbody>
<?php while ($r = $result->fetch()): ?>
<tr>
<td><strong style="color:#458589;"><?= htmlspecialchars($r['name']); ?></strong></td>
<td><?= htmlspecialchars($r['username'] ?? '—'); ?></td>
<td><?= htmlspecialchars($r['country_name']); ?></td>
<td class="rating"><?php for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:14px;color:#d79921;vertical-align:middle;"><?= $i < round($r['avg_rating']) ? 'star' : 'star_outline'; ?></span><?php endfor; ?></td>
<td><?= $r['created_at']; ?></td>
<td style="white-space:nowrap;">
<a href="../crs_app/view.php?id=<?= $r['recipe_id']; ?>" class="btn-sm btn-view">View</a>
<a href="../crs_app/edit.php?id=<?= $r['recipe_id']; ?>" class="btn-sm btn-view" style="background:var(--gold);color:var(--bg-dim);">Edit</a>
<a href="../crs_app/delete.php?id=<?= $r['recipe_id']; ?>" class="btn-sm btn-del" onclick="return confirm('Delete <?= htmlspecialchars($r['name']); ?>?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</main>
</div>
</div>

<div id="toast"><span class="check"><span class="material-icons" style="font-size:20px;vertical-align:middle;">check_circle</span></span><span id="toastMsg"></span></div>

<!-- Create Recipe Modal -->
<div class="modal-overlay" id="recipeModal" onclick="if(event.target===this)closeRecipeModal()">
<div class="modal-card">
<h3><span class="material-icons" style="vertical-align:middle;margin-right:4px;">restaurant</span> Create Recipe</h3>
<p class="sub">Add a new recipe to the collection</p>
<div class="modal-err" id="modalError"></div>
<form id="recipeForm" method="POST" onsubmit="return handleRecipeSubmit(event)">
<div class="form-grid">
<div class="fg full"><label>Recipe Name <span class="req">*</span></label><input type="text" name="name" required placeholder="e.g. Chicken Adobo"></div>
<div class="fg"><label>Food Type <span class="req">*</span></label>
<select name="food_type_id" id="rFt" required><option value="">— Select —</option>
<?php foreach ($food_types as $ft): ?><option value="<?= $ft['food_type_id']; ?>"><?= htmlspecialchars($ft['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg"><label>Region <span class="req">*</span></label>
<select name="region_id" id="rReg" required><option value="">Select food type</option></select></div>
<div class="fg"><label>Country <span class="req">*</span></label>
<select name="country_id" id="rCtry" required><option value="">Select region</option></select></div>
<div class="fg full"><label>Description</label><textarea name="description" placeholder="Brief description..."></textarea></div>
<div class="fg full"><label>Instructions <span class="req">*</span></label><textarea name="instructions" required placeholder="Step-by-step instructions..."></textarea></div>
<div class="fg full"><label>YouTube URL</label><input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..."></div>
<div class="fg full"><label>Image URL</label><input type="url" name="image_url" placeholder="https://example.com/image.jpg"></div>
<div class="fg"><label>Prep (min)</label><input type="number" name="prep_time_minutes" min="0" placeholder="15"></div>
<div class="fg"><label>Cook (min)</label><input type="number" name="cook_time_minutes" min="0" placeholder="30"></div>
<div class="fg full"><label>Ingredients</label>
<div id="rIngs">
<?php for ($j = 0; $j < 3; $j++): ?>
<div class="ing-row"><input type="text" name="ingredient_name[]" list="rSug" placeholder="Ingredient"><input type="text" name="amount[]" placeholder="Amount (e.g. 2 cups)"><?php if ($j > 0): ?><button type="button" class="rm-ing" onclick="this.parentElement.remove()">×</button><?php endif; ?></div>
<?php endfor; ?>
</div>
<datalist id="rSug"></datalist>
<button type="button" class="btn-add-ing" onclick="addRIngRow()">+ Add Ingredient</button>
</div>
<div class="fg full">
    <label>Categories</label>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
        <?php foreach ($categories_q as $cat): ?>
        <label class="cat-tag">
            <input type="checkbox" name="categories[]" value="<?= $cat['category_id']; ?>" onchange="this.parentElement.classList.toggle('checked', this.checked)">
            <?= htmlspecialchars($cat['name']); ?>
        </label>
        <?php endforeach; ?>
    </div>
</div>
<style>
.cat-tag{display:inline-flex;align-items:center;padding:5px 12px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;background:rgba(29,32,33,0.4);border:1px solid var(--border);color:var(--muted);transition:all .15s;user-select:none}
.cat-tag:hover{border-color:var(--teal-h);color:var(--gold)}
.cat-tag input{display:none}
.cat-tag.checked{background:rgba(69,133,137,0.2);border-color:var(--teal-h);color:var(--teal-h)}
</style>
</div>
<div class="modal-actions">
<button type="submit" name="quick_create" class="modal-btn modal-btn-primary" id="rSubmit"><span class="spinner" id="rSpinner"></span><span id="rBtnText">Create Recipe</span></button>
<button type="button" class="modal-btn modal-btn-close" onclick="closeRecipeModal()">Cancel</button>
</div>
</form>
</div>
</div>

<script>
function openRecipeModal() {
    document.getElementById('recipeModal').classList.add('show');
}
function closeRecipeModal() {
    document.getElementById('recipeModal').classList.remove('show');
    document.getElementById('recipeForm').reset();
    document.getElementById('rReg').innerHTML = '<option value="">Select food type</option>';
    document.getElementById('rCtry').innerHTML = '<option value="">Select region</option>';
    document.getElementById('modalError').style.display = 'none';
    document.getElementById('rSubmit').disabled = false;
    document.getElementById('rBtnText').textContent = 'Create Recipe';
    document.getElementById('rSpinner').classList.remove('show');
    document.querySelectorAll('#recipeForm .cat-tag').forEach(t => t.classList.remove('checked'));
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeRecipeModal(); });

// Cascading dropdowns
document.getElementById('rFt').addEventListener('change', function() {
    const r = document.getElementById('rReg'), c = document.getElementById('rCtry');
    r.innerHTML = '<option value="">— Region —</option>'; c.innerHTML = '<option value="">— Country —</option>';
    if (!this.value) return;
    fetch('../crs_app/api.php?regions&food_type_id=' + this.value).then(x=>x.json()).then(d => {
        d.forEach(x => { const o = document.createElement('option'); o.value = x.region_id; o.textContent = x.name; r.appendChild(o); });
    });
});
document.getElementById('rReg').addEventListener('change', function() {
    const c = document.getElementById('rCtry');
    c.innerHTML = '<option value="">— Country —</option>';
    if (!this.value) return;
    fetch('../crs_app/api.php?countries&region_id=' + this.value).then(x=>x.json()).then(d => {
        d.forEach(x => { const o = document.createElement('option'); o.value = x.country_id; o.textContent = x.name; c.appendChild(o); });
    });
});

// Ingredient autocomplete
function setupRIng(el) {
    el.addEventListener('input', function() {
        if (this.value.length < 1) return;
        fetch('../crs_app/api.php?ingredients&q=' + encodeURIComponent(this.value)).then(r=>r.json()).then(d => {
            const dl = document.getElementById('rSug'); dl.innerHTML = '';
            d.forEach(x => { const o = document.createElement('option'); o.value = x.name; dl.appendChild(o); });
        });
    });
}
function addRIngRow() {
    const c = document.getElementById('rIngs'), row = document.createElement('div');
    row.className = 'ing-row';
    row.innerHTML = '<input type="text" name="ingredient_name[]" list="rSug" placeholder="Ingredient"><input type="text" name="amount[]" placeholder="Amount (e.g. 2 cups)"><button type="button" class="rm-ing" onclick="this.parentElement.remove()">×</button>';
    c.appendChild(row); setupRIng(row.querySelector('input[name="ingredient_name[]"]'));
}
document.querySelectorAll('#rIngs input[name="ingredient_name[]"]').forEach(setupRIng);

function showToast(msg, isErr) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.className = isErr ? 'err' : '';
    t.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

function handleRecipeSubmit(e) {
    e.preventDefault();
    const err = document.getElementById('modalError');
    err.style.display = 'none';
    const name = document.querySelector('[name="name"]').value.trim();
    const country = document.getElementById('rCtry').value;
    const instructions = document.querySelector('[name="instructions"]').value.trim();
    let errors = [];
    if (!name) errors.push('Recipe name is required');
    if (!country) errors.push('Country is required');
    if (!instructions) errors.push('Instructions are required');
    if (errors.length) { err.textContent = errors.join('. '); err.style.display = 'block'; return false; }

    document.getElementById('rSubmit').disabled = true;
    document.getElementById('rBtnText').textContent = 'Creating...';
    document.getElementById('rSpinner').classList.add('show');

    const fd = new FormData(document.getElementById('recipeForm'));
    fd.set('quick_create', '1');

    fetch('recipes.php', { method: 'POST', body: fd })
        .then(() => { closeRecipeModal(); showToast('Recipe created!'); setTimeout(() => window.location.reload(), 1200); })
        .catch(() => {
            document.getElementById('rSubmit').disabled = false;
            document.getElementById('rBtnText').textContent = 'Create Recipe';
            document.getElementById('rSpinner').classList.remove('show');
            err.textContent = 'Server error. Try again.';
            err.style.display = 'block';
        });
    return false;
}

<?php if ($toast_msg): ?>
window.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($toast_msg); ?>); });
<?php endif; ?>
</script>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
