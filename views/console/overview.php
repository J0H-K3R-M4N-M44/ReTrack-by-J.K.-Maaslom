<?php
$rtStatusTotals = [];
foreach (($data['counts'] ?? []) as $rtCount) {
    $rtStatusTotals[(string) $rtCount['repair_status']] = (int) $rtCount['total'];
}
$rtStatusTotal = static fn(string $status): int => $rtStatusTotals[$status] ?? 0;
$rtActiveTotal = (int) ($data['page']['total'] ?? 0);
$rtDraftTotal = (int) ($overviewDrafts['total'] ?? 0);
$rtCashTotal = (int) ($overviewCash['total'] ?? 0);
$rtReturn = urlencode($returnQuery);

$rtCards = [];
$rtActions = [];
$rtRows = [];
$rtRowsType = 'device';
$rtRowsTitle = '';
$rtRowsNote = '';
$rtEmptyText = 'There are no matching records in your assigned scope.';

switch ($role) {
    case 'MANAGER':
    case 'ADMIN':
        $rtCards = [
            [
                'label' => 'Open repairs',
                'value' => $rtActiveTotal,
                'note' => 'Issued devices still in progress',
                'tip' => 'Counts issued devices in your assigned branches. Completed and cancelled devices are not included.',
                'icon' => 'repairs',
            ],
            [
                'label' => 'Awaiting customer decision',
                'value' => $rtStatusTotal('AWAITING_CUSTOMER_APPROVAL'),
                'note' => 'Approval step is still pending',
                'tip' => 'These devices are recorded as Awaiting customer approval. Staff must record the customer decision; this count does not approve a quote.',
                'icon' => 'decision',
            ],
            [
                'label' => 'Waiting for parts',
                'value' => $rtStatusTotal('AWAITING_PARTS'),
                'note' => 'Parts wait recorded by staff',
                'tip' => 'These devices are recorded as Awaiting parts. The count does not reserve stock, place an order or imply a supplier delivery date.',
                'icon' => 'parts',
            ],
            [
                'label' => 'Ready for pickup',
                'value' => $rtStatusTotal('READY_FOR_PICKUP'),
                'note' => 'Repair marked ready',
                'tip' => 'Ready for pickup is a repair status. It does not mean the customer has collected the device or that handover is complete.',
                'icon' => 'ready',
            ],
        ];
        $rtActions = [
            ['New intake', 'Create a draft repair intake.', 'intake_create.php', 'intake'],
            ['Repair queue', 'Review assignments and the oldest updates.', 'staff_home.php?view=queue', 'queue'],
            ['Saved drafts', number_format($rtDraftTotal) . ' intake drafts not yet issued.', 'staff_home.php?view=drafts', 'drafts'],
            ['Cash desk', number_format($rtCashTotal) . ' issued bill records in your branch scope.', 'staff_home.php?view=cash', 'cash'],
        ];
        $rtRows = $data['page']['rows'] ?? [];
        $rtRowsTitle = 'Repairs for review';
        $rtRowsNote = 'The queue shows the oldest last update first as an attention aid, not as an overdue score or ETA.';
        $rtEmptyText = 'No active repairs are currently listed in your assigned branches.';
        break;

    case 'INTAKE_AGENT':
        $rtCards = [
            [
                'label' => 'Saved intake drafts',
                'value' => $rtDraftTotal,
                'note' => 'Not yet issued as repair tickets',
                'tip' => 'Drafts are saved intake records. They do not appear in the customer repair portal until the ticket is issued.',
                'icon' => 'drafts',
            ],
            [
                'label' => 'Open repairs',
                'value' => $rtActiveTotal,
                'note' => 'Issued devices in assigned branches',
                'tip' => 'Counts open issued devices in the branches assigned to your account. This is a status overview, not a repair action.',
                'icon' => 'repairs',
            ],
            [
                'label' => 'Received',
                'value' => $rtStatusTotal('RECEIVED'),
                'note' => 'Awaiting diagnosis',
                'tip' => 'These devices are recorded as Received. A technician diagnosis has not yet been recorded.',
                'icon' => 'intake',
            ],
        ];
        $rtActions = [
            ['New intake', 'Register a device and capture its condition.', 'intake_create.php', 'intake'],
            ['Saved drafts', 'Reopen an incomplete intake record.', 'staff_home.php?view=drafts', 'drafts'],
            ['Repair queue', 'Check the current stage of issued devices.', 'staff_home.php?view=queue', 'queue'],
        ];
        $rtRows = $overviewDrafts['rows'] ?? [];
        $rtRowsType = 'draft';
        $rtRowsTitle = 'Saved drafts to continue';
        $rtRowsNote = 'Drafts remain private staff records until they are issued.';
        $rtEmptyText = 'No saved intake drafts are waiting for completion.';
        break;

    case 'TECHNICIAN':
        $rtCapacity = technicianActiveLimit();
        $rtRemaining = max(0, $rtCapacity - (int) $activeLoad);
        $rtCards = [
            [
                'label' => 'Assigned active devices',
                'value' => (int) $activeLoad,
                'note' => 'Your current workload',
                'tip' => 'Workload counts all open devices assigned to you, including those waiting for parts, awaiting a customer decision or ready for pickup until the repair is closed.',
                'icon' => 'repairs',
            ],
            [
                'label' => 'Remaining capacity',
                'value' => $rtRemaining,
                'note' => 'Slots under your configured limit',
                'tip' => 'This is a preview of remaining capacity. The server checks the limit again when a manager assigns work.',
                'icon' => 'capacity',
            ],
            [
                'label' => 'Waiting for parts',
                'value' => $rtStatusTotal('AWAITING_PARTS'),
                'note' => 'Your assigned devices',
                'tip' => 'These devices are recorded as Awaiting parts. This status does not mean stock has been reserved or ordered.',
                'icon' => 'parts',
            ],
            [
                'label' => 'Awaiting customer decision',
                'value' => $rtStatusTotal('AWAITING_CUSTOMER_APPROVAL'),
                'note' => 'Your assigned devices',
                'tip' => 'These devices are recorded as Awaiting customer approval. Do not proceed until the required approval is recorded.',
                'icon' => 'decision',
            ],
        ];
        $rtActions = [
            ['My repair queue', 'Open your assigned repair devices.', 'staff_home.php?view=queue', 'queue'],
            ['Status board', 'Review work grouped by repair stage.', 'staff_home.php?view=board', 'board'],
            ['Repair history', 'Find completed and cancelled devices.', 'staff_home.php?view=history', 'history'],
        ];
        $rtRows = $data['page']['rows'] ?? [];
        $rtRowsTitle = 'Devices assigned to you';
        $rtRowsNote = 'The oldest last update appears first. This helps you review work that may need attention; it is not an automatic deadline.';
        $rtEmptyText = 'No open devices are currently assigned to you.';
        break;

    case 'COURIER':
        $rtOpenTransfers = (int) ($overviewTransfers['total'] ?? 0);
        $rtHistoryTransfers = (int) ($overviewTransferHistory['total'] ?? 0);
        $rtCards = [
            [
                'label' => 'Open handovers',
                'value' => $rtOpenTransfers,
                'note' => 'In transit or exception',
                'tip' => 'Counts transfer items recorded as In transit or Exception in branches assigned to you. It is not a live GPS location or delivery ETA.',
                'icon' => 'transfers',
            ],
            [
                'label' => 'Transfer history',
                'value' => $rtHistoryTransfers,
                'note' => 'Received or cancelled items',
                'tip' => 'Received and cancelled transfer items remain available in history for reference. They are not part of the active handover count.',
                'icon' => 'history',
            ],
        ];
        $rtActions = [
            ['Transfer manifest', 'Review handovers in transit or marked with an exception.', 'staff_home.php?view=queue', 'transfers'],
            ['Device handovers', 'Open the handover workspace.', 'transfers.php', 'handovers'],
            ['Transfer history', 'Review completed or cancelled handovers.', 'staff_home.php?view=history', 'history'],
        ];
        $rtRows = $overviewTransfers['rows'] ?? [];
        $rtRowsType = 'transfer';
        $rtRowsTitle = 'Handovers awaiting receipt or review';
        $rtRowsNote = 'A transfer in this list is still in transit or has an exception recorded.';
        $rtEmptyText = 'No open handovers are currently listed for your assigned branches.';
        break;

    case 'CASHIER':
        $rtCards = [
            [
                'label' => 'Issued bill records',
                'value' => (int) ($overviewCash['total'] ?? 0),
                'note' => 'Available in your assigned branches',
                'tip' => 'This is a count of non-draft issued bills, including paid and unpaid records. It is not the total amount due.',
                'icon' => 'cash',
            ],
        ];
        $rtActions = [
            ['Cash desk', 'Search issued bills and record counter cash.', 'staff_home.php?view=cash', 'cash'],
        ];
        break;

    case 'VIEWER':
    default:
        $rtCards = [
            [
                'label' => 'Visible open repairs',
                'value' => $rtActiveTotal,
                'note' => 'Read-only branch overview',
                'tip' => 'Counts open issued devices within your assigned branch scope. Customer contact details and private notes remain hidden.',
                'icon' => 'repairs',
            ],
            [
                'label' => 'Awaiting customer decision',
                'value' => $rtStatusTotal('AWAITING_CUSTOMER_APPROVAL'),
                'note' => 'Recorded repair stage',
                'tip' => 'This stage indicates that a customer decision is pending. The overview does not expose private customer contact information.',
                'icon' => 'decision',
            ],
            [
                'label' => 'Waiting for parts',
                'value' => $rtStatusTotal('AWAITING_PARTS'),
                'note' => 'Recorded repair stage',
                'tip' => 'This stage is a staff-recorded status. It does not provide supplier tracking or a delivery date.',
                'icon' => 'parts',
            ],
            [
                'label' => 'Ready for pickup',
                'value' => $rtStatusTotal('READY_FOR_PICKUP'),
                'note' => 'Recorded repair stage',
                'tip' => 'This is a repair status only. It does not confirm customer collection or completed handover.',
                'icon' => 'ready',
            ],
        ];
        $rtActions = [
            ['Repair queue', 'Read the open repair records in your assigned branches.', 'staff_home.php?view=queue', 'queue'],
            ['Status board', 'Review devices grouped by repair stage.', 'staff_home.php?view=board', 'board'],
            ['Repair history', 'Read completed and cancelled records.', 'staff_home.php?view=history', 'history'],
        ];
        $rtRows = $data['page']['rows'] ?? [];
        $rtRowsTitle = 'Recently updated repair records';
        $rtRowsNote = 'This is a read-only view. It does not show customer contacts or private notes.';
        $rtEmptyText = 'No open repair records are currently available in your assigned branches.';
        break;
}

