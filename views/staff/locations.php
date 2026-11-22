<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Storage locations | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/staff.css">
</head>
<body class="dashboard-body">
<a class="skip-link" href="#locations-main">Skip to storage locations</a>
<main class="staff-page" id="locations-main" tabindex="-1">
    <header class="staff-heading">
        <div><p class="eyebrow">Administration · Branch readiness</p>
            <h1>Storage locations</h1>
            <p>Add a physical branch bin or workbench so intake staff can issue a repair into a known location.</p>
        </div>
        <a class="secondary-btn" href="<?= $user['role'] === 'ADMIN' ? 'dashboard.php' : 'staff_home.php' ?>">Back to workspace</a>
    </header>
    <?php if ($notice): ?><p role="status" class="staff-notice"><?= h($notice) ?></p><?php endif; ?>
    <?php if ($errors): ?><section class="dashboard-card" role="alert"><h2>Check this location</h2>
    <?php foreach ($errors as $message): ?><p class="field-error"><?= h($message) ?></p><?php endforeach; ?>
    </section><?php endif; ?>
    <section class="dashboard-card staff-editor" aria-labelledby="location-heading">
        <h2 id="location-heading"><?= $review ? 'Review before creating' : 'Add a location' ?></h2>
        <p>Locations are restricted to your active assigned branches. This does not create a branch or change repair ownership.</p>
        <?php if (!$branches): ?>
            <p role="alert">No active assigned branch is available. Ask an administrator with branch access to review your assignments.</p>
        <?php elseif ($review): ?>
            <dl class="staff-review">
                <div><dt>Branch</dt><dd><?php foreach ($branches as $branch) {
                    if ((string) $branch['branch_id'] === $values['branch_id']) {
                        echo h($branch['branch_name']);
                    }
                } ?></dd></div>
                <div><dt>Code</dt><dd><?= h($values['location_code']) ?></dd></div>
                <div><dt>Name</dt><dd><?= h($values['location_name']) ?></dd></div>
                <div><dt>Type</dt><dd><?= h($values['location_type'] === 'WORKBENCH' ? 'Workbench' : 'Branch bin') ?></dd></div>
            </dl>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                <div class="staff-actions">
                    <button name="intent" value="edit" class="secondary-btn">Edit details</button>
                    <button name="intent" value="confirm" class="logout-btn">Create storage location</button>
                </div>
            </form>
        <?php else: ?>
            <form method="post" class="staff-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                <label>Assigned branch
                    <select name="branch_id" required>
                        <option value="">Choose a branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['branch_id'] ?>" <?= $values['branch_id'] === (string) $branch['branch_id'] ? 'selected' : '' ?>><?= h($branch['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Location code
                    <input name="location_code" required minlength="2" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9-]{1,39}" value="<?= h($values['location_code']) ?>" placeholder="SERVICE-BENCH-01">
                </label>
                <label>Location name
                    <input name="location_name" required minlength="2" maxlength="120" value="<?= h($values['location_name']) ?>" placeholder="Diagnostics workbench">
                </label>
                <label>Physical location type
                    <select name="location_type">
                        <option value="BRANCH_BIN" <?= $values['location_type'] === 'BRANCH_BIN' ? 'selected' : '' ?>>Branch bin</option>
                        <option value="WORKBENCH" <?= $values['location_type'] === 'WORKBENCH' ? 'selected' : '' ?>>Workbench</option>
                    </select>
                </label>
                <div class="staff-actions staff-wide"><button name="intent" value="review" class="logout-btn">Review location</button></div>
            </form>
        <?php endif; ?>
    </section>
    <section class="dashboard-card staff-directory" aria-labelledby="location-list">
        <h2 id="location-list">Existing assigned-branch locations</h2>
        <p>Only your branches are listed. Inactive locations cannot be chosen for new issuance.</p>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead><tr><th scope="col">Branch</th><th scope="col">Code</th><th scope="col">Location</th><th scope="col">Type</th><th scope="col">State</th></tr></thead>
                <tbody>
                <?php foreach ($list['rows'] as $location): ?>
                <tr>
                    <td><?= h($location['branch_name']) ?></td>
                    <td><?= h($location['location_code']) ?></td>
                    <td><?= h($location['location_name']) ?></td>
                    <td><?= h($location['location_type'] === 'WORKBENCH' ? 'Workbench' : str_replace('_', ' ', $location['location_type'])) ?></td>
                    <td><?= $location['is_active'] ? 'Active' : 'Inactive' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$list['rows']): ?><tr><td colspan="5">No locations have been recorded for your assigned branches.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <nav class="staff-actions" aria-label="Location pages">
            <span>Page <?= (int) $list['page'] ?> of <?= (int) $list['pages'] ?> · <?= (int) $list['total'] ?> locations</span>
            <?php if ($list['page'] > 1): ?><a class="secondary-btn" href="?page=<?= (int) $list['page'] - 1 ?>">Previous</a><?php endif; ?>
            <?php if ($list['page'] < $list['pages']): ?><a class="secondary-btn" href="?page=<?= (int) $list['page'] + 1 ?>">Next</a><?php endif; ?>
        </nav>
    </section>
</main>
</body>
</html>
