<?php
declare(strict_types=1);

/**
 * Simple PHP development-server router for the MVP preview.
 * Let PHP resolve normal pages and assets. Only keep private source folders
 * out of the browser; these are not part of the user-facing repair tracker.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!is_string($path) || str_contains($path, '\\') ||
    str_contains($path, '..') || str_contains($path, '%') ||
    preg_match('~(?:^|/)(?:\\.[^/]+|app|config|database|docs|tests|tools|views)(?:/|$)~i', $path)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

// Native PHP server handles index.php, staff pages, JS, CSS, images and video.
// No custom route allowlist, file resolution, or blocking preflight.
return false;
