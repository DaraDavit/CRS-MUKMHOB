<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT username, email, created_at, avatar_url FROM users WHERE user_id = :id");
$query->execute(['id' => $user_id]);
$user = $query->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit;
}

$recipe_count = $conn->prepare("SELECT COUNT(*) FROM recipes WHERE user_id = :id");
$recipe_count->execute(['id' => $user_id]);
$recipe_count = $recipe_count->fetchColumn();

$review_count = $conn->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = :id");
$review_count->execute(['id' => $user_id]);
$review_count = $review_count->fetchColumn();

$fav_count = $conn->prepare("SELECT COUNT(*) FROM favorite_recipes WHERE user_id = :id");
$fav_count->execute(['id' => $user_id]);
$fav_count = $fav_count->fetchColumn();

$recent_recipes = $conn->prepare("SELECT r.recipe_id, r.name, r.image_url, r.created_at, c.name AS country_name, COALESCE(AVG(rev.rating), 0) AS avg_rating FROM recipes r JOIN countries c ON r.country_id = c.country_id LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id WHERE r.user_id = :id GROUP BY r.recipe_id ORDER BY r.created_at DESC LIMIT 3");
$recent_recipes->execute(['id' => $user_id]);

$recent_reviews = $conn->prepare("SELECT rev.*, r.name AS recipe_name, r.image_url AS recipe_image FROM reviews rev JOIN recipes r ON rev.recipe_id = r.recipe_id WHERE rev.user_id = :id ORDER BY rev.created_at DESC LIMIT 3");
$recent_reviews->execute(['id' => $user_id]);

