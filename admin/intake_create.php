<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/rules.php';
require_once __DIR__ . '/../app/intake/repository.php';
require_once __DIR__ . '/../app/intake/drafts.php';

startAppSession();
$admin = requireRoles(['ADMIN', 'MANAGER', 'INTAKE_AGENT']);
header('Cache-Control: no-store, private');
$userId = (int) $admin['user_id'];
$errors = [];
$review = false;
$showDeviceStep = false;
$savedDeviceCount = 0;

try {
    $pdo = getPDO();
    $branches = intakeBranches($pdo, $userId);
    $values = intakeDefaults($branches);
    // Each open form gets its own version and one-use submission key.
    foreach ($_SESSION['intake_forms'] ?? [] as $key => $form) {
        if ($form['expires'] < time()) {
            unset($_SESSION['intake_forms'][$key]);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formKey = requestText($_POST, 'form_key');
        $formState = $_SESSION['intake_forms'][$formKey] ?? null;
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('The form expired. Open intake again.', 403);
        }
        if (!$formState) {
            unavailablePage('This form expired or was already saved. Open intake again.', 409);
        }
        $draftId = $formState['draft_id'];
        $savedDeviceCount = $formState['device_count'] ?? 0;
        $values = intakeReadValues($values, $_POST);
        [$values, $choiceErrors] = intakeQuickValues($values, $_POST);
        $rawDevices = $_POST['extra_devices'] ?? [];
        $values['extra_devices'] = [];
        $limit = max(INTAKE_DEVICE_LIMIT, $savedDeviceCount);
        if (!is_array($rawDevices) || count($rawDevices) >= $limit) {
            unavailablePage('This intake contains too many devices. Reopen the draft.', 400);
        }
        foreach ($rawDevices as $rawDevice) {
            if (!is_array($rawDevice)) {
                unavailablePage('The device details are incomplete. Reopen the draft.', 400);
            }
            $number = count($values['extra_devices']) + 2;
            $device = intakeReadValues(intakeDeviceFields(), $rawDevice);
            [$device, $deviceErrors] = intakeQuickValues($device, $rawDevice);
            $values['extra_devices'][] = $device;
            foreach ($deviceErrors as $message) {
                $choiceErrors[] = 'Device ' . $number . ': ' . $message;
            }
        }
        $intent = requestText($_POST, 'intent');
        $showDeviceStep = true;
        $digest = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
        if ($intent === 'search-customer') {
            // Redisplay the posted intake without validating or saving an unfinished device.
            $showDeviceStep = false;
            unset($_SESSION['intake_forms'][$formKey]['review_hash']);
        } elseif ($intent === 'add-device') {
            if (count($values['extra_devices']) + 1 < $limit) {
                $values['extra_devices'][] = intakeDeviceFields();
            }
            unset($_SESSION['intake_forms'][$formKey]['review_hash']);
        } elseif (preg_match('/^remove-device-([1-9][0-9]*)$/D', $intent, $matches)) {
            $index = (int) $matches[1];
            if ($index >= $savedDeviceCount && isset($values['extra_devices'][$index - 1])) {
                array_splice($values['extra_devices'], $index - 1, 1);
            }
            unset($_SESSION['intake_forms'][$formKey]['review_hash']);
        } elseif ($intent === 'edit') {
            unset($_SESSION['intake_forms'][$formKey]['review_hash']);
        } else {
            $errors = array_merge($choiceErrors, intakeValidateDevices($pdo, $values, $branches, $savedDeviceCount));
            if ($errors === [] && $intent === 'review') {
                $_SESSION['intake_forms'][$formKey]['review_hash'] = $digest;
                $review = true;
            } elseif ($errors === [] && $intent === 'save') {
                if (
                    !hash_equals($formState['review_hash'] ?? '', $digest) ||
                    requestText($_POST, 'confirm_review') !== '1'
                ) {
                    $errors[] = 'Review the current details and confirm them before saving.';
                } else {
                    if ($draftId > 0) {
                        intakeUpdateDraft($pdo, $draftId, $userId, $formState['version'], $values);
                    } else {
                        intakeSaveDraft($pdo, $values, $userId);
                    }
                    unset($_SESSION['intake_forms'][$formKey]);
                    $_SESSION['intake_saved'] = true;
                    header('Location: ' . workbenchHome(), true, 303);
                    exit();
                }
            } elseif ($errors === []) {
                $errors[] = 'Use Review details before saving.';
            }
        }
    } else {
        $rawId = requestText($_GET, 'id', '0');
        if (!ctype_digit($rawId)) {
            unavailablePage('Invalid draft reference.', 400);
        }
        $draftId = (int) $rawId;
        $version = '';
        if ($draftId > 0) {
            $draft = intakeLoadDraft($pdo, $draftId, $userId);
            $version = intakeDraftVersion($draft);
            $savedDeviceCount = count($draft['devices']);
            foreach ($values as $key => $value) {
                if (array_key_exists($key, $draft)) {
                    $values[$key] = (string) ($draft[$key] ?? '');
                }
            }
        }
        $values['extra_devices'] = array_map(
            static fn(array $device): array => intakeReadValues(intakeDeviceFields(), array_map(
                static fn($value): string => (string) $value, intakeDeviceFields($device),
            )),
            array_slice($draft['devices'] ?? [], 1),
        );
        $values['customer_search'] = trim(requestText($_GET, 'customer_search'));
        $formKey = bin2hex(random_bytes(24));
        $_SESSION['intake_forms'][$formKey] = [
            'draft_id' => $draftId,
            'device_count' => $savedDeviceCount,
            'version' => $version,
            'expires' => time() + 1800,
        ];
        if (count($_SESSION['intake_forms']) > 20) {
            array_shift($_SESSION['intake_forms']);
        }
    }
    $customerResults = intakeSearchCustomers($pdo, $values['customer_search']);
    $matchCount = count($customerResults);
    if (
        ctype_digit($values['customer_id']) &&
        !in_array(
            (int) $values['customer_id'],
            array_map('intval', array_column($customerResults, 'customer_id')),
            true,
        )
    ) {
        $customer = intakeSelectedCustomer($pdo, (int) $values['customer_id']);
        if ($customer) {
            $customerResults[] = $customer;
        }
    }
} catch (DomainException $error) {
    unavailablePage($error->getMessage(), 409);
} catch (Throwable $error) {
    error_log('RepairTrack intake: ' . $error->getMessage());
    unavailablePage(
        'Intake is temporarily unavailable. Your changes were not saved. Please try again later.',
    );
}
$duplicateFound = str_contains(implode(' ', $errors), 'already exists');
require __DIR__ . '/../views/intake/create.php';
