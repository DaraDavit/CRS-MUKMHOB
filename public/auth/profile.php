<?php
session_start();
require '../../includes/db.php';
require '../../includes/cloudinary.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Profile update handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission.";
    } else {
    $new_username = trim($_POST['username']);
    $gender = $_POST['gender'] ?? '';
    $pronouns = trim($_POST['pronouns'] ?? '');
    if (empty($new_username)) {
        $error = "Username is required.";
    } else {
        $upd = $conn->prepare("UPDATE users SET username = :username, gender = :gender, pronouns = :pronouns WHERE user_id = :id");
        $upd->execute(['username' => $new_username, 'gender' => $gender, 'pronouns' => $pronouns, 'id' => $user_id]);
        $_SESSION['username'] = $new_username;
        $success = "Profile updated!";
        $user['username'] = $new_username;
        $user['gender'] = $gender;
        $user['pronouns'] = $pronouns;
    }
    }
}

// Avatar upload handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission.";
    } else {
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($_FILES['avatar']['type'], $allowed)) {
        $error = "Avatar must be JPG, PNG, or WebP.";
    } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        $error = "Avatar must be under 2MB.";
    } else {
        $avatar_url = cloudinary_upload($_FILES['avatar']['tmp_name'], 'avatar_' . $user_id, 'crs_app/profiles');
        if ($avatar_url) {
            $old_avatar = $conn->prepare("SELECT avatar_url FROM users WHERE user_id = :id");
            $old_avatar->execute(['id' => $user_id]);
            $old = $old_avatar->fetchColumn();
            $upd = $conn->prepare("UPDATE users SET avatar_url = :url WHERE user_id = :id");
            $upd->execute(['url' => $avatar_url, 'id' => $user_id]);
            if (!empty($old)) cloudinary_delete($old);
            $_SESSION['avatar_url'] = $avatar_url;
            $success = "Avatar updated!";
        } else {
            $error = "Upload failed. Check Cloudinary config.";
        }
    }
    }
}

