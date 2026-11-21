<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff activity | RepairTrack</title>
  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/workspace-design.css">
  <link rel="stylesheet" href="../css/reports.css">
  <script src="../js/workspace-design.js" defer></script>
</head>
<body class="rt-layout">
<?php
$workspaceUser = $user;
$currentSection = 'activity';
require __DIR__ . '/../workspace/navigation.php';
?>
<main id="rt-main" class="rt-content report-page" tabindex="-1">
  <header class="report-header">
    <h1>Staff activity</h1>
    <p>Choose a category to see updates for your assignments. Read entries stay in history.</p>
  </header>
  <form class="report-filters" method="get">
    <label>Category<select name="group">
      <?php foreach ($groups as $option): ?>
        <option <?= $group === $option ? 'selected' : '' ?> value="<?= h($option) ?>"><?= h(ucfirst($option)) ?><?= isset($activityCounts[$option]) ? ' · ' . (int) $activityCounts[$option] . ' unread' : '' ?></option>
      <?php endforeach; ?>
    </select></label>
    <label>Show<select name="view">
      <option value="inbox" <?= $mode === 'inbox' ? 'selected' : '' ?>>Unread inbox</option>
      <option value="history" <?= $mode === 'history' ? 'selected' : '' ?>>Full history</option>
    </select></label>
    <label>Ticket or reference<input type="search" name="q" maxlength="100" value="<?= h($search) ?>"></label>
    <button>Apply filters</button>
  </form>
  <section class="report-panel">
    <h2><?= h(ucfirst($group)) ?> · <?= $mode === 'inbox' ? 'Unread' : 'History' ?></h2>
    <?php if (!$events['total']): ?>
      <p><?= $search !== '' ? 'No matching entries.' : ($mode === 'inbox' ? 'No unread ' . $group . ' updates.' : 'No activity recorded yet.') ?></p>
      <?php if ($search !== ''): ?><a href="?group=<?= h($group) ?>&amp;view=<?= h($mode) ?>">Clear search</a><?php endif; ?>
    <?php else: ?>
      <ul class="activity-list">
        <?php foreach ($events['rows'] as $row): ?>
          <li>
            <div><strong><?= h($row['ticket_code']) ?> · <?= h((string) $row['reference']) ?></strong>
              <p><?= h($group === 'repairs' ? ucfirst(strtolower(str_replace('_', ' ', $row['detail']))) : $row['detail']) ?></p>
              <time><?= h(workspaceDate($row['event_at'])) ?> PHT</time>
            </div>
            <?php if ($row['seen'] === null): ?>
              <form method="post" action="<?= h($link($events['page'])) ?>">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="event_id" value="<?= h((string) $row['event_id']) ?>">
                <button>Mark read</button>
              </form>
            <?php else: ?><span>Read</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <nav class="report-pager" aria-label="Activity pages">
        <?php if ($events['page'] > 1): ?><a href="<?= h($link($events['page'] - 1)) ?>">Previous</a><?php endif; ?>
        <span><?= $events['from'] ?>–<?= $events['to'] ?> of <?= $events['total'] ?></span>
        <?php if ($events['page'] < $events['pages']): ?><a href="<?= h($link($events['page'] + 1)) ?>">Next</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
