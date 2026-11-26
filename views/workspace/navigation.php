<?php
// The page sets $workspaceUser and $currentSection after its normal access check.
// Prefix every internal variable because this include shares the caller's PHP scope.
require_once __DIR__ . '/../../app/console/workspace.php';
$rtWorkspaceRole = $workspaceUser['role'];
$rtNavigation = [];
if ($rtWorkspaceRole !== 'ADMIN') {
    $rtNavigation['overview'] = ['Overview', 'staff_home.php?view=overview', 'overview'];
}
if ($rtWorkspaceRole === 'ADMIN') {
    $rtNavigation = [
        'overview' => ['Overview', 'dashboard.php?tab=overview', 'overview'],
        'workbench' => ['Workbench', 'dashboard.php?tab=workbench', 'bench'],
        'customers' => ['Customers', 'dashboard.php?tab=customers', 'people'],
        'reports' => ['Reports', 'reports.php', 'chart'],
        'staff' => ['Staff accounts', 'staff.php', 'people'],
    ];
}
if ($rtWorkspaceRole !== 'CASHIER') {
    $rtNavigation['queue'] = [$rtWorkspaceRole === 'COURIER' ? 'Transfers' : 'Repair queue', 'staff_home.php?view=queue', $rtWorkspaceRole === 'COURIER' ? 'transfers' : 'queue'];
    if ($rtWorkspaceRole !== 'COURIER') {
        $rtNavigation['board'] = ['Status board', 'staff_home.php?view=board', 'board'];
    }
    $rtNavigation['history'] = [$rtWorkspaceRole === 'COURIER' ? 'Transfer history' : 'History', 'staff_home.php?view=history', 'history'];
}
if (in_array($rtWorkspaceRole, ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true)) {
    $rtNavigation['drafts'] = ['Saved drafts', 'staff_home.php?view=drafts', 'drafts'];
}
if (in_array($rtWorkspaceRole, ['ADMIN', 'MANAGER', 'CASHIER'], true)) {
    $rtNavigation['cash'] = ['Cash desk', 'staff_home.php?view=cash', 'cash'];
}
if ($rtWorkspaceRole !== 'ADMIN' && consoleCanViewReports($workspaceUser)) {
    $rtNavigation['reports'] = ['Reports', 'reports.php', 'chart'];
}
require_once __DIR__ . '/../../app/console/activity.php';
$rtActivityCounts = [];
if (activityGroups($rtWorkspaceRole)) {
    try {
        $rtActivityCounts = activityUnreadCounts(getPDO(), $workspaceUser);
    } catch (RuntimeException $rtActivityError) {
        // A missing activity migration should not interrupt unrelated staff work.
        error_log('Staff unread counts unavailable: ' . $rtActivityError->getCode());
    }
    $rtNavigation['activity'] = ['Staff activity', 'activity.php', 'activity'];
}
if (in_array($rtWorkspaceRole, ['ADMIN', 'MANAGER', 'COURIER'], true)) {
    $rtNavigation['handovers'] = ['Device handovers', 'transfers.php', 'handovers'];
}
if (in_array($rtWorkspaceRole, ['ADMIN', 'MANAGER'], true)) {
    $rtNavigation['locations'] = ['Storage locations', 'locations.php', 'locations'];
    $rtNavigation['inventory'] = ['Branch inventory', 'inventory.php', 'inventory'];
    $rtNavigation['parts-transfers'] = ['Parts transfers', 'parts_transfers.php', 'parts'];
    $rtNavigation['stocktaking'] = ['Monthly stocktaking', 'stocktaking.php', 'stocktaking'];
    $rtNavigation['acquisition-costs'] = ['Supplier acquisition costs', 'acquisition_costs.php', 'costs'];
}
$rtNavigation['learning'] = ['Learning center', 'learning.php', 'learning'];
$rtSymbols = [
    'overview' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
    'bench' => 'M3 5h18v12H3z M8 21h8 M12 17v4',
    'people' => 'M15 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M3 21v-2a8 8 0 0 1 16 0v2',
    'chart' => 'M3 3v18h18 M7 16l4-6 4 2 5-7',
    'board' => 'M3 4h18v16H3z M9 4v16 M15 4v16',
    'queue' => 'M4 5h16 M4 12h16 M4 19h10',
    'history' => 'M3 12a9 9 0 1 0 3-6.7L3 8 M3 3v5h5 M12 7v5l4 2',
    'drafts' => 'M5 3h10l4 4v14H5z M8 12h8 M8 16h6 M15 3v5h4',
    'cash' => 'M2 5h20v14H2z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    'activity' => 'M3 12h4l3-8 4 16 3-8h4',
    'transfers' => 'M4 7h15l-3-3 M20 17H5l3 3',
    'handovers' => 'M3 7h18v13H3z M8 7V4h8v3 M8 13h8 M12 10v6',
    'locations' => 'M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0 M12 10a2 2 0 1 0 0 .01',
    'inventory' => 'M3 7l9-4 9 4-9 4-9-4z M3 7v10l9 4 9-4V7 M12 11v10',
    'parts' => 'M3 7h18 M3 12h12 M3 17h8 M17 10l4 2-4 2',
    'stocktaking' => 'M5 3h14v18H5z M8 8l2 2 5-5 M8 15h8',
    'costs' => 'M3 5h18v14H3z M7 9h10 M7 13h5 M7 16h7',
    'learning' => 'M3 5h8a3 3 0 0 1 3 3v13a3 3 0 0 0-3-3H3z M21 5h-4a3 3 0 0 0-3 3v13a3 3 0 0 1 3-3h4z',
];
$rtShowLink = static function ($rtKey) use ($rtNavigation, $currentSection, $rtSymbols) {
    [$rtLabel, $rtUrl, $rtIcon] = $rtNavigation[$rtKey];
    ?>
    <a href="<?= h($rtUrl) ?>" title="<?= h($rtLabel) ?>" aria-label="<?= h($rtLabel) ?>" data-tooltip="<?= h($rtLabel) ?>" <?= $rtKey === $currentSection ? 'aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $rtSymbols[$rtIcon] ?>"/></svg>
        <span><?= h($rtLabel) ?></span>
    </a>
    <?php
};
$rtKeys = array_keys($rtNavigation);
$rtSidebarKeys = array_slice($rtKeys, 0, 6);
if (!in_array($currentSection, $rtSidebarKeys, true) && isset($rtNavigation[$currentSection])) {
    $rtSidebarKeys[] = $currentSection;
}
$rtShortcuts = array_slice($rtKeys, 0, 3);
$rtInMore = !in_array($currentSection, $rtShortcuts, true);
?>
<a class="rt-skip" href="#rt-main">Skip to content</a>
<aside class="rt-side">
    <div class="rt-brand"><b>RT</b><span>RepairTrack</span></div>
    <nav aria-label="Workspace pages">
        <?php foreach ($rtSidebarKeys as $rtKey) { $rtShowLink($rtKey); } ?>
    </nav>
    <button type="button" id="rt-rail" title="Toggle sidebar width" aria-label="Toggle sidebar width">⇆ <span>Sidebar width</span></button>
