<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track your repair | RepairTrack</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/public.css">
</head>
<body class="public-body">
<a class="skip-link" href="#main">Skip to content</a>
<header class="public-header"><a href="index.php">RepairTrack</a><nav aria-label="Customer navigation"><a href="index.php#prepare">Before your visit</a><a href="admin/login.php">Staff sign in</a></nav></header>
<main id="main" class="public-page">
    <p class="eyebrow">Your repair, clearly explained</p>
    <h1><?= $repair ? 'Your repair update' : 'Track your repair' ?></h1>
    <?php if ($error): ?><p class="public-notice" role="alert"><?= h($error) ?></p><?php endif; ?>
    <?php if (!$repair): ?>
    <section class="public-card">
        <h2>Use your private tracking code</h2>
        <p>Enter the code your service branch shared with you. The RT receipt number alone cannot open a repair.</p>
        <form method="post" id="tracking-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <label for="tracking-code">Private tracking code</label>
            <input id="tracking-code" name="tracking_code" required minlength="64" maxlength="64" pattern="[a-fA-F0-9]{64}" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="tracking-help">
            <p id="tracking-help">Paste the 64-character code. Keep it private: anyone with this code can view this repair.</p>
            <button class="public-button">View repair</button>
        </form>
    </section>
    <?php else: ?>
    <div class="public-toolbar">
        <p><strong><?= h($repair['ticket_code']) ?></strong> · <?= h($repair['branch_name']) ?></p>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= h(
            csrfToken(),
        ) ?>"><button class="public-button secondary" name="action" value="close">Close tracking</button></form>
    </div>
    <p>Updates reflect staff records, not live GPS. Refresh this page for the latest saved update.</p>
    <div class="public-grid">
    <?php foreach ($repair['devices'] as $device): ?>
        <section class="public-card">
            <p class="eyebrow"><?= h($device['item_code']) ?></p>
            <h2><?= h(dashboardDeviceLabel($device)) ?></h2>
            <p class="public-status"><?= h(dashboardStatusLabel($device['repair_status'])) ?></p>
            <h3>Device custody</h3><p><?= h(publicCustody($device)) ?></p>
            <?php if (
                $device['public_summary']
            ): ?><h3>Message from the service team</h3><p class="public-message"><?= h(
    $device['public_summary'],
) ?></p><?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div>
    <section class="public-card">
        <h2>Repair timeline</h2>
        <p>Newest updates first. Times are shown in Philippine time.</p>
        <?php if (
            !$repair['events']
        ): ?><p>No customer updates have been published yet.</p><?php endif; ?>
        <ol class="public-timeline">
        <?php foreach ($repair['events'] as $event): ?>
            <li><strong><?= h(dashboardStatusLabel($event['to_status'])) ?></strong> · <?= h(
    $event['item_code'],
) ?>
                <p class="public-message"><?= h(
                    $event['public_summary'] ?: 'Repair stage updated.',
                ) ?></p>
                <time><?= h(
                    (new DateTimeImmutable($event['created_at'], new DateTimeZone('UTC')))
                        ->setTimezone(new DateTimeZone('Asia/Manila'))
                        ->format('M j, Y · g:i a'),
                ) ?></time>
            </li>
        <?php endforeach; ?>
        </ol>
        <?php if (
            $repair['has_more_events']
        ): ?><p>Showing the latest 50 updates. Ask the branch for older history.</p><?php endif; ?>
    </section>
    <section class="public-card"><h2>Need help with this repair?</h2><p>Contact <?= h(
        $repair['branch_name'],
    ) ?> and quote <?= h($repair['ticket_code']) ?>.</p><?php if (
    $repair['phone']
): ?><p>Branch phone: <?= h(
    $repair['phone'],
) ?></p><?php endif; ?><p>Please confirm collection arrangements and any amount due with staff. This page does not take payments.</p></section>
    <?php endif; ?>
    <p><a href="index.php">Back to public home</a></p>
</main>
<script src="js/public-tracking.js"></script>
</body>
</html>
