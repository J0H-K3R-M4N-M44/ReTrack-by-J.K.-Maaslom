<?php
declare(strict_types=1);
require_once __DIR__.'/../app/technician.php';
require_once __DIR__.'/../app/holiday-api.php';
edpPreviewHeaders();
edpTechStartSession();
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        throw new DomainException('Method not allowed.');
    }
    $pdo=edpPreviewDb();
    if (!edpTechStaff($pdo)) {
        http_response_code(401);
        throw new DomainException('Technician sign-in required.');
    }
    $raw=$_GET['year'] ?? '';
    if (!is_string($raw) || !preg_match('/^[0-9]{4}$/D',$raw)) {
        throw new DomainException('Choose a valid calendar year.');
    }
    echo json_encode(['ok'=>true,'data'=>edpHolidayLookup((int)$raw)],JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('EDP holiday API: '.get_class($e));
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'Holiday reference is unavailable. Repair work is unaffected.'],JSON_THROW_ON_ERROR);
}
