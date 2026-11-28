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
        'notification' => 'template_notification_synthetic',
    ],
];

$captured = null;
$result = emailJsSendTemplate(
    $config,
    'password_reset',
    [
        'to_email' => 'staff@example.test',
        'to_name' => 'Synthetic Staff',
        'subject' => 'RepairTrack account password reset',
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
emailJsTestAssert(($captured['template_id'] ?? '') === 'template_reset_synthetic', 'Password template ID was not passed.');
emailJsTestAssert(($captured['user_id'] ?? '') === 'public_synthetic', 'Public key was not passed.');
emailJsTestAssert(($captured['accessToken'] ?? '') === 'private_synthetic', 'Private access token was not passed.');
emailJsTestAssert(
    ($captured['template_params']['to_email'] ?? '') === 'staff@example.test',
    'Recipient template parameter was not passed.',
);

$notificationCases = [
    'customer_update' => ['show_customer_update' => true],
    'customer_payment' => ['show_customer_payment' => true],
    'staff_alert' => ['show_staff_alert' => true],
];
foreach ($notificationCases as $logicalKey => $flag) {
    $captured = null;
    emailJsSendTemplate(
        $config,
        $logicalKey,
        [
            'to_email' => 'synthetic@example.test',
            'to_name' => 'Synthetic Recipient',
            'subject' => 'Synthetic RepairTrack notice',
        ] + $flag,
        static function (array $payload) use (&$captured): array {
            $captured = $payload;
            return ['ok' => true, 'status' => 200];
        },
    );

    emailJsTestAssert(
        ($captured['template_id'] ?? '') === 'template_notification_synthetic',
        $logicalKey . ' did not resolve to the shared notification template.',
    );
    $flagName = array_key_first($flag);
    emailJsTestAssert(
        ($captured['template_params'][$flagName] ?? false) === true,
        $logicalKey . ' did not pass its conditional rendering flag.',
    );
    emailJsTestAssert(
        emailJsTemplateReady($config, $logicalKey),
        $logicalKey . ' should be ready when the shared template is configured.',
    );
}

$incompleteConfig = $config;
unset($incompleteConfig['emailjs_templates']['notification']);
emailJsTestAssert(
    !emailJsTemplateReady($incompleteConfig, 'staff_alert'),
    'Staff alerts must not be ready when the shared notification template is missing.',
);
emailJsTestAssert(
    !emailJsTemplateReady($config, 'unknown_event'),
    'Unknown template names must not resolve to a configured template.',
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
foreach (array_keys($expectedRoles) as $event) {
    emailJsTestAssert(
        !in_array('VIEWER', notificationStaffRoles($event), true),
        'Read-only viewers must not receive ' . $event . ' alerts.',
    );
}

echo "PASS: two-template EmailJS mapping, conditional notification modes, private-key payload and staff role policy.\n";
