<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/payments/repository.php';
require_once __DIR__ . '/../app/console/workspace.php';
startAppSession();
$user = requireRoles(['ADMIN', 'MANAGER', 'CASHIER']);
header('Cache-Control: no-store, private');

$pdo = getPDO();
$error = '';
$review = false;
$formKey = '';
$formState = null;
$formValues = [];
$pageAction = '';
$pageId = 0;
$ticket = null;
$bill = null;
$handover = null;
$notice = $_SESSION['cash_saved'] ?? '';
unset($_SESSION['cash_saved']);

foreach ($_SESSION['cash_forms'] ?? [] as $key => $state) {
    if (($state['expires'] ?? 0) < time()) {
        unset($_SESSION['cash_forms'][$key]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
        unavailablePage('The cash form expired. Reopen the bill and try again.', 403);
    }
    $formKey = requestText($_POST, 'form_key');
    $formState = $_SESSION['cash_forms'][$formKey] ?? null;
    if (!$formState || ($formState['expires'] ?? 0) < time()) {
        unavailablePage('This review expired or was already used. Reopen the cash record.', 409);
    }
    $pageAction = $formState['action'];
    $pageId = (int) $formState['id'];
    try {
        $intent = requestText($_POST, 'intent');
        if ($intent === 'review') {
            $formValues = cashPostedValues($pageAction, $_POST);
            cashValidateReview($pageAction, $formValues);
            if ($pageAction === 'payment') {
                $currentBill = cashBillDetails($pdo, $user, $pageId);
                if (cashAmountCents($formValues['amount']) > $currentBill['balance_cents']) {
                    throw new DomainException('The amount cannot be greater than the current balance due.');
                }
            }
            $_SESSION['cash_forms'][$formKey]['values'] = $formValues;
            $formState = $_SESSION['cash_forms'][$formKey];
            $review = true;
        } elseif ($intent === 'edit' && isset($formState['values'])) {
            $formValues = $formState['values'];
        } elseif ($intent === 'save' && isset($formState['values'])) {
            $formValues = $formState['values'];
            if ($pageAction === 'issue') {
                $billId = cashIssueFinalBill(
                    $pdo,
                    (int) $user['user_id'],
                    $pageId,
                    $formState['version'],
                );
                $_SESSION['cash_saved'] = 'Final bill issued from the approved estimates.';
            } elseif ($pageAction === 'payment') {
                cashRecordPayment(
                    $pdo,
                    (int) $user['user_id'],
                    $pageId,
                    $formState['version'],
                    $formValues['amount'],
                    hash('sha256', $formKey),
                );
                $_SESSION['cash_saved'] = 'Counter-cash payment recorded.';
                $billId = $pageId;
            } else {
                cashCompleteHandover(
                    $pdo,
                    (int) $user['user_id'],
                    $pageId,
                    $formState['version'],
                    $formValues['recipient'],
                    $formValues['relationship'],
                    $formValues['condition_confirmed'],
                );
                $_SESSION['cash_saved'] = 'Handover recorded and the ticket was closed.';
                $billId = $pageId;
            }
            unset($_SESSION['cash_forms'][$formKey]);
            header('Location: cash.php?bill_id=' . $billId, true, 303);
            exit();
        } else {
            throw new DomainException('Review this cash action before confirming it.');
        }
    } catch (DomainException $problem) {
        $error = $problem->getMessage();
        if (isset($formState['values'])) {
            $formValues = $formState['values'];
            $review = true;
        }
    } catch (Throwable $problem) {
        error_log('RepairTrack cash workflow: ' . $problem->getMessage());
        $error = 'The cash action could not be saved. No partial change was recorded.';
        if (isset($formState['values'])) {
            $formValues = $formState['values'];
            $review = true;
        }
    }
}

try {
    if ($pageAction !== '') {
        if ($pageAction === 'issue') {
            $ticket = cashPageTicket($pdo, $user, $pageId);
        } else {
            $bill = cashBillDetails($pdo, $user, $pageId);
            $handover = cashHandoverPreview($pdo, $user, (int) $bill['ticket_id']);
        }
    } elseif (isset($_GET['ticket_id']) && ctype_digit(requestText($_GET, 'ticket_id'))) {
        $ticketId = (int) requestText($_GET, 'ticket_id');
        $ticket = cashPageTicket($pdo, $user, $ticketId);
        $issued = $pdo->prepare(
            "SELECT bill_id FROM repair_bills WHERE ticket_id = ? AND bill_status = 'ISSUED'
            ORDER BY version_no DESC LIMIT 1",
        );
        $issued->execute([$ticketId]);
        $existingBillId = $issued->fetchColumn();
        if ($existingBillId) {
            header('Location: cash.php?bill_id=' . (int) $existingBillId, true, 303);
            exit();
        }
        if (in_array($user['role'], ['ADMIN', 'MANAGER'], true)) {
            $formKey = cashStartForm('issue', $ticketId, cashTicketVersion($ticket));
            $formState = $_SESSION['cash_forms'][$formKey];
            $pageAction = 'issue';
            $pageId = $ticketId;
        }
    } elseif (isset($_GET['bill_id']) && ctype_digit(requestText($_GET, 'bill_id'))) {
        $bill = cashBillDetails($pdo, $user, (int) requestText($_GET, 'bill_id'));
        $handover = cashHandoverPreview($pdo, $user, (int) $bill['ticket_id']);
        $pageId = (int) $bill['bill_id'];
        if ($bill['bill_status'] === 'ISSUED' && $bill['case_state'] === 'OPEN') {
            $action = $bill['balance_cents'] > 0 ? 'payment' : 'handover';
            $formKey = cashStartForm($action, $pageId, $bill['version']);
            $formState = $_SESSION['cash_forms'][$formKey];
            $pageAction = $action;
            if ($action === 'payment') {
                $formValues['amount'] = cashAmountText($bill['balance_cents']);
            }
        }
    }
} catch (DomainException $problem) {
    unavailablePage($problem->getMessage(), 404);
} catch (Throwable $problem) {
    error_log('RepairTrack cash page: ' . $problem->getMessage());
    unavailablePage('The cash record could not be loaded. Ask a manager to check the database upgrade.');
}

// Replace an old review token when another staff member changed the record meanwhile.
if ($formState && $pageAction !== '') {
    $currentVersion = $pageAction === 'issue'
        ? cashTicketVersion($ticket)
        : $bill['version'];
    if (!hash_equals($formState['version'], $currentVersion)) {
        $error = 'This record changed while you were reviewing it. Check the latest details and review again.';
        unset($_SESSION['cash_forms'][$formKey]);
        $formKey = cashStartForm($pageAction, $pageId, $currentVersion);
        $formState = $_SESSION['cash_forms'][$formKey];
        $formValues = [];
        $review = false;
    }
}

if ($pageAction === 'issue' && !$formState) {
    $formKey = cashStartForm('issue', (int) $ticket['ticket_id'], cashTicketVersion($ticket));
    $formState = $_SESSION['cash_forms'][$formKey];
}
if (isset($formState['values']) && $review) {
    $formValues = $formState['values'];
}
require __DIR__ . '/../views/payments/desk.php';

function cashStartForm(string $action, int $id, string $version): string
{
    $key = bin2hex(random_bytes(24));
    $_SESSION['cash_forms'][$key] = [
        'action' => $action,
        'id' => $id,
        'version' => $version,
        'expires' => time() + 1200,
    ];
    while (count($_SESSION['cash_forms']) > 20) {
        array_shift($_SESSION['cash_forms']);
    }
    return $key;
}

function cashPostedValues(string $action, array $input): array
{
    return match ($action) {
        'issue' => ['confirm' => requestText($input, 'confirm')],
        'payment' => [
            'amount' => trim(requestText($input, 'amount')),
            'confirm' => requestText($input, 'confirm'),
        ],
        'handover' => [
            'recipient' => trim(requestText($input, 'recipient')),
            'relationship' => requestText($input, 'relationship'),
            'condition_confirmed' => requestText($input, 'condition_confirmed'),
        ],
        default => throw new DomainException('Unknown cash action.'),
    };
}

function cashValidateReview(string $action, array $values): void
{
    if ($action === 'issue' && $values['confirm'] !== 'yes') {
        throw new DomainException('Confirm the ready devices and approved estimates before issuing the bill.');
    }
    if ($action === 'payment') {
        if (cashAmountCents($values['amount']) <= 0) {
            throw new DomainException('Enter an amount greater than zero.');
        }
        if ($values['confirm'] !== 'yes') {
            throw new DomainException('Confirm that you counted the physical cash received.');
        }
    }
    if ($action === 'handover') {
        $recipient = trim($values['recipient']);
        if (
            $recipient === '' || mb_strlen($recipient) > 160 ||
            !in_array($values['relationship'], ['CUSTOMER', 'AUTHORIZED_REPRESENTATIVE'], true) ||
            $values['condition_confirmed'] !== 'yes'
        ) {
            throw new DomainException('Enter the recipient, choose their relationship, and confirm the device check.');
        }
    }
}
