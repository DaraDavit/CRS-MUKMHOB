<?php
session_start();
require '../includes/db.php';

$top_rated = $conn->query("SELECT r.recipe_id, r.name, r.image_url, r.prep_time_minutes, r.cook_time_minutes, r.created_at,
    c.name AS country_name, ft.name AS food_type_name,
    COALESCE(AVG(rev.rating), 0) AS avg_rating, COUNT(rev.review_id) AS review_count
    FROM recipes r
    JOIN countries c ON r.country_id = c.country_id
    JOIN regions reg ON c.region_id = reg.region_id
    JOIN food_types ft ON reg.food_type_id = ft.food_type_id
    LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id
    GROUP BY r.recipe_id ORDER BY avg_rating DESC, review_count DESC LIMIT 6")->fetchAll();

$latest = $conn->query("SELECT r.recipe_id, r.name, r.image_url, r.prep_time_minutes, r.cook_time_minutes, r.created_at,
    c.name AS country_name, ft.name AS food_type_name,
    COALESCE(AVG(rev.rating), 0) AS avg_rating, COUNT(rev.review_id) AS review_count
    FROM recipes r
    JOIN countries c ON r.country_id = c.country_id
    JOIN regions reg ON c.region_id = reg.region_id
    JOIN food_types ft ON reg.food_type_id = ft.food_type_id
    LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id
    GROUP BY r.recipe_id ORDER BY r.created_at DESC LIMIT 6")->fetchAll();

$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Collect unique image URLs for hero slideshow (from top-rated recipes)
$slides = [];
foreach ($top_rated as $s) {
    if (!empty($s['image_url']) && !in_array($s['image_url'], $slides)) $slides[] = $s['image_url'];
}

$food_types = $conn->query("SELECT * FROM food_types ORDER BY name")->fetchAll();

$user_stats = [];
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM recipes WHERE user_id = ?");
    $stmt->execute([$uid]);
    $user_stats['recipes'] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
    $stmt->execute([$uid]);
    $user_stats['reviews'] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM favorite_recipes WHERE user_id = ?");
    $stmt->execute([$uid]);
    $user_stats['favorites'] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MUK MHOB — Discover Recipes</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { background:var(--bg-color); color:var(--text-main); font-family:var(--font-stack); -webkit-font-smoothing:antialiased; transition:background .3s,color .3s; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .main-content { flex:1; }

        /* Hero */
        .hero {
            position: relative; overflow: hidden;
            padding: 70px 24px 60px; text-align: center; min-height: 380px;
            display: flex; align-items: center; justify-content: center;
        }
        .hero-slides { position:absolute; inset:0; z-index:0; }
        .hero-slide {
            position:absolute; inset:0;
            background-size:cover; background-position:center;
            opacity:0; transition:opacity 0.8s ease;
        }
        .hero-slide.active { opacity:1; }
        <?php foreach ($slides as $idx => $url): ?>
        .hero-slide:nth-child(<?= $idx + 1; ?>) { background-image: url('<?= htmlspecialchars($url); ?>'); }
        <?php endforeach; ?>
        <?php if (count($slides) === 0): ?>
        .hero { background: linear-gradient(135deg, #2d2a29 0%, #3c3836 50%, #282828 100%); }
        [data-theme="light"] .hero { background: linear-gradient(135deg, #fdf6e3 0%, #f9f5f0 50%, #f5edd6 100%); }
        <?php endif; ?>
        .hero-arrow {
            position:absolute; top:50%; z-index:3; transform:translateY(-50%);
            background:rgba(0,0,0,0.3); border:none; color:#fff;
            font-size:28px; width:44px; height:44px; border-radius:50%;
            cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center;
            line-height:1;
        }
        .hero-arrow:hover { background:rgba(0,0,0,0.6); transform:translateY(-50%) scale(1.1); }
        .hero-arrow.prev { left:16px; }
        .hero-arrow.next { right:16px; }
        .hero-dots {
            position:absolute; bottom:16px; left:50%; z-index:3;
            transform:translateX(-50%); display:flex; gap:8px;
        }
        .hero-dot {
            width:10px; height:10px; border-radius:50%; border:2px solid rgba(255,255,255,0.6);
            background:transparent; cursor:pointer; transition:all .2s; padding:0;
        }
        .hero-dot.active { background:#fff; border-color:#fff; }
        .hero-overlay {
            position:absolute; inset:0; z-index:1;
            background: rgba(0,0,0,0.55);
            pointer-events:none;
        }
        [data-theme="light"] .hero-overlay {
            background: rgba(253,246,227,0.7);
        }
        .hero-content { position:relative; z-index:2; width:100%; max-width:650px; }
        .hero h1 { font-size: 36px; font-weight: 800; color: #fff; margin-bottom: 8px; letter-spacing: -1px; text-shadow:0 2px 8px rgba(0,0,0,0.3); }
        [data-theme="light"] .hero h1 { color: #2d2a29; }
        .hero p { font-size: 16px; color: rgba(255,255,255,0.8); margin-bottom: 24px; }
        [data-theme="light"] .hero p { color: rgba(0,0,0,0.6); }
        .hero-search {
            display: flex; max-width: 500px; margin: 0 auto;
            border-radius: 50px; overflow: hidden;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            transition: all .2s;
        }
        .hero-search:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(204,106,76,0.15); }
        .hero-search input {
            flex:1; padding:14px 20px; border:none; background:transparent;
            font-size:15px; color:var(--text-main); outline:none;
        }
        .hero-search input::placeholder { color:var(--text-muted); }
        .hero-search button {
            padding:14px 24px; border:none; background:var(--primary); color:#fff;
            font-weight:700; font-size:14px; cursor:pointer; transition:background .2s;
        }
        .hero-search button:hover { background:var(--primary-hover); }
        .hero-btns { margin-top:20px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
        .hero-btn {
            padding:12px 28px; border-radius:50px; font-weight:700; font-size:14px;
            text-decoration:none; transition:all .2s; display:inline-block;
        }
        .hero-btn-primary { background:var(--primary); color:#fff; }
        .hero-btn-primary:hover { background:var(--primary-hover); transform:translateY(-2px); }
        .hero-btn-secondary { border:1px solid var(--border-color); color:var(--text-muted); }
        .hero-btn-secondary:hover { border-color:var(--primary); color:var(--primary); }

        /* Sections */
        .section { padding:40px 24px; max-width:1200px; margin:0 auto; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .section-header h2 { font-size:22px; font-weight:800; color:var(--text-main); letter-spacing:-.5px; }
        .section-header .see-all { font-size:14px; color:var(--primary); text-decoration:none; font-weight:600; }
        .section-header .see-all:hover { text-decoration:underline; }

        /* Chips */
        .chips { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px; }
        .chip {
            display:inline-flex; align-items:center; gap:5px;
            padding:8px 16px; border-radius:50px; font-size:13px; font-weight:600;
            text-decoration:none; transition:all .2s;
            background:var(--card-bg); border:1px solid var(--border-color);
            color:var(--text-muted);
        }
        .chip:hover { border-color:var(--primary); color:var(--primary); transform:translateY(-1px); }
        .chip.active { background:var(--primary); color:#fff; border-color:var(--primary); }
        .chip-grid { display:flex; flex-wrap:wrap; gap:8px; }

        /* Card grid */
        .card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:20px; }
        .card {
            background:var(--card-bg); border:1px solid var(--border-color);
            border-radius:14px; overflow:hidden; transition:all .25s;
            text-decoration:none; color:inherit; display:flex; flex-direction:column;
        }
        .card:hover { transform:translateY(-4px); box-shadow:0 12px 32px -8px rgba(0,0,0,0.3); border-color:var(--primary); }
        .card-img { height:170px; background:var(--bg-dim); overflow:hidden; }
        .card-img img { width:100%; height:100%; object-fit:cover; display:block; }
        .card-img .placeholder {
            width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-size:48px; background:linear-gradient(135deg, rgba(204,106,76,0.1), rgba(229,142,82,0.1));
        }
        .card-body { padding:16px 18px 18px; flex:1; display:flex; flex-direction:column; }
        .card-body h3 { font-size:16px; font-weight:700; color:var(--text-main); margin-bottom:4px; }
        .card-body .cuisine { font-size:12px; color:var(--text-muted); margin-bottom:8px; }
        .card-body .meta { display:flex; align-items:center; gap:10px; margin-bottom:10px; font-size:12px; color:var(--text-muted); }
        .card-body .meta .stars { color:var(--primary); font-size:14px; }
        .card-body .meta .count { color:var(--text-muted); }
        .card-body .badges { display:flex; gap:6px; margin-top:auto; }
        .card-body .badges span {
            display:inline-flex; align-items:center; gap:3px;
            padding:3px 9px; border-radius:6px; font-size:11px; font-weight:600;
            background:rgba(204,106,76,0.1); color:var(--primary);
        }

        /* User bar */
        .user-bar {
            background:var(--card-bg); border:1px solid var(--border-color);
            border-radius:14px; padding:24px; margin-bottom:32px;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;
        }
        .user-bar .greeting { display:flex; align-items:center; gap:12px; }
        .user-bar .avatar {
            width:48px; height:48px; border-radius:50%;
            background:var(--primary); color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:20px; font-weight:800;
        }
        .user-bar .greeting h3 { font-size:18px; font-weight:700; color:var(--text-main); }
        .user-bar .greeting p { font-size:13px; color:var(--text-muted); }
        .user-bar .stats { display:flex; gap:16px; }
        .user-bar .stat { text-align:center; }
        .user-bar .stat .num { font-size:22px; font-weight:800; color:var(--primary); }
        .user-bar .stat .lbl { font-size:11px; text-transform:uppercase; color:var(--text-muted); letter-spacing:.5px; }
        .user-bar .links { display:flex; gap:8px; }
        .user-bar .links a {
            padding:8px 16px; border-radius:8px; font-weight:600; font-size:13px;
            text-decoration:none; transition:all .2s;
        }
        .user-link { background:var(--primary); color:#fff; }
        .user-link:hover { background:var(--primary-hover); }
        .user-link-sec { border:1px solid var(--border-color); color:var(--text-muted); }
        .user-link-sec:hover { border-color:var(--primary); color:var(--primary); }

        @media (max-width:700px) {
            .hero h1 { font-size:28px; }
            .section { padding:30px 16px; }
            .card-grid { grid-template-columns:1fr; }
            .user-bar { flex-direction:column; text-align:center; }
            .user-bar .greeting { flex-direction:column; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../includes/navbar.php'; ?>

    <main class="main-content">

        <!-- HERO -->
        <section class="hero" id="heroSection">
            <?php if (count($slides) > 0): ?>
            <div class="hero-slides" id="heroSlides">
                <?php for ($i = 0; $i < count($slides); $i++): ?>
                <div class="hero-slide <?= $i === 0 ? 'active' : ''; ?>"></div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <div class="hero-overlay"></div>
            <?php if (count($slides) > 0): ?>
            <button class="hero-arrow prev" onclick="prevSlide()">‹</button>
            <button class="hero-arrow next" onclick="nextSlide()">›</button>
            <div class="hero-dots" id="heroDots">
                <?php for ($i = 0; $i < count($slides); $i++): ?>
                <button class="hero-dot <?= $i === 0 ? 'active' : ''; ?>" onclick="goSlide(<?= $i; ?>)"></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <div class="hero-content">
                <h1><span class="material-icons" style="vertical-align:middle;font-size:32px;">restaurant</span> Discover Delicious Recipes</h1>
                <p>Explore dishes from every cuisine — from Italian classics to Cambodian street food</p>
                <form action="crs_app/index.php" method="GET" class="hero-search">
                    <input type="text" name="search" placeholder="Search recipes, cuisines, ingredients...">
                    <button type="submit"><span class="material-icons">search</span></button>
                </form>
                <div class="hero-btns">
                    <a href="crs_app/index.php" class="hero-btn hero-btn-primary">Browse All Recipes</a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="auth/register.php" class="hero-btn hero-btn-secondary">Join Now</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- LOGGED IN USER BAR -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="section" style="padding-bottom:0;">
            <div class="user-bar">
                <div class="greeting">
                    <div class="avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                    <div>
                        <h3>Welcome back, <?= htmlspecialchars($_SESSION['username']); ?>!</h3>
                        <p>Ready to discover something new?</p>
                    </div>
                </div>
                <div class="stats">
                    <div class="stat"><div class="num"><?= $user_stats['recipes']; ?></div><div class="lbl">Recipes</div></div>
                    <div class="stat"><div class="num"><?= $user_stats['favorites']; ?></div><div class="lbl">Favorites</div></div>
                    <div class="stat"><div class="num"><?= $user_stats['reviews']; ?></div><div class="lbl">Reviews</div></div>
                </div>
                <div class="links">
                    <a href="user/my_recipes.php" class="user-link">My Recipes</a>
                    <a href="user/my_favorites.php" class="user-link-sec">Favorites</a>
                    <a href="auth/profile.php" class="user-link-sec">Profile</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CATEGORY CHIPS -->
        <section class="section">
            <div class="section-header"><h2><span class="material-icons" style="vertical-align:middle;">label</span> Browse by Category</h2><a href="crs_app/index.php" class="see-all">All →</a></div>
            <div class="chips">
                <?php foreach ($categories as $cat): ?>
                <a href="crs_app/index.php?category_id=<?= $cat['category_id']; ?>" class="chip"><?= htmlspecialchars($cat['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- CUISINE LINKS -->
        <section class="section">
            <div class="section-header"><h2><span class="material-icons" style="vertical-align:middle;">language</span> Explore Cuisines</h2><a href="crs_app/index.php" class="see-all">All →</a></div>
            <div class="chips">
                <?php foreach ($food_types as $ft): ?>
                <a href="crs_app/index.php?food_type_id=<?= $ft['food_type_id']; ?>" class="chip"><?= htmlspecialchars($ft['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- TRENDING -->
        <section class="section">
            <div class="section-header"><h2><span class="material-icons" style="vertical-align:middle;">local_fire_department</span> Trending Recipes</h2><a href="crs_app/index.php" class="see-all">View All →</a></div>
            <?php if (count($top_rated) > 0): ?>
            <div class="card-grid">
                <?php foreach ($top_rated as $r): ?>
                <a href="crs_app/view.php?id=<?= $r['recipe_id']; ?>" class="card">
                    <div class="card-img">
                        <?php if (!empty($r['image_url'])): ?>
                            <img src="<?= htmlspecialchars($r['image_url']); ?>" alt="">
                        <?php else: ?>
                            <div class="placeholder"><span class="material-icons" style="font-size:48px;">restaurant</span></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h3><?= htmlspecialchars($r['name']); ?></h3>
                        <div class="cuisine"><?= htmlspecialchars($r['food_type_name']); ?> · <?= htmlspecialchars($r['country_name']); ?></div>
                        <div class="meta">
                            <span class="stars"><?php $s = round($r['avg_rating']); for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:14px;color:var(--primary);vertical-align:middle;"><?= $i < $s ? 'star' : 'star_outline'; ?></span><?php endfor; ?></span>
                            <span class="count">(<?= $r['review_count']; ?>)</span>
                        </div>
                        <div class="badges">
                            <?php if ($r['prep_time_minutes']): ?><span><span class="material-icons" style="font-size:11px;vertical-align:middle;">schedule</span> <?= $r['prep_time_minutes']; ?>m</span><?php endif; ?>
                            <?php if ($r['cook_time_minutes']): ?><span><span class="material-icons" style="font-size:11px;vertical-align:middle;">restaurant_menu</span> <?= $r['cook_time_minutes']; ?>m</span><?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted);text-align:center;padding:40px;">No recipes yet. Be the first to add one!</p>
            <?php endif; ?>
        </section>

        <!-- LATEST -->
        <section class="section" style="padding-bottom:60px;">
            <div class="section-header"><h2><span class="material-icons" style="vertical-align:middle;">fiber_new</span> Latest Recipes</h2><a href="crs_app/index.php" class="see-all">View All →</a></div>
            <?php if (count($latest) > 0): ?>
            <div class="card-grid">
                <?php foreach ($latest as $r): ?>
                <a href="crs_app/view.php?id=<?= $r['recipe_id']; ?>" class="card">
                    <div class="card-img">
                        <?php if (!empty($r['image_url'])): ?>
                            <img src="<?= htmlspecialchars($r['image_url']); ?>" alt="">
                        <?php else: ?>
                            <div class="placeholder"><span class="material-icons" style="font-size:48px;">restaurant</span></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h3><?= htmlspecialchars($r['name']); ?></h3>
                        <div class="cuisine"><?= htmlspecialchars($r['food_type_name']); ?> · <?= htmlspecialchars($r['country_name']); ?></div>
                        <div class="meta">
                            <span class="stars"><?php $s = round($r['avg_rating']); for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:14px;color:var(--primary);vertical-align:middle;"><?= $i < $s ? 'star' : 'star_outline'; ?></span><?php endfor; ?></span>
                            <span class="count">(<?= $r['review_count']; ?>)</span>
                        </div>
                        <div class="badges">
                            <?php if ($r['prep_time_minutes']): ?><span><span class="material-icons" style="font-size:11px;vertical-align:middle;">schedule</span> <?= $r['prep_time_minutes']; ?>m</span><?php endif; ?>
                            <?php if ($r['cook_time_minutes']): ?><span><span class="material-icons" style="font-size:11px;vertical-align:middle;">restaurant_menu</span> <?= $r['cook_time_minutes']; ?>m</span><?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted);text-align:center;padding:40px;">No recipes yet.</p>
            <?php endif; ?>
        </section>

    </main>
</div>

<script>
let currentSlide = 0;
const totalSlides = <?= count($slides); ?>;
const slides = document.querySelectorAll('.hero-slide');
const dots = document.querySelectorAll('.hero-dot');
let autoTimer;

function showSlide(idx) {
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    currentSlide = (idx + totalSlides) % totalSlides;
    if (slides[currentSlide]) slides[currentSlide].classList.add('active');
    if (dots[currentSlide]) dots[currentSlide].classList.add('active');
    resetAuto();
}
window.nextSlide = function() { showSlide(currentSlide + 1); };
window.prevSlide = function() { showSlide(currentSlide - 1); };
window.goSlide = function(idx) { showSlide(idx); };
function resetAuto() {
    clearInterval(autoTimer);
    if (totalSlides > 1) autoTimer = setInterval(() => showSlide(currentSlide + 1), 5000);
}
if (totalSlides > 1) showSlide(0);
resetAuto();
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
