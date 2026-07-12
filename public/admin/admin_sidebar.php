<?php
$current = basename($_SERVER['PHP_SELF']);
$links = [
    ['index.php', 'bar_chart', 'Dashboard'],
    ['users.php', 'people', 'Users'],
    ['recipes.php', 'restaurant', 'Recipes'],
    ['reviews.php', 'star', 'Reviews'],
    ['food_types.php', 'label', 'Food Types'],
    ['regions.php', 'language', 'Regions'],
    ['countries.php', 'location_on', 'Countries'],
    ['ingredients.php', 'ramen_dining', 'Ingredients'],
    ['categories.php', 'label', 'Categories'],
    ['messages.php', 'mail', 'Messages'],
];
?>
<style>
.admin-layout { display: flex; flex: 1; min-height: 0; }
.admin-sidebar {
    width: 220px; min-width: 220px;
    background: rgba(29,32,33,0.5);
    border-right: 1px solid var(--border-color);
    display: flex; flex-direction: column;
    transition: width 0.25s ease, min-width 0.25s ease;
    overflow: hidden;
}
.admin-sidebar.collapsed { width: 58px; min-width: 58px; }
.admin-sidebar .links { flex: 1; padding: 12px 8px; display: flex; flex-direction: column; gap: 2px; }
.admin-sidebar a {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 10px; border-radius: 8px;
    text-decoration: none; font-size: 14px; font-weight: 600;
    color: var(--muted); transition: all 0.15s ease;
    white-space: nowrap; overflow: hidden;
}
.admin-sidebar a:hover { background: rgba(255,255,255,0.04); color: var(--gold); }
.admin-sidebar a.active { background: rgba(69,133,137,0.2); color: var(--teal-h); }
.admin-sidebar a .ico { font-size: 18px; min-width: 24px; text-align: center; flex-shrink: 0; }
.admin-sidebar a .txt { opacity: 1; transition: opacity 0.15s ease; }
.admin-sidebar.collapsed a .txt { opacity: 0; width: 0; display: none; }
.sidebar-toggle {
    padding: 12px; cursor: pointer; text-align: center;
    border-top: 1px solid var(--border-color);
    color: var(--muted); font-size: 18px;
    transition: all 0.15s ease; border: none; background: transparent;
}
.sidebar-toggle:hover { color: var(--gold); }
.admin-content {
    flex: 1; padding: 28px 24px; overflow-x: auto;
    min-width: 0;
}
@media (max-width: 768px) {
    .admin-sidebar { width: 58px; min-width: 58px; }
    .admin-sidebar a .txt { display: none; }
    .sidebar-toggle { display: none; }
}
</style>
<nav class="admin-sidebar" id="adminSidebar">
    <div class="links">
        <?php foreach ($links as $l): ?>
        <a href="<?= $l[0]; ?>" class="<?= $current === $l[0] ? 'active' : ''; ?>">
            <span class="material-icons ico"><?= $l[1]; ?></span>
            <span class="txt"><?= $l[2]; ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar"><span class="material-icons" style="font-size:18px;">skip_previous</span></button>
</nav>
<script>
(function() {
    const sidebar = document.getElementById('adminSidebar');
    const toggle = document.getElementById('sidebarToggle');
    const collapsed = localStorage.getItem('adminSidebarCollapsed') === 'true';
    if (collapsed) { sidebar.classList.add('collapsed'); toggle.innerHTML = '<span class="material-icons" style="font-size:18px;">skip_next</span>'; }
    toggle.addEventListener('click', function() {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        toggle.innerHTML = isCollapsed ? '<span class="material-icons" style="font-size:18px;">skip_next</span>' : '<span class="material-icons" style="font-size:18px;">skip_previous</span>';
        localStorage.setItem('adminSidebarCollapsed', isCollapsed);
    });
})();
</script>
