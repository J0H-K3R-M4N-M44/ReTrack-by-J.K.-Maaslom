<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/staff/repository.php';
require_once __DIR__ . '/../app/password_reset.php';
$pdo = getPDO();
if (
    !str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')
) {
    throw new RuntimeException('Use a disposable test database.');
}
function ensure(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function fails(callable $action, string $message): void
{
    try {
        $action();
    } catch (Throwable $error) {
        return;
    }
    throw new RuntimeException($message);
}
startAppSession();
$branches = intakeBranches($pdo, 1);
$branchId = (int) $branches[0]['branch_id'];
$values = staffReadValues([
    'full_name' => 'Test Technician',
    'email' => ' Staff.Test@Example.test ',
    'role' => 'TECHNICIAN',
    'branches' => [(string) $branchId],
    'default_branch' => (string) $branchId,
]);
ensure($values['email'] === 'staff.test@example.test', 'Email normalized');
ensure(staffValidate($pdo, $values, 1) === [], 'Valid staff fields');
foreach (
    [
        'role' => 'ADMIN',
        'branches' => [9999],
        'default_branch' => '9999',
        'email' => 'bad',
        'full_name' => '',
    ]
    as $key => $bad
) {
    ensure(
        staffValidate($pdo, array_replace($values, [$key => $bad]), 1) !== [],
        'Reject invalid ' . $key,
    );
}
// A late audit failure must not leave a user, assignment or token behind.
$pdo->exec(
    "CREATE TRIGGER test_staff_failure BEFORE INSERT ON audit_events FOR EACH ROW BEGIN IF NEW.action = 'STAFF_CREATED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected staff failure'; END IF; END",
);
try {
    fails(fn() => staffCreate($pdo, 1, $values), 'Audit failure must roll back');
} finally {
    $pdo->exec('DROP TRIGGER test_staff_failure');
}
$find = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
$find->execute([$values['email']]);
ensure((int) $find->fetchColumn() === 0, 'User rollback');
$created = staffCreate($pdo, 1, $values);
$id = $created['user_id'];
$token = $created['token'];
ensure(!loginAccount($values['email'], 'DemoRepair2026!'), 'Unactivated staff cannot log in');
$tokenQuery = $pdo->prepare(
    'SELECT token_hash FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL',
);
$tokenQuery->execute([$id]);
ensure($tokenQuery->fetchColumn() === hash('sha256', $token), 'Store only token hash');
ensure(
    !completeAdminPasswordReset($token, 'NewStaffPass2026!'),
    'Activation token cannot reset password',
);
ensure(staffActivate($pdo, $token, 'NewStaffPass2026!'), 'Staff activation');
ensure(!staffActivate($pdo, $token, 'NewStaffPass2026!'), 'Activation single use');
ensure(loginAccount($values['email'], 'NewStaffPass2026!'), 'Activated staff login');
ensure(
    accountHome() === 'staff_home.php' &&
        currentAdmin() === null &&
        currentAccount()['role'] === 'TECHNICIAN',
    'Staff cannot become admin',
);
fails(
    fn() => staffCreate($pdo, $id, array_replace($values, ['email' => 'forbidden@example.test'])),
    'Staff cannot create accounts',
);
fails(fn() => staffCreate($pdo, 1, $values), 'Duplicate email must fail');
fails(fn() => staffLoad($pdo, 1, 1), 'Cannot manage administrator');
$before = staffLoad($pdo, $id, 1);
$updated = array_replace($values, ['full_name' => 'Updated Technician']);
staffUpdate($pdo, 1, $id, staffVersion($before), $updated);
fails(fn() => staffUpdate($pdo, 1, $id, staffVersion($before), $values), 'Stale edit rejected');
$before = staffLoad($pdo, $id, 1);
staffChangeAccess($pdo, 1, $id, staffVersion($before), 'deactivate');
ensure(currentAccount() === null, 'Deactivation invalidates staff session');
$before = staffLoad($pdo, $id, 1);
$replacement = staffChangeAccess($pdo, 1, $id, staffVersion($before), 'invite');
$pdo->prepare(
    'UPDATE password_reset_tokens SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE user_id = ?',
)->execute([$id]);
ensure(!staffActivate($pdo, $replacement, 'NewStaffPass2026!'), 'Expired activation rejected');
$replacement = staffChangeAccess($pdo, 1, $id, staffVersion(staffLoad($pdo, $id, 1)), 'invite');
$next = staffChangeAccess($pdo, 1, $id, staffVersion(staffLoad($pdo, $id, 1)), 'invite');
ensure(
    !staffActivate($pdo, $replacement, 'NewStaffPass2026!') &&
        staffActivate($pdo, $next, 'NewStaffPass2026!'),
    'Replacement invalidates previous link',
);
// A branch-limited admin cannot manage a user shared with an unowned branch.
$pdo->prepare(
    "INSERT INTO users (full_name,email,password_hash,role) VALUES ('Scoped Admin','scoped.admin@example.test',?,'ADMIN')",
)->execute([password_hash('DemoRepair2026!', PASSWORD_DEFAULT)]);
$scopedId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO user_branch_assignments (user_id,branch_id,is_default) VALUES (?,?,1)',
)->execute([$scopedId, $branchId]);
$shared = staffCreate(
    $pdo,
    1,
    array_replace($values, [
        'email' => 'shared.staff@example.test',
        'branches' => array_map('intval', array_column($branches, 'branch_id')),
    ]),
);
fails(
    fn() => staffLoad($pdo, $shared['user_id'], $scopedId),
    'Partial branch ownership must not allow account edits',
);
ensure(
    !in_array(
        $shared['user_id'],
        array_map(
            'intval',
            array_column(staffList($pdo, $scopedId, ['q' => 'shared.staff'])['rows'], 'user_id'),
        ),
        true,
    ),
    'Shared account excluded from scoped list',
);
ensure(
    staffList($pdo, 1, ['q' => '%_notfound'])['total'] === 0,
    'Literal search escapes wildcard characters',
);
// Existing indexes support the roster's filters and bounded page size.
for ($i = 0; $i < 13; $i++) {
    staffCreate(
        $pdo,
        1,
        array_replace($values, [
            'email' => 'paging' . $i . '@example.test',
            'full_name' => 'Paging Staff ' . $i,
        ]),
    );
}
$page = staffList($pdo, 1, ['q' => 'Paging Staff', 'page' => '2']);
ensure(
    $page['total'] === 13 && count($page['rows']) === 1 && $page['from'] === 13,
    'Staff pagination',
);
// Active staff can reset passwords; password changes invalidate earlier sessions.
startAppSession();
ensure(loginAccount($values['email'], 'NewStaffPass2026!'), 'Reactivated staff login');
$reset = bin2hex(random_bytes(32));
$pdo->prepare(
    'INSERT INTO password_reset_tokens (user_id,token_hash,expires_at,created_at) VALUES (?,?,UTC_TIMESTAMP()+INTERVAL 30 MINUTE,UTC_TIMESTAMP())',
)->execute([$id, hash('sha256', $reset)]);
ensure(!staffActivate($pdo, $reset, 'ChangedStaff2026!'), 'Reset token cannot activate an account');
ensure(completeAdminPasswordReset($reset, 'ChangedStaff2026!'), 'Staff password reset works');
ensure(currentAccount() === null, 'Password reset invalidates earlier session');
echo "PASS: staff validation, rollback, activation, token separation/reuse/expiry, permissions, stale edits, disable, scoped listing, paging and staff recovery.\n";
