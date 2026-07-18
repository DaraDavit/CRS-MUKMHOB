<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$dir = dirname($_SERVER['PHP_SELF']);
$pub_pos = strpos($dir, '/public');
if ($pub_pos !== false) {
    $remaining = substr($dir, $pub_pos + 7);
    $prefix = $remaining ? str_repeat('../', substr_count(ltrim($remaining, '/'), '/') + 1) : '';
} else {
    $prefix = ($dir !== '/' && $dir !== '') ? '../' : '';
}
$base_url = $pub_pos !== false ? substr(dirname($_SERVER['PHP_SELF']), 0, $pub_pos + 7) : '';
?>
<style>
.site-footer {
    background: var(--bg-dim);
    border-top: 1px solid var(--border-color);
    padding: 20px 24px;
    font-family: var(--font-stack);
    margin-top: auto;
}
.footer-inner {
    max-width: 1200px; margin: 0 auto;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 12px;
    color: var(--text-muted); font-size: 13px;
}
.footer-brand { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--text-main); }
.footer-brand img { height: 24px; width: auto; display: block; }
.footer-links { display: flex; gap: 14px; }
.footer-links a {
    color: var(--text-muted); text-decoration: none; font-weight: 500;
}
.footer-links a:hover { color: var(--primary); }
.footer-links .sep { color: var(--border-color); }
.footer-copy { color: var(--text-muted); }
@media (max-width: 700px) {
    .footer-inner { flex-direction: column; text-align: center; gap: 8px; }
}
</style>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 190 54" height="24" width="auto" style="display:block;">
        <text x="0" y="24" font-family="system-ui,-apple-system,sans-serif" font-weight="800" font-size="22" fill="#d65d3c" letter-spacing="2">MUK</text>
        <text x="84" y="24" font-family="system-ui,-apple-system,sans-serif" font-weight="800" font-size="22" fill="var(--text-main)" letter-spacing="2">MHOB</text>
        <line x1="0" y1="32" x2="188" y2="32" stroke="var(--text-muted)" stroke-width="0.5" opacity="0.4"/>
        <text x="0" y="46" font-family="system-ui,-apple-system,sans-serif" font-weight="500" font-size="9" fill="var(--text-muted)" letter-spacing="1.5">COOKING RECIPES SYSTEM</text>
    </svg>
</div>
        <div class="footer-links">
            <a href="<?= $prefix; ?>terms.php">Terms</a>
            <span class="sep">·</span>
            <a href="<?= $prefix; ?>privacy.php">Privacy</a>
            <span class="sep">·</span>
            <a href="<?= $prefix; ?>contact.php">Contact</a>
        </div>
        <div class="footer-copy">&copy; <?= date('Y'); ?> MUK MHOB</div>
    </div>
</footer>
