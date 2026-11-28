<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/staff/locations.php';
startAppSession();
$user = requireRoles(['ADMIN', 'MANAGER']);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$actorId = (int) $user['user_id'];
$values = locationReadValues([]);
$errors = [];
$review = false;
$formKey = '';
$notice = $_SESSION['location_notice'] ?? '';
unset($_SESSION['location_notice']);
try {
    $pdo = getPDO();
    $branches = intakeBranches($pdo, $actorId);
    foreach ($_SESSION['location_forms'] ?? [] as $key => $form) {
        if ($form['expires'] < time()) {
            unset($_SESSION['location_forms'][$key]);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('This form expired. Reload locations and try again.', 403);
        }
        $formKey = requestText($_POST, 'form_key');
        $form = $_SESSION['location_forms'][$formKey] ?? null;
        if (!$form) {
            unavailablePage('This form was already used or expired. Reload locations.', 409);
        }
        $intent = requestText($_POST, 'intent');
        if ($intent === 'review') {
            $values = locationReadValues($_POST);
            $errors = locationValidate($values);
            // Give actionable validation errors before review; writing always rechecks scope.
            $allowed = array_map('intval', array_column($branches, 'branch_id'));
            if (!in_array((int) $values['branch_id'], $allowed, true)) {
                $errors['branch_id'] = 'Choose one of your active assigned branches.';
            }
            unset($_SESSION['location_forms'][$formKey]['values']);
            if (!$errors) {
                $_SESSION['location_forms'][$formKey]['values'] = $values;
                $review = true;
            }
        } elseif ($intent === 'edit') {
            $values = $form['values'] ?? locationReadValues([]);
            unset($_SESSION['location_forms'][$formKey]['values']);
        } elseif ($intent === 'confirm' && isset($form['values'])) {
            locationCreate($pdo, $actorId, $form['values']);
            unset($_SESSION['location_forms'][$formKey]);
            $_SESSION['location_notice'] = 'Storage location created. It is available for future intake issuance.';
            header('Location: locations.php', true, 303);
            exit();
        } else {
            throw new DomainException('Review the location before saving.');
        }
    } else {
        $formKey = bin2hex(random_bytes(24));
        $_SESSION['location_forms'][$formKey] = ['expires' => time() + 1200];
        if (count($_SESSION['location_forms']) > 12) {
            array_shift($_SESSION['location_forms']);
        }
    }
    $list = locationPage($pdo, $actorId, requestText($_GET, 'page', '1'));
} catch (DomainException $error) {
    $errors['general'] = $error->getMessage();
    $review = false;
    if (isset($formKey)) {
        unset($_SESSION['location_forms'][$formKey]['values']);
    }
    $list = locationPage($pdo, $actorId, requestText($_GET, 'page', '1'));
} catch (Throwable $error) {
    error_log('RepairTrack locations: ' . get_class($error));
    unavailablePage('Location setup is temporarily unavailable. No partial change was saved.');
}
require __DIR__ . '/../views/staff/locations.php';
