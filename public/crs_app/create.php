<?php
session_start();
require '../../includes/db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Content Collector')) {
    header('Location: ../auth/login.php');
    exit;
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $country_id = (int)$_POST['country_id'];
    $description = trim($_POST['description'] ?? '');
    $instructions = trim($_POST['instructions']);
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $prep_time = !empty($_POST['prep_time_minutes']) ? (int)$_POST['prep_time_minutes'] : null;
    $cook_time = !empty($_POST['cook_time_minutes']) ? (int)$_POST['cook_time_minutes'] : null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (empty($name) || empty($instructions) || empty($country_id)) {
        $error = "Name, instructions, and country are required.";
    } else {
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("INSERT INTO recipes (name, user_id, country_id, description, instructions, youtube_url, image_url, prep_time_minutes, cook_time_minutes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $user_id, $country_id, $description, $instructions, $youtube_url, $image_url, $prep_time, $cook_time]);
            $recipe_id = $conn->lastInsertId();

            $ing_names = $_POST['ingredient_name'] ?? [];
            $amounts = $_POST['amount'] ?? [];

            $find_ing = $conn->prepare("SELECT ingredient_id FROM ingredients WHERE name = ?");
            $create_ing = $conn->prepare("INSERT INTO ingredients (name) VALUES (?)");
            $link_ing = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)");

            for ($i = 0; $i < count($ing_names); $i++) {
                $ing_name = trim($ing_names[$i] ?? '');
                $amt = trim($amounts[$i] ?? '');
                if (empty($ing_name)) continue;
                $qty = null; $unit = $amt;
                if (preg_match('/^([\d\.\/\s]+)\s+(.+)$/', $amt, $m)) { $qty = $m[1]; $unit = $m[2]; }

                $find_ing->execute([$ing_name]);
                $row = $find_ing->fetch();

                if ($row) {
                    $ingredient_id = $row['ingredient_id'];
                } else {
                    $create_ing->execute([$ing_name]);
                    $ingredient_id = $conn->lastInsertId();
                }

                $link_ing->execute([$recipe_id, $ingredient_id, $qty, $unit]);
            }

            if (isset($_POST['categories'])) {
                $cs = $conn->prepare("INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)");
                foreach ($_POST['categories'] as $cid) { $cs->execute([$recipe_id, (int)$cid]); }
            }
            $conn->commit();
            $_SESSION['message'] = "Recipe created successfully!";
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error creating recipe: " . $e->getMessage();
        }
    }
}