$recent_favs = $conn->prepare("SELECT r.recipe_id, r.name, r.image_url FROM favorite_recipes f JOIN recipes r ON f.recipe_id = r.recipe_id WHERE f.user_id = :id ORDER BY f.recipe_id DESC LIMIT 3");
$recent_favs->execute(['id' => $user_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-color: #282828;
            --bg-dim: #1d2021;
            --card-bg: rgba(50, 48, 47, 0.7);
            --text-main: #ebdbb2;
            --text-muted: #a89984;
            --primary: #458588;
            --primary-hover: #83a598;
            --border-color: rgba(60, 56, 54, 0.6);
            --font-stack: system-ui, -apple-system, sans-serif;
            --green: #b8bb26;
            --gold: #d79921;
        }

        body {
            background-color: var(--bg-color);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: var(--font-stack);
            -webkit-font-smoothing: antialiased;
        }

        .page-wrapper { display: flex; flex-direction: column; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px 20px; display: flex; justify-content: center; }

        .profile-container {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            width: 100%;
            max-width: 700px;
            padding: 36px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border-color);
        }

        .back-link { display:inline-flex; align-items:center; gap:4px; color:var(--text-muted); text-decoration:none; font-size:13px; font-weight:600; margin-bottom:16px; transition:color .15s; }
        .back-link:hover { color:var(--primary-hover); }

        .profile-header { text-align: center; margin-bottom: 24px; }
        .avatar {
            width: 80px;
            height: 80px;
            background: rgba(29, 32, 33, 0.6);
            border: 2px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            color: var(--text-main);
            transition: border-color 0.2s;
        }
        .profile-container:hover .avatar { border-color: var(--primary-hover); }
        .profile-header h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .profile-header .role-badge {
            display: inline-block; padding: 3px 14px; border-radius: 12px; font-size: 12px; font-weight: 700;
            margin-top: 6px;
        }
        .role-Admin { background: rgba(69, 133, 136, 0.2); color: var(--primary-hover); }
        .role-User { background: rgba(168, 153, 132, 0.15); color: var(--text-muted); }
        .role-ContentCollector { background: rgba(214, 158, 46, 0.15); color: #d79921; }

        .info-group {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 12px;
        }
        .info-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .info-value { font-size: 15px; font-weight: 600; }

        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 24px;
        }
        .stat-box {
            
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 10px;
            text-align: center;
            transition: all 0.2s ease;
        }
        .stat-box:hover { border-color: var(--primary-hover); transform: translateY(-2px); }
        .stat-box .icon { font-size: 24px; }
        .stat-box .num { font-size: 22px; font-weight: 800; color: var(--text-main); margin-top: 2px; }
        .stat-box .label { font-size: 11px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }

        .badges-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            justify-content: center;
        }
        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            
            border: 1px solid var(--border-color);
            color: var(--text-muted);
        }
        .badge-pill .material-icons { font-size: 16px; }
        .badge-pill.active { border-color: var(--primary-hover); color: var(--primary-hover); }
        .badge-pill.gold { border-color: var(--gold); color: var(--gold); }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-title a {
            margin-left: auto;
            font-size: 12px;
            font-weight: 600;
            color: var(--primary-hover);
            text-decoration: none;
        }
        .section-title a:hover { text-decoration: underline; }

        .mini-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 24px;
        }
        .mini-card {
            
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
        }
        .mini-card:hover { border-color: var(--primary-hover); transform: translateY(-2px); }
        .mini-card .thumb {
            height: 90px;
            background: var(--bg-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .mini-card .thumb img { width: 100%; height: 100%; object-fit: cover; }
        .mini-card .thumb .placeholder { font-size: 28px; opacity: 0.4; }
        .mini-card .body { padding: 10px 12px; flex: 1; display: flex; flex-direction: column; }
        .mini-card .body .name { font-size: 13px; font-weight: 700; color: var(--text-main); }
        .mini-card .body .sub { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .mini-card .body .stars { font-size: 11px; color: var(--gold); margin-top: auto; padding-top: 4px; }

        .review-item {
            
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .review-item:hover { border-color: var(--primary-hover); }
        .review-item .rev-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .review-item .rev-recipe { font-size: 14px; font-weight: 700; color: var(--text-main); text-decoration: none; }
        .review-item .rev-recipe:hover { color: var(--primary-hover); }
        .review-item .rev-meta { font-size: 11px; color: var(--text-muted); }
        .review-item .rev-stars { color: var(--gold); font-size: 14px; }
        .review-item .rev-comment { font-size: 13px; color: var(--text-muted); margin-top: 4px; line-height: 1.5; }

        .nav-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .nav-btn-custom {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .nav-btn-custom:hover { transform: translateY(-1px); }
        .nav-primary { background: var(--primary); color: var(--bg-dim); }
        .nav-primary:hover { background: var(--primary-hover); box-shadow: 0 4px 12px rgba(69, 133, 136, 0.3); }
        .nav-secondary { background: rgba(131, 165, 152, 0.15); color: var(--primary-hover); border: 1px solid rgba(131, 165, 152, 0.2); }
        .nav-secondary:hover { background: rgba(131, 165, 152, 0.25); }
        .nav-admin { background: rgba(214, 158, 46, 0.15); color: #d79921; border: 1px solid rgba(214, 158, 46, 0.2); }
        .nav-admin:hover { background: rgba(214, 158, 46, 0.25); }
        .nav-danger { background: rgba(251, 73, 52, 0.1); color: #fb4934; border: 1px solid rgba(251, 73, 52, 0.2); grid-column: span 2; }
        .nav-danger:hover { background: rgba(251, 73, 52, 0.18); }

        .empty-text { font-size: 13px; color: var(--text-muted); padding: 10px 0 16px; }

        @media (max-width: 600px) {
            .profile-container { padding: 24px; }
            .mini-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr 1fr 1fr; }
            .nav-grid { grid-template-columns: 1fr; }
            .nav-danger { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include '../../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="profile-container">
                <a href="javascript:history.back()" class="back-link"><span class="material-icons" style="font-size:16px;">arrow_back</span> Back</a>

                <div class="profile-header">
                    <div class="avatar"><?php if (!empty($user['avatar_url'])): ?><img src="<?= htmlspecialchars($user['avatar_url']); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;display:block;"><?php else: ?><span class="material-icons" style="font-size:48px;">person</span><?php endif; ?></div>
                    <h1><?= htmlspecialchars($user['username']); ?></h1>
                    <span class="role-badge" style="display:inline-block;padding:3px 14px;border-radius:12px;font-size:12px;font-weight:700;margin-top:6px;background:<?= $_SESSION['role'] === 'Admin' ? 'rgba(69,133,136,0.2)' : ($_SESSION['role'] === 'Content Collector' ? 'rgba(214,158,46,0.15)' : 'rgba(168,153,132,0.15)'); ?>;color:<?= $_SESSION['role'] === 'Admin' ? 'var(--primary-hover)' : ($_SESSION['role'] === 'Content Collector' ? '#d79921' : 'var(--text-muted)'); ?>;"><?= htmlspecialchars($_SESSION['role'] ?? 'User'); ?></span>
                </div>

                <div class="info-group">
                    <div class="info-label"><span class="material-icons" style="font-size:14px;vertical-align:middle;">email</span> Email</div>
                    <div class="info-value" style="font-weight: 500;"><?= htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label"><span class="material-icons" style="font-size:14px;vertical-align:middle;">calendar_today</span> Member Since</div>
                    <div class="info-value" style="font-size: 14px; font-family: monospace;"><?= htmlspecialchars($user['created_at'] ?? '—'); ?></div>
                </div>

                <div class="stats-row">
                    <div class="stat-box"><div class="icon"><span class="material-icons" style="font-size:24px;">description</span></div><div class="num"><?= $recipe_count; ?></div><div class="label">Recipes</div></div>
                    <div class="stat-box"><div class="icon"><span class="material-icons" style="font-size:24px;">chat</span></div><div class="num"><?= $review_count; ?></div><div class="label">Reviews</div></div>
                    <div class="stat-box"><div class="icon"><span class="material-icons" style="font-size:24px;">favorite</span></div><div class="num"><?= $fav_count; ?></div><div class="label">Favorites</div></div>
                </div>

                <div class="badges-row">
                    <span class="badge-pill <?= $recipe_count >= 1 ? 'active' : ''; ?>"><span class="material-icons">description</span> Recipe Creator</span>
                    <span class="badge-pill <?= $review_count >= 1 ? 'active' : ''; ?>"><span class="material-icons">chat</span> Reviewer</span>
                    <span class="badge-pill <?= $fav_count >= 3 ? 'gold' : ($fav_count >= 1 ? 'active' : ''); ?>"><span class="material-icons">favorite</span> Food Lover</span>
                </div>

                <div class="section-title">
                    <span class="material-icons" style="font-size:18px;">description</span> Recent Recipes
                    <?php if ($recipe_count > 0): ?><a href="../user/my_recipes.php">View All</a><?php endif; ?>
                </div>
                <?php
                $rr = $recent_recipes->fetchAll();
                if (count($rr) > 0): ?>
                <div class="mini-grid">
                    <?php foreach ($rr as $r): ?>
                    <a href="../crs_app/view.php?id=<?= $r['recipe_id']; ?>" class="mini-card">
                        <div class="thumb">
                            <?php if (!empty($r['image_url'])): ?>
                                <img src="<?= htmlspecialchars($r['image_url']); ?>" alt="">
                            <?php else: ?>
                                <span class="placeholder material-icons" style="font-size:28px;">restaurant</span>
                            <?php endif; ?>
                        </div>
                        <div class="body">
                            <div class="name"><?= htmlspecialchars($r['name']); ?></div>
                            <div class="sub"><?= htmlspecialchars($r['country_name']); ?></div>
                            <div class="stars"><?php $s = round($r['avg_rating']); for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:11px;vertical-align:middle;"><?= $i < $s ? 'star' : 'star_outline'; ?></span><?php endfor; ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="empty-text">No recipes yet. Start creating!</p>
                <?php endif; ?>

                <div class="section-title">
                    <span class="material-icons" style="font-size:18px;">chat</span> Recent Reviews
                    <?php if ($review_count > 0): ?><a href="../user/my_reviews.php">View All</a><?php endif; ?>
                </div>
                <?php
                $rv = $recent_reviews->fetchAll();
                if (count($rv) > 0): ?>
                    <?php foreach ($rv as $rev): ?>
                    <div class="review-item">
                        <div class="rev-header">
                            <a href="../crs_app/view.php?id=<?= $rev['recipe_id']; ?>" class="rev-recipe"><?= htmlspecialchars($rev['recipe_name']); ?></a>
                            <span class="rev-meta"><?= $rev['created_at']; ?></span>
                        </div>
                        <div class="rev-stars"><?php for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:14px;vertical-align:middle;"><?= $i < $rev['rating'] ? 'star' : 'star_outline'; ?></span><?php endfor; ?></div>
                        <?php if ($rev['comment']): ?>
                        <div class="rev-comment"><?= htmlspecialchars($rev['comment']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <p class="empty-text">No reviews yet.</p>
                <?php endif; ?>

                <div class="section-title">
                    <span class="material-icons" style="font-size:18px;">favorite</span> Recent Favorites
                    <?php if ($fav_count > 0): ?><a href="../user/my_favorites.php">View All</a><?php endif; ?>
                </div>
                <?php
                $favs = $recent_favs->fetchAll();
                if (count($favs) > 0): ?>
                <div class="mini-grid">
                    <?php foreach ($favs as $f): ?>
                    <a href="../crs_app/view.php?id=<?= $f['recipe_id']; ?>" class="mini-card">
                        <div class="thumb">
                            <?php if (!empty($f['image_url'])): ?>
                                <img src="<?= htmlspecialchars($f['image_url']); ?>" alt="">
                            <?php else: ?>
                                <span class="placeholder material-icons" style="font-size:28px;">restaurant</span>
                            <?php endif; ?>
                        </div>
                        <div class="body">
                            <div class="name"><?= htmlspecialchars($f['name']); ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="empty-text">No favorites saved yet.</p>
                <?php endif; ?>

                <hr style="border:none;border-top:1px solid var(--border-color);margin:20px 0 16px;">

                <div class="nav-grid">
                    <a href="../user/my_recipes.php" class="nav-btn-custom nav-primary"><span class="material-icons">description</span> My Recipes</a>
                    <a href="../user/my_favorites.php" class="nav-btn-custom nav-secondary"><span class="material-icons">favorite</span> Favorites</a>
                    <a href="../user/my_reviews.php" class="nav-btn-custom nav-secondary"><span class="material-icons">chat</span> Reviews</a>
                    <a href="edit_profile.php" class="nav-btn-custom nav-secondary"><span class="material-icons">settings</span> Edit Profile</a>
                    <a href="../crs_app/index.php" class="nav-btn-custom nav-secondary"><span class="material-icons">restaurant</span> Browse</a>
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Content Collector')): ?>
                        <a href="../crs_app/create.php" class="nav-btn-custom nav-secondary"><span class="material-icons">add_circle</span> New Recipe</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                        <a href="../admin/index.php" class="nav-btn-custom nav-admin"><span class="material-icons">build</span> Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="nav-btn-custom nav-danger"><span class="material-icons">logout</span> Log Out</a>
                </div>
            </div>
        </main>
    </div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
