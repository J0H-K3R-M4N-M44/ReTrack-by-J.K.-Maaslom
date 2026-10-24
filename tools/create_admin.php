<?php
declare(strict_types=1);

// Run from the project folder with: php tools/create_admin.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/db.php';

function ask(string $question): string
{
    echo $question;
    return trim((string) fgets(STDIN));
}

$name = ask('Administrator full name: ');
$email = strtolower(ask('Administrator email: '));
$password = ask('Password (at least 12 characters): ');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Enter a name and a valid email address.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Use a password with at least 12 characters.\n");
    exit(1);
}

try {
    $pdo = getPDO();

    $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);

    if ($check->fetch()) {
        fwrite(STDERR, "That email already has an account. No user was changed.\n");
        exit(1);
    }

    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, role)
         VALUES (?, ?, ?, 'ADMIN')"
    );
    $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

    $userId = (int) $pdo->lastInsertId();

    $assignBranches = $pdo->prepare(
        'INSERT INTO user_branch_assignments (user_id, branch_id, is_default)
         SELECT ?, branch_id, 0 FROM branches WHERE is_active = 1'
    );
    $assignBranches->execute([$userId]);

    $pdo->commit();

    echo "Administrator account created for {$email}.\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Could not create the administrator: {$error->getMessage()}\n");
    exit(1);
}