</aside>
<header class="rt-top">
    <span>Workspace <b>/ <?= h($rtNavigation[$currentSection][0] ?? 'Repair operations') ?></b></span>
    <?php if (array_sum($rtActivityCounts) > 0): ?>
        <a class="rt-unread" href="activity.php"
            aria-label="Open staff activity. Unread counts are available by category.">
            Staff activity
        </a>
    <?php endif; ?>
    <details id="rt-menu">
        <summary>Menu <span aria-hidden="true">⌄</span></summary>
        <div class="rt-menu-body">
            <strong><?= h($workspaceUser['full_name']) ?></strong>
            <label>Find a page<input type="search" id="rt-search" autocomplete="off"></label>
            <nav aria-label="All destinations"><?php foreach ($rtKeys as $rtKey) { $rtShowLink($rtKey); } ?></nav>
            <p id="rt-no-results" hidden>No matching pages.</p>
            <fieldset><legend>Appearance</legend>
            <?php foreach ([
                'theme' => ['Theme', ['system','ember','tide','linen']],
                'surface' => ['Surfaces', ['glass','solid']],
                'density' => ['Table spacing', ['comfortable','compact']],
            ] as $rtSetting => [$rtSettingLabel, $rtOptions]): ?>
            <label><?= h($rtSettingLabel) ?><select data-rt-setting="<?= h($rtSetting) ?>">
                <?php foreach ($rtOptions as $rtOption): ?><option value="<?= h($rtOption) ?>"><?= h(ucfirst($rtOption)) ?></option><?php endforeach; ?>
            </select></label>
            <?php endforeach; ?></fieldset>
            <a href="../index.php">Public home</a>
            <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><button>Log out</button></form>
        </div>
    </details>
</header>
<nav class="rt-dock" aria-label="Mobile shortcuts">
    <?php foreach ($rtShortcuts as $rtKey) { $rtShowLink($rtKey); } ?>
    <a href="#rt-menu" id="rt-more" <?= $rtInMore ? 'aria-current="location"' : '' ?>>••• <span>More</span></a>
</nav>

<noscript>
<nav class="rt-nojs-pages" aria-label="All workspace pages without JavaScript">
    <p>All staff pages — JavaScript is turned off</p>
    <?php foreach ($rtKeys as $rtKey) { $rtShowLink($rtKey); } ?>
</nav>
</noscript>
