<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/parts/requests.php';
startAppSession();
$user = requireRoles(['ADMIN', 'MANAGER', 'TECHNICIAN']);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$error = '';
$notice = $_SESSION['part_request_notice'] ?? '';
unset($_SESSION['part_request_notice']);
$review = false;
$values = partRequestValues([]);
$action = '';
$deviceId = null;
$requestId = null;
$request = null;
$device = null;
try {
    $pdo = getPDO();
    foreach ($_SESSION['part_request_forms'] ?? [] as $key => $item) {
        if ($item['expires'] < time()) {
            unset($_SESSION['part_request_forms'][$key]);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('The request form expired. Reload and try again.', 403);
        }
        $key = requestText($_POST, 'form_key');
        $state = $_SESSION['part_request_forms'][$key] ?? null;
        if (!$state) {
            unavailablePage('This form expired or was already used.', 409);
        }
        $deviceId = $state['device_id'];
        $requestId = $state['request_id'];
        $intent = requestText($_POST, 'intent');
        if ($intent === 'review') {
            if ($requestId !== null) {
                $request = partRequestLoad($pdo, $user, $requestId);
                if (!partRequestCanReview($user)) {
                    throw new DomainException('Your role cannot review requests.');
                }
                $action = requestText($_POST, 'action');
                $note = trim(requestText($_POST, 'note'));
                if (!in_array($action, ['acknowledge', 'decline', 'resolve'], true) ||
                    !mb_check_encoding($note, 'UTF-8') || mb_strlen($note) > 500 ||
                    ($action !== 'acknowledge' && mb_strlen($note) < 10)) {
                    throw new DomainException('Choose a supported decision; decline and resolve require a reason of 10–500 characters.');
                }
                if (($action === 'resolve' && $request['status'] !== 'ACKNOWLEDGED') ||
                    ($action !== 'resolve' && $request['status'] !== 'OPEN')) {
                    throw new DomainException('That decision is not available at this request stage.');
                }
                $values = ['action' => $action, 'note' => $note];
            } else {
                if ($deviceId === null) {
                    throw new DomainException('Select an assigned device for this request.');
                }
                $device = partRequestDevice($pdo, $user, $deviceId);
                if ($user['role'] !== 'TECHNICIAN' || !partRequestOpenDevice($device)) {
                    throw new DomainException('Only an assigned technician working on an open repair can submit a request.');
                }
                $values = partRequestValues($_POST);
                partRequestValidate($values);
            }
            $_SESSION['part_request_forms'][$key]['values'] = $values;
            $review = true;
        } elseif ($intent === 'edit') {
            $values = $state['values'] ?? $values;
            unset($_SESSION['part_request_forms'][$key]['values']);
        } elseif ($intent === 'confirm' && isset($state['values'])) {
            if ($requestId !== null) {
                partRequestChange(
                    $pdo,
                    (int) $user['user_id'],
                    $requestId,
                    $state['version'],
                    $state['values']['action'],
                    $state['values']['note'],
                );
                $noticeText = 'Request decision recorded. No stock or purchase action was performed.';
            } elseif ($deviceId !== null) {
                partRequestCreate($pdo, (int) $user['user_id'], $deviceId, $state['values']);
                $noticeText = 'Internal part request submitted. It does not reserve or order stock.';
            } else {
                throw new DomainException('Request context is unavailable.');
            }
            unset($_SESSION['part_request_forms'][$key]);
            $_SESSION['part_request_notice'] = $noticeText;
            header('Location: part_requests.php' . ($deviceId === null ? '' : '?device_id=' . $deviceId), true, 303);
            exit();
        } else {
            throw new DomainException('Review your change before saving.');
        }
    } else {
        $rawDevice = requestText($_GET, 'device_id');
        $rawRequest = requestText($_GET, 'request_id');
        if ($rawDevice !== '' && (!ctype_digit($rawDevice) || (int) $rawDevice < 1)) {
            unavailablePage('Choose a valid device.', 400);
        }
        if ($rawRequest !== '' && (!ctype_digit($rawRequest) || (int) $rawRequest < 1)) {
            unavailablePage('Choose a valid request.', 400);
        }
        $deviceId = $rawDevice !== '' ? (int) $rawDevice : null;
        $requestId = $rawRequest !== '' ? (int) $rawRequest : null;
        if ($requestId !== null) {
            $request = partRequestLoad($pdo, $user, $requestId);
            $deviceId = (int) $request['ticket_device_id'];
        }
        $key = bin2hex(random_bytes(24));
        $_SESSION['part_request_forms'][$key] = [
            'device_id' => $deviceId,
            'request_id' => $requestId,
            'version' => $request ? partRequestVersion($request) : '',
            'expires' => time() + 1200,
        ];
        if (count($_SESSION['part_request_forms']) > 15) {
            array_shift($_SESSION['part_request_forms']);
        }
    }
    if ($deviceId !== null) {
        $device = partRequestDevice($pdo, $user, $deviceId);
    }
    if ($requestId !== null) {
        $request = partRequestLoad($pdo, $user, $requestId);
    }
    $page = partRequestList($pdo, $user, requestText($_GET, 'page', '1'), $requestId !== null ? null : $deviceId);
} catch (DomainException $problem) {
    $error = $problem->getMessage();
    $review = false;
    if (isset($key)) {
        unset($_SESSION['part_request_forms'][$key]['values']);
    }
    $page = partRequestList($pdo, $user, requestText($_GET, 'page', '1'));
} catch (Throwable $problem) {
    error_log('RepairTrack part requests: ' . get_class($problem));
    unavailablePage('Part requests are temporarily unavailable. No partial change was saved.');
}
require __DIR__ . '/../views/parts/requests.php';
