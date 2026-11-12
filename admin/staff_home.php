<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/staff/repository.php';
require_once __DIR__ . '/../app/console/repository.php';
startAppSession();
$user = requireAccount();
header('Cache-Control: no-store, private');
$role = $user['role'];
$descriptions = [
    'ADMIN' => ['Repair operations', 'Assign technicians and inspect branch repair records.'],
    'MANAGER' => [
        'Manager console',
        'Monitor your branches, distribute repair work and prepare intake.',
    ],
    'INTAKE_AGENT' => [
        'Intake console',
        'Register devices, reopen drafts and complete intake checks.',
    ],
    'TECHNICIAN' => [
        'Technician console',
        'Work on your assigned devices and record repair progress.',
    ],
    'COURIER' => ['Courier console', 'Check transfer manifests for your assigned branches.'],
    'VIEWER' => ['Viewer console', 'Read repair status for your assigned branches.'],
];
[$title, $description] = $descriptions[$role];
try {
    $pdo = getPDO();
    $branches = intakeBranches($pdo, (int) $user['user_id']);
    $isCourier = $role === 'COURIER';
    $data = $isCourier ? null : consoleDevices($pdo, $user, $_GET);
    $page = $isCourier ? consoleTransfers($pdo, $user, $_GET) : $data['page'];
    $canIntake = in_array($role, ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true);
    $drafts = $canIntake ? consoleDrafts($pdo, (int) $user['user_id'], $_GET) : null;
} catch (Throwable $error) {
    error_log('RepairTrack console: ' . $error->getMessage());
    unavailablePage('Your workspace is temporarily unavailable. Please try again.');
}
$notice = $_SESSION['intake_saved'] ?? false;
unset($_SESSION['intake_saved']);
require __DIR__ . '/../views/console/home.php';
