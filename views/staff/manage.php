<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $errors
    ? 'Error: '
    : '' ?>Staff management | RepairTrack</title><link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css"><link rel="stylesheet" href="../css/staff.css"></head>
<body class="dashboard-body"><main class="staff-page">
<header class="staff-heading"><div><div class="eyebrow">Administration · People and access</div><h1>Staff management</h1><p>Add people, choose their role and assign the branches they work in.</p></div><a class="secondary-btn" href="dashboard.php">Back to dashboard</a></header>
<?php if ($notice): ?><p class="staff-notice" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if (
    $invitation
): ?><section class="dashboard-card invitation"><h2>Activation link ready</h2><p>Share this privately with the intended staff member. It works once and expires in 24 hours. No email has been sent. The account remains inactive until they choose a password.</p><label for="activation-link">Private activation link</label><input id="activation-link" readonly value="<?= h(
    $activationLink,
) ?>"><div class="staff-actions"><button class="secondary-btn" type="button" data-copy-link>Copy link</button><span data-copy-status role="status"></span></div><p>This link is shown once. If you lose it, open the inactive staff account and generate a replacement. Make sure the site address is reachable by the staff member.</p></section><?php endif; ?>
<?php if (
    $errors
): ?><div class="intake-errors" role="alert" tabindex="-1" data-error-summary><h2>There is a problem</h2><ul><?php foreach (
    $errors
    as $key => $error
): ?><li><a href="#<?= h($key) ?>"><?= h(
    $error,
) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="dashboard-card staff-editor">
<?php if ($review): ?>
    <h2><?= $action === 'deactivate'
        ? 'Deactivate this staff account?'
        : ($action === 'invite'
            ? 'Generate a new activation link?'
            : 'Review staff details') ?></h2>
    <?php if ($action === 'save'): ?><dl class="staff-review"><div><dt>Name</dt><dd><?= h(
    $values['full_name'],
) ?></dd></div><div><dt>Email</dt><dd><?= h(
    $values['email'],
) ?></dd></div><div><dt>Role</dt><dd><?= h(
    staffRoles()[$values['role']],
) ?></dd></div><div><dt>Branches</dt><dd><?php foreach ($branches as $branch) {
    if (in_array((int) $branch['branch_id'], $values['branches'], true)) {
        echo '<p>' .
            h($branch['branch_name']) .
            ((int) $branch['branch_id'] === (int) $values['default_branch']
                ? ' · Home branch'
                : '') .
            '</p>';
    }
} ?></dd></div></dl>
    <?php else: ?><p><strong><?= h($user['full_name']) ?></strong> · <?= h(
    $user['email'],
) ?></p><p><?= $action === 'deactivate'
    ? 'They will lose access on their next request. Unused activation and recovery links will stop working. Their repair and audit records will remain.'
    : 'The old unused link will stop working. This account stays inactive until the new link is used to choose a password.' ?></p><?php endif; ?>
    <form method="post" action="staff.php"><input type="hidden" name="csrf_token" value="<?= h(
        csrfToken(),
    ) ?>"><input type="hidden" name="form_key" value="<?= h(
    $formKey,
) ?>"><div class="staff-actions"><button class="secondary-btn" name="intent" value="edit">Go back</button><button class="logout-btn" name="intent" value="confirm"><?= $action ===
'deactivate'
    ? 'Deactivate account'
    : ($action === 'invite'
        ? 'Generate replacement link'
        : ($editingId
            ? 'Save staff changes'
            : 'Create staff account')) ?></button></div></form>
