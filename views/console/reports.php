<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($definition['title']) ?> | RepairTrack</title>
  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/workspace-design.css">
  <link rel="stylesheet" href="../css/reports.css">
  <script src="../js/workspace-design.js" defer></script>
  <script src="../js/workspace.js" defer></script>
</head>
<body class="rt-layout">
<?php
$workspaceUser = $user;
$currentSection = 'reports';
require __DIR__ . '/../workspace/navigation.php';
?>
<main id="rt-main" class="rt-content report-page" tabindex="-1">
  <header class="report-header">
    <h1><?= h($definition['title']) ?></h1>
    <p><?= h($definition['scope']) ?></p>
  </header>

  <?php if ($problem === 'branch'): ?>
    <section class="report-panel report-message">
      <h2>Branch unavailable</h2>
      <p>This branch is outside your current assignments.</p>
      <a href="<?= h(staffReportLink($period['days'], null)) ?>">View my assigned branches</a>
    </section>
  <?php elseif ($problem === 'unavailable'): ?>
    <section class="report-panel report-message">
      <h2>Report temporarily unavailable</h2>
      <p>The reporting records could not be loaded.</p>
      <a href="">Retry report</a>
    </section>
  <?php elseif (!$branches): ?>
    <section class="report-panel report-message">
      <h2>No active branch assigned</h2>
      <p>Ask an administrator to assign your account to an active branch to see its reports.</p>
    </section>
  <?php else: ?>
    <form class="report-filters" action="reports.php" method="get" aria-label="Report filters">
      <label>Reporting period
        <select name="days">
          <?php foreach ([7, 30, 90] as $days): ?>
            <option value="<?= $days ?>" <?= $period['days'] === $days ? 'selected' : '' ?>>Last <?= $days ?> days</option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if (count($branches) > 1): ?>
        <label>Branch
          <select name="branch">
            <option value="">All assigned branches</option>
            <?php foreach ($branches as $branch): ?>
              <option value="<?= (int) $branch['branch_id'] ?>" <?= $branchId === (int) $branch['branch_id'] ? 'selected' : '' ?>>
                <?= h($branch['branch_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php else: ?>
        <input type="hidden" name="branch" value="<?= (int) $branches[0]['branch_id'] ?>">
        <p class="report-single-branch"><span>Branch</span><?= h($branches[0]['branch_name']) ?></p>
      <?php endif; ?>
      <button>Apply filters</button>
    </form>

    <section class="report-summary" aria-labelledby="period-heading">
      <div class="report-section-head">
        <h2 id="period-heading">Period totals</h2>
        <p><?= h($period['start']->format('M j, Y')) ?> – <?= h($period['today']->format('M j, Y')) ?> · Philippine time</p>
      </div>
      <dl class="report-kpis">
        <?php foreach ($metrics as $key => [$label, $hint]): ?>
          <div>
            <dt><?= h($label) ?></dt>
            <dd><?= h(staffReportValue($key, $report['totals'][$key])) ?></dd>
            <dd class="report-hint"><?= h($hint) ?></dd>
            <?php if ($key === 'returned' && $report['averageHours'] !== null): ?>
              <dd class="report-hint"><strong><?= number_format($report['averageHours'], 1) ?> h</strong> average intake to return
                (<?= number_format($report['timedReturns']) ?> timed <?= $report['timedReturns'] === 1 ? 'return' : 'returns' ?>).</dd>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </dl>
    </section>

    <div class="report-panels">
      <section class="report-panel" aria-labelledby="trend-heading">
        <div class="report-section-head">
          <h2 id="trend-heading"><?= h($metrics[$primaryMetric][0]) ?></h2>
          <p><?= h(match ($period['days']) { 30 => '5-day totals', 90 => '15-day totals', default => 'Daily totals' }) ?></p>
        </div>
        <?php if ($peak > 0): ?>
          <p class="report-scale">Peak <?= h(staffReportValue($primaryMetric, $peak)) ?></p>
          <div class="report-chart" style="--bars: <?= count($chart) ?>">
            <?php foreach ($chart as $bar):
                $value = staffReportValue($primaryMetric, $bar['total']);
                $description = $bar['date'] . ' · ' . $metrics[$primaryMetric][0] . ' · ' . $value;
                $height = round(100 * $bar['total'] / $peak, 2);
            ?>
              <details class="trend-point report-point" name="report-bars">
                <summary aria-label="<?= h($description) ?>">
                  <span class="report-track" aria-hidden="true"><span style="--level: <?= $height ?>%"></span></span>
                  <span class="report-tick"><?= h($bar['label']) ?></span>
                </summary>
                <span class="report-tip"><?= h($bar['date']) ?><strong><?= h($value) ?></strong></span>
              </details>
            <?php endforeach; ?>
          </div>
          <a class="report-table-link" href="#daily">View daily figures <span aria-hidden="true">↓</span></a>
        <?php else: ?>
          <p class="report-empty"><?= max($report['totals']) === 0
              ? 'No recorded activity in this period.'
              : 'No ' . h(strtolower($metrics[$primaryMetric][0])) . ' in this period.' ?></p>
          <?php if ($period['days'] < 90): ?>
            <a class="report-table-link" href="<?= h(staffReportLink(90, $branchId)) ?>">View the last 90 days</a>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="report-panel" aria-labelledby="snapshot-heading">
        <?php if ($role === 'CASHIER'): ?>
          <div class="report-section-head"><h2 id="snapshot-heading">Outstanding balances</h2><p>Current</p></div>
          <p class="report-balance"><?= h(staffReportValue('cash_cents', $report['balance']['cents'])) ?></p>
          <p class="report-note"><?= number_format($report['balance']['bills']) ?> open <?= $report['balance']['bills'] === 1 ? 'bill' : 'bills' ?> with a peso balance.</p>
          <a class="report-table-link" href="staff_home.php?view=cash">Open cash desk <span aria-hidden="true">→</span></a>
        <?php else: ?>
          <div class="report-section-head">
            <h2 id="snapshot-heading"><?= $role === 'TECHNICIAN' ? 'Assigned workload' : 'Device statuses' ?></h2><p>Current</p>
          </div>
          <?php if (!$report['statusCounts']): ?>
            <p class="report-note"><?= $role === 'TECHNICIAN' ? 'No active devices assigned.' : 'No issued devices in this branch selection.' ?></p>
          <?php else: ?>
            <?php $statusTotal = (int) array_sum(array_column($report['statusCounts'], 'total')); ?>
            <details class="report-status">
              <summary>
                <span><strong><?= number_format($statusTotal) ?></strong> <?= $role === 'TECHNICIAN' ? 'active ' : '' ?><?= $statusTotal === 1 ? 'device' : 'devices' ?></span>
                <span>By status <span class="report-caret" aria-hidden="true">▾</span></span>
              </summary>
              <dl>
                <?php foreach ($report['statusCounts'] as $status): ?>
                  <div><dt><?= h(dashboardStatusLabel($status['repair_status'])) ?></dt><dd><?= number_format((int) $status['total']) ?></dd></div>
                <?php endforeach; ?>
              </dl>
            </details>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>

    <?php if (max($report['totals']) > 0): ?>
      <section class="report-panel report-daily" id="daily" tabindex="-1" aria-labelledby="daily-heading">
        <div class="report-section-head"><h2 id="daily-heading">Daily breakdown</h2><p>Newest first · Philippine time</p></div>
        <div class="report-table-wrap" role="region" aria-label="Daily figures" tabindex="0">
          <table>
            <caption class="report-sr-only">Daily totals for the selected period and branches</caption>
            <thead><tr>
              <th scope="col">Date</th>
              <?php foreach ($metrics as [$label]): ?><th scope="col"><?= h($label) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
              <?php foreach ($page['rows'] as $day => $values): $date = new DateTimeImmutable($day); ?>
                <tr>
                  <th scope="row"><time datetime="<?= h($day) ?>"><?= h($date->format('M j')) ?><span class="report-year"> <?= h($date->format('Y')) ?></span></time></th>
                  <?php foreach ($values as $key => $value): ?><td><?= h(staffReportValue($key, $value)) ?></td><?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <nav class="report-pager" aria-label="Daily breakdown pages">
          <span>Page <?= $page['number'] ?> of <?= $page['pages'] ?></span>
          <?php if ($page['number'] > 1): ?>
            <a rel="prev" href="<?= h(staffReportLink($period['days'], $branchId, $page['number'] - 1)) ?>#daily">Previous</a>
          <?php endif; ?>
          <?php if ($page['number'] < $page['pages']): ?>
            <a rel="next" href="<?= h(staffReportLink($period['days'], $branchId, $page['number'] + 1)) ?>#daily">Next</a>
          <?php endif; ?>
        </nav>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
