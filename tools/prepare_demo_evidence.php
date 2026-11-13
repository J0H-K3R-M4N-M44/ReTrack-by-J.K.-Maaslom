<?php
declare(strict_types=1);

// CLI only: install watermarked demo images into the configured private directory.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/intake/evidence.php';
try {
    $pdo = getPDO();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'repairtrack_demo') {
        throw new RuntimeException('This helper only runs against repairtrack_demo.');
    }
    $marker = $pdo
        ->query("SELECT COUNT(*) FROM audit_events WHERE action = 'CONSOLE_DEMO_V2_SEEDED'")
        ->fetchColumn();
    if ((int) $marker !== 1) {
        throw new RuntimeException('Import the console demo seed before preparing evidence.');
    }
    foreach (['condition' => 'a', 'signed-form' => 'b'] as $kind => $letter) {
        $encoded = file_get_contents(__DIR__ . '/../database/demo-assets/' . $kind . '.png.b64');
        $bytes = base64_decode(trim($encoded), true);
        if ($bytes === false) {
            throw new RuntimeException('Invalid packaged demo image.');
        }
        $path = intakeEvidencePath(str_repeat($letter, 48) . '.png');
        if (is_link($path)) {
            throw new RuntimeException('Refusing a symbolic-link evidence target.');
        }
        if (file_exists($path)) {
            if (hash_file('sha256', $path) !== hash('sha256', $bytes)) {
                throw new RuntimeException('An unrelated file already uses the demo filename.');
            }
        } else {
            $handle = fopen($path, 'xb');
            if (!$handle) {
                throw new RuntimeException('Cannot create private demo evidence.');
            }
            $written = fwrite($handle, $bytes);
            fclose($handle);
            if ($written !== strlen($bytes)) {
                unlink($path);
                throw new RuntimeException('Incomplete demo image write. Try again.');
            }
            chmod($path, 0600);
        }
        intakeInspectImage($path);
        echo 'Ready: ' . $kind . PHP_EOL;
    }
    echo 'Demo images installed. They are marked as synthetic and are not real consent evidence.' .
        PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