<?php else: ?>
    <div class="staff-heading"><h2><?= $editingId
        ? 'Edit staff member'
        : 'Add staff member' ?></h2><?php if (
    $editingId
): ?><a href="staff.php">Add someone else</a><?php endif; ?></div>
    <p>Staff roles do not grant administrator access. Each staff member has a separate sign-in and a console limited to their role and assigned branches.</p>
    <?php if (
        !$branches
    ): ?><p role="alert">You need an assigned active branch before adding staff.</p><?php else: ?>
    <form class="staff-form" action="staff.php" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(
            csrfToken(),
        ) ?>"><input type="hidden" name="form_key" value="<?= h($formKey) ?>">
        <label for="full_name">Full name<input id="full_name" name="full_name" autocomplete="name" maxlength="160" required value="<?= h(
            $values['full_name'],
        ) ?>"<?= isset($errors['full_name'])
    ? ' aria-invalid="true" aria-describedby="full_name-error"'
    : '' ?>><?php if (
    isset($errors['full_name'])
): ?><span id="full_name-error" class="field-error"><?= h(
    $errors['full_name'],
) ?></span><?php endif; ?></label>
        <label for="email">Email address<input id="email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= h(
            $values['email'],
        ) ?>"<?= isset($errors['email'])
    ? ' aria-invalid="true" aria-describedby="email-error"'
    : '' ?>><?php if (isset($errors['email'])): ?><span id="email-error" class="field-error"><?= h(
    $errors['email'],
) ?></span><?php endif; ?></label>
        <label for="role">Staff role<select id="role" name="role" required<?= isset($errors['role'])
            ? ' aria-invalid="true" aria-describedby="role-error"'
            : '' ?>><option value="">Choose a role</option><?php foreach (
    staffRoles()
    as $role => $label
): ?><option value="<?= h($role) ?>"<?= $values['role'] === $role ? ' selected' : '' ?>><?= h(
    $label,
) ?></option><?php endforeach; ?></select><?php if (
    isset($errors['role'])
): ?><span id="role-error" class="field-error"><?= h(
    $errors['role'],
) ?></span><?php endif; ?></label>
        <fieldset class="staff-wide" id="branches" tabindex="-1"><legend>Branch assignments</legend><p>Select the branches this person works in, then choose one home branch.</p><?php if (
            isset($errors['branches'])
        ): ?><p class="field-error"><?= h(
    $errors['branches'],
) ?></p><?php endif; ?><div class="branch-options"><?php foreach (
    $branches
    as $branch
): ?><div class="branch-option"><label><input type="checkbox" name="branches[]" value="<?= (int) $branch[
    'branch_id'
] ?>"<?= in_array((int) $branch['branch_id'], $values['branches'], true) ? ' checked' : '' ?>><?= h(
    $branch['branch_name'],
) ?></label><label><input type="radio" name="default_branch" value="<?= (int) $branch[
    'branch_id'
] ?>"<?= $values['default_branch'] === (string) $branch['branch_id']
    ? ' checked'
    : '' ?>>Home branch<span class="sr-only">: <?= h(
    $branch['branch_name'],
) ?></span></label></div><?php endforeach; ?></div><div id="default_branch" tabindex="-1"><?php if (
    isset($errors['default_branch'])
): ?><p class="field-error"><?= h($errors['default_branch']) ?></p><?php endif; ?></div></fieldset>
        <div class="staff-actions staff-wide"><button class="logout-btn" name="intent" value="review">Review staff details</button><?php if (
            $editingId
        ): ?><a class="secondary-btn" href="staff.php">Cancel editing</a><?php endif; ?></div>
    </form>
    <?php endif; ?>
    <?php if (
        $editingId
    ): ?><form class="access-action" method="post" action="staff.php"><input type="hidden" name="csrf_token" value="<?= h(
    csrfToken(),
) ?>"><input type="hidden" name="form_key" value="<?= h(
    $formKey,
) ?>"><input type="hidden" name="action" value="<?= (int) $user['is_active']
    ? 'deactivate'
    : 'invite' ?>"><h3>Account access</h3><p><?= (int) $user['is_active']
    ? 'This account is active. Deactivate it when the person should no longer have access.'
    : 'This account is inactive. Generate an activation link to let this staff member set a password and activate it.' ?></p><button class="secondary-btn" name="intent" value="review_access"><?= (int) $user[
    'is_active'
]
    ? 'Review deactivation'
    : 'Generate activation link' ?></button></form><?php endif; ?>
<?php endif; ?>
</section>
<section class="dashboard-card staff-directory"><h2>Staff directory</h2><p>Only non-admin accounts whose branches you fully manage appear here. Shared accounts spanning other administrators’ branches need an administrator assigned to all of those branches.</p>
<form class="staff-filters" method="get" action="staff.php"><label for="staff-search">Search name or email<input id="staff-search" name="q" maxlength="190" value="<?= h(
    requestText($_GET, 'q'),
) ?>"></label><label for="filter-role">Role<select id="filter-role" name="role"><option value="">All staff roles</option><?php foreach (
    staffRoles()
    as $role => $label
): ?><option value="<?= h($role) ?>"<?= requestText($_GET, 'role') === $role
    ? ' selected'
    : '' ?>><?= h(
    $label,
) ?></option><?php endforeach; ?></select></label><label for="filter-status">Access<select id="filter-status" name="status"><option value="">All accounts</option><option value="active"<?= requestText(
    $_GET,
    'status',
) === 'active'
    ? ' selected'
    : '' ?>>Active</option><option value="inactive"<?= requestText($_GET, 'status') === 'inactive'
    ? ' selected'
    : '' ?>>Inactive / pending</option></select></label><button class="secondary-btn">Filter staff</button><a href="staff.php">Clear filters</a></form>
<div class="staff-table-wrap"><table class="staff-table"><caption>Showing <?= $list[
    'from'
] ?>–<?= $list['to'] ?> of <?= $list[
     'total'
 ] ?> staff</caption><thead><tr><th scope="col">Staff member</th><th scope="col">Role</th><th scope="col">Branches</th><th scope="col">Access</th><th scope="col">Action</th></tr></thead><tbody><?php
 foreach ($list['rows'] as $row): ?><tr><td><strong><?= h(
    $row['full_name'],
) ?></strong><br><span><?= h($row['email']) ?></span></td><td><?= h(
    staffRoles()[$row['role']] ?? $row['role'],
) ?></td><td><?= h($row['branch_codes']) ?></td><td><?= $row['is_active']
    ? 'Active'
    : ($row['has_pending_link']
        ? 'Pending activation'
        : 'Inactive / link expired') ?></td><td><a href="staff.php?id=<?= (int) $row[
    'user_id'
] ?>">Manage<span class="sr-only"> <?= h($row['full_name']) ?></span></a></td></tr><?php endforeach;
 if (
     !$list['rows']
 ): ?><tr><td colspan="5">No staff match these filters. Add a staff member above or clear your search.</td></tr><?php endif;
 ?></tbody></table></div>
<nav class="staff-actions" aria-label="Staff pages"><?php foreach (
    ['Previous' => $list['page'] - 1, 'Next' => $list['page'] + 1]
    as $label => $number
):
    if ($number < 1 || $number > $list['pages']) {
        continue;
    } ?><a class="secondary-btn" href="?<?= h(
    http_build_query([
        'q' => requestText($_GET, 'q'),
        'role' => requestText($_GET, 'role'),
        'status' => requestText($_GET, 'status'),
        'page' => $number,
    ]),
) ?>"><?= h($label) ?></a><?php
endforeach; ?></nav>
</section></main><script src="../js/staff.js"></script></body></html>
