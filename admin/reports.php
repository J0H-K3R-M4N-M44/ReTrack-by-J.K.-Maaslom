<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/console/reports.php';

startAppSession();
$user = requireAccount();
header('Cache-Control: no-store, private');
if (!consoleCanViewReports($user)) {
    unavailablePage('Your role does not have access to staff reports.', 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    unavailablePage('Open Reports from your workspace.', 405);
}

$role = $user['role'];
$definition = staffReportDefinition($role);
$period = staffReportPeriod($_GET);
$branchId = null;
$branches = [];
$report = null;
$problem = null;

try {
    $pdo = getPDO();
    // Read grants, daily totals and the current snapshot from the same database snapshot.
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    $branches = intakeBranches($pdo, (int) $user['user_id']);
    $branchId = staffReportBranch($_GET, $branches);
    if ($branches) {
        $report = loadStaffReport($pdo, $user, $period, $branchId);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $report = null;
    if ($error instanceof DomainException) {
        http_response_code(403);
        $problem = 'branch';
    } else {
        error_log('RepairTrack staff reports: ' . $error->getMessage());
        http_response_code(503);
        $problem = 'unavailable';
    }
}

if ($report !== null) {
    $metrics = $definition['metrics'];
    $primaryMetric = array_key_first($metrics);
    $chart = staffReportChart($report['daily'], $primaryMetric);
    $peak = max(array_column($chart, 'total'));
    $page = staffReportPage($report['daily'], $_GET);
}
require __DIR__ . '/../views/console/reports.php';
