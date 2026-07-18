<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

$msg = '';

// --- Queries ---
$t_users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch()['c'];
$t_recipes = $conn->query("SELECT COUNT(*) AS c FROM recipes")->fetch()['c'];
$t_reviews = $conn->query("SELECT COUNT(*) AS c FROM reviews")->fetch()['c'];
$t_ings = $conn->query("SELECT COUNT(*) AS c FROM ingredients")->fetch()['c'];

$by_cuisine = $conn->query("SELECT ft.name, COUNT(r.recipe_id) AS cnt FROM food_types ft JOIN regions reg ON ft.food_type_id = reg.food_type_id JOIN countries c ON reg.region_id = c.region_id LEFT JOIN recipes r ON c.country_id = r.country_id GROUP BY ft.name ORDER BY cnt DESC");
$cuisine_data = []; $max_cuisine = 0;
while ($row = $by_cuisine->fetch()) { $cuisine_data[] = $row; if ($row['cnt'] > $max_cuisine) $max_cuisine = $row['cnt']; }

$rating_by_cuisine = $conn->query("SELECT ft.name, COALESCE(AVG(rev.rating), 0) AS avg_r FROM food_types ft JOIN regions reg ON ft.food_type_id = reg.food_type_id JOIN countries c ON reg.region_id = c.region_id LEFT JOIN recipes r ON c.country_id = r.country_id LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id GROUP BY ft.name ORDER BY avg_r DESC");
$rating_data = []; while ($row = $rating_by_cuisine->fetch()) $rating_data[] = $row;

$top_ings = $conn->query("SELECT i.name, COUNT(ri.recipe_id) AS cnt FROM ingredients i JOIN recipe_ingredients ri ON i.ingredient_id = ri.ingredient_id GROUP BY i.name ORDER BY cnt DESC LIMIT 10");

$rdist = []; $mrc = 0;
$rq = $conn->query("SELECT rating, COUNT(*) AS cnt FROM reviews GROUP BY rating ORDER BY rating DESC");
while ($row = $rq->fetch()) { $rdist[] = $row; if ($row['cnt'] > $mrc) $mrc = $row['cnt']; }

$no_rev = $conn->query("SELECT COUNT(*) AS c FROM recipes r LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id WHERE rev.review_id IS NULL")->fetch()['c'];

