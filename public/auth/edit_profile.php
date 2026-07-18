<?php
session_start();
require '../../includes/db.php';
require '../../includes/cloudinary.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$query = $conn->prepare("SELECT username, email, avatar_url FROM users WHERE user_id = :id");
$query->execute(['id' => $user_id]);
$user = $query->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];

    if (empty($username) || empty($email)) {
        $error = "Username and email are required.";
    } else {
        $check = $conn->prepare("SELECT password_hash FROM users WHERE user_id = :id");
        $check->execute(['id' => $user_id]);
        $stored = $check->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($current_password, $stored['password_hash'])) {
            $error = "Current password is incorrect.";
        } else {
            if ($username !== $user['username'] || $email !== $user['email']) {
                $update = $conn->prepare("UPDATE users SET username = :username, email = :email WHERE user_id = :id");
                $update->execute(['username' => $username, 'email' => $email, 'id' => $user_id]);
                $_SESSION['username'] = $username;
            }

            if (!empty($_POST['new_password'])) {
                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    $error = "New passwords do not match.";
                } elseif (strlen($_POST['new_password']) < 6) {
                    $error = "New password must be at least 6 characters.";
                } else {
                    $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
                    $update = $conn->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :id");
                    $update->execute(['hash' => $hash, 'id' => $user_id]);
                }
            }

            // Avatar upload
            if (!$error && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($_FILES['avatar']['type'], $allowed)) {
                    $error = "Avatar must be JPG, PNG, or WebP.";
                } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    $error = "Avatar must be under 2MB.";
                } else {
                    $avatar_url = cloudinary_upload($_FILES['avatar']['tmp_name'], 'avatar_' . $user_id);
                    if ($avatar_url) {
                        $upd = $conn->prepare("UPDATE users SET avatar_url = :url WHERE user_id = :id");
                        $upd->execute(['url' => $avatar_url, 'id' => $user_id]);
                        // Delete old avatar from Cloudinary
                        if (!empty($user['avatar_url'])) {
                            cloudinary_delete($user['avatar_url']);
                        }
                        $_SESSION['avatar_url'] = $avatar_url;
                        $user['avatar_url'] = $avatar_url;
                    }
                }
            }

            if (!$error) {
                $user['username'] = $username;
                $user['email'] = $email;
                $success = "Profile updated successfully!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
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
        input[type="text"], input[type="email"], input[type="password"], input[type="file"] {
            width:100%; padding:13px 16px; font-size:15px; color:var(--text-main);
            border:1px solid var(--border-color); border-radius:10px;
            background:var(--bg-dim); outline:none; transition:all 0.2s ease;
        }
        input[type="file"] { padding:10px 16px; }
        input:hover { border-color:var(--border-hover); }
        input:focus { border-color:var(--primary-hover); background:var(--bg-dim); box-shadow:0 0 0 3px rgba(69,133,136,0.25); }
        .avatar-preview { width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid var(--border-color); margin:0 auto 12px; display:block; }
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
        .section-divider { border:none; border-top:1px solid var(--border-color); margin:24px 0; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../../includes/navbar.php'; ?>
    <main class="main-content">
        <div class="auth-container">
            <div class="form-header">
                <h1>Edit Profile</h1>
            </div>

            <?php if ($error): ?><div class="msg-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="msg-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?php if (!empty($user['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($user['avatar_url']); ?>" alt="" class="avatar-preview">
                <?php endif; ?>
                <div class="form-group">
                    <label>Avatar</label>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($user['username']); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($user['email']); ?>">
                </div>

                <hr class="section-divider">

                <div class="form-group">
                    <label>Current Password (required to save changes)</label>
                    <input type="password" name="current_password" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="new_password" placeholder="At least 6 characters">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Re-enter new password">
                </div>

                <button type="submit" class="btn-submit">Save Changes</button>
            </form>

            <a href="profile.php" class="btn-back">&larr; Back to Profile</a>
        </div>
    </main>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
