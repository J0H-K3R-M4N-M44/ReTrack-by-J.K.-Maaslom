<?php
// Copy this file to config.php and enter your local MySQL settings.
// config.php is ignored by Git so local passwords are not committed.
return [
    // Open assigned devices across all branches, excluding finished/cancelled work.
    'technician_active_limit' => 5,

    'host' => 'localhost',
    'port' => 3306,
    'database' => 'repairtrack',
    'username' => 'root',
    'password' => '', // Enter your local MySQL password here.

    // Use the full site URL including the project folder, with no trailing slash.
    'app_url' => 'http://localhost/ReTrack-by-J.K.-Maaslom',
    // EmailJS sends only through templates you configure in your EmailJS account.
    // Copy these IDs/keys from EmailJS. Keep the private key in this ignored file only.
    'emailjs_public_key' => '',
    'emailjs_private_key' => '',
    'emailjs_service_id' => '',
    // Enable staff email only after the shared notification template and recipient rules are reviewed.
    'emailjs_staff_notifications_enabled' => false,
    // Two EmailJS templates total: password recovery and all other notifications.
    'emailjs_templates' => [
        'password_reset' => 'r3tr4ck_resetpassword',
        'notification' => 'r3tr4ck_notification',
    ],

    // Issuance only: create this private folder outside htdocs and this project.
    // Example on Windows: C:/repairtrack-private/intake (give PHP write access).
    'intake_storage_path' => '',
    // Private stock investigation attachments (JPEG, PNG, PDF) - outside project/web root.
    // Example on Windows: C:/repairtrack-private/inventory-evidence
    'inventory_evidence_path' => '',
    // Generate once: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    // Keep the generated key unchanged and private; it protects duplicate fingerprints.
    'intake_hmac_key' => '',
];
