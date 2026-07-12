<?php
session_start();
require '../includes/db.php';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $message]);
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — MUK MHOB</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { background:var(--bg-color); color:var(--text-main); font-family:var(--font-stack); -webkit-font-smoothing:antialiased; }
        :root { --bg-color:#282828; --bg-dim:#1d2021; --card-bg:rgba(50,48,47,0.7); --text-main:#ebdbb2; --text-muted:#a89984; --primary:#d65d3c; --primary-hover:#e67e52; --border-color:rgba(60,56,54,0.6); --border-hover:#504945; --font-stack:system-ui,-apple-system,sans-serif; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .main-content { flex:1; padding:40px 24px; display:flex; justify-content:center; }
        .container { max-width:560px; width:100%; background:var(--card-bg); padding:36px; border-radius:12px; border:1px solid var(--border-color); backdrop-filter:blur(16px); }
        h1 { font-size:26px; font-weight:800; margin-bottom:4px; letter-spacing:-.5px; }
        .sub { font-size:14px; color:var(--text-muted); margin-bottom:24px; }
        .form-group { margin-bottom:16px; }
        label { display:block; font-size:13px; font-weight:600; color:var(--text-muted); margin-bottom:4px; }
        input, textarea { width:100%; padding:12px 14px; border-radius:8px; border:1px solid var(--border-color); background:rgba(29,32,33,0.6); color:var(--text-main); font-size:14px; outline:none; transition:all .2s; }
        input:focus, textarea:focus { border-color:var(--primary-hover); box-shadow:0 0 0 3px rgba(214,93,60,0.15); }
        textarea { min-height:120px; resize:vertical; }
        .btn { width:100%; padding:14px; border:none; border-radius:8px; font-weight:700; font-size:15px; cursor:pointer; background:var(--primary); color:#fff; transition:all .2s; }
        .btn:hover { background:var(--primary-hover); transform:translateY(-1px); }
        .alert { padding:14px; border-radius:8px; font-size:14px; font-weight:600; margin-bottom:20px; text-align:center; }
        .alert-ok { background:rgba(184,187,38,0.12); color:#b8bb26; border:1px solid rgba(184,187,38,0.25); }
        .alert-err { background:rgba(251,73,52,0.1); color:#fb4934; border:1px solid rgba(251,73,52,0.2); }
        @media(max-width:600px){ .container { padding:24px; } }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../includes/navbar.php'; ?>
    <main class="main-content">
        <div class="container">
            <h1>Contact Us</h1>
            <p class="sub">Have a question, suggestion, or issue? Send us a message.</p>

            <?php if ($sent): ?>
                <div class="alert alert-ok">Message sent! We'll get back to you soon.</div>
            <?php elseif ($error): ?>
                <div class="alert alert-err"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required placeholder="Your name" value="<?= htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required placeholder="What's this about?" value="<?= htmlspecialchars($_POST['subject'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" required placeholder="Your message..."><?= htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn">Send Message</button>
            </form>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</div>
</body>
</html>
