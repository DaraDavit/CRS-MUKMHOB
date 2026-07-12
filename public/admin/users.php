<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit;
}

// AJAX: username availability
if (isset($_GET['check_username'])) {
    header('Content-Type: application/json');
    $ex = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
    $s = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $s->execute([$_GET['check_username'], $ex]);
    echo json_encode(['available' => $s->fetch() === false]);
    exit;
}

// AJAX: get single user data
if (isset($_GET['get_user'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['get_user'];
    $r = $conn->prepare("SELECT user_id, username, email, role FROM users WHERE user_id = ?");
    $r->execute([$id]);
    $u = $r->fetch();
    echo json_encode($u ?: []);
    exit;
}

$toast_msg = '';

// Create user
if (isset($_POST['add_user'])) {
    $u = trim($_POST['username']); $e = trim($_POST['email']); $p = $_POST['password']; $r = $_POST['role'];
    if (!empty($u) && !empty($e) && !empty($p)) {
        $h = password_hash($p, PASSWORD_BCRYPT);
        $s = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $s->execute([$u, $e, $h, $r]);
        $toast_msg = "User '$u' created!";
    } else { $toast_msg = "All fields required."; }
}

// Edit user
if (isset($_POST['edit_user'])) {
    $id = (int)$_POST['edit_user_id'];
    $u = trim($_POST['username']); $e = trim($_POST['email']); $r = $_POST['role'];
    if (!empty($u) && !empty($e)) {
        if (!empty($_POST['password'])) {
            $h = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $s = $conn->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, role = ? WHERE user_id = ?");
            $s->execute([$u, $e, $h, $r, $id]);
        } else {
            $s = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?");
            $s->execute([$u, $e, $r, $id]);
        }
        $toast_msg = "User '$u' updated!";
    } else { $toast_msg = "All fields required."; }
}

// Delete user
if (isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM users WHERE user_id = ?")->execute([$uid]);
    header("Location: users.php"); exit;
}

$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$sort = $_GET['sort'] ?? 'created';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$opposite_order = $order === 'ASC' ? 'DESC' : 'ASC';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (username LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($role_filter === 'Admin' || $role_filter === 'User' || $role_filter === 'Content Collector') {
    $where .= ' AND role = ?';
    $params[] = $role_filter;
}
$sort_map = ['created' => 'created_at', 'username' => 'username', 'email' => 'email'];
$order_col = $sort_map[$sort] ?? 'created_at';

$result = $conn->prepare("SELECT * FROM users WHERE $where ORDER BY $order_col $order");
$result->execute($params);
$max_len = 20;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin — Users</title>
    <style>
        :root {
            --bg-color: #282828; --bg-dim: #1d2021;
            --card-bg: rgba(50,48,47,0.7);
            --text-main: #d79921; --text-muted: #a89984;
            --primary: #458589; --primary-hover: #83a598;
            --border-color: rgba(60,56,54,0.6);
            --font-stack: system-ui,-apple-system,sans-serif;
            --danger: #cc241d; --danger-hover: #fb4934;
            --green: #b8bb26;
        }
        * { box-sizing:border-box; font-family:var(--font-stack); margin:0; padding:0; }
        body { background-color:var(--bg-dim); color:var(--text-muted); -webkit-font-smoothing:antialiased; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .admin-layout { display:flex; flex:1; min-height:0; }
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
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px 14px; text-align:left; border-bottom:1px solid var(--border-color); font-size:14px; }
        th { background:rgba(40,40,40,0.6); color:var(--text-main); font-weight:700; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; }
        td { color:var(--text-muted); }
        tr:hover { background:rgba(60,56,54,0.3); }
        .role-badge { display:inline-block; padding:2px 10px; border-radius:12px; font-size:12px; font-weight:700; }
        .role-Admin { background:rgba(69,133,137,0.2); color:var(--primary-hover); }
        .role-User { background:rgba(168,153,132,0.15); color:var(--text-muted); }
        .role-ContentCollector { background:rgba(214,158,46,0.15); color:#d79921; }

        .btn-add { display:inline-flex; align-items:center; gap:6px; padding:10px 18px; border-radius:8px; font-weight:700; font-size:14px; border:none; cursor:pointer; background:var(--text-main); color:var(--bg-dim); transition:all .15s; margin-bottom:20px; }
        .btn-add:hover { background:#fabd2f; }
        .btn-xs { padding:5px 10px; border-radius:5px; font-size:12px; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all .15s; display:inline-block; }
        .btn-e { background:var(--primary); color:var(--bg-dim); }
        .btn-e:hover { background:var(--primary-hover); }
        .btn-d { background:rgba(204,36,29,0.15); color:var(--danger-hover); border:1px solid rgba(204,36,29,0.3); }
        .btn-d:hover { background:rgba(251,73,52,0.25); }

        /* Modal */
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999; align-items:center; justify-content:center; opacity:0; transition:opacity 0.2s ease; }
        .modal-overlay.show { display:flex; opacity:1; }
        .modal-card { background:var(--card-bg); border:1px solid var(--border-color); border-radius:16px; padding:32px; width:100%; max-width:440px; backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); box-shadow:0 25px 50px -12px rgba(0,0,0,.5); transform:scale(0.92) translateY(10px); transition:transform 0.25s ease; }
        .show .modal-card { transform:scale(1) translateY(0); }
        .modal-card h3 { color:var(--text-main); font-size:22px; font-weight:800; margin-bottom:2px; }
        .modal-card .sub { font-size:13px; color:var(--text-muted); margin-bottom:20px; }
        .modal-card .fg { margin-bottom:14px; }
        .modal-card .fg label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px; letter-spacing:.4px; }
        .modal-card .fg label .req { color:var(--danger-hover); }
        .modal-card .fg label .counter { float:right; font-weight:600; font-size:11px; color:var(--text-muted); text-transform:none; }
        .modal-card .fg input,.modal-card .fg select { width:100%; padding:10px 12px; border-radius:7px; border:1px solid var(--border-color); background:rgba(29,32,33,0.6); color:var(--text-main); font-size:14px; outline:none; transition:all .2s; }
        .modal-card .fg input:focus,.modal-card .fg select:focus { border-color:var(--primary-hover); box-shadow:0 0 0 3px rgba(69,133,136,.2); }
        .modal-card .fg input.error { border-color:var(--danger-hover); }
        .modal-card .fg input.valid { border-color:var(--green); }
        .modal-card .fg .hint { font-size:11px; color:var(--text-muted); margin-top:3px; }
        .modal-card .fg .hint.error { color:var(--danger-hover); }
        .modal-card .fg .hint.ok { color:var(--green); }
        .pw-wrap { position:relative; }
        .pw-wrap input { width:100%; padding:10px 38px 10px 12px; border-radius:7px; border:1px solid var(--border-color); background:rgba(29,32,33,0.6); color:var(--text-main); font-size:14px; outline:none; }
        .pw-wrap input:focus { border-color:var(--primary-hover); box-shadow:0 0 0 3px rgba(69,133,136,.2); }
        .pw-toggle { position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:16px; color:var(--text-muted); padding:4px; line-height:1; }
        .pw-toggle:hover { color:var(--text-main); }
        .pw-meter { display:flex; gap:4px; margin-top:6px; }
        .pw-meter span { flex:1; height:4px; border-radius:2px; background:rgba(60,56,54,.5); transition:all .3s; }
        .pw-meter span.active.weak { background:var(--danger-hover); }
        .pw-meter span.active.mid { background:#d79921; }
        .pw-meter span.active.strong { background:var(--green); }
        .modal-actions { display:flex; gap:8px; margin-top:20px; }
        .modal-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 20px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; border:none; transition:all .15s; }
        .modal-btn-primary { background:var(--primary); color:var(--bg-dim); flex:1; }
        .modal-btn-primary:hover { background:var(--primary-hover); }
        .modal-btn-primary:disabled { opacity:.5; cursor:not-allowed; }
        .modal-btn-close { background:transparent; color:var(--text-muted); border:1px solid var(--border-color); }
        .modal-btn-close:hover { border-color:var(--border-hover); color:var(--text-main); }
        .modal-err { padding:8px 12px; border-radius:6px; font-size:12px; font-weight:600; margin-bottom:12px; background:rgba(251,73,52,.1); color:var(--danger-hover); border:1px solid rgba(251,73,52,.2); display:none; }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner { display:none; width:14px; height:14px; border:2px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:spin .6s linear infinite; }
        .spinner.show { display:inline-block; }
        #toast { position:fixed; top:20px; right:20px; z-index:9999; padding:14px 20px; border-radius:10px; font-size:14px; font-weight:700; background:rgba(40,40,40,.95); border:1px solid var(--border-color); backdrop-filter:blur(12px); color:var(--green); transform:translateX(120%); opacity:0; transition:all .35s cubic-bezier(.4,0,.2,1); box-shadow:0 10px 30px -8px rgba(0,0,0,.5); display:flex; align-items:center; gap:10px; }
        #toast.show { transform:translateX(0); opacity:1; }
        #toast .check { font-size:20px; }
        #toast.err { color:var(--danger-hover); }
        @media (max-width:600px) { .modal-card { max-width:100%; margin:0 10px; padding:24px; } }
        @keyframes slideUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        .modal-card .fg { animation:slideUp .25s ease both; }
        .modal-card .fg:nth-child(2) { animation-delay:.03s; }
        .modal-card .fg:nth-child(3) { animation-delay:.06s; }
        .modal-card .fg:nth-child(4) { animation-delay:.09s; }
        .modal-card .fg:nth-child(5) { animation-delay:.12s; }
        .modal-card .fg:nth-child(6) { animation-delay:.15s; }
        .modal-card .fg:nth-child(7) { animation-delay:.18s; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../../includes/navbar.php'; ?>
    <div class="admin-layout">
        <?php include 'admin_sidebar.php'; ?>
        <main class="admin-content">
        <div class="container">
            <h2>User Management</h2>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert"><?= htmlspecialchars($_SESSION['message']); ?><?php unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <button class="btn-add" onclick="openModal('create')">+ Create User</button>

            <form method="GET" class="filter-bar">
                <div class="fg">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search by username or email..." value="<?= htmlspecialchars($search); ?>">
                </div>
                <div class="fg">
                    <label>Role</label>
                    <select name="role">
                        <option value="">All</option>
                        <option value="Admin" <?= $role_filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="Content Collector" <?= $role_filter === 'Content Collector' ? 'selected' : ''; ?>>Content Collector</option>
                        <option value="User" <?= $role_filter === 'User' ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                <div class="fg">
                    <label>Sort</label>
                    <select name="sort">
                        <option value="created" <?= $sort === 'created' ? 'selected' : ''; ?>>Registered</option>
                        <option value="username" <?= $sort === 'username' ? 'selected' : ''; ?>>Username</option>
                        <option value="email" <?= $sort === 'email' ? 'selected' : ''; ?>>Email</option>
                    </select>
                </div>
                <a href="?sort=<?= $sort ?>&order=<?= $opposite_order ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>" class="btn-order" title="Toggle order">
                    <span class="material-icons"><?= $order === 'ASC' ? 'arrow_upward' : 'arrow_downward'; ?></span>
                </a>
                <button type="submit" class="btn-filter">Filter</button>
                <a href="users.php" class="btn-clear">Clear</a>
            </form>

            <div class="table-responsive">
            <table>
                <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Registered</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php while ($u = $result->fetch()): ?>
                    <tr>
                        <td><?= $u['user_id']; ?></td>
                        <td><strong style="color:#458589;"><?= htmlspecialchars($u['username']); ?></strong></td>
                        <td><?= htmlspecialchars($u['email']); ?></td>
                        <td><span class="role-badge role-<?= str_replace(' ', '', $u['role']); ?>"><?= htmlspecialchars($u['role']); ?></span></td>
                        <td><?= $u['created_at']; ?></td>
                        <td style="white-space:nowrap;">
                            <button class="btn-xs btn-e" onclick="editUser(<?= $u['user_id']; ?>)">Edit</button>
                            <?php if ($u['user_id'] !== $_SESSION['user_id']): ?>
                                <a href="users.php?delete=<?= $u['user_id']; ?>" class="btn-xs btn-d" onclick="return confirm('Delete <?= htmlspecialchars($u['username']); ?>?')">Delete</a>
                            <?php endif; ?>
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

<!-- Create / Edit User Modal -->
<div class="modal-overlay" id="userModal" onclick="if(event.target===this)closeModal()">
    <div class="modal-card">
        <h3 id="modalTitle"><span class="material-icons" style="vertical-align:middle;margin-right:4px;">person</span> Create User</h3>
        <p class="sub" id="modalSub">Add a new user to the system</p>
        <div class="modal-err" id="modalError"></div>
        <form id="userForm" method="POST" onsubmit="return handleSubmit(event)">
            <input type="hidden" name="edit_user_id" id="editUserId" value="">

            <div class="fg">
                <label>Username <span class="req">*</span><span class="counter" id="uCounter">0/<?= $max_len; ?></span></label>
                <input type="text" name="username" id="uName" required placeholder="e.g. johndoe" maxlength="<?= $max_len; ?>" oninput="checkUsername(this)" onblur="checkAvailable(this)">
                <div class="hint" id="uHint">At least 3 characters</div>
            </div>
            <div class="fg">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" id="uEmail" required placeholder="e.g. john@example.com" oninput="checkEmail(this)">
                <div class="hint" id="eHint">Enter a valid email address</div>
            </div>
            <div class="fg" id="pwGroup">
                <label>Password <span class="req" id="pwReq">*</span></label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="uPass" placeholder="At least 6 characters" minlength="6" oninput="checkPassword(this)">
                    <button type="button" class="pw-toggle" onclick="togglePW('uPass',this)" tabindex="-1"><span class="material-icons" style="font-size:16px;vertical-align:middle;">visibility</span></button>
                </div>
                <div class="pw-meter" id="pwMeter"><span></span><span></span><span></span><span></span></div>
                <div class="hint" id="pHint">At least 6 characters</div>
            </div>
            <div class="fg" id="pwGroup2">
                <label>Confirm Password <span class="req" id="pwReq2">*</span></label>
                <div class="pw-wrap">
                    <input type="password" id="uPass2" placeholder="Re-enter password" oninput="checkPassMatch(this)">
                    <button type="button" class="pw-toggle" onclick="togglePW('uPass2',this)" tabindex="-1"><span class="material-icons" style="font-size:16px;vertical-align:middle;">visibility</span></button>
                </div>
                <div class="hint" id="p2Hint">Re-enter your password</div>
            </div>
            <div class="fg">
                <label>Role</label>
                <select name="role" id="uRole" required>
                    <option value="User">User</option>
                    <option value="Content Collector">Content Collector</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="submit" class="modal-btn modal-btn-primary" id="uSubmit"><span class="spinner" id="uSpinner"></span><span id="uBtnText">Create User</span></button>
                <button type="button" class="modal-btn modal-btn-close" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const MAXLEN = <?= $max_len; ?>;
let editingUserId = 0;

function openModal(mode) {
    const m = document.getElementById('userModal');
    if (mode === 'create') {
        resetForm();
        document.getElementById('modalTitle').innerHTML = '<span class="material-icons" style="vertical-align:middle;margin-right:4px;">person</span> Create User';
        document.getElementById('modalSub').textContent = 'Add a new user to the system';
        document.getElementById('uSubmit').querySelector('#uBtnText').textContent = 'Create User';
        document.getElementById('editUserId').value = '';
        document.getElementById('uPass').required = true;
        document.getElementById('uPass2').required = true;
        document.getElementById('pwReq').textContent = '*';
        document.getElementById('pwReq2').textContent = '*';
        document.getElementById('pHint').textContent = 'At least 6 characters';
        document.getElementById('p2Hint').textContent = 'Re-enter your password';
        editingUserId = 0;
    }
    m.classList.add('show');
    setTimeout(() => document.getElementById('uName').focus(), 300);
}

function editUser(id) {
    editingUserId = id;
    document.getElementById('modalTitle').innerHTML = '<span class="material-icons" style="vertical-align:middle;margin-right:4px;">edit</span> Edit User';
    document.getElementById('modalSub').textContent = 'Update user details';
    document.getElementById('uSubmit').querySelector('#uBtnText').textContent = 'Update User';
    document.getElementById('editUserId').value = id;
    document.getElementById('uPass').required = false;
    document.getElementById('uPass2').required = false;
    document.getElementById('pwReq').textContent = '';
    document.getElementById('pwReq2').textContent = '';
    document.getElementById('pHint').textContent = 'Leave blank to keep current password';
    document.getElementById('p2Hint').textContent = 'Leave blank to keep current';

    resetForm();

    fetch('users.php?get_user=' + id)
        .then(r => r.json())
        .then(u => {
            if (!u.user_id) return;
            document.getElementById('uName').value = u.username;
            document.getElementById('uEmail').value = u.email;
            document.getElementById('uRole').value = u.role;
            document.getElementById('uCounter').textContent = u.username.length + '/' + MAXLEN;
            document.getElementById('uName').className = 'valid';
            document.getElementById('uHint').className = 'hint ok';
            document.getElementById('uHint').innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--green);">check</span> Current username';
            document.getElementById('uEmail').className = 'valid';
            document.getElementById('eHint').className = 'hint ok';
            document.getElementById('eHint').innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--green);">check</span> Current email';

            document.getElementById('userModal').classList.add('show');
            setTimeout(() => document.getElementById('uName').focus(), 300);
        });
}

function closeModal() {
    document.getElementById('userModal').classList.remove('show');
    resetForm();
}

function resetForm() {
    const f = document.getElementById('userForm');
    f.reset();
    document.querySelectorAll('#userForm .hint').forEach(h => { h.className = 'hint'; });
    document.querySelectorAll('#userForm input').forEach(i => { i.className = ''; });
    document.getElementById('uSubmit').disabled = false;
    document.getElementById('uBtnText').textContent = 'Create User';
    document.getElementById('uSpinner').classList.remove('show');
    document.getElementById('modalError').style.display = 'none';
    document.querySelectorAll('#pwMeter span').forEach(s => s.className = '');
    document.getElementById('uCounter').textContent = '0/' + MAXLEN;
    document.getElementById('pHint').textContent = 'At least 6 characters';
    document.getElementById('p2Hint').textContent = 'Re-enter your password';
    document.getElementById('eHint').textContent = 'Enter a valid email address';
    document.getElementById('uHint').textContent = 'At least 3 characters';
    document.querySelectorAll('.pw-toggle').forEach(b => b.innerHTML = '<span class="material-icons" style="font-size:16px;vertical-align:middle;">visibility</span>');
    document.getElementById('uPass').type = 'password';
    document.getElementById('uPass2').type = 'password';
    document.getElementById('uPass').required = true;
    document.getElementById('uPass2').required = true;
    document.getElementById('pwReq').textContent = '*';
    document.getElementById('pwReq2').textContent = '*';
    editingUserId = 0;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

function togglePW(inputId, btn) {
    const inp = document.getElementById(inputId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.innerHTML = inp.type === 'password'
        ? '<span class="material-icons" style="font-size:16px;vertical-align:middle;">visibility</span>'
        : '<span class="material-icons" style="font-size:16px;vertical-align:middle;">visibility_off</span>';
}

let availTimer = null;
function checkUsername(el) {
    const h = document.getElementById('uHint');
    const c = document.getElementById('uCounter');
    c.textContent = el.value.length + '/' + MAXLEN;
    if (el.value.length === 0) { el.className = ''; h.className = 'hint'; h.textContent = 'At least 3 characters'; return; }
    if (el.value.length < 3) { el.className = 'error'; h.className = 'hint error'; h.textContent = 'Too short (' + el.value.length + '/' + MAXLEN + ')'; return; }
    el.className = 'valid'; h.className = 'hint ok'; h.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--green);">check</span> Checking...';
}
function checkAvailable(el) {
    clearTimeout(availTimer);
    if (el.value.length < 3) return;
    availTimer = setTimeout(() => {
        let url = 'users.php?check_username=' + encodeURIComponent(el.value.trim());
        if (editingUserId > 0) url += '&exclude_id=' + editingUserId;
        fetch(url).then(r => r.json()).then(d => {
            const h = document.getElementById('uHint');
            if (!d.available) { el.className = 'error'; h.className = 'hint error'; h.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--danger-hover);">close</span> Username taken'; }
            else { el.className = 'valid'; h.className = 'hint success'; h.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--green);">check</span> Available'; }
        });
    }, 400);
}
function checkEmail(el) {
    const h = document.getElementById('eHint');
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (el.value.length > 0 && !re.test(el.value)) { el.className = 'error'; h.className = 'hint error'; h.textContent = 'Invalid email format'; }
    else if (re.test(el.value)) { el.className = 'valid'; h.className = 'hint ok'; h.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--green);">check</span> Valid email'; }
    else { el.className = ''; h.className = 'hint'; h.textContent = 'Enter a valid email address'; }
}
function checkPassword(el) {
    const h = document.getElementById('pHint');
    const bars = document.querySelectorAll('#pwMeter span');
    const val = el.value;
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/\d/.test(val)) score++;
    if (/[^a-zA-Z0-9]/.test(val)) score++;
    bars.forEach((b, i) => {
        b.className = '';
        if (i < Math.min(score, 4)) { b.classList.add('active'); b.classList.add(score <= 2 ? 'weak' : score <= 3 ? 'mid' : 'strong'); }
    });
    if (val.length === 0) { el.className = ''; h.className = 'hint'; h.textContent = editingUserId > 0 ? 'Leave blank to keep current' : 'At least 6 characters'; }
    else if (val.length < 6) { el.className = 'error'; h.className = 'hint error'; h.textContent = val.length + '/6 — too short'; }
    else if (score <= 2) { el.className = 'valid'; h.className = 'hint'; h.textContent = 'Weak — add symbols & mixed case'; }
    else if (score <= 3) { el.className = 'valid'; h.className = 'hint ok'; h.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--green);">check</span> Medium strength'; }
    else { el.className = 'valid'; h.className = 'hint ok'; h.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--green);">check</span> Strong password'; }
    checkPassMatch(document.getElementById('uPass2'));
}
function checkPassMatch(el) {
    const p1 = document.getElementById('uPass').value;
    const h = document.getElementById('p2Hint');
    if (el.value.length === 0) { el.className = ''; h.className = 'hint'; h.textContent = editingUserId > 0 ? 'Leave blank to keep current' : 'Re-enter your password'; }
    else if (el.value !== p1) { el.className = 'error'; h.className = 'hint error'; h.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--danger-hover);">close</span> Passwords do not match'; }
    else { el.className = 'valid'; h.className = 'hint ok'; h.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--green);">check</span> Passwords match'; }
}

function showToast(msg, isErr) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.className = isErr ? 'err' : '';
    t.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

function handleSubmit(e) {
    e.preventDefault();
    const err = document.getElementById('modalError');
    err.style.display = 'none';
    const name = document.getElementById('uName').value.trim();
    const email = document.getElementById('uEmail').value.trim();
    const pass = document.getElementById('uPass').value;
    const pass2 = document.getElementById('uPass2').value;
    const isEdit = editingUserId > 0;
    let errors = [];
    if (name.length < 3) errors.push('Username must be at least 3 characters');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Invalid email address');
    if (!isEdit && pass.length < 6) errors.push('Password must be at least 6 characters');
    if (isEdit && pass.length > 0 && pass.length < 6) errors.push('Password must be at least 6 characters');
    if (pass !== pass2) errors.push('Passwords do not match');
    if (errors.length) {
        err.textContent = errors.join('. ');
        err.style.display = 'block';
        return false;
    }
    const btn = document.getElementById('uSubmit');
    btn.disabled = true;
    document.getElementById('uBtnText').textContent = isEdit ? 'Updating...' : 'Creating...';
    document.getElementById('uSpinner').classList.add('show');

    const formData = new FormData(document.getElementById('userForm'));
    formData.set(isEdit ? 'edit_user' : 'add_user', '1');
    if (!isEdit) formData.delete('edit_user_id');

    fetch('users.php', { method: 'POST', body: formData })
        .then(() => {
            closeModal();
            showToast(isEdit ? 'User updated!' : 'User created!');
            setTimeout(() => window.location.reload(), 1200);
        })
        .catch(() => {
            btn.disabled = false;
            document.getElementById('uBtnText').textContent = isEdit ? 'Update User' : 'Create User';
            document.getElementById('uSpinner').classList.remove('show');
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
