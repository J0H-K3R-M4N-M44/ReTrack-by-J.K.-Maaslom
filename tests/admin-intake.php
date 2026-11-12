<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/dashboard_data.php';
require_once __DIR__ . '/../app/intake/rules.php';
require_once __DIR__ . '/../app/intake/repository.php';
require_once __DIR__ . '/../app/intake/drafts.php';
require_once __DIR__ . '/../app/password_reset.php';

// Run against a fresh fixture database only. This suite deliberately writes data.
$pdo = getPDO();
if (
    !str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'repairtrack_test_')
) {
    throw new RuntimeException('Tests require a disposable repairtrack_test_ database.');
}
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function rejected(callable $action, string $message): void
{
    try {
        $action();
    } catch (Throwable $error) {
        return;
    }
    throw new RuntimeException($message);
}
startAppSession();
check(loginAdmin('admin@example.test', 'DemoRepair2026!'), 'Seeded admin signs in');
check(!loginAdmin('unknown@example.test', 'DemoRepair2026!'), 'Unknown email rejected');
check(!loginAdmin('admin@example.test', 'wrong'), 'Wrong password rejected');
$pdo->exec('UPDATE users SET is_active = 0 WHERE user_id = 1');
try {
    check(!loginAdmin('admin@example.test', 'DemoRepair2026!'), 'Inactive admin rejected');
} finally {
    $pdo->exec('UPDATE users SET is_active = 1 WHERE user_id = 1');
}
$pdo->exec("UPDATE users SET role = 'TECHNICIAN' WHERE user_id = 1");
try {
    check(!loginAdmin('admin@example.test', 'DemoRepair2026!'), 'Non-admin rejected');
} finally {
    $pdo->exec("UPDATE users SET role = 'ADMIN' WHERE user_id = 1");
}
$data = loadDashboardData($pdo, ['customers_page' => '2']);
check($data['pages']['customers']['total'] === 30, 'Count all customers');
check(
    count($data['customers']) === 12 && $data['pages']['customers']['from'] === 13,
    'Second customer page',
);
check(
    loadDashboardData($pdo, ['q' => '%'])['pages']['customers']['total'] === 0,
    'Literal wildcard search',
);
$branches = intakeBranches($pdo, 1);
$values = array_replace(intakeDefaults($branches), [
    'customer_name' => 'Regression %_ Customer',
    'contact_value' => '0917-123-4567',
    'device_type' => 'Laptop',
    'make' => 'Test',
    'model' => 'Model',
    'reported_symptoms' => 'No power',
    'condition_notes' => 'No visible damage',
]);
check(intakeValidate($pdo, $values, $branches) === [], 'Valid draft accepted');
check(
    !intakePhoneIsValid('not a phone') && intakePhoneIsValid('+63 917 123 4567'),
    'Phone validation',
);
check(
    intakeValidate($pdo, array_replace($values, ['intake_branch_id' => '9999']), $branches) !== [],
    'Invalid branch rejected',
);
$count = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$pdo->exec(
    "CREATE TRIGGER test_reject_device BEFORE INSERT ON ticket_devices FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected failure'",
);
try {
    rejected(fn() => intakeSaveDraft($pdo, $values, 1), 'Injected failure must reject save');
} finally {
    $pdo->exec('DROP TRIGGER test_reject_device');
}
check(
    (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn() === $count,
    'Customer insert rolled back',
);
$id = intakeSaveDraft($pdo, $values, 1);
check(count(intakeSearchCustomers($pdo, 'Regression %_')) === 1, 'Literal customer lookup');
check(count(intakeSearchCustomers($pdo, '0917-123')) > 0, 'Formatted phone lookup');
check(intakeValidate($pdo, $values, $branches) !== [], 'Duplicate customer flagged');
$before = intakeLoadDraft($pdo, $id, 1);
$version = intakeDraftVersion($before);
$values['customer_id'] = (string) $before['customer_id'];
$values['condition_notes'] = 'Scratch on lid';
intakeUpdateDraft($pdo, $id, 1, $version, $values);
check(
    intakeLoadDraft($pdo, $id, 1)['condition_notes'] === 'Scratch on lid',
    'Draft edit persisted',
);
check(
    (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn() === $count + 1,
    'Existing customer reused',
);
rejected(fn() => intakeUpdateDraft($pdo, $id, 1, $version, $values), 'Stale edit must fail');
rejected(fn() => intakeLoadDraft($pdo, 2, 1), 'Issued ticket must not be editable');
rejected(fn() => intakeLoadDraft($pdo, $id, 9999), 'Unassigned user must not edit');
check(!passwordIsAcceptable(str_repeat('é', 6)), 'Twelve bytes is not twelve characters');
check(passwordIsAcceptable(str_repeat('é', 12)), 'Twelve Unicode characters accepted');
check(!passwordIsAcceptable(str_repeat('é', 37)), 'Bcrypt byte limit enforced');
$token = bin2hex(random_bytes(32));
$pdo->prepare(
    'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (1, ?, UTC_TIMESTAMP() + INTERVAL 30 MINUTE, UTC_TIMESTAMP())',
)->execute([hash('sha256', $token)]);
check(completeAdminPasswordReset($token, 'DemoRepair2026!'), 'Password reset completes');
check(!completeAdminPasswordReset($token, 'DemoRepair2026!'), 'Reset token is single-use');
$email = bin2hex(random_bytes(8)) . '@example.test';
for ($i = 0; $i < 5; $i++) {
    check(reserveLoginAttempt($email, 'test-' . $i), 'Attempt within limit');
}
check(!reserveLoginAttempt($email, 'another-address'), 'Account attempt limit');
echo "PASS: authentication, paging, search, validation, rollback, draft edit, stale edit, password reset and throttling.\n";
