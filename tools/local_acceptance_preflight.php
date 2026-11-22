<?php
declare(strict_types=1);

/**
 * Read-only checks for an isolated RepairTrack Windows/XAMPP or Linux test copy.
 * This does not connect to MySQL, inspect secret values, change config, or run migrations.
 * A PASS here is NOT a server security assessment or permission to deploy.
 */
function acceptanceCanonicalDirectory(?string $path): ?string
{
    if ($path === null || trim($path) === '') {
        return null;
    }
    $resolved = realpath($path);
    if ($resolved === false || !is_dir($resolved)) {
        return null;
    }
    $normalized = strtolower(str_replace('\\', '/', $resolved));
    return rtrim($normalized, '/') ?: '/';
}

function acceptanceIsInside(string $candidate, string $root): bool
{
    return $candidate === $root || str_starts_with($candidate, rtrim($root, '/') . '/');
}

function acceptancePreflight(array $config, string $projectPath, ?string $documentRoot): array
{
    $checks = [];
    $record = static function (string $level, string $summary) use (&$checks): void {
        $checks[] = ['level' => $level, 'summary' => $summary];
    };
    $record(PHP_VERSION_ID >= 80100 ? 'PASS' : 'BLOCK',
        'PHP 8.1 or later is required by the application.');
    foreach (['pdo_mysql', 'mbstring', 'fileinfo'] as $extension) {
        $record(extension_loaded($extension) ? 'PASS' : 'BLOCK',
            'Required PHP extension: ' . $extension);
    }

    $database = $config['database'] ?? null;
    $record(is_string($database) &&
        preg_match('/^repairtrack_test_[a-zA-Z0-9_]+$/D', $database) === 1 ? 'PASS' : 'BLOCK',
        'Use a newly created repairtrack_test_* database, never a real shop database.');

    $url = $config['app_url'] ?? null;
    $parsed = is_string($url) ? parse_url($url) : false;
    $safeLocalUrl = is_array($parsed) &&
        in_array(strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true) &&
        in_array(strtolower((string) ($parsed['host'] ?? '')), ['localhost', '127.0.0.1'], true) &&
        empty($parsed['user']) && empty($parsed['pass']) &&
        empty($parsed['query']) && empty($parsed['fragment']);
    $record($safeLocalUrl ? 'PASS' : 'BLOCK',
        'Acceptance app_url must use local HTTP(S) and a loopback host.');

    $hmacKey = $config['intake_hmac_key'] ?? null;
    $record(is_string($hmacKey) && preg_match('/^[a-fA-F0-9]{64}$/D', $hmacKey) === 1
        ? 'PASS' : 'BLOCK', 'Provide a separate 32-byte hexadecimal HMAC key for fictional intake.');

    $project = acceptanceCanonicalDirectory($projectPath);
    $docroot = acceptanceCanonicalDirectory($documentRoot);
    $record($project === null ? 'BLOCK' : 'PASS', 'Application directory is readable.');
    if ($documentRoot === null || trim($documentRoot) === '') {
        $record('WARN', 'Document root was not provided; HTTP boundary is not assessed.');
    } elseif ($docroot === null) {
        $record('BLOCK', 'Configured document root is not an existing directory.');
    } elseif ($project !== null && acceptanceIsInside($project, $docroot)) {
        $record('WARN', 'Internal application folders sit inside the document root; staging deny rules need separate verification.');
    } else {
        $record('PASS', 'Application folder is outside the supplied document root.');
    }

    $paths = [];
    foreach ([
        'intake_storage_path' => 'Intake evidence',
        'inventory_evidence_path' => 'Inventory evidence',
    ] as $key => $label) {
        $raw = $config[$key] ?? null;
        $location = is_string($raw) ? acceptanceCanonicalDirectory($raw) : null;
        $paths[$key] = $location;
        if ($location === null) {
            $record('BLOCK', $label . ' directory does not exist.');
            continue;
        }
        if (!is_writable($location)) {
            $record('BLOCK', $label . ' directory is not writable by this PHP CLI account.');
        } elseif (($project !== null && acceptanceIsInside($location, $project)) ||
            ($docroot !== null && acceptanceIsInside($location, $docroot))) {
            $record('BLOCK', $label . ' directory must be outside both application and document root.');
        } else {
            $record('PASS', $label . ' exists outside the known public folders and is CLI-writable.');
        }
    }
    if ($paths['intake_storage_path'] !== null &&
        $paths['intake_storage_path'] === $paths['inventory_evidence_path']) {
        $record('BLOCK', 'Intake and inventory evidence must use different private directories.');
    }

    // Deliberately omit config values, usernames, passwords, secret hashes and absolute paths.
    return $checks;
}

function acceptanceResult(array $checks): int
{
    if (in_array('BLOCK', array_column($checks, 'level'), true)) {
        return 2;
    }
    return in_array('WARN', array_column($checks, 'level'), true) ? 3 : 0;
}

// Expose the pure checks to the disposable test harness without running the CLI.
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) {
    return;
}
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
if ($argc === 2 && $argv[1] === '--help') {
    echo "php tools/local_acceptance_preflight.php\n";
    echo "Set REPAIRTRACK_DOCUMENT_ROOT to the local XAMPP/Apache document root.\n";
    echo "Read-only; supports ONLY fictional repairtrack_test_* local installations.\n";
    echo "Exit 0 = all local checks passed, 2 = blocked, 3 = warning/host review needed.\n";
    exit(0);
}
if ($argc !== 1) {
    fwrite(STDERR, "No other arguments are supported. Use --help.\n");
    exit(2);
}
$configPath = __DIR__ . '/../config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "BLOCK: Local config/config.php is missing. Copy the example in a disposable test copy.\n");
    exit(2);
}
try {
    $config = require $configPath;
    if (!is_array($config)) {
        throw new RuntimeException('Configuration did not return an array.');
    }
    $checks = acceptancePreflight(
        $config,
        dirname(__DIR__),
        getenv('REPAIRTRACK_DOCUMENT_ROOT') ?: null,
    );
    foreach ($checks as $check) {
        echo $check['level'] . ': ' . $check['summary'] . "\n";
    }
    $result = acceptanceResult($checks);
    echo $result === 0
        ? "Local prerequisites checked. Run SQL status, HTTP privacy checks, restore and human UAT separately.\n"
        : "Release acceptance is NOT complete. Resolve blocking or warning checks and obtain host verification.\n";
    exit($result);
} catch (Throwable $error) {
    fwrite(STDERR, "BLOCK: Could not inspect local prerequisites; no configuration values were printed.\n");
    exit(2);
}
