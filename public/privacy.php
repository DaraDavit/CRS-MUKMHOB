<?php
session_start();
require '../includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — MUK MHOB</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { background:var(--bg-color); color:var(--text-main); font-family:var(--font-stack); -webkit-font-smoothing:antialiased; }
        :root { --bg-color:#282828; --bg-dim:#1d2021; --card-bg:rgba(50,48,47,0.7); --text-main:#ebdbb2; --text-muted:#a89984; --primary:#d65d3c; --primary-hover:#e67e52; --border-color:rgba(60,56,54,0.6); --font-stack:system-ui,-apple-system,sans-serif; }
        .page-wrapper { display:flex; flex-direction:column; min-height:100vh; }
        .main-content { flex:1; padding:40px 24px; display:flex; justify-content:center; }
        .container { max-width:720px; width:100%; background:var(--card-bg); padding:36px; border-radius:12px; border:1px solid var(--border-color); backdrop-filter:blur(16px); }
        h1 { font-size:26px; font-weight:800; margin-bottom:6px; letter-spacing:-.5px; }
        .date { font-size:12px; color:var(--text-muted); margin-bottom:24px; }
        h2 { font-size:16px; font-weight:700; margin-top:20px; margin-bottom:8px; color:var(--primary-hover); }
        p { font-size:14px; line-height:1.7; color:var(--text-muted); margin-bottom:12px; }
        ul { margin:8px 0 16px 20px; }
        li { font-size:14px; line-height:1.7; color:var(--text-muted); margin-bottom:4px; }
        @media(max-width:600px){ .container { padding:24px; } }
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php include '../includes/navbar.php'; ?>
    <main class="main-content">
        <div class="container">
            <h1>Privacy Policy</h1>
            <p class="date">Last updated: July 2026</p>

            <h2>1. Information We Collect</h2>
            <p>We collect information you provide when registering: username, email address, and password. If you upload an avatar, we store the image URL. When you submit a recipe, review, or contact form, we store that content along with timestamps.</p>

            <h2>2. How We Use Your Information</h2>
            <ul>
                <li>To operate and maintain the Platform</li>
                <li>To personalize your experience (e.g., remembering your favorites)</li>
                <li>To respond to your inquiries submitted via the contact form</li>
                <li>To improve our services</li>
            </ul>

            <h2>3. Data Storage</h2>
            <p>Your data is stored on our servers. Images uploaded via Cloudinary are stored on Cloudinary's servers subject to their privacy policy. We retain your data for as long as your account is active.</p>

            <h2>4. Cookies</h2>
            <p>We use session cookies to maintain your login state. We also use localStorage to persist your theme preference (dark/light mode). No third-party tracking cookies are used.</p>

            <h2>5. Data Sharing</h2>
            <p>We do not sell your personal data. Public content such as recipes, reviews, and usernames is visible to other users of the Platform. We may disclose information if required by law.</p>

            <h2>6. Your Rights</h2>
            <p>You may update or delete your account information at any time through your profile settings. Contact us to request permanent deletion of your data.</p>

            <h2>7. Security</h2>
            <p>We use industry-standard password hashing (bcrypt) and prepared SQL statements to protect your data. However, no method of transmission is 100% secure.</p>

            <h2>8. Contact</h2>
            <p>For privacy-related inquiries, please <a href="contact.php" style="color:var(--primary-hover);">contact us</a>.</p>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</div>
</body>
</html>
