<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/quotes/repository.php';
startAppSession();
$user = requireRoles(['ADMIN', 'MANAGER', 'TECHNICIAN', 'INTAKE_AGENT']);
header('Cache-Control: no-store, private');
$error = '';
$review = false;
$values = [];
$returnQuery = workspaceReturnQuery($user, requestText($_GET, 'return'));
try {
    $pdo = getPDO();
    foreach ($_SESSION['quote_forms'] ?? [] as $key => $state) {
        if ($state['expires'] < time()) {
            unset($_SESSION['quote_forms'][$key]);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('The quote form expired. Reopen the device.', 403);
        }
        $formKey = requestText($_POST, 'form_key');
        $state = $_SESSION['quote_forms'][$formKey] ?? null;
        if (!$state) {
            unavailablePage('This quote form expired or was already saved.', 409);
        }
        $id = $state['id'];
        $returnQuery = $state['return'];
    } else {
        $raw = requestText($_GET, 'id');
        if (!ctype_digit($raw)) {
            unavailablePage('Choose a device first.', 400);
        }
        $id = (int) $raw;
    }
    $device = quoteDevice($pdo, $user, $id);
    $latest = quoteLatest($pdo, $id);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $formKey = bin2hex(random_bytes(24));
        $_SESSION['quote_forms'][$formKey] = [
            'id' => $id,
            'return' => $returnQuery,
            'version' => quoteVersion($latest),
            'expires' => time() + 1200,
        ];
        if (count($_SESSION['quote_forms']) > 20) {
            array_shift($_SESSION['quote_forms']);
        }
    } else {
        $intent = requestText($_POST, 'intent');
        if ($intent === 'review') {
            foreach (
                [
                    'action',
                    'labor_description',
                    'labor_amount',
                    'parts_description',
                    'parts_amount',
                    'other_description',
                    'other_amount',
                    'decision',
                    'customer_name',
                    'reference',
                    'confirm',
                ]
                as $field
            ) {
                $values[$field] = requestText($_POST, $field);
            }
            if ($values['action'] === 'draft') {
                quoteValues($values);
            }
            if (!in_array($values['action'], ['draft', 'share', 'decision'], true)) {
                throw new DomainException('Choose a quote action.');
            }
            $_SESSION['quote_forms'][$formKey]['values'] = $values;
            $review = true;
        } elseif ($intent === 'edit') {
            $values = $state['values'] ?? [];
            unset($_SESSION['quote_forms'][$formKey]['values']);
        } elseif ($intent === 'save' && isset($state['values'])) {
            $values = $state['values'];
            quoteSave(
                $pdo,
                (int) $user['user_id'],
                $id,
                $state['version'],
                $values['action'],
                $values,
            );
            unset($_SESSION['quote_forms'][$formKey]);
            $_SESSION['quote_saved'] = match ($values['action']) {
                'draft' => 'Draft saved. Review it with the customer before marking it shared.',
                'share' => 'Quote marked shared. No email or SMS was sent.',
                default => 'Customer response recorded.',
            };
            header(
                'Location: quote.php?' . http_build_query(['id' => $id, 'return' => $returnQuery]),
                true,
                303,
            );
            exit();
        } else {
            throw new DomainException('Review the quote action before confirming it.');
        }
    }
} catch (DomainException $problem) {
    if (!isset($device)) {
        unavailablePage($problem->getMessage(), 404);
    }
    $error = $problem->getMessage();
    $review = false;
} catch (Throwable $problem) {
    error_log('RepairTrack quote: ' . $problem->getMessage());
    unavailablePage('The quote could not be loaded or saved. No partial quote was saved.');
}
try {
    $items = [];
    if ($latest) {
        $query = $pdo->prepare(
            'SELECT item_type, description, quantity, unit_price, line_total FROM repair_quote_items WHERE quote_id = ? ORDER BY quote_item_id',
        );
        $query->execute([$latest['quote_id']]);
        $items = $query->fetchAll();
    }
    $history = dashboardPage(
        $pdo,
        'SELECT q.quote_id, q.version_no, q.quote_status, q.total_amount, q.currency, q.created_at, d.decision FROM repair_quotes q LEFT JOIN repair_quote_decisions d ON d.quote_id = q.quote_id WHERE q.ticket_device_id = ?',
        [$id],
        'version_no DESC',
        requestText($_GET, 'page', '1'),
    );
} catch (Throwable $problem) {
    error_log('RepairTrack quote history: ' . $problem->getMessage());
    unavailablePage('Quote history is temporarily unavailable.');
}
$saved = $_SESSION['quote_saved'] ?? '';
unset($_SESSION['quote_saved']);
$canChange = quoteMayChange($device);
$canPrepare = quoteCanPrepare($user);
$canRecord = quoteCanRecord($user);
$expired = quoteExpired($latest);
require __DIR__ . '/../views/quotes/editor.php';
