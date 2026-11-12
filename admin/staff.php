<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/staff/repository.php';
startAppSession();
$admin = requireAdmin();
$adminId = (int) $admin['user_id'];
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$errors = [];
$review = false;
$values = staffReadValues([]);
$editingId = 0;
$action = '';
try {
    $pdo = getPDO();
    $branches = intakeBranches($pdo, $adminId);
    foreach ($_SESSION['staff_forms'] ?? [] as $key => $state) {
        if ($state['expires'] < time()) {
            unset($_SESSION['staff_forms'][$key]);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('The form expired. Reload Staff and try again.', 403);
        }
        $formKey = requestText($_POST, 'form_key');
        $state = $_SESSION['staff_forms'][$formKey] ?? null;
        if (!$state) {
            unavailablePage('This form expired or was already used. Reload Staff.', 409);
        }
        $editingId = $state['user_id'];
        $intent = requestText($_POST, 'intent');
        if ($intent === 'review') {
            unset(
                $_SESSION['staff_forms'][$formKey]['action'],
                $_SESSION['staff_forms'][$formKey]['values'],
            );
            $values = staffReadValues($_POST);
            $errors = staffValidate($pdo, $values, $adminId, $editingId);
            if (!$errors) {
                $_SESSION['staff_forms'][$formKey]['values'] = $values;
                $_SESSION['staff_forms'][$formKey]['action'] = 'save';
                $review = true;
                $action = 'save';
            }
        } elseif ($intent === 'review_access' && $editingId) {
            $action = requestText($_POST, 'action');
            if (!in_array($action, ['invite', 'deactivate'], true)) {
                throw new DomainException('Choose a valid account action.');
            }
            $_SESSION['staff_forms'][$formKey]['action'] = $action;
            $review = true;
        } elseif ($intent === 'edit') {
            $values = $state['values'] ?? $values;
            unset($_SESSION['staff_forms'][$formKey]['action']);
        } elseif ($intent === 'confirm' && isset($state['action'])) {
            if ($state['action'] === 'save') {
                if (!$editingId) {
                    $result = staffCreate($pdo, $adminId, $state['values']);
                    $_SESSION['staff_invitation'] = $result;
                } else {
                    staffUpdate($pdo, $adminId, $editingId, $state['version'], $state['values']);
                }
            } else {
                $token = staffChangeAccess(
                    $pdo,
                    $adminId,
                    $editingId,
                    $state['version'],
                    $state['action'],
                );
                if ($token) {
                    $_SESSION['staff_invitation'] = ['user_id' => $editingId, 'token' => $token];
                }
            }
            unset($_SESSION['staff_forms'][$formKey]);
            $_SESSION['staff_notice'] = 'Staff account saved.';
            header('Location: staff.php', true, 303);
            exit();
        } else {
            throw new DomainException('Review the details before confirming.');
        }
    } else {
        $rawId = requestText($_GET, 'id', '0');
        if (!ctype_digit($rawId)) {
            unavailablePage('Invalid staff reference.', 400);
        }
        $editingId = (int) $rawId;
        $version = '';
        if ($editingId) {
            $user = staffLoad($pdo, $editingId, $adminId);
            $version = staffVersion($user);
            $values = [
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'branches' => array_map('intval', array_column($user['assignments'], 'branch_id')),
                'default_branch' => '',
            ];
            foreach ($user['assignments'] as $assignment) {
                if ($assignment['is_default']) {
                    $values['default_branch'] = (string) $assignment['branch_id'];
                }
            }
        }
        $formKey = bin2hex(random_bytes(24));
        $_SESSION['staff_forms'][$formKey] = [
            'user_id' => $editingId,
            'version' => $version,
            'values' => $values,
            'expires' => time() + 1200,
        ];
        if (count($_SESSION['staff_forms']) > 10) {
            array_shift($_SESSION['staff_forms']);
        }
    }
    if ($editingId) {
        $user = staffLoad($pdo, $editingId, $adminId);
    }
    $list = staffList($pdo, $adminId, $_GET);
    $invitation = $_SESSION['staff_invitation'] ?? null;
    unset($_SESSION['staff_invitation']);
    $notice = $_SESSION['staff_notice'] ?? '';
    unset($_SESSION['staff_notice']);
    $activationLink = '';
    if ($invitation) {
        // Recheck management scope before revealing a previously generated link.
        staffLoad($pdo, $invitation['user_id'], $adminId);
        $config = require __DIR__ . '/../config/config.php';
        $baseUrl = rtrim((string) ($config['app_url'] ?? ''), '/');
        $activationLink =
            ($baseUrl &&
            filter_var($baseUrl, FILTER_VALIDATE_URL) &&
            in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)
                ? $baseUrl . '/admin/'
                : '') .
            'activate.php?token=' .
            rawurlencode($invitation['token']);
    }
} catch (DomainException $error) {
    unavailablePage($error->getMessage() . ' Return to Staff and reload.', 409);
} catch (Throwable $error) {
    error_log('RepairTrack staff: ' . $error->getMessage());
    unavailablePage(
        'Staff management is temporarily unavailable. No incomplete account changes were saved.',
    );
}
require __DIR__ . '/../views/staff/manage.php';
