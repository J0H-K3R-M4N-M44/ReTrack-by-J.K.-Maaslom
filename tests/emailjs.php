<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/notifications.php';

function emailJsTestAssert(bool $passed, string $message): void
{
    if (!$passed) {
        throw new RuntimeException($message);
    }
}

$config = [
    'emailjs_service_id' => 'service_synthetic',
    'emailjs_public_key' => 'public_synthetic',
    'emailjs_private_key' => 'private_synthetic',
    'emailjs_templates' => [
        'password_reset' => 'template_reset_synthetic',
    ],
];
$captured = null;
$result = emailJsSendTemplate(
    $config,
    'password_reset',
    [
        'to_email' => 'staff@example.test',
        'to_name' => 'Synthetic Staff',
        'reset_url' => 'https://example.test/admin/reset_password.php?token=synthetic',
        'expires_minutes' => 30,
    ],
    static function (array $payload) use (&$captured): array {
        $captured = $payload;
        return ['ok' => true, 'status' => 200];
    },
);
emailJsTestAssert($result['ok'] === true, 'Test transport did not report success.');
emailJsTestAssert(($captured['service_id'] ?? '') === 'service_synthetic', 'Service ID was not passed.');
emailJsTestAssert(($captured['template_id'] ?? '') === 'template_reset_synthetic', 'Template ID was not passed.');
emailJsTestAssert(($captured['user_id'] ?? '') === 'public_synthetic', 'Public key was not passed.');
emailJsTestAssert(($captured['accessToken'] ?? '') === 'private_synthetic', 'Private access token was not passed.');
emailJsTestAssert(
    ($captured['template_params']['to_email'] ?? '') === 'staff@example.test',
    'Recipient template parameter was not passed.',
);

$missingTemplateFailed = false;
try {
    emailJsSendTemplate($config, 'customer_update', [], static fn(): array => ['ok' => true, 'status' => 200]);
} catch (RuntimeException $error) {
    $missingTemplateFailed = str_contains($error->getMessage(), 'not configured');
}
emailJsTestAssert($missingTemplateFailed, 'A missing template was not rejected before transport.');
emailJsTestAssert(
    !emailJsTemplateReady($config, 'customer_update'),
    'A template not configured in the service was marked ready.',
);

$expectedRoles = [
    'TICKET_ISSUED' => ['ADMIN', 'MANAGER', 'INTAKE_AGENT'],
    'QUOTE_SHARED' => ['ADMIN', 'MANAGER', 'INTAKE_AGENT'],
    'QUOTE_DECISION' => ['ADMIN', 'MANAGER', 'TECHNICIAN', 'INTAKE_AGENT'],
    'READY_FOR_PICKUP' => ['ADMIN', 'MANAGER', 'CASHIER'],
    'PAYMENT_RECORDED' => ['ADMIN', 'MANAGER', 'CASHIER'],
    'DEVICE_ASSIGNED' => ['TECHNICIAN'],
    'DEVICE_TRANSFER' => ['ADMIN', 'MANAGER', 'COURIER'],
];
foreach ($expectedRoles as $event => $roles) {
    emailJsTestAssert(
        notificationStaffRoles($event) === $roles,
        'Staff role routing changed unexpectedly for ' . $event . '.',
    );
}
foreach (['TICKET_ISSUED', 'QUOTE_SHARED', 'QUOTE_DECISION', 'READY_FOR_PICKUP',
    'PAYMENT_RECORDED', 'DEVICE_ASSIGNED', 'DEVICE_TRANSFER'] as $event) {
    emailJsTestAssert(
        !in_array('VIEWER', notificationStaffRoles($event), true),
        'Read-only viewers must not receive ' . $event . ' alerts.',
    );
}

echo "PASS: EmailJS test transport, template validation, private-key payload and staff role policy.\n";
