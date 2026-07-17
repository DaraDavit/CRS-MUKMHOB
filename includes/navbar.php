<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);
$dir = dirname($_SERVER['PHP_SELF']);
$pub_pos = strpos($dir, '/public');
if ($pub_pos !== false) {
    $remaining = substr($dir, $pub_pos + 7);
    $prefix = $remaining ? str_repeat('../', substr_count(ltrim($remaining, '/'), '/') + 1) : '';
} else {
    $prefix = ($dir !== '/' && $dir !== '') ? '../' : '';
}
?>
<style>
@import url("https://fonts.googleapis.com/icon?family=Material+Icons");
.material-icons { font-size: 16px; vertical-align: middle; }
:root {
    --bg-color: #282828;
    --bg-dim: #1d2021;
    --card-bg: rgba(50,48,47,0.7);
    --text-main: #ebdbb2;
    --text-muted: #a89984;
    --primary: #d65d3c;
    --primary-hover: #e67e52;
    --border-color: rgba(60,56,54,0.6);
    --border-hover: #504945;
    --danger: #cc241d;
    --danger-hover: #fb4934;
    --green: #b8bb26;
    --font-stack: system-ui,-apple-system,sans-serif;
    --navbar-bg: rgba(40,40,40,0.92);
    --navbar-border: rgba(60,56,54,0.6);
}
[data-theme="light"] {
    --bg-color: #fdf6e3;
    --bg-dim: #f9f5f0;
    --card-bg: rgba(255,255,255,0.85);
    --text-main: #3c3836;
    --text-muted: #7c6f64;
    --primary: #cc6a4c;
    --primary-hover: #b85c3f;
    --border-color: #e6d5b8;
    --border-hover: #d5c4a1;
    --navbar-bg: rgba(255,255,255,0.9);
    --navbar-border: #e6d5b8;
}
[data-theme="light"] .nav-btn.active {
    color: #fff;
}
[data-theme="light"] .nav-btn.btn-logout-ui:hover {
    background-color: rgba(204,36,29,0.08);
}
[data-theme="light"] .nav-btn:hover {
    background-color: rgba(0,0,0,0.04);
}