$food_types = $conn->query("SELECT * FROM food_types ORDER BY name")->fetchAll();
$regions_all = $conn->query("SELECT r.*, ft.name AS ft_name FROM regions r JOIN food_types ft ON r.food_type_id = ft.food_type_id ORDER BY ft.name, r.name")->fetchAll();
$countries_all = $conn->query("SELECT c.*, r.name AS reg_name, ft.name AS ft_name FROM countries c JOIN regions r ON c.region_id = r.region_id JOIN food_types ft ON r.food_type_id = ft.food_type_id ORDER BY ft.name, r.name, c.name")->fetchAll();
$ingredients_all = $conn->query("SELECT i.*, (SELECT COUNT(*) FROM recipe_ingredients WHERE ingredient_id = i.ingredient_id) AS cnt FROM ingredients i ORDER BY i.name")->fetchAll();
$edit_ft = null;
if (isset($_GET['edit_ft'])) {
    $s = $conn->prepare("SELECT * FROM food_types WHERE food_type_id = ?");
    $s->execute([(int)$_GET['edit_ft']]);
    $edit_ft = $s->fetch();
}
$edit_reg = null;
if (isset($_GET['edit_reg'])) {
    $s = $conn->prepare("SELECT * FROM regions WHERE region_id = ?");
    $s->execute([(int)$_GET['edit_reg']]);
    $edit_reg = $s->fetch();
}
$edit_c = null;
if (isset($_GET['edit_c'])) {
    $s = $conn->prepare("SELECT * FROM countries WHERE country_id = ?");
    $s->execute([(int)$_GET['edit_c']]);
    $edit_c = $s->fetch();
}
$edit_ing = null;
if (isset($_GET['edit_ing'])) {
    $s = $conn->prepare("SELECT * FROM ingredients WHERE ingredient_id = ?");
    $s->execute([(int)$_GET['edit_ing']]);
    $edit_ing = $s->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Admin Dashboard</title>
<style>
:root{--bg:#282828;--bg-dim:#1d2021;--card:rgba(50,48,47,0.7);--gold:#d79921;--muted:#a89984;--teal:#458589;--teal-h:#83a598;--border:rgba(60,56,54,0.6);--font:system-ui,-apple-system,sans-serif;--red:#cc241d;--red-h:#fb4934;--green:#b8bb26}
[data-theme="light"]{--bg:#f8f5f0;--bg-dim:#f0ebe4;--card:#ffffff;--gold:#b5895c;--muted:#8a7f78;--teal:#5a9a9c;--teal-h:#7ab0b2;--border:#e0d6cc;--red:#c0392b;--red-h:#e74c3c;--green:#689d6a}
*{box-sizing:border-box;font-family:var(--font);margin:0;padding:0}
body{background:var(--bg-dim);color:var(--muted);-webkit-font-smoothing:antialiased}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh}
.admin-layout{display:flex;flex:1;min-height:0}

/* Sidebar styles from admin_sidebar.php */

.admin-content{flex:1;padding:24px 20px;overflow-x:auto;min-width:0}
.container{--gold:#d79921;width:100%;max-width:1400px;margin:0 auto;background:var(--card);padding:24px;border-radius:10px;border:1px solid var(--border);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,.4)}
h2{color:var(--gold);font-weight:800;letter-spacing:-.5px;margin-bottom:16px;font-size:20px}
h3{color:var(--gold);font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:.5px;padding-bottom:6px;border-bottom:1px solid var(--border);margin-bottom:12px}
h4{color:var(--muted);font-weight:700;font-size:13px;margin-bottom:8px}

/* Msg */
.msg{padding:9px 12px;border-radius:5px;font-size:13px;font-weight:600;margin-bottom:12px}
.msg-ok{background:rgba(184,187,38,.12);color:var(--green);border:1px solid rgba(184,187,38,.25)}
.msg-err{background:rgba(251,73,52,.1);color:var(--red-h);border:1px solid rgba(251,73,52,.2)}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px}
.stat-c{background:rgba(29,32,33,.4);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center;border-left:4px solid var(--border);transition:all .2s}
.stat-c:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.3)}
.stat-c .ico{font-size:24px}.stat-c .n{font-size:28px;font-weight:800;color:var(--gold)}.stat-c .l{font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:.5px;margin-top:2px}
.s-users{border-left-color:var(--teal-h)}.s-recipes{border-left-color:var(--gold)}.s-reviews{border-left-color:var(--green)}.s-ings{border-left-color:var(--red-h)}

/* Analytics grid */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:900px){.grid2{grid-template-columns:1fr}}
.panel{background:rgba(29,32,33,.3);border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:0}
.panel p{font-size:12px;color:var(--muted)}
.bar-row{display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:12px}
.bar-row .lbl{min-width:85px;font-weight:600;color:var(--gold);font-size:12px}
.bar-row .bar{height:18px;border-radius:3px;background:var(--teal);min-width:4px}
.bar-row .val{font-size:11px;color:var(--muted);min-width:18px}
.star-row{display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:12px}
.star-row .lbl{min-width:85px;font-size:12px}.star-row .s{color:var(--gold);font-size:13px}
.star-row .bar{height:14px;border-radius:3px;background:var(--gold);min-width:4px}
.ing-list{list-style:none;padding:0}
.ing-list li{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(60,56,54,.3);font-size:12px}
.ing-list li .name{color:var(--muted)}.ing-list li .cnt{color:var(--gold);font-weight:700}

