<?php
declare(strict_types=1);
// Preview orphaned uploads first: php tools/cleanup_intake_uploads.php
// Delete only that category: php tools/cleanup_intake_uploads.php --apply
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/intake/evidence.php';

try {
    $directory = intakePrivateDirectory();
    $pdo = getPDO();
    $apply = in_array('--apply', $argv, true);
    $references = $pdo->prepare('SELECT 1 FROM repair_tickets WHERE terms_signature_path = ?
        UNION ALL SELECT 1 FROM ticket_device_photos WHERE stored_path = ?
        UNION ALL SELECT 1 FROM ticket_device_acknowledgments WHERE signature_path = ? LIMIT 1');
    $count = 0;
    foreach (new DirectoryIterator($directory) as $file) {
        $name = $file->getFilename();
        if (
            !$file->isFile() ||
            $file->isLink() ||
            !preg_match('/^[a-f0-9]{48}\.(jpg|png)$/', $name) ||
            $file->getMTime() >= time() - 86400
        ) {
            continue;
        }
        // Reviews expire in 30 minutes. Keep a full-day margin before cleanup.
        $references->execute([$name, $name, $name]);
        if ($references->fetchColumn()) {
            continue;
        }
        if ($apply && !unlink($file->getPathname())) {
            throw new RuntimeException('Could not remove an orphaned upload.');
        }
        echo ($apply ? 'Removed: ' : 'Would remove: ') . $name . PHP_EOL;
        $count++;
    }
    echo $count .
        ' orphaned image(s). ' .
        ($apply ? 'Cleanup complete.' : 'No files deleted; add --apply to delete.') .
        PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Cleanup stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
