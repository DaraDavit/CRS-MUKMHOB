<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_GET['delete'])) {
    $rid = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM reviews WHERE review_id = ?")->execute([$rid]);
    $_SESSION['message'] = "Review deleted.";
    header("Location: reviews.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$rating_filter = (int)($_GET['rating'] ?? 0);
$sort = $_GET['sort'] ?? 'created';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (r.name LIKE ? OR u.username LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($rating_filter > 0) {
    $where .= ' AND rev.rating = ?';
    $params[] = $rating_filter;
}
$sort_map = ['created' => 'rev.created_at', 'rating' => 'rev.rating'];
$order_col = $sort_map[$sort] ?? 'rev.created_at';

$result = $conn->prepare("
    SELECT rev.*, r.name AS recipe_name, u.username
    FROM reviews rev
    JOIN recipes r ON rev.recipe_id = r.recipe_id
    JOIN users u ON rev.user_id = u.user_id
    WHERE $where
    ORDER BY $order_col $order
");
$result->execute($params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin — Reviews</title>
    <style>
        :root {
            --bg-color: #282828; --bg-dim: #1d2021;
            --card-bg: rgba(50,48,47,0.7);
            --text-main: #d79921; --text-muted: #a89984;
            --primary: #458589; --primary-hover: #83a598;
            --border-color: rgba(60,56,54,0.6);
            --font-stack: system-ui,-apple-system,sans-serif;
            --danger: #cc241d; --danger-hover: #fb4934;
        }
        [data-theme="light"] {
            --bg-color: #f8f5f0; --bg-dim: #f0ebe4;
            --card-bg: #ffffff;
            --text-main: #4a3b2e; --text-muted: #8a7f78;
            --primary: #458589; --primary-hover: #83a598;
            --border-color: #e0d6cc;
            --danger: #c0392b; --danger-hover: #e74c3c;
        }
        * { box-sizing:border-box; font-family:var(--font-stack); margin:0; padding:0; }
        body { background-color:var(--bg-dim); color:var(--text-muted); -webkit-font-smoothing:antialiased; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .main-content { flex:1; padding:40px 24px; }
        .container {
            --text-main: #d79921;
            width:100%; max-width:1200px; margin:0 auto;
            background:var(--card-bg); padding:30px; border-radius:12px;
            border:1px solid var(--border-color);
            backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
            box-shadow:0 20px 40px -10px rgba(0,0,0,0.4);
        }
        h2 { color:var(--text-main); font-weight:800; margin-bottom:20px; }
        .alert { padding:12px; background:rgba(69,133,137,0.15); color:var(--primary-hover); border:1px solid rgba(69,133,137,0.3); border-radius:6px; margin-bottom:20px; font-weight:bold; font-size:14px; }
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
        .review-card { background:rgba(29,32,33,0.4); border:1px solid var(--border-color); border-radius:8px; padding:16px; margin-bottom:12px; }
        .review-card .header { display:flex; justify-content:space-between; margin-bottom:6px; }
        .review-card .recipe-link { font-weight:700; color:var(--text-main); text-decoration:none; font-size:15px; }
        .review-card .recipe-link:hover { color:var(--primary-hover); }
        .review-card .meta { font-size:12px; color:var(--text-muted); }
        .review-card .stars { color:#d79921; font-size:16px; margin-bottom:4px; }
        .review-card .comment { color:var(--text-muted); font-size:14px; line-height:1.5; }
        .btn-del { display:inline-block; padding:6px 12px; border-radius:6px; font-size:13px; font-weight:600; background:rgba(204,36,29,0.15); color:var(--danger-hover); border:1px solid rgba(204,36,29,0.3); text-decoration:none; margin-top:8px; }
        .btn-del:hover { background:rgba(251,73,52,0.25); color:#fff; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../../includes/navbar.php'; ?>
    <div class="admin-layout">
        <?php include 'admin_sidebar.php'; ?>
        <main class="admin-content">
        <div class="container">
            <h2>Review Moderation</h2>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert"><?= htmlspecialchars($_SESSION['message']); ?><?php unset($_SESSION['message']); ?></div>
            <?php endif; ?>

            <form method="GET" class="filter-bar">
                <div class="fg">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search by recipe or user..." value="<?= htmlspecialchars($search); ?>">
                </div>
                <div class="fg">
                    <label>Rating</label>
                    <select name="rating">
                        <option value="">All</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i; ?>" <?= $rating_filter === $i ? 'selected' : ''; ?>><?= $i; ?> Star<?= $i > 1 ? 's' : ''; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>Sort</label>
                    <select name="sort">
                        <option value="created" <?= $sort === 'created' ? 'selected' : ''; ?>>Date</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : ''; ?>>Rating</option>
                    </select>
                </div>
                <a href="?sort=<?= $sort ?>&order=<?= $opposite_order ?>&search=<?= urlencode($search) ?>&rating=<?= $rating_filter ?>" class="btn-order" title="Toggle order">
                    <span class="material-icons"><?= $order === 'ASC' ? 'arrow_upward' : 'arrow_downward'; ?></span>
                </a>
                <button type="submit" class="btn-filter">Filter</button>
                <a href="reviews.php" class="btn-clear">Clear</a>
            </form>

            <?php if ($result->rowCount() === 0): ?>
                <p style="text-align:center;padding:40px;color:var(--text-muted);">No reviews found.</p>
            <?php else: ?>
                <?php while ($rev = $result->fetch()): ?>
                <div class="review-card">
                    <div class="header">
                        <div>
                            <a href="../crs_app/view.php?id=<?= $rev['recipe_id']; ?>" class="recipe-link"><?= htmlspecialchars($rev['recipe_name']); ?></a>
                            <span class="meta"> — by <?= htmlspecialchars($rev['username']); ?></span>
                        </div>
                        <span class="meta"><?= $rev['created_at']; ?></span>
                    </div>
                    <div class="stars"><?php for ($i = 0; $i < 5; $i++): ?><span class="material-icons" style="font-size:16px;color:#d79921;vertical-align:middle;"><?= $i < $rev['rating'] ? 'star' : 'star_outline'; ?></span><?php endfor; ?></div>
                    <?php if ($rev['comment']): ?>
                        <div class="comment"><?= nl2br(htmlspecialchars($rev['comment'])); ?></div>
                    <?php endif; ?>
                    <a href="reviews.php?delete=<?= $rev['review_id']; ?>" class="btn-del" onclick="return confirm('Delete this review?')">Delete</a>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </main>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