/* Accordion */
.accordion{border:1px solid var(--border);border-radius:8px;margin-bottom:10px;overflow:hidden;background:rgba(29,32,33,.25)}
.accordion summary{display:flex;align-items:center;gap:8px;padding:12px 16px;cursor:pointer;font-weight:700;font-size:14px;color:var(--gold);list-style:none;transition:background .15s}
.accordion summary::-webkit-details-marker{display:none}
.accordion summary:hover{background:rgba(255,255,255,.03)}
.accordion summary .arrow{font-size:12px;transition:transform .2s;margin-left:auto;color:var(--muted)}
.accordion[open] summary .arrow{transform:rotate(180deg)}
.accordion-body{padding:14px 16px 16px;border-top:1px solid var(--border)}
.accordion-body h4{margin-bottom:8px;font-size:13px;color:var(--gold)}

/* Tables */
.ctbl{width:100%;border-collapse:collapse;font-size:12px}
.ctbl th,.ctbl td{padding:7px 8px;text-align:left;border-bottom:1px solid rgba(60,56,54,.3)}
.ctbl th{color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;background:rgba(40,40,40,.3)}
.ctbl td{color:var(--muted)}
.ctbl tr:hover{background:rgba(60,56,54,.15)}

/* Inline form */
.inline-form{display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap;align-items:end}
.inline-form input,.inline-form select{padding:7px 10px;border-radius:5px;border:1px solid var(--border);background:rgba(29,32,33,.6);color:var(--gold);font-size:12px;outline:none}
.inline-form input:focus,.inline-form select:focus{border-color:var(--teal-h)}
.inline-form input{min-width:160px}.inline-form select{min-width:140px}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:5px;font-weight:700;text-decoration:none;font-size:12px;cursor:pointer;border:none;transition:all .15s}
.btn-p{background:var(--teal);color:var(--bg-dim)}.btn-p:hover{background:var(--teal-h)}
.btn-xs{padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .15s;display:inline-block}
.btn-e{background:var(--teal);color:var(--bg-dim)}.btn-e:hover{background:var(--teal-h)}
.btn-d{background:rgba(204,36,29,.15);color:var(--red-h);border:1px solid rgba(204,36,29,.3)}.btn-d:hover{background:rgba(251,73,52,.25)}
.btn-cancel{background:transparent;color:var(--muted);border:1px solid var(--border)}.btn-cancel:hover{border-color:var(--teal-h);color:var(--gold)}

/* Bages */
.badge{display:inline-block;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700}
.badge-A{background:rgba(69,133,137,.2);color:var(--teal-h)}
.badge-U{background:rgba(168,153,132,.15);color:var(--muted)}

