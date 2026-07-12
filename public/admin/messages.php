<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Mark as read
if (isset($_GET['read'])) {
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read = 1 WHERE message_id = ?");
    $stmt->execute([(int)$_GET['read']]);
    header("Location: messages.php");
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM contact_messages WHERE message_id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    header("Location: messages.php");
    exit;
}

$sort = $_GET['sort'] ?? 'created';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$show_read = isset($_GET['show_read']);
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$where = $show_read ? '1=1' : 'is_read = 0';
$sort_map = ['created' => 'created_at', 'name' => 'name'];
$order_col = $sort_map[$sort] ?? 'created_at';

$result = $conn->prepare("SELECT * FROM contact_messages WHERE $where ORDER BY $order_col $order");
$result->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Admin — Messages</title>
<style>
:root{--bg:#282828;--bg-dim:#1d2021;--card:rgba(50,48,47,0.7);--gold:#d79921;--muted:#a89984;--teal:#458589;--teal-h:#83a598;--border:rgba(60,56,54,0.6);--font:system-ui,-apple-system,sans-serif;--danger:#cc241d;--danger-hover:#fb4934}
*{box-sizing:border-box;font-family:var(--font);margin:0;padding:0}
body{background:var(--bg-dim);color:var(--muted);-webkit-font-smoothing:antialiased}
.page-wrapper{display:flex;flex-direction:column;min-height:100vh}
.admin-layout{display:flex;flex:1;min-height:0}
.main-content{flex:1;padding:40px 24px}
.container{--gold:#d79921;width:100%;max-width:1000px;margin:0 auto;background:var(--card);padding:30px;border-radius:12px;border:1px solid var(--border);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 20px 40px -10px rgba(0,0,0,.4)}
h2{color:var(--gold);font-weight:800;margin-bottom:16px}
.alert{padding:12px;background:rgba(69,133,137,.15);color:var(--teal-h);border:1px solid rgba(69,133,137,.3);border-radius:6px;margin-bottom:20px;font-weight:700;font-size:14px}
.filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:end;padding:14px;background:rgba(29,32,33,0.3);border-radius:8px;border:1px solid var(--border);}
.filter-bar .fg{display:flex;flex-direction:column;gap:2px}
.filter-bar .fg label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:.4px}
.filter-bar select{padding:7px 10px;border-radius:5px;border:1px solid var(--border);background:rgba(29,32,33,0.6);color:var(--gold);font-size:12px;outline:none}
.filter-bar select:focus{border-color:var(--teal-h)}
.btn-filter{padding:7px 14px;border-radius:5px;border:none;font-weight:700;font-size:12px;cursor:pointer;background:var(--teal);color:var(--bg-dim);transition:all .15s}
.btn-filter:hover{background:var(--teal-h)}
.btn-order{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:5px;border:1px solid var(--border);background:rgba(29,32,33,0.6);color:var(--muted);cursor:pointer;font-size:16px;transition:all .15s;text-decoration:none;align-self:end}
.btn-order:hover{border-color:var(--teal-h);color:var(--teal-h)}
.btn-clear{padding:7px 14px;border-radius:5px;border:1px solid var(--border);font-weight:600;font-size:12px;cursor:pointer;background:transparent;color:var(--muted);text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;align-self:end}
.btn-clear:hover{border-color:var(--teal-h);color:var(--gold)}
.card{border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:10px;background:rgba(29,32,33,.3);transition:all .15s}
.card:hover{border-color:var(--teal-h)}
.card.unread{border-left:3px solid var(--teal-h)}
.card .hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:4px}
.card .hdr .from{font-weight:700;color:var(--gold);font-size:14px}
.card .hdr .subj{font-weight:600;color:var(--muted);font-size:13px}
.card .hdr .date{font-size:11px;color:var(--muted)}
.card .body{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:10px}
.card .email{font-size:12px;color:var(--teal-h)}
.card .actions{display:flex;gap:6px}
.btn-xs{padding:5px 10px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .15s;display:inline-block}
.btn-e{background:var(--teal);color:var(--bg-dim)}.btn-e:hover{background:var(--teal-h)}
.btn-d{background:rgba(204,36,29,.15);color:var(--danger-hover);border:1px solid rgba(204,36,29,.3)}.btn-d:hover{background:rgba(251,73,52,.25)}
.empty{text-align:center;padding:60px 20px;color:var(--muted);font-size:14px}
</style>
</head>
<body>
<div class="page-wrapper">
<?php include '../../includes/navbar.php'; ?>
<div class="admin-layout">
<?php include 'admin_sidebar.php'; ?>
<main class="main-content">
<div class="container">
<h2><span class="material-icons" style="vertical-align:middle;margin-right:4px;">mail</span> Messages</h2>

<form method="GET" class="filter-bar">
    <div class="fg">
        <label>Show</label>
        <select name="show_read">
            <option value="0" <?= !$show_read ? 'selected' : ''; ?>>Unread only</option>
            <option value="1" <?= $show_read ? 'selected' : ''; ?>>All messages</option>
        </select>
    </div>
    <div class="fg">
        <label>Sort</label>
        <select name="sort">
            <option value="created" <?= $sort === 'created' ? 'selected' : ''; ?>>Date</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name</option>
        </select>
    </div>
    <a href="?show_read=<?= $show_read ? '1' : '0' ?>&sort=<?= $sort ?>&order=<?= $opposite_order ?>" class="btn-order" title="Toggle order">
        <span class="material-icons"><?= $order === 'ASC' ? 'arrow_upward' : 'arrow_downward'; ?></span>
    </a>
    <button type="submit" class="btn-filter">Filter</button>
    <a href="messages.php" class="btn-clear">Clear</a>
</form>

<?php if ($result->rowCount() === 0): ?>
<div class="empty"><span class="material-icons" style="font-size:48px;margin-bottom:12px;">mail_outline</span><p>No messages found.</p></div>
<?php else: ?>
<?php while ($m = $result->fetch()): ?>
<div class="card <?= $m['is_read'] ? '' : 'unread'; ?>">
    <div class="hdr">
        <span class="from"><?= htmlspecialchars($m['name']); ?></span>
        <span class="subj"><?= htmlspecialchars($m['subject']); ?></span>
        <span class="date"><?= $m['created_at']; ?></span>
    </div>
    <div class="email"><?= htmlspecialchars($m['email']); ?></div>
    <div class="body"><?= nl2br(htmlspecialchars($m['message'])); ?></div>
    <div class="actions">
        <?php if (!$m['is_read']): ?>
        <a href="messages.php?read=<?= $m['message_id']; ?>" class="btn-xs btn-e">Mark Read</a>
        <?php endif; ?>
        <a href="messages.php?delete=<?= $m['message_id']; ?>" class="btn-xs btn-d" onclick="return confirm('Delete this message?')">Delete</a>
    </div>
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
