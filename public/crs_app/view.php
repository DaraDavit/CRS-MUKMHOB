<?php
session_start();
require '../../includes/db.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$recipe_id = (int)$_GET['id'];

if (isset($_GET['favorite']) && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    if ($_GET['favorite'] === 'add') {
        $s = $conn->prepare("INSERT IGNORE INTO favorite_recipes (user_id, recipe_id) VALUES (?, ?)");
        $s->execute([$uid, $recipe_id]);
    } elseif ($_GET['favorite'] === 'remove') {
        $s = $conn->prepare("DELETE FROM favorite_recipes WHERE user_id = ? AND recipe_id = ?");
        $s->execute([$uid, $recipe_id]);
    }
    header("Location: view.php?id=$recipe_id");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit']) && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    $s = $conn->prepare("INSERT INTO reviews (recipe_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    try {
        $s->execute([$recipe_id, $uid, $rating, $comment]);
        $_SESSION['message'] = "Review submitted!";
    } catch (PDOException $e) {
        $_SESSION['message'] = "You have already reviewed this recipe.";
    }
    header("Location: view.php?id=$recipe_id");
    exit;
}

$stmt = $conn->prepare("
    SELECT r.*, c.name AS country_name, reg.name AS region_name,
           ft.name AS food_type_name, u.username
    FROM recipes r
    JOIN countries c ON r.country_id = c.country_id
    JOIN regions reg ON c.region_id = reg.region_id
    JOIN food_types ft ON reg.food_type_id = ft.food_type_id
    LEFT JOIN users u ON r.user_id = u.user_id
    WHERE r.recipe_id = ?
");
$stmt->execute([$recipe_id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    header('Location: index.php');
    exit;
}

$recipe_cats = $conn->prepare("SELECT c.name FROM recipe_categories rc JOIN categories c ON rc.category_id = c.category_id WHERE rc.recipe_id = ?");
$recipe_cats->execute([$recipe_id]);

$stmt = $conn->prepare("
    SELECT i.name, ri.quantity, ri.unit
    FROM recipe_ingredients ri
    JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
    WHERE ri.recipe_id = ?
");
$stmt->execute([$recipe_id]);
$ingredients_rows = $stmt->fetchAll();

$cal_stmt = $conn->prepare("SELECT COALESCE(SUM(COALESCE(i.calories_per_100g, 0) * CASE WHEN ri.unit IN ('g','ml') THEN ri.quantity ELSE ri.quantity * COALESCE(i.grams_per_unit, 0) END / 100), 0) AS total_calories FROM recipe_ingredients ri JOIN ingredients i ON ri.ingredient_id = i.ingredient_id WHERE ri.recipe_id = ?");
$cal_stmt->execute([$recipe_id]);
$total_calories = (int)$cal_stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT rev.*, u.username
    FROM reviews rev
    JOIN users u ON rev.user_id = u.user_id
    WHERE rev.recipe_id = ? ORDER BY rev.created_at DESC
");
$stmt->execute([$recipe_id]);

$is_favorited = false;
if (isset($_SESSION['user_id'])) {
    $s = $conn->prepare("SELECT 1 FROM favorite_recipes WHERE user_id = ? AND recipe_id = ?");
    $s->execute([$_SESSION['user_id'], $recipe_id]);
    $is_favorited = $s->fetch() !== false;
}

$stmt_avg = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM reviews WHERE recipe_id = ?");
$stmt_avg->execute([$recipe_id]);
$rating_data = $stmt_avg->fetch();
$avg_rating = round($rating_data['avg_rating'] ?? 0);

function youtube_embed_id($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m);
    return $m[1] ?? null;
}
$yt_id = $recipe['youtube_url'] ? youtube_embed_id($recipe['youtube_url']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($recipe['name']); ?> — Recipe</title>
    <style>
        :root, [data-theme="light"] {
            --primary: #458589; --primary-hover: #83a598;
        }
        * { box-sizing:border-box; font-family:var(--font-stack); margin:0; padding:0; }
        body { background-color:var(--bg-dim); color:var(--text-muted); -webkit-font-smoothing:antialiased; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .main-content { flex:1; padding:40px 24px; }
        .container {
            width:100%; max-width:900px; margin:0 auto;
            background:var(--card-bg); padding:40px;
            border-radius:12px; border:1px solid var(--border-color);
            backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
            box-shadow:0 20px 40px -10px rgba(0,0,0,0.4);
        }
        h1 { font-size:28px; color:var(--text-main); font-weight:800; letter-spacing:-0.5px; margin-bottom:4px; }
        .meta { color:var(--text-muted); font-size:14px; margin-bottom:24px; }
        .meta span { margin-right:16px; }
        .meta .label { font-weight:600; color:var(--primary-hover); }
        .section { margin-top:28px; }
        .section h3 { font-size:18px; color:var(--text-main); font-weight:700; margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid var(--border-color); }
        .description { line-height:1.7; color:var(--text-muted); font-size:15px; }
        .instructions { line-height:1.7; color:var(--text-muted); font-size:15px; white-space:pre-wrap; }
        .rating-display { font-size:22px; color:#d79921; margin-bottom:8px; }

        .ingredient-list { display:flex; flex-direction:column; }
        .ingredient-item { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color); }
        .ingredient-item:last-child { border-bottom:none; }
        .ing-name { color:var(--text-main); font-size:15px; font-weight:500; }
        .ing-amount { color:var(--text-muted); font-size:14px; }

        .review-card { background:rgba(29,32,33,0.4); border:1px solid var(--border-color); border-radius:8px; padding:16px; margin-bottom:12px; }
        .review-card .review-header { display:flex; justify-content:space-between; margin-bottom:6px; }
        .review-card .review-user { font-weight:700; color:var(--text-main); font-size:14px; }
        .review-card .review-date { font-size:12px; color:var(--text-muted); }
        .review-card .review-stars { color:#d79921; font-size:16px; }
        .review-card .review-comment { color:var(--text-muted); font-size:14px; line-height:1.5; }

        .review-form { background:rgba(29,32,33,0.4); border:1px solid var(--border-color); border-radius:8px; padding:20px; margin-top:16px; }
        .review-form select, .review-form textarea {
            width:100%; padding:10px 12px; border-radius:6px; border:1px solid var(--border-color);
             color:var(--text-main); font-size:14px; outline:none; margin-bottom:12px;
        }
        .review-form select:focus, .review-form textarea:focus { border-color:var(--primary-hover); }
        .review-form textarea { min-height:80px; resize:vertical; }

        .btn {
            display:inline-flex; align-items:center; padding:10px 16px;
            text-decoration:none; border-radius:8px; font-weight:700;
            cursor:pointer; border:none; font-size:14px; transition:all 0.15s ease;
        }
        .btn-primary { background-color:var(--primary); color:var(--bg-dim); }
        .btn-primary:hover { background-color:var(--primary-hover); transform:translateY(-1px); }
        .btn-fav { background-color:rgba(204,36,29,0.15); color:var(--danger-hover); border:1px solid rgba(204,36,29,0.3); }
        .btn-fav:hover { background-color:rgba(251,73,52,0.25); }
        .btn-fav.active { background-color:rgba(251,73,52,0.3); color:#fff; }
        .btn-edit { background-color:var(--primary); color:var(--bg-dim); }
        .btn-edit:hover { background-color:var(--primary-hover); }
        .btn-delete { background-color:rgba(204,36,29,0.15); color:var(--danger-hover); border:1px solid rgba(204,36,29,0.3); }
        .btn-delete:hover { background-color:rgba(251,73,52,0.25); color:#fff; }
        .btn-back { background:transparent; color:var(--text-muted); border:1px solid var(--border-color); }
        .btn-back:hover { border-color:var(--border-hover); color:var(--text-main); }

        .action-bar { display:flex; gap:8px; margin-top:28px; flex-wrap:wrap; }
        .alert {
            padding:12px; background-color:rgba(69,133,137,0.15);
            color:var(--primary-hover); border:1px solid rgba(69,133,137,0.3);
            border-radius:6px; margin-bottom:20px; font-weight:bold; font-size:14px;
        }
        .yt-embed { margin-top:16px; }
        .yt-embed iframe { width:100%; max-width:560px; height:315px; border-radius:8px; border:none; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../../includes/navbar.php'; ?>
    <main class="main-content">
        <div class="container">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert"><?= htmlspecialchars($_SESSION['message']); ?><?php unset($_SESSION['message']); ?></div>
            <?php endif; ?>

            <div style="margin-bottom:20px;">
                <a href="index.php" class="btn btn-back">&larr; Back to Recipes</a>
            </div>

            <h1><?= htmlspecialchars($recipe['name']); ?></h1>
            <div class="meta">
                <span><span class="label">Food:</span> <?= htmlspecialchars($recipe['food_type_name']); ?></span>
                <span><span class="label">Region:</span> <?= htmlspecialchars($recipe['region_name']); ?></span>
                <span><span class="label">Country:</span> <?= htmlspecialchars($recipe['country_name']); ?></span>
                <?php if ($recipe['prep_time_minutes']): ?>
                    <span><span class="label">Prep:</span> <?= $recipe['prep_time_minutes']; ?>m</span>
                <?php endif; ?>
                <?php if ($recipe['cook_time_minutes']): ?>
                    <span><span class="label">Cook:</span> <?= $recipe['cook_time_minutes']; ?>m</span>
                <?php endif; ?>
                <?php if ($recipe['username']): ?>
                    <span><span class="label">By:</span> <?= htmlspecialchars($recipe['username']); ?></span>
                <?php endif; ?>
            </div>

            <div class="rating-display"><?php for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:22px;color:#d79921;vertical-align:middle;"><?= $i < $avg_rating ? 'star' : 'star_outline'; ?></span><?php endfor; ?></div>

            <?php if (!empty($recipe['image_url'])): ?>
            <div style="margin-bottom:20px;border-radius:12px;overflow:hidden;max-height:400px;">
                <img src="<?= htmlspecialchars($recipe['image_url']); ?>" alt="<?= htmlspecialchars($recipe['name']); ?>" style="width:100%;height:auto;max-height:400px;object-fit:cover;display:block;border-radius:12px;">
            </div>
            <?php endif; ?>

            <?php
            $recipe_cats_rows = $recipe_cats->fetchAll();
            if (count($recipe_cats_rows) > 0):
            ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
                <?php foreach ($recipe_cats_rows as $rc): ?>
                <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;background:rgba(69,133,137,0.12);color:var(--primary-hover);"><?= htmlspecialchars($rc['name']); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($recipe['description'])): ?>
                <div class="section">
                    <h3>Description</h3>
                    <div class="description"><?= nl2br(htmlspecialchars($recipe['description'])); ?></div>
                </div>
            <?php endif; ?>

            <div class="section">
                <h3>Ingredients</h3>
                <?php
                if (count($ingredients_rows) > 0):
                ?>
                    <div class="ingredient-list">
                        <?php foreach ($ingredients_rows as $ing): ?>
                        <div class="ingredient-item">
                            <span class="ing-name"><?= htmlspecialchars($ing['name']); ?></span>
                            <span class="ing-amount"><?= rtrim(rtrim($ing['quantity'], '0'), '.') . ' ' . htmlspecialchars($ing['unit']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-muted);">No ingredients listed.</p>
                <?php endif; ?>
                <?php if ($total_calories > 0): ?>
                <div style="margin-top:12px;padding:12px 14px;background:rgba(29,32,33,0.4);border:1px solid var(--border-color);border-radius:8px;display:flex;align-items:center;gap:8px;">
                    <span class="material-icons" style="color:#d79921;">bolt</span>
                    <span style="font-weight:700;color:var(--text-main);font-size:15px;">~<?= $total_calories; ?> kcal</span>
                    <span style="font-size:12px;color:var(--text-muted);">estimated from ingredients</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <h3>Instructions</h3>
                <div class="instructions"><?= nl2br(htmlspecialchars($recipe['instructions'])); ?></div>
            </div>

            <?php if ($yt_id): ?>
                <div class="section yt-embed">
                    <h3>Video</h3>
                    <iframe src="https://www.youtube.com/embed/<?= $yt_id; ?>" allowfullscreen></iframe>
                </div>
            <?php elseif ($recipe['youtube_url']): ?>
                <div class="section">
                    <h3>Video Link</h3>
                    <a href="<?= htmlspecialchars($recipe['youtube_url']); ?>" target="_blank" style="color:var(--primary-hover);"><?= htmlspecialchars($recipe['youtube_url']); ?></a>
                </div>
            <?php endif; ?>

            <div class="section">
                <h3>Reviews</h3>
                <?php
                $reviews_rows = $stmt->fetchAll();
                if (count($reviews_rows) > 0):
                ?>
                    <?php foreach ($reviews_rows as $rev): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <span class="review-user"><?= htmlspecialchars($rev['username']); ?></span>
                            <span class="review-date"><?= $rev['created_at']; ?></span>
                        </div>
                        <div class="review-stars"><?php for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:16px;color:#d79921;vertical-align:middle;"><?= $i < $rev['rating'] ? 'star' : 'star_outline'; ?></span><?php endfor; ?></div>
                        <?php if ($rev['comment']): ?>
                            <div class="review-comment"><?= nl2br(htmlspecialchars($rev['comment'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--text-muted); margin-bottom:12px;">No reviews yet.</p>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="review-form">
                        <h4 style="color:var(--text-main); margin-bottom:12px; font-size:15px;">Write a Review</h4>
                        <form method="POST">
                            <select name="rating" required>
                                <option value="">Rating</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i; ?>"><?= $i; ?> Star<?= $i > 1 ? 's' : ''; ?></option>
                                <?php endfor; ?>
                            </select>
                            <textarea name="comment" placeholder="Share your thoughts..."></textarea>
                            <button type="submit" name="review_submit" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="action-bar">
                <a href="index.php" class="btn btn-back">&larr; Back</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="view.php?id=<?= $recipe_id; ?>&favorite=<?= $is_favorited ? 'remove' : 'add'; ?>" class="btn btn-fav <?= $is_favorited ? 'active' : ''; ?>">
                        <?= $is_favorited ? '<span class="material-icons" style="vertical-align:middle;">star</span> Favorited' : '<span class="material-icons" style="vertical-align:middle;">star_outline</span> Add to Favorites'; ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
