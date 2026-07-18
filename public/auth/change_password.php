<?php
session_start();
require '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission.";
    } else {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (empty($current) || empty($new) || empty($confirm)) {
            $error = "All fields are required.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } elseif (strlen($new) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            $check = $conn->prepare("SELECT password_hash FROM users WHERE user_id = :id");
            $check->execute(['id' => $user_id]);
            $stored = $check->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($current, $stored['password_hash'])) {
                $error = "Current password is incorrect.";
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $update = $conn->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :id");
                $update->execute(['hash' => $hash, 'id' => $user_id]);
                $success = "Password updated successfully!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <style>
        :root, [data-theme="light"] {
            --primary: #458588; --primary-hover: #83a598;
        }
        * { box-sizing:border-box; font-family:var(--font-stack); margin:0; padding:0; }
        body { background-color:var(--bg-color); color:var(--text-muted); -webkit-font-smoothing:antialiased; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .main-content { flex:1; display:flex; align-items:center; justify-content:center; padding:60px 20px; }
        .auth-container {
            background:var(--card-bg); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
            width:100%; max-width:460px; padding:40px; border-radius:16px;
            box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);
            border:1px solid rgba(255,255,255,0.07);
        }
        .form-header { text-align:center; margin-bottom:32px; }
        .form-header h1 { font-size:26px; color:var(--text-main); font-weight:800; letter-spacing:-0.75px; }
        .form-group { margin-bottom:20px; }
        label { display:block; font-size:14px; font-weight:600; color:var(--text-main); margin-bottom:8px; }
        input[type="password"] {
            width:100%; padding:13px 16px; font-size:15px; color:var(--text-main);
            border:1px solid var(--border-color); border-radius:10px;
            background:var(--bg-dim); outline:none; transition:all 0.2s ease;
        }
        input:hover { border-color:var(--border-hover); }
        input:focus { border-color:var(--primary-hover); background:var(--bg-dim); box-shadow:0 0 0 3px rgba(69,133,136,0.25); }
        .btn-submit {
            width:100%; padding:14px; background:var(--primary); color:#fff;
            border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer;
            box-shadow:0 4px 14px rgba(69,133,136,0.3); transition:all 0.2s ease; margin-top:10px;
        }
        .btn-submit:hover { background:var(--primary-hover); transform:translateY(-1px); }
        .btn-back { display:block; text-align:center; margin-top:16px; color:var(--text-muted); text-decoration:none; font-size:14px; }
        .btn-back:hover { color:var(--primary-hover); }
        .msg-error { background:rgba(251,73,52,0.1); color:#fb4934; border:1px solid rgba(251,73,52,0.2); padding:12px; border-radius:10px; font-size:14px; font-weight:500; margin-bottom:20px; text-align:center; }
        .msg-success { background:rgba(184,187,38,0.1); color:#b8bb26; border:1px solid rgba(184,187,38,0.2); padding:12px; border-radius:10px; font-size:14px; font-weight:500; margin-bottom:20px; text-align:center; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../../includes/navbar.php'; ?>
    <main class="main-content">
        <div class="auth-container">
            <div class="form-header">
                <h1>Change Password</h1>
            </div>

            <?php if ($error): ?><div class="msg-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="msg-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required placeholder="At least 6 characters">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required placeholder="Re-enter new password">
                </div>

                <button type="submit" class="btn-submit">Update Password</button>
            </form>

            <a href="edit_profile.php" class="btn-back">&larr; Back to Edit Profile</a>
            <a href="profile.php" class="btn-back">← Back to Profile</a>
        </div>
    </main>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
