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

$view = consoleView($user, $_GET);
$_GET['view'] = $view;

if ($view === 'overview') {
    [$title, $description] = match ($role) {
        'ADMIN' => ['Operations overview', 'Review repair activity and the work that needs attention.'],
        'MANAGER' => ['Branch overview', 'Monitor active repairs, saved intake and branch work queues.'],
        'INTAKE_AGENT' => ['Intake overview', 'Start new intake and continue saved drafts.'],
        'TECHNICIAN' => ['My repair bench', 'Review the devices assigned to you and your current workload.'],
        'COURIER' => ['Transfer overview', 'Review handovers that are still in transit or need an exception check.'],
        'CASHIER' => ['Cash desk overview', 'Find issued bills and continue counter-cash work.'],
        'VIEWER' => ['Branch snapshot', 'Review repair progress in your assigned branches.'],
    };
}

$notice = $_SESSION['intake_saved'] ?? false;
unset($_SESSION['intake_saved']);
if ($notice && !isset($_GET['view'])) {
    $_GET['view'] = 'drafts';
}

$returnQuery = workspaceReturnQuery($user, http_build_query($_GET));
$isCourier = $role === 'COURIER';
$canIntake = in_array($role, ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true);
$canCash = in_array($role, ['ADMIN', 'MANAGER', 'CASHIER'], true);

try {
    $pdo = getPDO();
    $branches = intakeBranches($pdo, (int) $user['user_id']);
    $data = null;
    $trend = [];
    $overviewDrafts = null;
    $overviewCash = null;
    $overviewTransfers = null;
    $overviewTransferHistory = null;

    if ($view === 'overview') {
        if ($isCourier) {
            $overviewTransfers = consoleTransfers($pdo, $user, ['view' => 'queue', 'page' => '1']);
            $overviewTransferHistory = consoleTransfers($pdo, $user, ['view' => 'history', 'page' => '1']);
            $page = $overviewTransfers;
        } elseif ($role === 'CASHIER') {
            $overviewCash = consoleCashDesk($pdo, $user, ['page' => '1']);
            $page = $overviewCash;
        } else {
            $data = consoleDevices($pdo, $user, ['view' => 'queue', 'page' => '1']);
            $page = $data['page'];

            if ($canIntake) {
                $overviewDrafts = consoleDrafts($pdo, (int) $user['user_id'], ['page' => '1']);
            }
            if (in_array($role, ['ADMIN', 'MANAGER'], true)) {
                $overviewCash = consoleCashDesk($pdo, $user, ['page' => '1']);
            }
        }
    } elseif ($view === 'cash') {
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

    $activeLoad = $role === 'TECHNICIAN'
        ? technicianActiveCount($pdo, (int) $user['user_id'])
        : null;
} catch (Throwable $error) {
    error_log('RepairTrack console: ' . $error->getMessage());
    unavailablePage(
        'Your workspace is temporarily unavailable. Ask your administrator to check the staff workspace migration.',
    );
}

require __DIR__ . '/../views/console/home.php';
