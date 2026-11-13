<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/console/repository.php';
require_once __DIR__ . '/../app/intake/evidence.php';
$pdo = getPDO();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'repairtrack_demo') {
    throw new RuntimeException('Use the disposable demo database.');
}
function requireDemo(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
foreach (
    ['customers' => 150, 'repair_tickets' => 150, 'ticket_devices' => 151, 'users' => 17]
    as $table => $count
) {
    requireDemo(
        (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === $count,
        'Count: ' . $table,
    );
}
startAppSession();
$accounts = $pdo
    ->query('SELECT user_id,email,role,is_active FROM users ORDER BY user_id')
    ->fetchAll();
foreach ($accounts as $account) {
    requireDemo(
        loginAccount($account['email'], 'DemoRepair2026!') === (bool) $account['is_active'],
        'Login state: ' . $account['email'],
    );
    if (!$account['is_active']) {
        continue;
    }
    if ($account['role'] === 'COURIER') {
        requireDemo(consoleTransfers($pdo, $account, [])['total'] > 0, 'Courier has a manifest');
    } else {
        requireDemo(
            consoleDevices($pdo, $account, [])['page']['total'] > 0,
            'Role has visible work',
        );
    }
}
$global = loadDashboardData($pdo, [], 1);
$central = loadDashboardData($pdo, [], 9);
requireDemo(
    $central['pages']['queue']['total'] < $global['pages']['queue']['total'],
    'Branch isolation',
);
requireDemo($global['pages']['queue']['pages'] > 1, 'Queue pagination');
requireDemo(
    (int) $pdo
        ->query("SELECT COUNT(*) FROM repair_tickets WHERE record_stage='DRAFT'")
        ->fetchColumn() === 27,
    '27 draft scenarios',
);
requireDemo(
    (int) $pdo
        ->query(
            "SELECT COUNT(*) FROM ticket_devices WHERE repair_status='DIAGNOSING' AND ticket_device_id > 31
    AND EXISTS(SELECT 1 FROM repair_quotes q WHERE q.ticket_device_id=ticket_devices.ticket_device_id AND q.quote_status='APPROVED')",
        )
        ->fetchColumn() > 0,
    'Approved diagnosis example',
);
requireDemo(
    (int) $pdo
        ->query(
            "SELECT COUNT(*) FROM ticket_devices WHERE repair_status='DIAGNOSING' AND ticket_device_id > 31
    AND EXISTS(SELECT 1 FROM repair_quotes q WHERE q.ticket_device_id=ticket_devices.ticket_device_id AND q.quote_status='AWAITING_CUSTOMER')",
        )
        ->fetchColumn() > 0,
    'Unapproved diagnosis example',
);
foreach (
    $pdo
        ->query('SELECT DISTINCT stored_path,byte_size,sha256_hash FROM ticket_device_photos')
        ->fetchAll()
    as $photo
) {
    $image = intakeInspectImage(intakeEvidencePath($photo['stored_path']));
    requireDemo(
        $image['bytes'] === (int) $photo['byte_size'] && $image['hash'] === $photo['sha256_hash'],
        'Evidence metadata matches bytes',
    );
}
file_put_contents(
    __DIR__ . '/demo-seed-fixture.json',
    json_encode([
        'ticket_id' => (int) $pdo
            ->query("SELECT ticket_id FROM repair_tickets WHERE ticket_code = 'DEMO-V2-0031'")
            ->fetchColumn(),
    ]),
);
echo "PASS: dataset counts, 17 login states, every role has work, branch isolation, paging, quote scenarios and valid evidence.\n";
