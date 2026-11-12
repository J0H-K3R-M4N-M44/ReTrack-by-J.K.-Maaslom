<?php
declare(strict_types=1);

// Escape plain text at the HTML boundary, not before saving it.
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requestText(array $input, string $key, string $default = ''): string
{
    return is_string($input[$key] ?? null) ? $input[$key] : $default;
}

// Count Unicode characters consistently; bcrypt accepts at most 72 bytes.
function passwordIsAcceptable(string $password): bool
{
    return mb_check_encoding($password, 'UTF-8') &&
        mb_strlen($password, 'UTF-8') >= 12 &&
        strlen($password) <= 72 &&
        !str_contains($password, "\0");
}

function likePattern(string $text): string
{
    return '%' .
        strtr(mb_strtolower($text, 'UTF-8'), ['!' => '!!', '%' => '!%', '_' => '!_']) .
        '%';
}

function unavailablePage(string $message, int $status = 503): never
{
    http_response_code($status);
    echo '<!doctype html><html lang="en"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>RepairTrack</title><main><h1>RepairTrack</h1><p>' .
        h($message) .
        '</p><a href="dashboard.php">Dashboard</a> · <a href="login.php">Login</a></main></html>';
    exit();
}