$food_types = $conn->query("SELECT * FROM food_types ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Recipe</title>
    <style>
        :root, [data-theme="light"] {
            --primary: #458589; --primary-hover: #83a598;
        }
        * { box-sizing:border-box; font-family:var(--font-stack); margin:0; padding:0; }
        body { background-color:var(--bg-dim); color:var(--text-muted); -webkit-font-smoothing:antialiased; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .main-content { flex:1; display:flex; align-items:center; justify-content:center; padding:40px 24px; }
        .container {
            width:100%; max-width:650px;
            background:var(--card-bg); padding:40px;
            border-radius:16px; border:1px solid var(--border-color);
            backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
            box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);
        }
        h2 { margin-bottom:28px; color:var(--text-main); font-weight:800; letter-spacing:-0.75px; font-size:24px; }
        .form-group { margin-bottom:20px; }
        label { display:block; margin-bottom:8px; font-size:14px; font-weight:600; color:var(--text-main); }
        input[type="text"], input[type="number"], input[type="url"], select, textarea {
            width:100%; padding:12px 16px; background-color:var(--bg-dim);
            border:1px solid var(--border-color); color:var(--text-main);
            border-radius:10px; font-size:15px; outline:none; transition:all 0.2s ease;
        }
        input:hover, select:hover, textarea:hover { border-color:var(--border-hover); }
        input:focus, select:focus, textarea:focus { border-color:var(--primary-hover); background-color:var(--bg-dim); box-shadow:0 0 0 3px rgba(69,133,137,0.25); }
        textarea { min-height:100px; resize:vertical; }

        .ingredient-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
        .ingredient-row input[name="ingredient_name[]"] { flex:2; }
        .ingredient-row input[name="amount[]"] { flex:1; max-width:140px; }
        .btn-remove-ingredient {
            background:rgba(204,36,29,0.15); color:#fb4934; border:1px solid rgba(204,36,29,0.3);
            border-radius:6px; width:36px; height:36px; font-size:18px; cursor:pointer;
            display:flex; align-items:center; justify-content:center; flex-shrink:0;
        }
        .btn-remove-ingredient:hover { background:rgba(251,73,52,0.25); }
        .btn-add-ingredient {
            background:transparent; color:var(--primary-hover); border:1px dashed var(--border-color);
            border-radius:8px; padding:8px 16px; font-size:14px; font-weight:600; cursor:pointer; width:100%;
        }
        .btn-add-ingredient:hover { border-color:var(--primary-hover); color:var(--primary-hover); }

        .btn {
            display:inline-flex; align-items:center; justify-content:center;
            padding:12px 24px; border:none; border-radius:10px; font-weight:700;
            cursor:pointer; text-decoration:none; font-size:15px; transition:all 0.2s ease;
        }
        .btn-submit { background-color:var(--primary); color:var(--bg-dim); box-shadow:0 4px 14px rgba(69,133,137,0.3); }
        .btn-submit:hover { background-color:var(--primary-hover); box-shadow:0 6px 20px rgba(131,165,152,0.4); transform:translateY(-1px); }
        .btn-back { background:transparent; color:var(--text-muted); border:1px solid var(--border-color); margin-left:10px; }
        .btn-back:hover { border-color:var(--border-hover); color:var(--text-main); }
        .form-actions { margin-top:32px; display:flex; align-items:center; }
        .msg-error { background:rgba(251,73,52,0.1); color:#fb4934; border:1px solid rgba(251,73,52,0.2); padding:12px; border-radius:10px; font-size:14px; font-weight:500; margin-bottom:20px; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../../includes/navbar.php'; ?>
    <main class="main-content">
        <div class="container">
            <h2>Add New Recipe</h2>

            <?php if ($error): ?>
                <div class="msg-error"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Recipe Name</label>
                    <input type="text" name="name" required placeholder="e.g. Chicken Adobo">
                </div>
                <div class="form-group">
                    <label>Food Type</label>
                    <select name="food_type_id" id="food-type" required>
                        <option value="">-- Select Food Type --</option>
                        <?php while ($ft = $food_types->fetch()): ?>
                            <option value="<?= $ft['food_type_id']; ?>"><?= htmlspecialchars($ft['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Region</label>
                    <select name="region_id" id="region" required>
                        <option value="">-- Select Food Type First --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <select name="country_id" id="country" required>
                        <option value="">-- Select Region First --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description of the recipe..."></textarea>
                </div>
                <div class="form-group">
                    <label>Instructions</label>
                    <textarea name="instructions" required placeholder="Step-by-step instructions..."></textarea>
                </div>
                <div class="form-group">
                    <label>YouTube URL</label>
                    <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=...">
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label>Image URL</label>
                    <input type="url" name="image_url" placeholder="https://example.com/image.jpg">
                </div>
                <div style="display:flex; gap:16px;">
                    <div class="form-group" style="flex:1;">
                        <label>Prep Time (minutes)</label>
                        <input type="number" name="prep_time_minutes" min="0" placeholder="e.g. 15">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Cook Time (minutes)</label>
                        <input type="number" name="cook_time_minutes" min="0" placeholder="e.g. 30">
                    </div>
                </div>

                <div class="form-group">
                    <label>Ingredients</label>
                    <div id="ingredients-container">
                        <div class="ingredient-row">
                            <input type="text" name="ingredient_name[]" list="ingredient-suggestions" placeholder="Ingredient" required>
                            <input type="text" name="amount[]" placeholder="Amount (e.g. 2 cups)" required>
                            <button type="button" class="btn-remove-ingredient" onclick="this.parentElement.remove()">×</button>
                        </div>
                    </div>
                    <datalist id="ingredient-suggestions"></datalist>
                    <button type="button" class="btn-add-ingredient" onclick="addIngredientRow()">+ Add Ingredient</button>
                </div>

                <div class="form-group">
                    <label>Categories</label>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        <?php $cats = $conn->query("SELECT * FROM categories ORDER BY name"); while ($cat = $cats->fetch()): ?>
                        <label class="cat-tag">
                            <input type="checkbox" name="categories[]" value="<?= $cat['category_id']; ?>" onchange="this.parentElement.classList.toggle('checked', this.checked)">
                            <?= htmlspecialchars($cat['name']); ?>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <style>
                .cat-tag { display:inline-flex; align-items:center; padding:5px 12px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; background:var(--card-bg); border:1px solid var(--border-color); color:var(--text-muted); transition:all 0.15s ease; user-select:none; }
                .cat-tag:hover { border-color:var(--primary-hover); color:var(--text-main); }
                .cat-tag input { display:none; }
                .cat-tag.checked { background:rgba(69,133,137,0.2); border-color:var(--primary-hover); color:var(--primary-hover); }
                </style>

                <div class="form-actions">
                    <button type="submit" name="submit" class="btn btn-submit">Save Recipe</button>
                    <a href="index.php" class="btn btn-back">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>
<script>
document.getElementById('food-type').addEventListener('change', function() {
    const ftId = this.value;
    const regionSel = document.getElementById('region');
    const countrySel = document.getElementById('country');
    regionSel.innerHTML = '<option value="">-- Select Region --</option>';
    countrySel.innerHTML = '<option value="">-- Select Region First --</option>';
    if (!ftId) return;
    fetch('api.php?regions&food_type_id=' + ftId)
        .then(r => r.json())
        .then(data => {
            data.forEach(function(reg) {
                const opt = document.createElement('option');
                opt.value = reg.region_id;
                opt.textContent = reg.name;
                regionSel.appendChild(opt);
            });
        });
});
document.getElementById('region').addEventListener('change', function() {
    const regId = this.value;
    const countrySel = document.getElementById('country');
    countrySel.innerHTML = '<option value="">-- Select Country --</option>';
    if (!regId) return;
    fetch('api.php?countries&region_id=' + regId)
        .then(r => r.json())
        .then(data => {
            data.forEach(function(c) {
                const opt = document.createElement('option');
                opt.value = c.country_id;
                opt.textContent = c.name;
                countrySel.appendChild(opt);
            });
        });
});

function setupIngredientAutocomplete(input) {
    input.addEventListener('input', function() {
        const q = this.value;
        if (q.length < 1) return;
        fetch('api.php?ingredients&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                const dl = document.getElementById('ingredient-suggestions');
                dl.innerHTML = '';
                data.forEach(function(ing) {
                    const opt = document.createElement('option');
                    opt.value = ing.name;
                    dl.appendChild(opt);
                });
            });
    });
}

function addIngredientRow(name, qty, unit) {
    const container = document.getElementById('ingredients-container');
    const row = document.createElement('div');
    row.className = 'ingredient-row';
    row.innerHTML =
        '<input type="text" name="ingredient_name[]" list="ingredient-suggestions" placeholder="Ingredient" required>' +
        '<input type="text" name="amount[]" placeholder="Amount (e.g. 2 cups)" required>' +
        '<button type="button" class="btn-remove-ingredient" onclick="this.parentElement.remove()">×</button>';
    container.appendChild(row);
    setupIngredientAutocomplete(row.querySelector('input[name="ingredient_name[]"]'));
}

document.querySelectorAll('.ingredient-row input[name="ingredient_name[]"]').forEach(setupIngredientAutocomplete);
</script>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
