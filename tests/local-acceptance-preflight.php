<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../tools/local_acceptance_preflight.php';

function confirm(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function resultFor(array $config, string $project, ?string $web): array
{
    return acceptancePreflight($config, $project, $web);
}
function containsState(array $checks, string $state, string $fragment): bool
{
    foreach ($checks as $check) {
        if ($check['level'] === $state && str_contains($check['summary'], $fragment)) {
            return true;
        }
    }
    return false;
}
$root = sys_get_temp_dir() . '/repairtrack-test-preflight-' . bin2hex(random_bytes(8));
$project = $root . '/application';
$web = $root . '/htdocs';
$intake = $root . '/private-intake';
$inventory = $root . '/private-inventory';
foreach ([$project, $web, $intake, $inventory] as $dir) {
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Test fixture could not create directory');
    }
}
try {
    $valid = [
        'host' => '127.0.0.1',
        'database' => 'repairtrack_test_candidate',
        'app_url' => 'http://127.0.0.1/repairtrack',
        'intake_hmac_key' => str_repeat('ab', 32),
        'intake_storage_path' => $intake,
        'inventory_evidence_path' => $inventory,
    ];
    $ok = resultFor($valid, $project, $web);
    confirm(acceptanceResult($ok) === 0, 'Valid isolated local setup not accepted');
    confirm(!containsState($ok, 'BLOCK', 'private'), 'Unexpected private file block');

    $badDb = resultFor(array_replace($valid, ['database' => 'repairtrack']), $project, $web);
    confirm(acceptanceResult($badDb) === 2 && containsState($badDb, 'BLOCK', 'database'),
        'Real database must always be blocked');

    $badUrl = resultFor(array_replace($valid, ['app_url' => 'https://repairtrackpilot.infinityfree.me']),
        $project, $web);
    confirm(acceptanceResult($badUrl) === 2 &&
        containsState($badUrl, 'BLOCK', 'loopback host'), 'Live-host preflight must be blocked');

    $badKey = resultFor(array_replace($valid, ['intake_hmac_key' => 'wrong']),
        $project, $web);
    confirm(acceptanceResult($badKey) === 2 &&
        containsState($badKey, 'BLOCK', 'HMAC'), 'Missing fictional HMAC key should block');

    $overlap = resultFor(array_replace($valid, ['inventory_evidence_path' => $intake]),
        $project, $web);
    confirm(acceptanceResult($overlap) === 2 &&
        containsState($overlap, 'BLOCK', 'different private directories'),
        'Evidence directory overlap accepted');

    $public = resultFor(array_replace($valid, ['intake_storage_path' => $web]), $project, $web);
    confirm(acceptanceResult($public) === 2 &&
        containsState($public, 'BLOCK', 'Intake evidence'), 'Evidence under public web root accepted');

    $inside = $project . '/uploaded';
    mkdir($inside, 0700);
    $projectStorage = resultFor(array_replace($valid, ['inventory_evidence_path' => $inside]),
        $project, $web);
    confirm(acceptanceResult($projectStorage) === 2 &&
        containsState($projectStorage, 'BLOCK', 'Inventory evidence'),
        'Evidence under project root accepted');

    $unknownRoot = resultFor($valid, $project, null);
    confirm(acceptanceResult($unknownRoot) === 3 &&
        containsState($unknownRoot, 'WARN', 'Document root'),
        'Unknown document root should require review');

    $webApp = resultFor($valid, $project, $root);
    confirm(acceptanceResult($webApp) === 3 &&
        containsState($webApp, 'WARN', 'Internal application folders'),
        'Application inside docroot warning missing');

    $invalidRoot = resultFor($valid, $project, $root . '/missing');
    confirm(acceptanceResult($invalidRoot) === 2 &&
        containsState($invalidRoot, 'BLOCK', 'document root'), 'Non-existent docroot not blocked');

    confirm(!str_contains(json_encode($ok, JSON_THROW_ON_ERROR), $root),
        'Preflight output reveals absolute temporary paths');
    confirm(!str_contains(json_encode($ok, JSON_THROW_ON_ERROR), $valid['intake_hmac_key']),
        'Preflight output reveals secrets');

    echo "PASS: acceptance preflight valid/invalid local, URL, DB, storage, docroot and redaction.\n";
} finally {
    foreach ([$project . '/uploaded', $project, $web, $intake, $inventory] as $dir) {
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
    rmdir($root);
}