.navbar-header {
    display: grid; grid-template-columns: 1fr auto 1fr; align-items: center;
    padding: 14px 24px; max-width: 1200px; margin: 0 auto;
    background: var(--navbar-bg);
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--navbar-border);
    position: sticky; top: 0; z-index: 100; width: 100%;
    border-radius: 0 0 14px 14px;
    transition: background 0.3s, border-color 0.3s;
}
.nav-left { justify-self: start; display:flex; align-items:center; gap:8px; }
.nav-center { justify-self: center; display:flex; align-items:center; gap:15px; flex-wrap:wrap; justify-content:center; }
.nav-right { justify-self: end; display:flex; align-items:center; gap:6px; }
.nav-logo {
    display: flex; align-items: center;
    font-size: 20px; font-weight: 800; color: var(--primary);
    letter-spacing: -0.5px; text-decoration: none;
    transition: color 0.3s;
}
.nav-logo img { height: 43px; width: auto; display: block; }
.nav-btn {
    background: none; border: 1px solid transparent;
    color: var(--text-muted); padding: 6px 14px; border-radius: 8px;
    font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer;
    display: inline-flex; align-items: center; gap: 4px;
    transition: all 0.2s ease; white-space:nowrap;
}
.nav-btn:hover { color: var(--text-main); background-color: rgba(255,255,255,0.05); }
.nav-btn.active { background-color: var(--primary); color: #fff; border-color: var(--primary); }
[data-theme="light"] .nav-btn.active { color: #fff; }
.nav-btn.logout { color: var(--danger); }
.nav-btn.logout:hover { background-color: rgba(251,73,52,0.1); color: var(--danger); }
.theme-toggle {
    background: none; border: 1px solid var(--border-color);
    border-radius: 8px; padding: 6px 10px; cursor: pointer;
    font-size: 16px; line-height: 1; transition: all 0.2s;
    display: flex; align-items: center; gap: 4px;
    color: var(--text-muted);
}
.theme-toggle:hover { border-color: var(--primary); color: var(--primary); }
.theme-toggle .label { font-size: 11px; font-weight: 600; }

.hamburger {
    display: none; background: none; border: none;
    color: var(--text-muted); font-size: 28px; line-height: 1;
    cursor: pointer; padding: 2px 6px; border-radius: 8px;
    transition: all 0.2s;
}
.hamburger:hover { color: var(--text-main); }

.back-link {
    display: inline-flex; align-items: center; gap: 4px;
    color: var(--text-muted); text-decoration: none;
    font-size: 13px; font-weight: 600; margin-bottom: 16px;
    transition: color 0.2s;
}
.back-link:hover { color: var(--primary); }

.mobile-menu {
    display: none; position: absolute; top: 100%; left: 0; right: 0;
    background: var(--navbar-bg);
    border-bottom: 1px solid var(--navbar-border);
    padding: 8px 16px 16px; z-index: 99;
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
    flex-direction: column; gap: 2px;
}
.mobile-menu.open { display: flex; }
.mobile-menu .nav-btn {
    width: 100%; justify-content: flex-start;
    padding: 12px 16px; border-radius: 8px; font-size: 15px;
}
.mobile-menu .nav-btn.logout {
    margin-top: 6px; border-top: 1px solid var(--navbar-border);
    border-radius: 0; padding-top: 14px;
}
.mobile-menu .nav-btn.logout:hover { background-color: rgba(251,73,52,0.1); }

@media (max-width: 700px) {
    .hamburger { display: flex; align-items: center; }
    .nav-center, .nav-right .nav-btn.logout { display: none; }
    .navbar-header {
        grid-template-columns: 1fr auto auto; justify-items:stretch;
        padding: 12px 16px; gap: 4px;
    }
    .nav-left { justify-self:start; }
    .nav-right { justify-self:end; }
    .theme-toggle .label { display: none; }
}
</style>

<header class="navbar-header">
    <div class="nav-left">
        <a href="<?= $prefix; ?>index.php" class="nav-logo"><img src="/Web/public/img/logo.svg" alt="MUK MHOB"></a>
    </div>

    <div class="nav-center">
        <a href="<?= $prefix; ?>index.php" class="nav-btn <?= ($current_page == 'index.php' && $prefix === '') ? 'active' : ''; ?>"><span class="material-icons">home</span> Home</a>
        <a href="<?= $prefix; ?>crs_app/index.php" class="nav-btn <?= (strpos($_SERVER['PHP_SELF'], '/crs_app/') !== false && basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"><span class="material-icons">restaurant_menu</span> Recipes</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $prefix; ?>user/my_favorites.php" class="nav-btn <?= (basename($_SERVER['PHP_SELF']) == 'my_favorites.php') ? 'active' : ''; ?>"><span class="material-icons">favorite</span> Favourites</a>
            <a href="<?= $prefix; ?>auth/profile.php" class="nav-btn <?= ($current_page == 'profile.php') ? 'active' : ''; ?>"><?php if (!empty($_SESSION['avatar_url'])): ?><img src="<?= htmlspecialchars($_SESSION['avatar_url']); ?>" alt="" style="width:20px;height:20px;border-radius:50%;object-fit:cover;vertical-align:middle;"><?php else: ?><span class="material-icons">person</span><?php endif; ?> Profile</a>
            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Content Collector')): ?>
                <a href="<?= $prefix; ?>crs_app/create.php" class="nav-btn <?= (basename($_SERVER['PHP_SELF']) == 'create.php') ? 'active' : ''; ?>"><span class="material-icons">add_circle</span> New Recipe</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <a href="<?= $prefix; ?>admin/index.php" class="nav-btn <?= (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : ''; ?>"><span class="material-icons">admin_panel_settings</span> Admin</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?= $prefix; ?>auth/login.php" class="nav-btn <?= ($current_page == 'login.php') ? 'active' : ''; ?>"><span class="material-icons">login</span> Sign In</a>
            <a href="<?= $prefix; ?>auth/register.php" class="nav-btn <?= ($current_page == 'register.php') ? 'active' : ''; ?>"><span class="material-icons">person_add</span> Register</a>
        <?php endif; ?>
    </div>

    <div class="nav-right">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu"><span class="material-icons">menu</span></button>
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
            <span id="themeIcon" class="material-icons">dark_mode</span>
            <span class="label" id="themeLabel">Dark</span>
        </button>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $prefix; ?>auth/logout.php" class="nav-btn logout"><span class="material-icons">logout</span> Log Out</a>
        <?php endif; ?>
    </div>

<nav class="mobile-menu" id="mobileMenu">
    <a href="<?= $prefix; ?>index.php" class="nav-btn <?= ($current_page == 'index.php' && $prefix === '') ? 'active' : ''; ?>"><span class="material-icons">home</span> Home</a>
    <a href="<?= $prefix; ?>crs_app/index.php" class="nav-btn <?= (strpos($_SERVER['PHP_SELF'], '/crs_app/') !== false && basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"><span class="material-icons">restaurant_menu</span> Recipes</a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $prefix; ?>user/my_favorites.php" class="nav-btn <?= (basename($_SERVER['PHP_SELF']) == 'my_favorites.php') ? 'active' : ''; ?>"><span class="material-icons">favorite</span> Favourites</a>
        <a href="<?= $prefix; ?>auth/profile.php" class="nav-btn <?= ($current_page == 'profile.php') ? 'active' : ''; ?>"><?php if (!empty($_SESSION['avatar_url'])): ?><img src="<?= htmlspecialchars($_SESSION['avatar_url']); ?>" alt="" style="width:20px;height:20px;border-radius:50%;object-fit:cover;vertical-align:middle;"><?php else: ?><span class="material-icons">person</span><?php endif; ?> Profile</a>
        <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Content Collector')): ?>
            <a href="<?= $prefix; ?>crs_app/create.php" class="nav-btn <?= (basename($_SERVER['PHP_SELF']) == 'create.php') ? 'active' : ''; ?>"><span class="material-icons">add_circle</span> New Recipe</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <a href="<?= $prefix; ?>admin/index.php" class="nav-btn <?= (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : ''; ?>"><span class="material-icons">admin_panel_settings</span> Admin</a>
        <?php endif; ?>
        <a href="<?= $prefix; ?>auth/logout.php" class="nav-btn logout"><span class="material-icons">logout</span> Log Out</a>
    <?php else: ?>
        <a href="<?= $prefix; ?>auth/login.php" class="nav-btn <?= ($current_page == 'login.php') ? 'active' : ''; ?>"><span class="material-icons">login</span> Sign In</a>
        <a href="<?= $prefix; ?>auth/register.php" class="nav-btn <?= ($current_page == 'register.php') ? 'active' : ''; ?>"><span class="material-icons">person_add</span> Register</a>
    <?php endif; ?>
</nav>
</header>

<script>
(function() {
    const html = document.documentElement;
    const saved = localStorage.getItem('crsTheme');
    if (saved === 'light') html.setAttribute('data-theme', 'light');

    const themeBtn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');

    function updateUI() {
        const isLight = html.getAttribute('data-theme') === 'light';
        icon.textContent = isLight ? 'light_mode' : 'dark_mode';
        label.textContent = isLight ? 'Light' : 'Dark';
    }
    updateUI();

    if (themeBtn) {
        themeBtn.addEventListener('click', function() {
            const isLight = html.getAttribute('data-theme') === 'light';
            if (isLight) {
                html.removeAttribute('data-theme');
                localStorage.setItem('crsTheme', 'dark');
            } else {
                html.setAttribute('data-theme', 'light');
                localStorage.setItem('crsTheme', 'light');
            }
            updateUI();
        });
    }

    // Hamburger menu
    const hamburger = document.getElementById('hamburgerBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if (hamburger && mobileMenu) {
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileMenu.classList.toggle('open');
            hamburger.innerHTML = mobileMenu.classList.contains('open') ? '<span class="material-icons">close</span>' : '<span class="material-icons">menu</span>';
        });
        mobileMenu.querySelectorAll('.nav-btn').forEach(function(link) {
            link.addEventListener('click', function() {
                mobileMenu.classList.remove('open');
                hamburger.innerHTML = '<span class="material-icons">menu</span>';
            });
        });
        document.addEventListener('click', function(e) {
            if (mobileMenu.classList.contains('open') && !mobileMenu.contains(e.target) && e.target !== hamburger) {
                mobileMenu.classList.remove('open');
                hamburger.innerHTML = '<span class="material-icons">menu</span>';
            }
        });
    }
})();
</script>
