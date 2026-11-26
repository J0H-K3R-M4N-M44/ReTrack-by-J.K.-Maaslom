<?php
declare(strict_types=1);

// Run from Task Scheduler/cron or Git Bash. Never expose this worker to HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/notifications.php';

try {
    $configFile = __DIR__ . '/../config/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Local configuration is missing.');
    }
    $config = require $configFile;
    $pdo = getPDO();
    $limit = 10;
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--limit=(\d{1,2})$/', $argument, $match)) {
            $limit = max(1, min(20, (int) $match[1]));
        } else {
            throw new RuntimeException('Use --limit=1 through --limit=20.');
        }
    }

    $result = notificationSendDue($pdo, $config, $limit);
    echo 'EmailJS customer emails sent: ' . $result['sent'] .
        '; failed/retried: ' . $result['failed'] . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Notification worker stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
