<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications | RepairTrack</title>
  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/workspace-design.css">
  <link rel="stylesheet" href="../css/reports.css">
  <script src="../js/workspace-design.js" defer></script>
</head>
<body class="rt-layout">
<?php
$workspaceUser = $user;
$currentSection = 'notifications';
require __DIR__ . '/../views/workspace/navigation.php';
?>
<main id="rt-main" class="rt-content report-page" tabindex="-1">
  <header class="report-header">
    <h1>Notifications</h1>
    <p>Only updates routed to your account and assigned work appear here.</p>
    <p><strong><?= (int) $unreadCount ?></strong> unread notification<?= $unreadCount === 1 ? '' : 's' ?></p>
  </header>

  <section class="report-panel" aria-label="Staff notifications">
    <?php if (!$notifications['total']): ?>
      <p>No notifications yet. Updates will appear here when an event involves your role and branch.</p>
    <?php else: ?>
      <ul class="activity-list">
        <?php foreach ($notifications['rows'] as $item): ?>
          <li>
            <div>
              <strong><?= h($item['title']) ?></strong>
              <p><?= h($item['message']) ?></p>
              <time><?= h(workspaceDate($item['created_at'])) ?> PHT</time>
            </div>
            <div class="notification-actions">
              <a href="<?= h($item['target_path']) ?>">Open</a>
              <?php if ($item['read_at'] === null): ?>
                <form method="post" action="notifications.php?page=<?= (int) $notifications['page'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="notification_id" value="<?= (int) $item['staff_notification_id'] ?>">
                  <button type="submit">Mark read</button>
                </form>
              <?php else: ?>
                <span>Read</span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <nav class="report-pager" aria-label="Notification pages">
        <?php if ($notifications['page'] > 1): ?>
          <a href="notifications.php?page=<?= $notifications['page'] - 1 ?>">Previous</a>
        <?php endif; ?>
        <span><?= $notifications['from'] ?>–<?= $notifications['to'] ?> of <?= $notifications['total'] ?></span>
        <?php if ($notifications['page'] < $notifications['pages']): ?>
          <a href="notifications.php?page=<?= $notifications['page'] + 1 ?>">Next</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </section>
</main>
</body>
</html>