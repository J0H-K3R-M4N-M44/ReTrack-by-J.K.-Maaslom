<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/console/activity.php';

startAppSession();
$user = requireAccount();
header('Cache-Control: private, no-store');
$groups = activityGroups($user['role']);
$group = requestText($_GET, 'group') ?: ($groups[0] ?? '');
if (!in_array($group, $groups, true)) {
    unavailablePage('This activity category is outside your workspace.', 403);
}
$mode = requestText($_GET, 'view') === 'history' ? 'history' : 'inbox';
$page = requestText($_GET, 'page');
$search = mb_substr(trim(requestText($_GET, 'q')), 0, 100);
$link = static fn(int $number): string => '?' . http_build_query([
    'group' => $group, 'view' => $mode, 'q' => $search, 'page' => $number,
]);
try {
    $pdo = getPDO();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('Reload Staff activity and try again.', 403);
        }
        $id = requestText($_POST, 'event_id');
        if (!ctype_digit($id)) {
            unavailablePage('Select an activity entry.', 400);
        }
        activityMarkRead($pdo, $user, $group, $id);
        header('Location: ' . $link(max(1, (int) $page)), true, 303);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        unavailablePage('Open Staff activity from the menu.', 405);
    }
    [$source, $params] = activitySource($user, $group);
    $sql = "SELECT s.*, r.event_id AS seen FROM ($source) s
        LEFT JOIN staff_activity_reads r ON r.event_id = s.event_id AND r.user_id = ? AND r.event_group = ?
        WHERE (s.ticket_code LIKE ? ESCAPE '!' OR s.reference LIKE ? ESCAPE '!')";
    if ($mode === 'inbox') {
        $sql .= ' AND r.event_id IS NULL';
    }
    $pattern = likePattern($search);
    $events = dashboardPage($pdo, $sql, [...$params, $user['user_id'], $group, $pattern, $pattern],
        's.event_at DESC, s.event_id DESC', $page);
} catch (Throwable $error) {
    error_log('Staff activity: ' . $error->getMessage());
    unavailablePage('Activity is temporarily unavailable. Ask your administrator to check the staff activity migration.');
}
require __DIR__ . '/../views/console/activity.php';
