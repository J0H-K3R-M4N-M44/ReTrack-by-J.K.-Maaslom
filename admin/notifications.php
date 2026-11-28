<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/notifications.php';

startAppSession();
$user = requireAccount();
header('Cache-Control: private, no-store');
$page = requestText($_GET, 'page', '1');

try {
    $pdo = getPDO();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('Reload notifications and try again.', 403);
        }
        $id = requestText($_POST, 'notification_id');
        if (!ctype_digit($id) || (int) $id < 1) {
            unavailablePage('Choose a notification.', 400);
        }
        notificationMarkRead($pdo, (int) $user['user_id'], (int) $id);
        header('Location: notifications.php?' . http_build_query(['page' => $page]), true, 303);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        unavailablePage('Open notifications from your workspace.', 405);
    }

    $unreadCount = notificationUnreadCount($pdo, (int) $user['user_id']);
    $notifications = notificationPage($pdo, (int) $user['user_id'], $page);
} catch (Throwable $error) {
    error_log('RepairTrack notifications: ' . $error->getMessage());
    unavailablePage('Notifications are temporarily unavailable. Ask your administrator to check the notification migration.');
}

require __DIR__ . '/../views/console/notifications.php';
