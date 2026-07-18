<?php
session_start();
require '../../includes/db.php';
$error_message = '';
$success_message = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($email) && !empty($password)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            $check_query = $conn->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
            $check_query->execute(['username' => $username, 'email' => $email]);
            $existing_user = $check_query->fetch();

            if ($existing_user) {
                $error_message = "That username or email is already taken.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $insert_query = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password)");
                $insert_query->execute([
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashedPassword
                ]);
                $success_message = "Registration successful! Redirecting to login...";
            }
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MUK MHOB</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root, [data-theme="light"] {
            --primary: #458588;
        }
        :root { --primary-hover: #d79921; }
        [data-theme="light"] { --primary-hover: #83a598; }

        body {
            background-color: var(--bg-color);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: var(--font-stack);
            -webkit-font-smoothing: antialiased;
        }

        .page-wrapper { display: flex; flex-direction: column; min-height: 100vh; }
        .main-content { flex: 1; display: flex; align-items: center; justify-content: center; padding: 60px 20px; }

        /* Scoped custom variables securely lock yellow color parameters */
        .auth-container {
            background-color: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            width: 100%;
            max-width: 460px;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }

        .form-header { text-align: center; margin-bottom: 32px; }
        .form-header h1 { font-size: 26px; color: var(--text-main); font-weight: 800; letter-spacing: -0.75px; }
        .form-header p { font-size: 14px; color: var(--text-muted); margin-top: 8px; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; font-weight: 600; color: var(--text-main); margin-bottom: 8px; }

        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 13px 16px; font-size: 15px; color: var(--text-main);
            border: 1px solid var(--border-color); border-radius: 10px;
            background-color: var(--bg-dim); outline: none; transition: all 0.2s ease;
        }
        input:hover { border-color: var(--border-hover); }
        input:focus { border-color: var(--primary-hover); background-color: var(--bg-dim); box-shadow: 0 0 0 3px rgba(69, 133, 136, 0.25); }

        .btn-submit {
            width: 100%; padding: 14px; background-color: var(--primary); color: var(--bg-dim);
            border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 14px rgba(69, 133, 136, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); margin-top: 10px;
        }
        .btn-submit:hover { background-color: var(--primary-hover); box-shadow: 0 6px 20px rgba(131, 165, 152, 0.4); transform: translateY(-1px); }

        .form-footer { text-align: center; margin-top: 28px; font-size: 14px; color: var(--text-muted); }
        .form-footer a { color: var(--primary-hover); text-decoration: none; font-weight: 600; }
        .form-footer a:hover { color: var(--primary); text-decoration: underline; }

        .msg-error {
            background-color: rgba(251, 73, 52, 0.1); color: #fb4934;
            border: 1px solid rgba(251, 73, 52, 0.2); padding: 12px;
            border-radius: 10px; font-size: 14px; font-weight: 500; margin-bottom: 20px; text-align: center;
        }

        .msg-success {
            background-color: rgba(184, 187, 38, 0.1); color: #b8bb26;
            border: 1px solid rgba(184, 187, 38, 0.2); padding: 12px;
            border-radius: 10px; font-size: 14px; font-weight: 500; margin-bottom: 20px; text-align: center;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include '../../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="auth-container">
                <a href="javascript:history.back()" class="back-link">&larr; Back</a>
                <div class="form-header">
                    <h1>Create account</h1>
                    <p>Join us to start managing your workspace</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="msg-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="msg-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <script>setTimeout(() => { window.location.href = 'login.php'; }, 2000);</script>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required placeholder="johndoe" value="<?php echo htmlspecialchars($username); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email address</label>
                        <input type="email" name="email" required placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-submit">Create account</button>
                </form>
                
                <p class="form-footer">
                    Already have an account? <a href="login.php">Sign in</a>
                </p>
            </div>
        </main>
    </div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>