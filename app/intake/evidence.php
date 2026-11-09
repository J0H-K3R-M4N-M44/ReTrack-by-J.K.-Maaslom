<?php
declare(strict_types=1);

// Keep customer evidence outside both the project folder and web document root.
function intakePrivateDirectory(): string
{
    $config = require __DIR__ . '/../../config/config.php';
    $configured = $config['intake_storage_path'] ?? '';
    if (!is_string($configured) || $configured === '' || !is_dir($configured)) {
        throw new RuntimeException(
            'Create and configure intake_storage_path outside the public web root.',
        );
    }
    $directory = realpath($configured);
    foreach (
        [
            realpath(__DIR__ . '/../..'),
            !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false,
        ]
        as $publicRoot
    ) {
        if (
            $publicRoot &&
            ($directory === $publicRoot ||
                str_starts_with(
                    strtolower($directory . DIRECTORY_SEPARATOR),
                    strtolower($publicRoot . DIRECTORY_SEPARATOR),
                ))
        ) {
            throw new RuntimeException('Intake evidence must be outside public folders.');
        }
    }
    if (!$directory || !is_writable($directory)) {
        throw new RuntimeException('Intake storage is not writable.');
    }
    return $directory;
}

function intakeEvidencePath(string $name): string
{
    if (!preg_match('/^[a-f0-9]{48}\.(jpg|png)$/', $name)) {
        throw new DomainException('This evidence file is unavailable.');
    }
    return intakePrivateDirectory() . DIRECTORY_SEPARATOR . $name;
}

// Validate actual bytes, not the supplied filename or browser content type.
function intakeInspectImage(string $path): array
{
    $size = filesize($path);
    $image = @getimagesize($path);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (
        $size === false ||
        $size < 1 ||
        $size > 5 * 1024 * 1024 ||
        !$image ||
        !in_array($mime, ['image/jpeg', 'image/png'], true) ||
        ($image['mime'] ?? '') !== $mime ||
        $image[0] > 6000 ||
        $image[1] > 6000 ||
        $image[0] * $image[1] > 20000000
    ) {
        throw new DomainException(
            'Use a JPEG or PNG image up to 5 MB and 20 megapixels (maximum 6000 pixels per side).',
        );
    }
    return ['mime' => $mime, 'bytes' => $size, 'hash' => hash_file('sha256', $path)];
}

function intakeStoreUpload(array $file): array
{
    if (
        ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        !is_string($file['tmp_name'] ?? null) ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        throw new DomainException('Select both required images again. Each must be at most 5 MB.');
    }
    $image = intakeInspectImage($file['tmp_name']);
    $name = bin2hex(random_bytes(24)) . ($image['mime'] === 'image/png' ? '.png' : '.jpg');
    if (!move_uploaded_file($file['tmp_name'], intakeEvidencePath($name))) {
        throw new RuntimeException('Evidence could not be stored.');
    }
    @chmod(intakeEvidencePath($name), 0600);
    return ['name' => $name] + $image;
}

function intakeVerifyEvidence(array $evidence): void
{
    foreach (['photo', 'signed_form'] as $kind) {
        $file = $evidence[$kind] ?? [];
        $path = intakeEvidencePath($file['name'] ?? '');
        if (!is_file($path) || is_link($path)) {
            throw new DomainException('An intake image is missing. Upload the images again.');
        }
        $actual = intakeInspectImage($path);
        if ($actual !== array_intersect_key($file, $actual)) {
            throw new DomainException('An intake image changed. Upload the images again.');
        }
    }
}

// Delete only temporary files created by this form, never existing ticket evidence.
function intakeDiscardEvidence(array $evidence): void
{
    foreach ($evidence as $file) {
        if (is_array($file) && is_string($file['name'] ?? null)) {
            $path = intakeEvidencePath($file['name']);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
