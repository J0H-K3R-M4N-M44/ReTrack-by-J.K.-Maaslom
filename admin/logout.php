<?php
require_once __DIR__ . '/../app/auth.php';
startAppSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    unavailablePage('Use the Log out button to end your session.', 405);
}
if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
    unavailablePage('This form expired. Reload the dashboard and try again.', 403);
}
logoutAdmin();
header('Location: login.php', true, 303);
exit();
