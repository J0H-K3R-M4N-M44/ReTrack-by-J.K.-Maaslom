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
<header class="public-header"><a href="index.php">RepairTrack</a><nav aria-label="Customer navigation"><a href="index.php#prepare">Before your visit</a><a href="learn.php">Customer guide</a><a href="admin/login.php">Staff sign in</a></nav></header>
<main id="main" class="public-page" tabindex="-1">
    <p class="eyebrow">Your repair, clearly explained</p>
    <h1><?= $repair ? 'Your repair update' : 'Track your repair' ?></h1>
    <?php if ($error): ?><p id="tracking-error" class="public-notice" role="alert"><?= h($error) ?></p><?php endif; ?>
    <?php if (!$repair): ?>
    <section class="public-card">
        <h2>Use your tracking code and PIN</h2>
        <p>Enter the code your service branch shared with you. The RT receipt number alone cannot open a repair.</p>
        <form method="post" id="tracking-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <label for="tracking-code">Tracking code</label>
            <input id="tracking-code" name="tracking_code" required minlength="5" maxlength="64" pattern="([a-fA-F0-9]{5}|[a-fA-F0-9]{64})" value="<?= h($enteredCode) ?>" title="Use five hexadecimal characters or your original 64-character private code." autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="tracking-help<?= $errorField === 'tracking-code' ? ' tracking-error' : '' ?>" <?= $errorField === 'tracking-code' ? 'aria-invalid="true" autofocus' : '' ?>>
            <p id="tracking-help">Enter the five-character code from your branch (for example A3F90).</p>
            <details><summary>Using an older tracking code?</summary><p>Enter your full 64-character private code. You can leave the PIN blank.</p></details>
            <label for="portal-pin">Portal PIN</label>
            <input id="portal-pin" name="portal_pin" type="password" inputmode="numeric" minlength="4" maxlength="4" pattern="[0-9]{4}" autocomplete="off" aria-describedby="pin-help<?= $errorField === 'portal-pin' ? ' tracking-error' : '' ?>" <?= $errorField === 'portal-pin' ? 'aria-invalid="true" autofocus' : '' ?>>
            <p id="pin-help">Last four digits of the phone number recorded when your code was issued. If your number changed, ask the branch for new access.</p>
            <button class="public-button" type="submit">View repair</button>
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
    <section class="public-devices" aria-labelledby="public-devices-heading">
        <div class="public-section-heading">
            <h2 id="public-devices-heading">Devices on this ticket</h2>
            <p>Each device has its own repair stage, estimate and next step.</p>
        </div>
        <div class="public-grid">
        <?php foreach ($repair['devices'] as $deviceIndex => $device): ?>
            <section class="public-card public-device-card" aria-labelledby="public-device-title-<?= $deviceIndex + 1 ?>">
                <header class="public-device-heading">
                    <p class="eyebrow public-device-label">Device <?= $deviceIndex + 1 ?> of <?= count($repair['devices']) ?> · <?= h($device['item_code']) ?></p>
                    <h3 id="public-device-title-<?= $deviceIndex + 1 ?>"><?= h(dashboardDeviceLabel($device)) ?></h3>
                </header>
                <div class="public-device-status">
                    <p class="eyebrow public-stage-label">Current repair stage</p>
                    <p class="public-status"><?= h(publicStageLabel($device)) ?></p>
                </div>
                <h4>Device custody</h4>
                <p><?= h(publicCustody($device)) ?></p>
                <?php if ($device['estimate']): ?>
                <div class="public-estimate">
                    <h4>Current shared estimate</h4>
                    <p class="public-estimate-status"><?= h(publicEstimateLabel($device['estimate'])) ?> · Revision <?= (int) $device['estimate']['revision'] ?></p>
                    <p class="public-estimate-total"><span>Estimated total</span><strong><?= h($device['estimate']['currency']) ?> <?= h(number_format((float) $device['estimate']['total'], 2)) ?></strong></p>
                    <p class="public-estimate-note">This is not a bill, payment receipt or balance due. Staff record decisions; this page cannot approve estimates or accept payments.</p>
                </div>
                <?php else: ?>
                <p class="public-empty-estimate">No current estimate has been shared for this device.</p>
                <?php endif; ?>
                <h4>Next step</h4>
                <p><?= h(publicNextStep($device)) ?></p>
                <?php if ($device['public_summary']): ?>
                    <h4>Message from the service team</h4>
                    <p class="public-message"><?= h($device['public_summary']) ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
        </div>
    </section>
    <section class="public-card">
        <h2>Repair timeline</h2>
        <p>Newest updates across all devices first. Each update names the device it refers to. Times are shown in Philippine time.</p>
        <?php if (!$repair['events']): ?>
            <p class="public-empty-history">No customer updates have been published yet.</p>
        <?php endif; ?>
        <ol class="public-timeline">
        <?php foreach ($repair['events'] as $event): ?>
            <?php
                $eventTime = (new DateTimeImmutable($event['created_at'], new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone('Asia/Manila'));
            ?>
            <li>
                <article class="public-timeline-entry">
                    <h3 class="public-timeline-title">
                        <span><?= h(dashboardStatusLabel($event['to_status'])) ?></span>
                        <span class="public-timeline-device">Item <?= h($event['item_code']) ?></span>
                    </h3>
                    <p class="public-message"><?= h($event['public_summary'] ?: 'Repair stage updated.') ?></p>
                    <time datetime="<?= h($eventTime->format(DATE_ATOM)) ?>">Updated <?= h($eventTime->format('M j, Y')) ?> · <?= h($eventTime->format('g:i a')) ?></time>
                </article>
            </li>
        <?php endforeach; ?>
        </ol>
        <nav class="public-toolbar" aria-label="Timeline pages"><span>Showing <?= $repair[
            'history_page'
        ]['from'] ?>–<?= $repair['history_page']['to'] ?> of <?= $repair['history_page'][
     'total'
 ] ?></span>
        <?php foreach (
            [
                'Previous' => $repair['history_page']['page'] - 1,
                'Next' => $repair['history_page']['page'] + 1,
            ]
            as $label => $number
        ):
            if ($number < 1 || $number > $repair['history_page']['pages']) {
                continue;
            } ?>
            <a href="?history_page=<?= $number ?>"><?= h($label) ?></a>
        <?php
        endforeach; ?></nav>
    </section>
    <section class="public-card public-contact-card">
        <h2>Need help with this repair?</h2>
        <p>Contact <?= h($repair['branch_name']) ?> and quote <?= h($repair['ticket_code']) ?>.</p>
        <?php if ($repair['phone']): ?>
            <p class="public-branch-phone">Branch phone:
                <a aria-label="Call <?= h($repair['branch_name']) ?>" href="tel:<?= h(preg_replace('/[^0-9+]/', '', (string) $repair['phone'])) ?>"><?= h($repair['phone']) ?></a>
            </p>
        <?php else: ?>
            <p>A branch phone number is not listed on this tracking page. Use the contact details on your service receipt or visit the branch.</p>
        <?php endif; ?>
        <p>Please confirm collection arrangements and any amount due with staff. This page does not take payments.</p>
    </section>
    <?php endif; ?>
    <p><a href="index.php">Back to public home</a></p>
</main>
<script src="js/public-tracking.js"></script>
</body>
</html>
