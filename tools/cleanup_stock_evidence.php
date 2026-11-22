<?php
declare(strict_types=1);
// Preview: php tools/cleanup_stock_evidence.php
// Remove unreferenced private files older than 24 hours only:
// php tools/cleanup_stock_evidence.php --apply
// Never remove evidence that has a database reference, even on closed cases.
if (PHP_SAPI!=='cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/inventory/evidence.php';

try {
    $directory=stockEvidenceDirectory();
    $pdo=getPDO();
    $apply=in_array('--apply',$argv,true);
    $referenced=$pdo->prepare('SELECT 1 FROM stock_transfer_evidence
        WHERE stored_name=? LIMIT 1');
    $count=0;
    foreach (new DirectoryIterator($directory) as $file) {
        $name=$file->getFilename();
        if (!$file->isFile() || $file->isLink() ||
            !preg_match('/^[a-f0-9]{48}\.(jpg|png|pdf)$/D',$name) ||
            $file->getMTime()>=time()-86400) {
            continue;
        }
        $referenced->execute([$name]);
        if ($referenced->fetchColumn()) continue;
        if ($apply && !unlink($file->getPathname())) {
            throw new RuntimeException('Unable to remove an orphaned investigation file.');
        }
        echo ($apply?'Removed orphan: ':'Would remove orphan: ').$name.PHP_EOL;
        $count++;
    }
    echo $count.' older unreferenced file(s). '.
        ($apply?'Cleanup complete.':'Dry run; no files deleted.').PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Private evidence cleanup stopped: '.$error->getMessage().PHP_EOL);
    exit(1);
}
