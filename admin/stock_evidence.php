<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/inventory/evidence.php';

startAppSession();
$user=requireRoles(['ADMIN','MANAGER']);
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; sandbox");
try {
    $id=requestText($_GET,'id');
    if (!ctype_digit($id) || (int)$id<1) {
        throw new DomainException('Evidence unavailable.');
    }
    $file=stockEvidenceRead(getPDO(),(int)$user['user_id'],(int)$id);
    header('Content-Type: '.$file['mime']);
    header('Content-Length: '.$file['bytes']);
    // Always download: PDF may contain active content; no inline preview.
    header('Content-Disposition: attachment; filename="repairtrack-evidence-'.
        $file['id'].'.'.$file['extension'].'"');
    session_write_close();
    readfile($file['path']);
} catch (DomainException $error) {
    http_response_code(404); // Same response for missing, tampered, or unauthorized files.
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Evidence unavailable.';
} catch (Throwable $error) {
    error_log('Private stock evidence download: '.$error->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Evidence temporarily unavailable.';
}
