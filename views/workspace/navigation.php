<?php
// The page sets $workspaceUser and $currentSection after its normal access check.
$workspaceRole = $workspaceUser['role'];
$navigation = [];
if ($workspaceRole === 'ADMIN') {
    $navigation = [
        'overview' => ['Overview', 'dashboard.php?tab=overview', 'overview'],
        'workbench' => ['Workbench', 'dashboard.php?tab=workbench', 'bench'],
        'customers' => ['Customers', 'dashboard.php?tab=customers', 'people'],
        'reports' => ['Reports', 'dashboard.php?tab=reports', 'chart'],
        'staff' => ['Staff accounts', 'staff.php', 'people'],
    ];
}
if ($workspaceRole !== 'CASHIER') {
    $navigation['queue'] = [$workspaceRole === 'COURIER' ? 'Transfers' : 'Repair queue', 'staff_home.php?view=queue', 'bench'];
    if ($workspaceRole !== 'COURIER') {
        $navigation['board'] = ['Status board', 'staff_home.php?view=board', 'board'];
    }
    $navigation['history'] = ['History', 'staff_home.php?view=history', 'clock'];
}
if (in_array($workspaceRole, ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true)) {
    $navigation['drafts'] = ['Saved drafts', 'staff_home.php?view=drafts', 'file'];
}
if (in_array($workspaceRole, ['ADMIN', 'MANAGER', 'CASHIER'], true)) {
    $navigation['cash'] = ['Cash desk', 'staff_home.php?view=cash', 'cash'];
}
$navigation['learning'] = ['Learning center', 'learning.php', 'file'];
$symbols = [
    'overview' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
    'bench' => 'M3 5h18v12H3z M8 21h8 M12 17v4',
    'people' => 'M15 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M3 21v-2a8 8 0 0 1 16 0v2',
    'chart' => 'M3 3v18h18 M7 16l4-6 4 2 5-7',
    'board' => 'M3 4h18v16H3z M9 4v16 M15 4v16',
    'clock' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0 M12 6v6l4 2',
    'file' => 'M5 3h10l4 4v14H5z M8 12h8 M8 16h6',
    'cash' => 'M2 5h20v14H2z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
];
$showLink = static function ($key) use ($navigation, $currentSection, $symbols) {
    [$label, $url, $icon] = $navigation[$key];
    ?>
    <a href="<?= h($url) ?>" title="<?= h($label) ?>" <?= $key === $currentSection ? 'aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $symbols[$icon] ?>"/></svg>
        <span><?= h($label) ?></span>
    </a>
    <?php
};
$keys = array_keys($navigation);
$sidebarKeys = array_slice($keys, 0, 6);
if (!in_array($currentSection, $sidebarKeys, true) && isset($navigation[$currentSection])) {
    $sidebarKeys[] = $currentSection;
}
$shortcuts = array_slice($keys, 0, 3);
// Keep dock destinations in a consistent position as staff move between pages.
$inMore = !in_array($currentSection, $shortcuts, true);
?>
<a class="rt-skip" href="#rt-main">Skip to content</a>
<aside class="rt-side">
    <div class="rt-brand"><b>RT</b><span>RepairTrack</span></div>
    <nav aria-label="Workspace pages">
        <?php foreach ($sidebarKeys as $key) { $showLink($key); } ?>
    </nav>
    <button type="button" id="rt-rail" title="Toggle sidebar width" aria-label="Toggle sidebar width">⇆ <span>Sidebar width</span></button>
</aside>
<header class="rt-top">
    <span>Workspace <b>/ <?= h($navigation[$currentSection][0] ?? 'Repair operations') ?></b></span>
    <details id="rt-menu">
        <summary>Menu <span aria-hidden="true">⌄</span></summary>
        <div class="rt-menu-body">
            <strong><?= h($workspaceUser['full_name']) ?></strong>
            <label>Find a page<input type="search" id="rt-search" autocomplete="off"></label>
            <nav aria-label="All destinations"><?php foreach ($keys as $key) { $showLink($key); } ?></nav>
            <p id="rt-no-results" hidden>No matching pages.</p>
            <fieldset><legend>Appearance</legend>
            <?php foreach ([
                'theme' => ['Theme', ['system','ember','tide','linen']],
                'surface' => ['Surfaces', ['glass','solid']],
                'density' => ['Table spacing', ['comfortable','compact']],
            ] as $setting => [$label,$values]): ?>
            <label><?= h($label) ?><select data-rt-setting="<?= h($setting) ?>">
                <?php foreach ($values as $value): ?><option value="<?= h($value) ?>"><?= h(ucfirst($value)) ?></option><?php endforeach; ?>
            </select></label>
            <?php endforeach; ?></fieldset>
            <a href="../index.php">Public home</a>
            <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><button>Log out</button></form>
        </div>
    </details>
</header>
<nav class="rt-dock" aria-label="Mobile shortcuts">
    <?php foreach ($shortcuts as $key) { $showLink($key); } ?>
    <a href="#rt-menu" id="rt-more" <?= $inMore ? 'aria-current="location"' : '' ?>>••• <span>More</span></a>
</nav>
