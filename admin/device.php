<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/console/repository.php';
startAppSession();
$user = requireRoles(['ADMIN', 'MANAGER', 'TECHNICIAN', 'INTAKE_AGENT', 'VIEWER']);
header('Cache-Control: no-store, private');
$error = '';
$review = false;
$values = ['action' => '', 'technician' => '', 'status' => '', 'note' => ''];
try {
    $pdo = getPDO();
    foreach ($_SESSION['device_forms'] ?? [] as $key => $state) {
        if ($state['expires'] < time()) {
            unset($_SESSION['device_forms'][$key]);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('The form expired. Reload the device.', 403);
        }
        $formKey = requestText($_POST, 'form_key');
        $state = $_SESSION['device_forms'][$formKey] ?? null;
        if (!$state) {
            unavailablePage('This form expired or was already used.', 409);
        }
        $id = $state['id'];
        $device = consoleDevice($pdo, $user, $id);
        $intent = requestText($_POST, 'intent');
        if ($intent === 'review') {
            foreach ($values as $key => $value) {
                $values[$key] = requestText($_POST, $key);
            }
            $_SESSION['device_forms'][$formKey]['values'] = $values;
            $review = true;
        } elseif ($intent === 'edit') {
            $values = $state['values'] ?? $values;
            unset($_SESSION['device_forms'][$formKey]['values']);
        } elseif ($intent === 'save' && isset($state['values'])) {
            $values = $state['values'];
            consoleSave($pdo, (int) $user['user_id'], $id, $state['version'], $values);
            unset($_SESSION['device_forms'][$formKey]);
            $_SESSION['device_saved'] = true;
            header('Location: device.php?id=' . $id, true, 303);
            exit();
        } else {
            throw new DomainException('Review the change before saving.');
        }
    } else {
        $rawId = requestText($_GET, 'id');
        if (!ctype_digit($rawId)) {
            unavailablePage('Choose a device.', 400);
        }
        $id = (int) $rawId;
        $device = consoleDevice($pdo, $user, $id);
        $values['status'] = $device['repair_status'];
        $values['technician'] = (string) ($device['assigned_technician_id'] ?? '');
        $formKey = bin2hex(random_bytes(24));
        $_SESSION['device_forms'][$formKey] = [
            'id' => $id,
            'version' => consoleVersion($device),
            'expires' => time() + 1200,
        ];
        if (count($_SESSION['device_forms']) > 20) {
            array_shift($_SESSION['device_forms']);
        }
    }
} catch (DomainException $problem) {
    if (!isset($device)) {
        unavailablePage($problem->getMessage(), 404);
    }
    $error = $problem->getMessage();
    $review = false;
} catch (Throwable $problem) {
    error_log('RepairTrack device: ' . $problem->getMessage());
    unavailablePage('The device could not be updated. No partial change was saved.');
}
try {
    $technicians = in_array($user['role'], ['ADMIN', 'MANAGER'], true)
        ? consoleTechnicians($pdo, (int) $device['accountable_branch_id'])
        : [];
    $events = [];
    if (in_array($user['role'], ['ADMIN', 'TECHNICIAN'], true)) {
        $query = $pdo->prepare(
            "SELECT to_status, internal_note, created_at FROM ticket_status_events
            WHERE ticket_device_id = ?" .
                ($user['role'] === 'TECHNICIAN' ? ' AND actor_user_id = ?' : '') .
                ' ORDER BY status_event_id DESC LIMIT 20',
        );
        $query->execute($user['role'] === 'TECHNICIAN' ? [$id, $user['user_id']] : [$id]);
        $events = $query->fetchAll();
    }
} catch (Throwable $problem) {
    error_log('RepairTrack device details: ' . $problem->getMessage());
    unavailablePage('Device details are temporarily unavailable.');
}
$saved = $_SESSION['device_saved'] ?? false;
unset($_SESSION['device_saved']);
require __DIR__ . '/../views/console/device.php';