$rtSymbols = [
    'repairs' => 'M4 5h16v12H4z M8 21h8 M12 17v4 M8 9h8 M8 13h5',
    'decision' => 'M12 3v18 M3 12h18 M7 7l10 10 M17 7 7 17',
    'parts' => 'M3 7h18 M3 12h12 M3 17h8 M17 10l4 2-4 2',
    'ready' => 'M4 12l5 5L20 6 M3 3h18v18H3z',
    'drafts' => 'M5 3h10l4 4v14H5z M8 12h8 M8 16h6 M15 3v5h4',
    'intake' => 'M8 4h8 M9 3h6v3H9z M5 5H4v16h16V5h-1 M8 12l2 2 5-5',
    'capacity' => 'M4 19V5 M4 19h16 M8 15v-3 M12 15V8 M16 15V5',
    'transfers' => 'M4 7h15l-3-3 M20 17H5l3 3',
    'history' => 'M3 12a9 9 0 1 0 3-6.7L3 8 M3 3v5h5 M12 7v5l4 2',
    'cash' => 'M2 5h20v14H2z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0 M5 8h2 M17 16h2',
    'queue' => 'M4 5h16 M4 12h16 M4 19h10',
    'board' => 'M3 4h18v16H3z M9 4v16 M15 4v16',
    'handovers' => 'M3 7h18v13H3z M8 7V4h8v3 M8 13h8 M12 10v6',
];
?>
<section class="rt-overview" data-role-overview="<?= h($role) ?>">
    <div class="rt-overview-intro">
        <div>
            <p class="eyebrow">Your work at a glance</p>
            <h2>At a glance</h2>
            <p>Counts and recent records reflect the access assigned to your account.</p>
        </div>
        <?php if (in_array($role, ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true)): ?>
            <a class="primary-btn" href="intake_create.php">
                <span aria-hidden="true">+</span> New intake
            </a>
        <?php endif; ?>
    </div>

    <section class="rt-overview-cards" aria-label="<?= h($title) ?> metrics">
        <?php foreach ($rtCards as $rtIndex => $rtCard): ?>
            <article class="rt-overview-card">
                <div class="rt-overview-card-head">
                    <span><?= h($rtCard['label']) ?></span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= h($rtSymbols[$rtCard['icon']]) ?>"/></svg>
                </div>
                <strong class="rt-overview-value"><?= h((string) $rtCard['value']) ?></strong>
                <p class="rt-overview-note"><?= h($rtCard['note']) ?></p>
                <details class="rt-help-tip<?= $rtIndex > 1 ? ' tip-align-end' : '' ?>">
                    <summary aria-label="Explain <?= h($rtCard['label']) ?>" aria-describedby="rt-overview-tip-<?= $rtIndex ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5 M12 7h.01"/></svg>
                    </summary>
                    <span class="rt-help-tooltip" id="rt-overview-tip-<?= $rtIndex ?>" role="tooltip"><?= h($rtCard['tip']) ?></span>
                </details>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="rt-overview-columns">
        <section class="rt-overview-section" aria-labelledby="rt-overview-actions">
            <h2 id="rt-overview-actions">Next actions</h2>
            <div class="rt-overview-actions">
                <?php foreach ($rtActions as [$rtLabel, $rtNote, $rtUrl, $rtIcon]): ?>
                    <a href="<?= h($rtUrl) ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= h($rtSymbols[$rtIcon]) ?>"/></svg>
                        <span><strong><?= h($rtLabel) ?></strong><small><?= h($rtNote) ?></small></span>
                        <span class="rt-overview-arrow" aria-hidden="true">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($role === 'CASHIER'): ?>
                <form class="rt-overview-search" method="get" action="staff_home.php">
                    <input type="hidden" name="view" value="cash">
                    <label for="overview-bill-search">Ticket code, tracking code or bill number
                        <input id="overview-bill-search" type="search" name="q" maxlength="100" autocomplete="off">
                    </label>
                    <button type="submit">Find bill</button>
                </form>
                <p class="rt-overview-footnote">Counter cash only. Online payment is not available in this workspace.</p>
            <?php endif; ?>
        </section>

        <?php if ($rtRowsTitle !== ''): ?>
            <section class="rt-overview-section" aria-labelledby="rt-overview-records">
                <div class="rt-overview-section-head">
                    <div>
                        <h2 id="rt-overview-records"><?= h($rtRowsTitle) ?></h2>
                        <?php if ($rtRowsNote !== ''): ?><p><?= h($rtRowsNote) ?></p><?php endif; ?>
                    </div>
                    <?php if ($role === 'INTAKE_AGENT'): ?>
                        <a href="staff_home.php?view=drafts">All drafts</a>
                    <?php elseif ($role === 'COURIER'): ?>
                        <a href="staff_home.php?view=queue">Open manifest</a>
                    <?php else: ?>
                        <a href="staff_home.php?view=queue">Open queue</a>
                    <?php endif; ?>
                </div>
                <?php if (!$rtRows): ?>
                    <div class="rt-overview-empty"><?= h($rtEmptyText) ?></div>
                <?php else: ?>
                    <div class="rt-overview-records">
                        <?php foreach (array_slice($rtRows, 0, 4) as $rtRow): ?>
                            <article class="rt-overview-record">
                                <?php if ($rtRowsType === 'draft'): ?>
                                    <div>
                                        <strong><a href="intake_create.php?id=<?= (int) $rtRow['ticket_id'] ?>">Draft #<?= (int) $rtRow['ticket_id'] ?></a></strong>
                                        <small><?= h($rtRow['customer_name'] ?? 'Customer not linked') ?> · <?= h($rtRow['branch_name']) ?></small>
                                    </div>
                                    <span><?= h(workspaceDate($rtRow['created_at'])) ?></span>
                                <?php elseif ($rtRowsType === 'transfer'): ?>
                                    <div>
                                        <strong><?= h($rtRow['transfer_code']) ?></strong>
                                        <small><?= h($rtRow['item_code']) ?> · <?= h($rtRow['origin']) ?> → <?= h($rtRow['destination']) ?></small>
                                    </div>
                                    <span class="rt-overview-status"><?= h(ucwords(strtolower(str_replace('_', ' ', $rtRow['item_status'])))) ?></span>
                                <?php else: ?>
                                    <div>
                                        <strong><a href="device.php?id=<?= (int) $rtRow['ticket_device_id'] ?>&amp;return=<?= h($rtReturn) ?>"><?= h($rtRow['item_code']) ?></a></strong>
                                        <small><?= h(dashboardDeviceLabel($rtRow)) ?> · <?= h($rtRow['branch_name']) ?></small>
                                    </div>
                                    <div class="rt-overview-record-meta">
                                        <span class="workspace-status status-<?= h(strtolower($rtRow['repair_status'])) ?>"><?= h(dashboardStatusLabel($rtRow['repair_status'])) ?></span>
                                        <small>Updated <?= h(workspaceDate($rtRow['updated_at'])) ?></small>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
