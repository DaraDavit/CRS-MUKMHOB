<?php
session_start();
require '../includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service — MUK MHOB</title>
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
            <h1>Terms of Service</h1>
            <p class="date">Last updated: July 2026</p>

            <h2>1. Acceptance of Terms</h2>
            <p>By accessing or using MUK MHOB ("the Platform"), you agree to be bound by these Terms of Service. If you do not agree, do not use the Platform.</p>

            <h2>2. Description of Service</h2>
            <p>MUK MHOB is a recipe sharing and discovery platform. Users can browse, create, review, and save recipes. Registered users may contribute content, while administrators manage the Platform.</p>

            <h2>3. User Accounts</h2>
            <p>You are responsible for maintaining the confidentiality of your account credentials. You must provide accurate information when registering. You may not impersonate another person.</p>

            <h2>4. User Content</h2>
            <p>By submitting recipes, reviews, or other content, you grant MUK MHOB a non-exclusive, royalty-free license to display and distribute your content on the Platform. You represent that your content does not infringe third-party rights.</p>

            <h2>5. Prohibited Conduct</h2>
            <ul>
                <li>Uploading harmful, offensive, or illegal content</li>
                <li>Attempting to disrupt the Platform's security or performance</li>
                <li>Scraping or harvesting user data without permission</li>
                <li>Using the Platform for commercial purposes without authorization</li>
            </ul>

            <h2>6. Limitation of Liability</h2>
            <p>MUK MHOB is provided "as is" without warranties of any kind. We are not liable for damages arising from your use of the Platform, including but not limited to recipe inaccuracies or allergic reactions to published recipes.</p>

            <h2>7. Changes to Terms</h2>
            <p>We reserve the right to modify these terms at any time. Changes take effect immediately upon posting. Continued use of the Platform constitutes acceptance of the revised terms.</p>

            <h2>8. Contact</h2>
            <p>For questions about these terms, please <a href="contact.php" style="color:var(--primary-hover);">contact us</a>.</p>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</div>
</body>
</html>
