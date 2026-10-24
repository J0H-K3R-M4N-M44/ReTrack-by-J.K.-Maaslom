<?php
declare(strict_types=1);

// Form choices are not database migrations. Keep stored enum values compatible.
function intakeDeviceTypes(): array
{
    return ['Laptop', 'Desktop computer', 'All-in-one computer', 'Tablet', 'Other'];
}

function intakeDefaults(array $branches): array
{
    $defaultBranchId = '';
    foreach ($branches as $branch) {
        if ((int) $branch['is_default'] === 1) {
            $defaultBranchId = (string) $branch['branch_id'];
            break;
        }
}
if ($defaultBranchId === '' && $branches !== []) {
    $defaultBranchId = (string) $branches[0]['branch_id'];
}

return [
    'intake_branch_id' => $defaultBranchId,
    'arrival_method' => 'WALK_IN',
    'customer_id' => '',
    'customer_search' => '',
    'customer_name' => '',
    'contact_type' => 'PHONE',
    'contact_value' => '',
    'device_type' => '',
    'make' => '',
    'model' => '',
    'serial_number' => '',
    'reported_symptoms' => '',
    'condition_notes' => '',
    'confirm_different_person' => '',
];
}

// Copy only known scalar fields; unexpected arrays must not reach SQL or HTML.
function intakeReadValues(array $defaults, array $input): array
{
    foreach ($defaults as $key => $default) {
        $value = $input[$key] ?? $default;
        $defaults[$key] = is_string($value) ? trim($value) : $default;
    }
    return $defaults;
}

// This validates a DRAFT, not ticket issuance. Signature/evidence gates remain separate.
function intakeValidate(PDO $pdo, array $values, array $branches): array
{
    $errors = [];
    $branchId = ctype_digit($values['intake_branch_id']) ? (int) $values['intake_branch_id'] : 0;
    $branchIds = array_map(static fn ($branch) => (int) $branch['branch_id'], $branches);

    if (!in_array($branchId, $branchIds, true)) {
        $errors[] = 'Choose one of your assigned active branches.';
    }

    if (!in_array($values['arrival_method'], ['WALK_IN', 'MAIL'], true)) {
        $errors[] = 'Choose walk-in or mail.';
    }

    $existingCustomerId = ctype_digit($values['customer_id']) ? (int) $values['customer_id'] : 0;
    if ($existingCustomerId > 0) {
        $customerCheck = $pdo->prepare('SELECT customer_id FROM customers WHERE customer_id = ?');
        $customerCheck->execute([$existingCustomerId]);
        if (!$customerCheck->fetchColumn()) {
            $errors[] = 'Choose a customer from the search results.';
        }
    } else {
        if ($values['customer_name'] === '' || mb_strlen($values['customer_name']) > 160) {
            $errors[] = 'Enter the customer name (up to 160 characters).';
        } else {
            $cleanName = preg_replace('/\\s+/', ' ', $values['customer_name']);
            $normalizedName = mb_strtolower((string) $cleanName, 'UTF-8');
            $sameNameCheck = $pdo->prepare('SELECT customer_id FROM customers WHERE normalized_name = ? LIMIT 1');
            $sameNameCheck->execute([$normalizedName]);

            if ($sameNameCheck->fetchColumn() && $values['confirm_different_person'] !== '1') {
                $errors[] = 'A customer with this name already exists. Search and select that customer, or confirm this is a different person.';
            }
        }

        if (!in_array($values['contact_type'], ['PHONE', 'EMAIL'], true) || $values['contact_value'] === '') {
            $errors[] = 'Enter a customer phone number or email address.';
        } elseif ($values['contact_type'] === 'EMAIL' && !filter_var($values['contact_value'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        } elseif (mb_strlen($values['contact_value']) > 190) {
            $errors[] = 'The contact value is too long.';
        }
    }

    if ($values['device_type'] === '' || mb_strlen($values['device_type']) > 80) {
        $errors[] = 'Enter a device type (up to 80 characters).';
    }

    if (trim($values['make'] . $values['model']) === '') {
        $errors[] = 'Enter at least a make or model.';
    }

    if ($values['reported_symptoms'] === '') {
        $errors[] = 'Describe the reported problem.';
    }

    if ($values['condition_notes'] === '') {
        $errors[] = 'Record the device condition.';
    }


    // HTML limits are conveniences; enforce them again for direct POST requests.
    foreach (['make' => 100, 'model' => 160, 'serial_number' => 190,
              'reported_symptoms' => 3000, 'condition_notes' => 2000] as $field => $limit) {
        if (mb_strlen($values[$field]) > $limit) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be at most $limit characters.";
        }
    }
    return $errors;
}
