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
    'CASHIER' => [
        'Cash desk',
        'Record counter cash and complete paid customer pickups in your assigned branches.',
    ],
    'VIEWER' => ['Viewer console', 'Read repair status for your assigned branches.'],
];
[$title, $description] = $descriptions[$role];
$notice = $_SESSION['intake_saved'] ?? false;
unset($_SESSION['intake_saved']);
if ($notice && !isset($_GET['view'])) {
    $_GET['view'] = 'drafts';
}
$view = consoleView($user, $_GET);
$_GET['view'] = $view;
$returnQuery = workspaceReturnQuery($user, http_build_query($_GET));
$isCourier = $role === 'COURIER';
$canIntake = in_array($role, ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true);
$canCash = in_array($role, ['ADMIN', 'MANAGER', 'CASHIER'], true);
try {
    $pdo = getPDO();
    $branches = intakeBranches($pdo, (int) $user['user_id']);
    $data = null;
    $trend = [];
    if ($view === 'cash') {
        $page = consoleCashDesk($pdo, $user, $_GET);
    } elseif ($view === 'drafts') {
        $page = consoleDrafts($pdo, (int) $user['user_id'], $_GET);
    } elseif ($isCourier) {
        $page = consoleTransfers($pdo, $user, $_GET);
    } else {
        $data = consoleDevices($pdo, $user, $_GET);
        $page = $data['page'];
        $trend = consoleStartTrend($pdo, $user);
    }
    $activeLoad =
        $role === 'TECHNICIAN' ? technicianActiveCount($pdo, (int) $user['user_id']) : null;
} catch (Throwable $error) {
    error_log('RepairTrack console: ' . $error->getMessage());
    unavailablePage(
        'Your workspace is temporarily unavailable. Ask your administrator to check the staff workspace migration.',
    );
}
require __DIR__ . '/../views/console/home.php';
