<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/rules.php';
require_once __DIR__ . '/../app/intake/repository.php';

startAppSession();
$admin = requireAdmin(); // Keep access checks before any customer queries.
$pdo = getPDO();
$branches = intakeBranches($pdo, (int) $admin['user_id']);
$values = intakeDefaults($branches);
$errors = [];

function intakeH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = intakeReadValues($values, $_POST);
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if (!csrfIsValid($token)) {
        $errors[] = 'The form expired. Reload this page and try again.';
    } else {
        $errors = intakeValidate($pdo, $values, $branches);
    }
    if ($errors === []) {
        try {
            intakeSaveDraft($pdo, $values, (int) $admin['user_id']);
            header('Location: dashboard.php?tab=workbench&created=1');
            exit;
        } catch (Throwable $error) {
            error_log('RepairTrack draft intake error: ' . $error->getMessage());
            $errors[] = 'The draft could not be saved. Check the database and try again.';
        }
    }
} elseif (is_string($_GET['customer_search'] ?? null)) {
    $values['customer_search'] = trim($_GET['customer_search']);
}

// Rebuild search results after POST too: keep an existing selection on errors.
$customerResults = intakeSearchCustomers($pdo, $values['customer_search']);
if (ctype_digit($values['customer_id']) && !in_array((int) $values['customer_id'], array_map('intval', array_column($customerResults, 'customer_id')), true)) {
    $customer = intakeSelectedCustomer($pdo, (int) $values['customer_id']);
    if ($customer !== null) {
        $customerResults[] = $customer;
    }
}

require __DIR__ . '/../views/intake/create.php';