/* Create recipe form grid */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.form-grid .full{grid-column:span 2}
@media(max-width:700px){.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:span 1}}
.fg{display:flex;flex-direction:column;gap:2px}
.fg label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:.4px}
.fg input,.fg select,.fg textarea{padding:8px 10px;border-radius:5px;border:1px solid var(--border);background:rgba(29,32,33,.6);color:var(--gold);font-size:12px;outline:none}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--teal-h)}
.fg textarea{min-height:50px;resize:vertical}
.ing-row{display:flex;gap:5px;margin-bottom:4px;align-items:center}
.ing-row input{padding:6px 8px;border-radius:4px;border:1px solid var(--border);background:rgba(29,32,33,.6);color:var(--gold);font-size:12px;outline:none;flex:1}
.ing-row input:focus{border-color:var(--teal-h)}
.ing-row input[name="quantity[]"]{max-width:60px;flex:none}
.ing-row input[name="unit[]"]{max-width:70px;flex:none}
.ing-row .rm-ing{background:rgba(204,36,29,.15);color:var(--red-h);border:1px solid rgba(204,36,29,.3);border-radius:4px;width:26px;height:26px;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.ing-row .rm-ing:hover{background:rgba(251,73,52,.25)}
.btn-add-ing{background:transparent;color:var(--teal-h);border:1px dashed var(--border);border-radius:5px;padding:5px;font-size:11px;font-weight:600;cursor:pointer;width:100%;margin-top:4px}
.btn-add-ing:hover{border-color:var(--teal-h)}
</style>
</head>
<body>
<div class="page-wrapper">
<?php include '../../includes/navbar.php'; ?>
<div class="admin-layout">
<?php include 'admin_sidebar.php'; ?>
<main class="admin-content">
<div class="container">

<?php if ($msg): ?><div class="msg msg-ok"><?= htmlspecialchars($msg); ?></div><?php endif; ?>

<!-- STATS -->
<div class="stats">
    <div class="stat-c s-users"><div class="ico"><span class="material-icons" style="font-size:24px;">people</span></div><div class="n"><?= $t_users; ?></div><div class="l">Users</div></div>
    <div class="stat-c s-recipes"><div class="ico"><span class="material-icons" style="font-size:24px;">restaurant</span></div><div class="n"><?= $t_recipes; ?></div><div class="l">Recipes</div></div>
    <div class="stat-c s-reviews"><div class="ico"><span class="material-icons" style="font-size:24px;">star</span></div><div class="n"><?= $t_reviews; ?></div><div class="l">Reviews</div></div>
    <div class="stat-c s-ings"><div class="ico"><span class="material-icons" style="font-size:24px;">inventory_2</span></div><div class="n"><?= $t_ings; ?></div><div class="l">Ingredients</div></div>
</div>

<!-- ANALYTICS -->
<h2><span class="material-icons" style="vertical-align:middle;margin-right:4px;">bar_chart</span> Analytics</h2>
<div class="grid2" style="margin-bottom:20px;">
    <div class="panel"><h3>Recipes by Cuisine</h3>
        <?php foreach ($cuisine_data as $c): $p = $max_cuisine > 0 ? round($c['cnt']/$max_cuisine*100) : 0; ?>
        <div class="bar-row"><span class="lbl"><?= htmlspecialchars($c['name']); ?></span><div class="bar" style="width:<?= $p; ?>%"></div><span class="val"><?= $c['cnt']; ?></span></div>
        <?php endforeach; ?>
        <h3 style="margin-top:14px;">Avg Rating by Cuisine</h3>
        <?php foreach ($rating_data as $r): $w = round($r['avg_r']/5*100); ?>
        <div class="star-row"><span class="lbl"><?= htmlspecialchars($r['name']); ?></span><div class="bar" style="width:<?= $w; ?>%"></div><span class="s"><?php for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:13px;color:#d79921;vertical-align:middle;"><?= $i < round($r['avg_r']) ? 'star' : 'star_outline'; ?></span><?php endfor; ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="panel"><h3>Top 10 Ingredients</h3>
        <ul class="ing-list"><?php while ($i = $top_ings->fetch()): ?><li><span class="name"><?= htmlspecialchars($i['name']); ?></span><span class="cnt"><?= $i['cnt']; ?></span></li><?php endwhile; ?></ul>
        <h3 style="margin-top:14px;">Rating Distribution</h3>
        <?php foreach ($rdist as $rd): $p = $mrc > 0 ? round($rd['cnt']/$mrc*100) : 0; ?>
        <div class="star-row"><span style="min-width:26px;"><?= $rd['rating']; ?> <span class="material-icons" style="font-size:13px;vertical-align:middle;">star</span></span><div class="bar" style="width:<?= $p; ?>%;background:<?= $rd['rating']>=4?'#b8bb26':($rd['rating']>=3?'#d79921':'#fb4934'); ?>"></div><span style="font-size:11px;"><?= $rd['cnt']; ?></span></div>
        <?php endforeach; ?>
        <p style="margin-top:10px;font-size:12px;">No reviews: <strong style="color:var(--gold);"><?= $no_rev; ?></strong></p>
    </div>
</div>



</div>
</main>
</div>
</div>


<?php include '../../includes/footer.php'; ?>
</body>
</html>
