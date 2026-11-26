<?php
declare(strict_types=1);

/**
 * EmailJS is called from the server so reset tokens and customer/staff data
 * never need to be placed in browser JavaScript.
 */
function emailJsIsEnabled(array $config): bool
{
    return trim((string) ($config['emailjs_public_key'] ?? '')) !== '' ||
        trim((string) ($config['emailjs_service_id'] ?? '')) !== '' ||
        !empty($config['emailjs_templates']);
}

function emailJsTemplateReady(array $config, string $templateKey): bool
{
    $templates = $config['emailjs_templates'] ?? [];
    return trim((string) ($config['emailjs_public_key'] ?? '')) !== '' &&
        trim((string) ($config['emailjs_service_id'] ?? '')) !== '' &&
        is_array($templates) &&
        trim((string) ($templates[$templateKey] ?? '')) !== '';
}

/** Return only the HTTP result; provider response bodies are not logged. */
function emailJsHttpPost(array $payload): array
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents(
        'https://api.emailjs.com/api/v1.0/email/send',
        false,
        $context,
    );
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
            $status = (int) $match[1];
        }
    }

    return ['ok' => $status === 200 && $response !== false, 'status' => $status];
}

/**
 * Serialize requests from this PHP installation to respect EmailJS's
 * documented one-request-per-second limit. Test transports bypass the lock.
 */
function emailJsPostRateLimited(array $payload): array
{
    $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR . 'repairtrack-emailjs-rate.lock';
    $handle = @fopen($lockPath, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('EmailJS rate-limit lock is unavailable.');
    }

    try {
        rewind($handle);
        $lastSentAt = (float) trim((string) stream_get_contents($handle));
        $wait = 1.05 - (microtime(true) - $lastSentAt);
        if ($wait > 0) {
            usleep((int) ceil($wait * 1000000));
        }
        $result = emailJsHttpPost($payload);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) microtime(true));
        fflush($handle);
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Send one configured template. The optional transport is for deterministic
 * tests; production always uses the HTTPS EmailJS endpoint above.
 */
function emailJsSendTemplate(
    array $config,
    string $templateKey,
    array $templateParams,
    ?callable $testTransport = null,
): array {
    if (!emailJsTemplateReady($config, $templateKey)) {
        throw new RuntimeException('EmailJS service or template is not configured.');
    }

    $templates = $config['emailjs_templates'];
    $payload = [
        'service_id' => trim((string) $config['emailjs_service_id']),
        'template_id' => trim((string) $templates[$templateKey]),
        'user_id' => trim((string) $config['emailjs_public_key']),
        'template_params' => $templateParams,
    ];
    $privateKey = trim((string) ($config['emailjs_private_key'] ?? ''));
    if ($privateKey !== '') {
        $payload['accessToken'] = $privateKey;
    }

    $result = $testTransport
        ? $testTransport($payload)
        : emailJsPostRateLimited($payload);

    if (!is_array($result) || empty($result['ok'])) {
        $status = is_array($result) ? (int) ($result['status'] ?? 0) : 0;
        throw new RuntimeException('EmailJS send failed (HTTP ' . $status . ').');
    }

    return ['ok' => true, 'status' => (int) ($result['status'] ?? 200)];
}
