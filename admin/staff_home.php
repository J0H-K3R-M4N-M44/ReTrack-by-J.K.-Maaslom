<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/staff/repository.php';
startAppSession();
$user = requireAccount();
if ($user['role'] === 'ADMIN') {
    header('Location: dashboard.php');
    exit();
}
header('Cache-Control: no-store, private');
try {
    $branches = intakeBranches(getPDO(), (int) $user['user_id']);
} catch (Throwable $error) {
    error_log('Staff branches: ' . $error->getMessage());
    unavailablePage('Your branch information is temporarily unavailable.');
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Staff workspace | RepairTrack</title><link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css"><link rel="stylesheet" href="../css/staff.css"></head><body class="dashboard-body"><main class="staff-page">
<header class="staff-heading"><div><div class="eyebrow">Staff workspace</div><h1>Welcome, <?= h(
    $user['full_name'],
) ?></h1><p><?= h(staffRoles()[$user['role']] ?? $user['role']) ?> · <?= h(
     $user['email'],
 ) ?></p></div><form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= h(
    csrfToken(),
) ?>"><button class="logout-btn">Log out</button></form></header>
<section class="dashboard-card"><h2>Your branches</h2><?php if (
    !$branches
): ?><p>No active branch is assigned. Contact your administrator.</p><?php else: ?><ul><?php foreach (
    $branches
    as $branch
): ?><li><?= h($branch['branch_name']) .
    ((int) $branch['is_default']
        ? ' — Home branch'
        : '') ?></li><?php endforeach; ?></ul><?php endif; ?><h2>Account ready</h2><p>Your account and branch assignments are active. Role-specific repair, cashier and courier tools are not available in this version. Your administrator can update your role or branches.</p><a href="../index.php">RepairTrack home</a></section>
</main></body></html>
