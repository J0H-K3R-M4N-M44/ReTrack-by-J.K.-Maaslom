<?php
declare(strict_types=1);

/**
 * Local-only RepairTrack preview router.
 * Launch with the provided tools/start-mvp-preview.sh, never on a public interface.
 * Do not use PHP's built-in server for production.
 */
$root = realpath(dirname(__DIR__));
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$notFound = static function (): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
};

if (!is_string($uri) || $uri === '' ||
    preg_match('~[%\\\\\\x00-\\x1F]~', $uri) ||
    str_contains($uri, '//') ||
    str_contains($uri, '/./') ||
    str_contains($uri, '/../') ||
    str_ends_with($uri, '/..') ||
    str_ends_with($uri, '/.')) {
    $notFound();
    return true;
}

if ($uri === '/') {
    $uri = '/index.php';
}

$pages = [
    '/index.php', '/track.php', '/learn.php',
    '/admin/acquisition_costs.php', '/admin/activate.php',
    '/admin/activity.php', '/admin/cash.php',
    '/admin/dashboard.php', '/admin/device.php',
    '/admin/forgot_password.php', '/admin/intake_create.php',
    '/admin/intake_evidence.php', '/admin/intake_issue.php',
    '/admin/inventory.php', '/admin/learning.php',
    '/admin/locations.php', '/admin/login.php',
    '/admin/logout.php', '/admin/part_requests.php',
    '/admin/parts_transfers.php', '/admin/quote.php',
    '/admin/reports.php', '/admin/reset_password.php',
    '/admin/staff.php', '/admin/staff_home.php',
    '/admin/stock_evidence.php', '/admin/stocktaking.php',
    '/admin/ticket.php', '/admin/transfers.php',
];

$static = preg_match(
    '~^/(?:assets|css|js)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\\.(?:css|js|png|jpg|jpeg|gif|svg|webp|ico|mp4|woff2?|ttf)$~D',
    $uri
) === 1;
$isPage = in_array($uri, $pages, true);

if ((!$isPage && !$static) ||
    !in_array($method, $static ? ['GET', 'HEAD'] : ['GET', 'HEAD', 'POST'], true) ||
    $root === false) {
    $notFound();
    return true;
}

$resolved = realpath($root . str_replace('/', DIRECTORY_SEPARATOR, $uri));
if ($resolved === false || !is_file($resolved) ||
    !str_starts_with(strtolower($resolved), strtolower($root . DIRECTORY_SEPARATOR))) {
    $notFound();
    return true;
}

return false;
