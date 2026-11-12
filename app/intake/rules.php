<?php
declare(strict_types=1);

// Form choices are not database migrations. Keep stored enum values compatible.
function intakeDeviceTypes(): array
{
    return [
        'Laptop',
        'Desktop computer',
        'All-in-one computer',
        'Tablet',
        'Mobile phone',
        'Monitor',
        'Printer',
        'Game console',
        'Other',
    ];
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
// Read only the known form fields instead of accepting arbitrary submitted keys.
function intakeReadValues(array $defaults, array $input): array
{
    foreach ($defaults as $key => $default) {
        $value = $input[$key] ?? $default;
        $defaults[$key] = is_string($value)
            ? str_replace(["\r\n", "\r"], "\n", trim($value))
            : $default;
    }
    return $defaults;
}

// This validates a DRAFT, not ticket issuance. Signature/evidence gates remain separate.
// Collect validation errors before any customer or repair record is saved.
function intakeValidate(PDO $pdo, array $values, array $branches): array
{
    $errors = [];
    $branchId = ctype_digit($values['intake_branch_id']) ? (int) $values['intake_branch_id'] : 0;
    $branchIds = array_map(static fn($branch) => (int) $branch['branch_id'], $branches);

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
            $sameNameCheck = $pdo->prepare(
                'SELECT customer_id FROM customers WHERE normalized_name = ? LIMIT 1',
            );
            $sameNameCheck->execute([$normalizedName]);

            if ($sameNameCheck->fetchColumn() && $values['confirm_different_person'] !== '1') {
                $errors[] =
                    'A customer with this name already exists. Search and select that customer, or confirm this is a different person.';
            }
        }

        if (
            !in_array($values['contact_type'], ['PHONE', 'EMAIL'], true) ||
            $values['contact_value'] === ''
        ) {
            $errors[] = 'Enter a customer phone number or email address.';
        } elseif (
            $values['contact_type'] === 'EMAIL' &&
            !filter_var($values['contact_value'], FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = 'Enter a valid email address.';
        } elseif (
            $values['contact_type'] === 'PHONE' &&
            !intakePhoneIsValid($values['contact_value'])
        ) {
            $errors[] =
                'Enter a phone number with 7 to 15 digits. Spaces, parentheses and dashes are allowed.';
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
    foreach (
        [
            'make' => 100,
            'model' => 160,
            'serial_number' => 190,
            'reported_symptoms' => 3000,
            'condition_notes' => 2000,
        ]
        as $field => $limit
    ) {
        if (mb_strlen($values[$field]) > $limit) {
            $errors[] =
                ucfirst(str_replace('_', ' ', $field)) . " must be at most $limit characters.";
        }
    }
    return $errors;
}

function intakePhoneIsValid(string $value): bool
{
    $digits = preg_replace('/[^0-9]/', '', $value);
    return preg_match('/^\+?[0-9() -]+$/', $value) === 1 &&
        strlen($digits) >= 7 &&
        strlen($digits) <= 15;
}

// Keep the menus in one place so changing a label does not change the schema.
function intakeProblemChoices(): array
{
    return [
        'Will not turn on',
        'Will not charge',
        'Slow or freezing',
        'Unexpected shutdown',
        'No display',
        'Screen flickers',
        'Keyboard or touchpad issue',
        'No sound',
        'Wi-Fi or network issue',
        'Overheating',
        'Software or startup error',
        'Other',
    ];
}

function intakeConditionChoices(): array
{
    return [
        'No visible damage',
        'Scratched casing',
        'Dented casing',
        'Cracked screen',
        'Loose or broken hinge',
        'Missing keys',
        'Damaged port',
        'Liquid exposure reported',
        'Other',
    ];
}

// Existing free-text records remain editable; only our own marked summaries are unpacked.
function intakeChoiceState(string $text, array $choices): array
{
    // Browsers submit multiline form values with CRLF line endings.
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (!str_starts_with($text, 'Selected: ')) {
        return ['selected' => [], 'details' => $text];
    }
    $parts = explode("\nDetails: ", substr($text, 10), 2);
    $selected = explode('; ', $parts[0]);
    if (array_diff($selected, $choices)) {
        return ['selected' => [], 'details' => $text];
    }
    return ['selected' => $selected, 'details' => $parts[1] ?? ''];
}

function intakeQuickValues(array $values, array $input): array
{
    $errors = [];
    if (($input['quick_choices'] ?? '') !== '1') {
        return [$values, $errors];
    }
    if ($values['device_type'] === 'Other') {
        $other = trim(requestText($input, 'device_other'));
        if ($other === '' || mb_strlen($other) > 80) {
            $errors[] = 'For Other device, enter a device name of up to 80 characters.';
        } else {
            $values['device_type'] = $other;
        }
    } elseif (!in_array($values['device_type'], intakeDeviceTypes(), true)) {
        $errors[] = 'Choose a device type from the menu.';
    }
    foreach (
        [
            'reported_symptoms' => ['problems', intakeProblemChoices(), 'reported problem'],
            'condition_notes' => ['conditions', intakeConditionChoices(), 'visible condition'],
        ]
        as $field => [$key, $options, $label]
    ) {
        $selected = $input[$key] ?? [];
        if (!is_array($selected) || count($selected) > count($options)) {
            $errors[] = 'Choose valid ' . $label . ' options.';
            continue;
        }
        $valid = array_filter(
            $selected,
            static fn($v) => is_string($v) && in_array($v, $options, true),
        );
        if (count($valid) !== count($selected)) {
            $errors[] = 'Choose valid ' . $label . ' options.';
            continue;
        }
        $valid = array_values(array_unique($valid));
        $details = $values[$field];
        if (!$valid && $details === '') {
            $errors[] = 'Choose at least one ' . $label . ' or enter a short description.';
        }
        if (in_array('Other', $valid, true) && $details === '') {
            $errors[] = 'Describe Other ' . $label . ' briefly.';
        }
        if (
            $key === 'conditions' &&
            in_array('No visible damage', $valid, true) &&
            count($valid) > 1
        ) {
            $errors[] = 'Choose No visible damage by itself, or select the observed damage.';
        }
        $values[$field] = $valid
            ? 'Selected: ' .
                implode('; ', $valid) .
                ($details !== '' ? "\nDetails: " . $details : '')
            : $details;
    }
    return [$values, $errors];
}