$query = $conn->prepare("SELECT username, email, gender, pronouns, created_at, avatar_url FROM users WHERE user_id = :id");
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

        :root, [data-theme="light"] {
            --primary: #458588;
            --primary-hover: #83a598;
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
            background: var(--bg-dim);
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

        .avatar-wrap {
            position: relative;
            display: inline-block;
            margin: 0 auto 12px;
            text-decoration: none;
        }
        .avatar-wrap .avatar { margin-bottom: 0; }
        .avatar-plus {
            position: absolute;
            bottom: -2px;
            right: -6px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            border: 2px solid var(--bg-color);
            transition: background 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        .avatar-plus:hover { background: var(--primary-hover); }
        .edit-link {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            margin-top: 2px;
            transition: color 0.2s;
        }
        .edit-link:hover { color: var(--primary-hover); }
        .upload-msg {
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
            padding: 4px 12px;
            border-radius: 6px;
            display: inline-block;
        }
        .upload-msg.error { background: rgba(251,73,52,0.1); color: #fb4934; }
        .upload-msg.success { background: rgba(184,187,38,0.1); color: #b8bb26; }
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
        .nav-primary { background: var(--primary); color: #fff; }
        .nav-primary:hover { background: var(--primary-hover); box-shadow: 0 4px 12px rgba(69, 133, 136, 0.3); }
        .nav-secondary { background: rgba(131, 165, 152, 0.15); color: var(--primary-hover); border: 1px solid rgba(131, 165, 152, 0.2); }
        .nav-secondary:hover { background: rgba(131, 165, 152, 0.25); }
        .nav-admin { background: rgba(214, 158, 46, 0.15); color: #d79921; border: 1px solid rgba(214, 158, 46, 0.2); }
        .nav-admin:hover { background: rgba(214, 158, 46, 0.25); }
        .nav-danger { background: rgba(251, 73, 52, 0.1); color: #fb4934; border: 1px solid rgba(251, 73, 52, 0.2); grid-column: span 2; }
        .nav-danger:hover { background: rgba(251, 73, 52, 0.18); }

        .empty-text { font-size: 13px; color: var(--text-muted); padding: 10px 0 16px; }

        .modal-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(0,0,0,0.6);
            display: none; align-items: center; justify-content: center;
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-card {
            background: rgba(50,48,47,0.55); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            width: 100%; max-width: 420px; padding: 32px; border-radius: 18px;
            box-shadow: 0 40px 80px -20px rgba(0,0,0,0.6);
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
        }
        .modal-card h2 { font-size: 22px; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; margin-bottom: 24px; }
        .modal-close {
            position: absolute; top: 16px; right: 18px;
            background: none; border: none; color: var(--text-muted);
            font-size: 22px; cursor: pointer; padding: 4px; line-height: 1;
            transition: color 0.2s;
        }
        .modal-close:hover { color: var(--text-main); }
        .modal-card .form-group { margin-bottom: 18px; }
        .modal-card label { display: block; font-size: 13px; font-weight: 600; color: var(--text-main); margin-bottom: 6px; }
        .modal-card input[type="text"], .modal-card select {
            width: 100%; padding: 11px 14px; font-size: 14px; color: var(--text-main);
            border: 1px solid var(--border-color); border-radius: 10px;
            background: var(--bg-dim); outline: none; transition: border-color 0.2s;
        }
        .modal-card select { cursor: pointer; }
        .modal-card input:focus, .modal-card select:focus { border-color: var(--primary-hover); }
        .modal-card .btn-submit {
            width: 100%; padding: 12px; background: var(--primary); color: #fff;
            border: none; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer;
            transition: background 0.2s; margin-top: 4px;
        }
        .modal-card .btn-submit:hover { background: var(--primary-hover); }
        .modal-card .modal-footer { text-align: center; margin-top: 16px; }
        .modal-card .modal-footer a { color: var(--text-muted); font-size: 13px; font-weight: 600; text-decoration: none; transition: color 0.2s; }
        .modal-card .modal-footer a:hover { color: var(--primary-hover); }
        .modal-card .modal-msg { font-size: 13px; font-weight: 600; text-align: center; margin-bottom: 16px; padding: 8px 12px; border-radius: 8px; }
        .modal-card .modal-msg.error { background: rgba(251,73,52,0.1); color: #fb4934; }
        .modal-card .modal-msg.success { background: rgba(184,187,38,0.1); color: #b8bb26; }
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
                    <div class="avatar-wrap" id="avatarUploader">
                        <div class="avatar"><?php if (!empty($user['avatar_url'])): ?><img src="<?= htmlspecialchars($user['avatar_url']); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;display:block;"><?php else: ?><span class="material-icons" style="font-size:48px;">person</span><?php endif; ?></div>
                        <span class="avatar-plus">+</span>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp">
                    </form>
                    <h1><?= htmlspecialchars($user['username']); ?></h1>
                    <a href="javascript:void(0)" class="edit-link" id="editProfileBtn"><span class="material-icons" style="font-size:14px;">edit</span> Edit profile</a>
                    <span class="role-badge" style="display:inline-block;padding:3px 14px;border-radius:12px;font-size:12px;font-weight:700;margin-top:6px;background:<?= $_SESSION['role'] === 'Admin' ? 'rgba(69,133,136,0.2)' : ($_SESSION['role'] === 'Content Collector' ? 'rgba(214,158,46,0.15)' : 'rgba(168,153,132,0.15)'); ?>;color:<?= $_SESSION['role'] === 'Admin' ? 'var(--primary-hover)' : ($_SESSION['role'] === 'Content Collector' ? '#d79921' : 'var(--text-muted)'); ?>;"><?= htmlspecialchars($_SESSION['role'] ?? 'User'); ?></span>
                    <?php if ($error): ?><div class="upload-msg error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
                    <?php if ($success && !isset($_POST['action'])): ?><div class="upload-msg success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
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

            <!-- Edit Profile Modal -->
            <div class="modal-overlay <?= isset($_POST['action']) && $_POST['action'] === 'update_profile' ? 'open' : ''; ?>" id="editModal">
                <div class="modal-card">
                    <button class="modal-close" id="editModalClose">&times;</button>
                    <h2>Edit Profile</h2>
                    <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'update_profile'): ?>
                        <div class="modal-msg error"><?= htmlspecialchars($error); ?></div>
                    <?php elseif ($success && isset($_POST['action']) && $_POST['action'] === 'update_profile'): ?>
                        <div class="modal-msg success"><?= htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required value="<?= htmlspecialchars($user['username']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Prefer not to say</option>
                                <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Pronouns</label>
                            <input type="text" name="pronouns" placeholder="e.g. they/them, she/her, he/him" value="<?= htmlspecialchars($user['pronouns'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn-submit">Save Changes</button>
                    </form>
                    <div class="modal-footer">
                        <a href="change_password.php">Change Password</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
<?php include '../../includes/footer.php'; ?>
<script>
(function() {
    const uploader = document.getElementById('avatarUploader');
    const input = document.getElementById('avatarInput');
    const form = document.getElementById('avatarForm');
    if (uploader && input && form) {
        uploader.addEventListener('click', function() { input.click(); });
        input.addEventListener('change', function() {
            if (input.files && input.files[0]) form.submit();
        });
    }

    const modal = document.getElementById('editModal');
    const openBtn = document.getElementById('editProfileBtn');
    const closeBtn = document.getElementById('editModalClose');
    if (modal && openBtn && closeBtn) {
        function openModal() { modal.classList.add('open'); }
        function closeModal() { modal.classList.remove('open'); }
        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    }
})();
</script>
</body>
</html>
