<?php
// Copy this file to config.php and enter your local MySQL settings.
// config.php is ignored by Git so local passwords are not committed.
return [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'repairtrack',
    'username' => 'root',
    'password' => '', // Enter your local MySQL password here.

    // Use the full site URL including the project folder, with no trailing slash.
    'app_url' => 'http://localhost/ReTrack-by-J.K.-Maaslom',
    // Use a real sender address accepted by your configured PHP mail server.
    'mail_from' => 'no-reply@example.com',

    // Issuance only: create this private folder outside htdocs and this project.
    // Example on Windows: C:/repairtrack-private/intake (give PHP write access).
    'intake_storage_path' => '',
    // Generate once: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    // Keep the generated key unchanged and private; it protects duplicate fingerprints.
    'intake_hmac_key' => '',
];
