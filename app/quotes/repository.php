<?php
declare(strict_types=1);
require_once __DIR__ . '/../console/repository.php';

function quoteCanPrepare(array $user): bool
{
    return in_array($user['role'], ['ADMIN', 'MANAGER', 'TECHNICIAN'], true);
}
function quoteCanRecord(array $user): bool
{
    return in_array($user['role'], ['ADMIN', 'MANAGER', 'INTAKE_AGENT'], true);
}
function quoteDevice(PDO $pdo, array $user, int $id, bool $lock = false): array
{
    if (!quoteCanPrepare($user) && !quoteCanRecord($user)) {
        throw new DomainException('Your role cannot access repair quotes.');
    }
    return consoleDevice($pdo, $user, $id, $lock);
}
function quoteLatest(PDO $pdo, int $id, bool $lock = false): ?array
{
    $query = $pdo->prepare(
        'SELECT * FROM repair_quotes WHERE ticket_device_id = ? ORDER BY version_no DESC LIMIT 1' .
            ($lock ? ' FOR UPDATE' : ''),
    );
    $query->execute([$id]);
    return $query->fetch() ?: null;
}
function quoteVersion(?array $quote): string
{
    return hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR));
}
// Work in integer centavos so totals do not suffer floating-point rounding.
function quoteCents(string $value): int
{
    if (!preg_match('/^([0-9]{1,7})(?:\.([0-9]{1,2}))?$/D', trim($value), $matches)) {
        throw new DomainException(
            'Enter amounts from 0 to 9,999,999.99, with at most two decimal places.',
        );
    }
    return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
}
function quoteDecimal(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}
function quoteValues(array $input): array
{
    $items = [];
    $total = 0;
    foreach (['labor' => 'LABOR', 'parts' => 'PART', 'other' => 'OTHER'] as $key => $type) {
        $description = trim(requestText($input, $key . '_description'));
        $cents = quoteCents(requestText($input, $key . '_amount', '0'));
        if (
            mb_strlen($description) > 300 ||
            ($key === 'labor' && $description === '') ||
            ($cents > 0 && $description === '')
        ) {
            throw new DomainException(
                'Describe the diagnosis/work and each charge, using no more than 300 characters per description.',
            );
        }
        $items[$key] = [
            'type' => $type,
            'description' => $description,
            'amount' => quoteDecimal($cents),
        ];
        $total += $cents;
    }
    return ['items' => $items, 'total' => quoteDecimal($total)];
}
function quoteMayChange(array $device): bool
{
    return $device['case_state'] === 'OPEN' &&
        $device['repair_started_at'] === null &&
        in_array(
            $device['repair_status'],
            ['DIAGNOSING', 'AWAITING_PARTS', 'AWAITING_CUSTOMER_APPROVAL'],
            true,
        );
}
function quoteExpired(?array $quote): bool
{
    return $quote &&
        $quote['quote_status'] === 'AWAITING_CUSTOMER' &&
        $quote['expires_at'] &&
        strtotime($quote['expires_at'] . ' UTC') <= time();
}
// Device locks serialize revisions, decisions and the technician's repair start.
function quoteSave(
    PDO $pdo,
    int $actorId,
    int $id,
    string $expected,
    string $action,
    array $input,
): void {
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare(
            'SELECT user_id, role, is_active FROM users WHERE user_id = ? FOR UPDATE',
        );
        $query->execute([$actorId]);
        $user = $query->fetch();
        if (!$user || !$user['is_active']) {
            throw new DomainException('Your account cannot change this quote.');
        }
        $device = quoteDevice($pdo, $user, $id, true);
        $latest = quoteLatest($pdo, $id, true);
        if (!hash_equals(quoteVersion($latest), $expected)) {
            throw new DomainException('The quote changed. Reload it before continuing.');
        }
        if (!quoteMayChange($device)) {
            throw new DomainException(
                'Quotes can change after diagnosis begins and before repair starts. Started or closed repairs need a separate change-order process.',
            );
        }
        if ($action === 'draft' && quoteCanPrepare($user)) {
            $values = quoteValues($input);
            $version = (int) ($latest['version_no'] ?? 0) + 1;
            if ($version > 65535) {
                throw new DomainException('The quote revision limit has been reached.');
            }
            if ($latest) {
                $pdo->prepare(
                    "UPDATE repair_quotes SET quote_status = 'SUPERSEDED' WHERE quote_id = ?",
                )->execute([$latest['quote_id']]);
            }
            $pdo->prepare(
                "INSERT INTO repair_quotes (ticket_device_id, version_no, quote_status, labor_amount, parts_amount, other_amount, total_amount, prepared_by_user_id)
                VALUES (?, ?, 'DRAFT', ?, ?, ?, ?, ?)",
            )->execute([
                $id,
                $version,
                $values['items']['labor']['amount'],
                $values['items']['parts']['amount'],
                $values['items']['other']['amount'],
                $values['total'],
                $actorId,
            ]);
            $quoteId = (int) $pdo->lastInsertId();
            foreach ($values['items'] as $item) {
                if ($item['description'] === '') {
                    continue;
                }
                $pdo->prepare(
                    'INSERT INTO repair_quote_items (quote_id, item_type, description, quantity, unit_price, line_total) VALUES (?, ?, ?, 1, ?, ?)',
                )->execute([
                    $quoteId,
                    $item['type'],
                    $item['description'],
                    $item['amount'],
                    $item['amount'],
                ]);
            }
        } elseif ($action === 'share' && quoteCanPrepare($user)) {
            if (
                !$latest ||
                $latest['quote_status'] !== 'DRAFT' ||
                requestText($input, 'confirm') !== '1'
            ) {
                throw new DomainException(
                    'Review the draft and confirm that you shared this exact estimate with the customer.',
                );
            }
            $quoteId = (int) $latest['quote_id'];
            $pdo->prepare(
                "UPDATE repair_quotes SET quote_status = 'AWAITING_CUSTOMER', sent_at = UTC_TIMESTAMP(), expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY) WHERE quote_id = ?",
            )->execute([$quoteId]);
            $pdo->prepare(
                "UPDATE ticket_devices SET repair_status = 'AWAITING_CUSTOMER_APPROVAL' WHERE ticket_device_id = ?",
            )->execute([$id]);
            $pdo->prepare(
                "INSERT INTO ticket_status_events (ticket_device_id, from_status, to_status, actor_type, actor_user_id, public_summary, is_customer_visible, created_at) VALUES (?, ?, 'AWAITING_CUSTOMER_APPROVAL', 'USER', ?, 'An estimate is awaiting your response. Please contact the branch.', 1, UTC_TIMESTAMP())",
            )->execute([$id, $device['repair_status'], $actorId]);
        } elseif ($action === 'decision' && quoteCanRecord($user)) {
            if (
                !$latest ||
                $latest['quote_status'] !== 'AWAITING_CUSTOMER' ||
                quoteExpired($latest)
            ) {
                throw new DomainException(
                    'Only a current, unexpired quote awaiting a response can receive a decision. Create a revised estimate if needed.',
                );
            }
            $decision = requestText($input, 'decision');
            $name = trim(requestText($input, 'customer_name'));
            $reference = trim(requestText($input, 'reference'));
            if (
                !in_array($decision, ['APPROVED', 'DECLINED'], true) ||
                $name === '' ||
                mb_strlen($name) > 160 ||
                $reference === '' ||
                mb_strlen($reference) > 1000 ||
                requestText($input, 'confirm') !== '1'
            ) {
                throw new DomainException(
                    'Choose the response, enter the customer name and conversation reference, then confirm it was received.',
                );
            }
            $quoteId = (int) $latest['quote_id'];
            $pdo->prepare(
                "INSERT INTO repair_quote_decisions (quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at) VALUES (?, ?, 'STAFF_RECORDED', ?, ?, ?, UTC_TIMESTAMP())",
            )->execute([$quoteId, $decision, $actorId, $name, $reference]);
            $pdo->prepare('UPDATE repair_quotes SET quote_status = ? WHERE quote_id = ?')->execute([
                $decision,
                $quoteId,
            ]);
            // Rejection never cancels or releases the device automatically.
        } else {
            throw new DomainException('Your role cannot perform this quote action.');
        }
        $pdo->prepare(
            "INSERT INTO audit_events (actor_type, actor_user_id, entity_type, entity_id, action, after_data, created_at) VALUES ('USER', ?, 'repair_quote', ?, ?, ?, UTC_TIMESTAMP())",
        )->execute([
            $actorId,
            $quoteId,
            'QUOTE_' . strtoupper($action),
            json_encode(['device_id' => $id], JSON_THROW_ON_ERROR),
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
