<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cash and handover | RepairTrack</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/staff.css">
    <link rel="stylesheet" href="../css/console.css">
    <link rel="stylesheet" href="../css/workspace.css">
    <link rel="stylesheet" href="../css/payments.css">
</head>
<body class="dashboard-body">
<a class="skip-link" href="#cash-content">Skip to cash record</a>
<main id="cash-content" class="staff-page workspace payments-page">
    <header class="staff-heading">
        <div>
            <p class="eyebrow">RepairTrack · <?= h(staffRoles()[$user['role']] ?? 'Cash desk') ?></p>
            <h1><?= $ticket ? 'Issue final bill' : 'Cash and handover' ?></h1>
            <p>Record counter cash and return repaired devices with a clear audit trail.</p>
        </div>
        <div class="staff-actions">
            <a class="secondary-btn" href="staff_home.php?view=cash">Back to cash desk</a>
            <details class="console-account">
                <summary><?= h($user['full_name']) ?> · Account</summary>
                <p><?= h($user['email']) ?></p>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <button class="secondary-btn">Log out</button>
                </form>
            </details>
        </div>
    </header>

    <?php if ($notice): ?><p class="staff-notice" role="status"><?= h($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="field-error" role="alert"><?= h($error) ?></p><?php endif; ?>

    <?php if ($ticket): ?>
        <?php
        $issueEligible = $ticket['case_state'] === 'OPEN' && $ticket['devices'];
        foreach ($ticket['devices'] as $device) {
            $issueEligible = $issueEligible &&
                $device['repair_status'] === 'READY_FOR_PICKUP' &&
                $device['custody_state'] === 'AT_LOCATION' &&
                $device['physical_location_id'] &&
                (int) $device['location_branch_id'] === (int) $ticket['accountable_branch_id'] &&
                $device['quote_status'] === 'APPROVED';
        }
        ?>
        <section class="dashboard-card">
            <p class="eyebrow"><?= h($ticket['branch_name']) ?></p>
            <h2>Ticket <?= h($ticket['ticket_code']) ?></h2>
            <?php if ($pageAction === 'cancel'): ?>
                <p>Return every listed device as-is. Only the agreed diagnostic fee appears on the bill.</p>
            <?php else: ?>
            <p>One bill will include each device's latest approved estimate. No discounts or taxes are added here.</p>
            <?php endif; ?>
            <div class="payments-table-wrap">
                <table class="staff-table">
                    <caption class="sr-only">Devices and estimate status for this ticket</caption>
                    <thead><tr><th scope="col">Device</th><th scope="col">Repair status</th><th scope="col">Location</th><th scope="col">Latest estimate</th></tr></thead>
                    <tbody>
                    <?php foreach ($ticket['devices'] as $device): ?>
                        <tr>
                            <td><?= h($device['item_code'] ?: $device['device_type_snapshot']) ?><small><?= h(trim(($device['make_snapshot'] ?? '') . ' ' . ($device['model_snapshot'] ?? ''))) ?></small></td>
                            <td><?= h(dashboardStatusLabel($device['repair_status'])) ?></td>
                            <td><?= h($device['location_name'] ?: 'No branch storage location') ?></td>
                            <td><?= h(str_replace('_', ' ', $device['quote_status'] ?? 'NOT PREPARED')) ?><?php if ($device['total_amount'] !== null): ?><small>PHP <?= number_format((float) $device['total_amount'], 2) ?></small><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($ticket['case_state'] !== 'OPEN'): ?>
                <p class="payments-note">This ticket is already closed.</p>
            <?php elseif ($pageAction === 'cancel'): ?>
                <form method="post" class="payments-action">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                    <?php if ($review): ?>
                        <h3>Review as-is return for all <?= count($ticket['devices']) ?> devices</h3>
                        <p>Total due: PHP <?= h(cashAmountText(cashAmountCents($formValues['amount']))) ?></p>
                        <p><?= h($formValues['reason']) ?></p>
                        <button class="secondary-btn" name="intent" value="edit">Edit details</button>
                        <button class="logout-btn" name="intent" value="save">Approve return and issue bill</button>
                    <?php else: ?>
                        <label>Agreed diagnostic fee (PHP, enter 0 for free return)
                            <input type="number" min="0" step="0.01" name="amount" required value="<?= h($formValues['amount'] ?? '') ?>">
                        </label>
                        <label>How did the customer agree?
                            <textarea name="reason" maxlength="240" required><?= h($formValues['reason'] ?? '') ?></textarea>
                        </label>
                        <label><input type="checkbox" name="confirm" value="yes" required> The customer agreed to this fee and return of all listed devices.</label>
                        <button class="logout-btn" name="intent" value="review">Review as-is return</button>
                    <?php endif; ?>
                </form>
            <?php elseif (!$issueEligible): ?>
                <p class="payments-note">Billing opens after every device is ready for pickup, physically at this branch, and linked to an approved estimate.</p>
            <?php elseif ($review && $pageAction === 'issue'): ?>
                <div class="payments-review">
                    <h3>Review final bill</h3>
                    <p>Each device is ready at <?= h($ticket['branch_name']) ?> and its latest estimate is approved.</p>
                    <form method="post" class="staff-actions">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                        <button class="secondary-btn" name="intent" value="edit">Go back</button>
                        <button class="logout-btn" name="intent" value="save">Issue final bill</button>
                    </form>
                </div>
            <?php else: ?>
                <form method="post" class="payments-action">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                    <label class="payments-check"><input type="checkbox" name="confirm" value="yes" required> I checked that every device is ready and every estimate is approved.</label>
                    <button class="logout-btn" name="intent" value="review">Review bill issuance</button>
                </form>
            <?php endif; ?>
            <?php if ($pageAction === 'issue' && !$review && $ticket['case_state'] === 'OPEN'): ?>
                <a class="secondary-btn" href="?ticket_id=<?= (int) $ticket['ticket_id'] ?>&amp;cash_action=cancel">Review whole-ticket cancellation</a>
            <?php endif; ?>
        </section>

    <?php elseif ($bill): ?>
        <?php
        $handoverBlockers = cashHandoverBlockers($bill, $handover);
        ?>
        <section class="dashboard-card">
            <div class="payments-heading-row">
                <div><p class="eyebrow"><?= h($bill['branch_name']) ?> · <?= h($bill['ticket_code']) ?></p><h2><?= h($bill['bill_number'] ?: 'Unnumbered bill') ?></h2></div>
                <span class="workspace-status"><?= h(ucfirst(strtolower($bill['bill_status']))) ?></span>
            </div>
            <div class="payments-summary">
                <div><span>Original bill total</span><strong>PHP <?= number_format((float) $bill['total_amount'], 2) ?></strong></div>
                <div><span>Cash recorded</span><strong>PHP <?= number_format((float) $bill['amount_paid'], 2) ?></strong></div>
                <div><span>Corrected total</span><strong>PHP <?= number_format($bill['corrected_cents'] / 100, 2) ?></strong></div>
                <div><span>Refunds recorded</span><strong>PHP <?= number_format($bill['refunded_cents'] / 100, 2) ?></strong></div>
                <div><span>Refund due</span><strong>PHP <?= number_format($bill['refund_due_cents'] / 100, 2) ?></strong></div>
                <div><span>Balance due</span><strong>PHP <?= number_format($bill['balance_cents'] / 100, 2) ?></strong></div>
            </div>
            <div class="staff-actions">
                <?php if ($bill['bill_status'] === 'ISSUED' && cashAmountCents((string) $bill['amount_paid']) > 0 &&
                    in_array($user['role'], ['ADMIN', 'MANAGER'], true)): ?>
                    <a class="secondary-btn" href="cash.php?bill_id=<?= (int) $bill['bill_id'] ?>&amp;cash_action=correction">Correct paid bill</a>
                <?php endif; ?>
                <?php if ($bill['bill_status'] === 'ISSUED' && $bill['refund_due_cents'] > 0 &&
                    in_array($user['role'], ['ADMIN', 'CASHIER'], true)): ?>
                    <a class="secondary-btn" href="cash.php?bill_id=<?= (int) $bill['bill_id'] ?>&amp;cash_action=refund">Record cash refund</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-card">
            <h2>Bill items</h2>
            <div class="payments-table-wrap">
                <table class="staff-table">
                    <caption class="sr-only">Final bill line items</caption>
                    <thead><tr><th scope="col">Device</th><th scope="col">Item</th><th scope="col" class="numeric">Quantity</th><th scope="col" class="numeric">Unit price</th><th scope="col" class="numeric">Line total</th></tr></thead>
                    <tbody>
                    <?php foreach ($bill['items'] as $item): ?>
                        <tr><td><?= h($item['item_code'] ?: 'Ticket item') ?></td><td><?= h($item['description']) ?></td><td class="numeric"><?= h($item['quantity']) ?></td><td class="numeric">PHP <?= number_format((float) $item['unit_price'], 2) ?></td><td class="numeric">PHP <?= number_format((float) $item['line_total'], 2) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($bill['payments']): ?>
        <section class="dashboard-card">
            <h2>Cash collection history</h2>
            <div class="payments-table-wrap">
                <table class="staff-table">
                    <caption class="sr-only">Recorded counter cash collections</caption>
                    <thead><tr><th scope="col">Received (Philippine time)</th><th scope="col">Collected by</th><th scope="col">Method</th><th scope="col" class="numeric">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($bill['payments'] as $payment): ?>
                        <tr><td><?= h(workspaceDate($payment['received_at'])) ?></td><td><?= h($payment['collector']) ?></td><td>Counter cash</td><td class="numeric">PHP <?= number_format((float) $payment['amount'], 2) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($bill['changes']): ?>
            <section class="dashboard-card">
                <h2>Corrections and refunds</h2>
                <ul>
                    <?php foreach ($bill['changes'] as $change): ?>
                        <li><strong><?= $change['change_type'] === 'CORRECTION' ? 'Corrected total' : 'Cash refunded' ?>
                            PHP <?= h($change['amount']) ?></strong> · <?= h($change['full_name']) ?>
                            · <?= h(workspaceDate($change['created_at'])) ?><br><?= h($change['reason']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (in_array($pageAction, ['correction', 'refund'], true)): ?>
            <?php $isRefund = $pageAction === 'refund'; ?>
            <section class="dashboard-card payments-next-action">
                <h2><?= $isRefund ? 'Record cash refund' : 'Correct paid bill' ?></h2>
                <form method="post" class="staff-actions">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                    <?php if ($review): ?>
                        <p><?= $isRefund ? 'Cash returned' : 'New total' ?>:
                            <strong>PHP <?= h($formValues['amount']) ?></strong><br><?= h($formValues['reason']) ?></p>
                        <button class="secondary-btn" name="intent" value="edit">Back to edit</button>
                        <button class="logout-btn" name="intent" value="save">Confirm <?= $isRefund ? 'refund' : 'correction' ?></button>
                    <?php else: ?>
                        <label><?= $isRefund ? 'Cash returned (PHP)' : 'New bill total (PHP)' ?>
                            <input type="number" name="amount" step="0.01" min="<?= $isRefund ? '0.01' : '0' ?>"
                                value="<?= h($formValues['amount'] ?? '') ?>" required>
                        </label>
                        <label><?= $isRefund ? 'Refund reason or reference' : 'Reason and customer approval reference, when required' ?>
                            <textarea name="reason" maxlength="500" required><?= h($formValues['reason'] ?? '') ?></textarea>
                        </label>
                        <label class="payments-check"><input type="checkbox" name="confirm" value="yes" required>
                            <?= $isRefund ? 'I counted and returned this cash to the customer.' : 'I verified the new total and recorded any required customer approval.' ?>
                        </label>
                        <button class="logout-btn" name="intent" value="review">Review</button>
                    <?php endif; ?>
                </form>
            </section>
        <?php elseif ($pageAction === 'payment'): ?>
            <section class="dashboard-card payments-next-action">
                <h2><?= $review ? 'Review cash received' : 'Record counter cash' ?></h2>
                <?php if ($review && $pageAction === 'payment'): ?>
                    <p>Cash to record: <strong>PHP <?= number_format((float) $formValues['amount'], 2) ?></strong></p>
                    <p>The remaining balance after this payment will be PHP <?= number_format(($bill['balance_cents'] - cashAmountCents($formValues['amount'])) / 100, 2) ?>.</p>
                    <form method="post" class="staff-actions">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                        <button class="secondary-btn" name="intent" value="edit">Go back</button><button class="logout-btn" name="intent" value="save">Confirm cash received</button>
                    </form>
                <?php else: ?>
                    <p>Record only the amount physically counted at the counter. You can record the bill in more than one payment.</p>
                    <form method="post" class="payments-action">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                        <label for="cash-amount">Amount received (PHP)</label>
                        <input id="cash-amount" name="amount" inputmode="decimal" autocomplete="off" pattern="[0-9]+(\.[0-9]{1,2})?" value="<?= h($formValues['amount'] ?? cashAmountText($bill['balance_cents'])) ?>" required>
                        <label class="payments-check"><input type="checkbox" name="confirm" value="yes" required> I counted this physical cash and confirmed the amount with the customer.</label>
                        <button class="logout-btn" name="intent" value="review">Review payment</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php elseif ($pageAction === 'handover'): ?>
            <section class="dashboard-card payments-next-action">
                <h2><?= $review ? 'Review customer handover' : 'Return repaired devices' ?></h2>
                <?php if ($handoverBlockers): ?>
                    <ul class="payments-note">
                        <?php foreach ($handoverBlockers as $blocker): ?>
                            <li><?= h($blocker) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($review && $pageAction === 'handover'): ?>
                    <dl class="staff-review"><div><dt>Recipient</dt><dd><?= h($formValues['recipient']) ?> · <?= $formValues['relationship'] === 'CUSTOMER' ? 'Customer' : 'Authorized representative' ?></dd></div><div><dt>Device check</dt><dd>Confirmed with recipient</dd></div></dl>
                    <form method="post" class="staff-actions">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                        <button class="secondary-btn" name="intent" value="edit">Go back</button><button class="logout-btn" name="intent" value="save">Confirm handover and close ticket</button>
                    </form>
                <?php else: ?>
                    <p>Confirm that the customer or their authorized representative checked the device and returned accessories.</p>
                    <form method="post" class="payments-action">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="form_key" value="<?= h($formKey) ?>">
                        <label for="recipient">Received by</label><input id="recipient" name="recipient" maxlength="160" required value="<?= h($formValues['recipient'] ?? '') ?>">
                        <label for="relationship">Relationship</label><select id="relationship" name="relationship"><option value="CUSTOMER" <?= ($formValues['relationship'] ?? '') === 'CUSTOMER' ? 'selected' : '' ?>>Customer</option><option value="AUTHORIZED_REPRESENTATIVE" <?= ($formValues['relationship'] ?? '') === 'AUTHORIZED_REPRESENTATIVE' ? 'selected' : '' ?>>Authorized representative</option></select>
                        <label class="payments-check"><input type="checkbox" name="condition_confirmed" value="yes" required> Recipient confirmed the device and accessories were checked.</label>
                        <button class="logout-btn" name="intent" value="review">Review handover</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php elseif ($bill['case_state'] === 'CLOSED'): ?>
            <section class="dashboard-card"><h2>Ticket closed</h2><p>All devices were returned to the customer. This page remains available as a cash and handover record.</p></section>
        <?php else: ?>
            <section class="dashboard-card"><h2>Cash action unavailable</h2><p>The bill is voided or the ticket is no longer open.</p></section>
        <?php endif; ?>
    <?php else: ?>
        <section class="dashboard-card">
            <h2>Find an issued bill</h2>
            <p>Use the cash desk to search by ticket code, tracking code, or bill number. Managers can issue a final bill from a ready device record.</p>
            <a class="logout-btn" href="staff_home.php?view=cash">Open cash desk</a>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
