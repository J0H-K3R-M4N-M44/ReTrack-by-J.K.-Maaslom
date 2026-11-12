<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/intake/issuance.php';
startAppSession();
$admin = requireRoles(['ADMIN', 'MANAGER', 'INTAKE_AGENT']);
header('Cache-Control: no-store, private');
$userId = (int) $admin['user_id'];
$errors = [];
$review = false;
$values = intakeIssueValues([]);

try {
    $pdo = getPDO();
    intakePrivateDirectory();
    // Reclaim expired reviews when the administrator next opens issuance.
    foreach ($_SESSION['issue_forms'] ?? [] as $key => $state) {
        if ($state['expires'] < time()) {
            intakeDiscardEvidence($state['evidence'] ?? []);
            unset($_SESSION['issue_forms'][$key]);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfIsValid(requestText($_POST, 'csrf_token'))) {
            unavailablePage('This form expired. Reopen the draft.', 403);
        }
        $formKey = requestText($_POST, 'form_key');
        $state = $_SESSION['issue_forms'][$formKey] ?? null;
        if (!$state) {
            unavailablePage('This review expired or was already used. Reopen the draft.', 409);
        }
        $ticketId = $state['ticket_id'];
        $draft = intakeIssueDraft($pdo, $ticketId, $userId);
        if (!hash_equals($state['version'], intakeDraftVersion($draft))) {
            throw new DomainException(
                'The draft changed. Reopen issuance to review the latest details.',
            );
        }
        $intent = requestText($_POST, 'intent');
        if ($intent === 'issue') {
            // Save only the values and evidence reviewed on the server, never new POST values.
            if (
                !isset($state['values'], $state['evidence']) ||
                requestText($_POST, 'confirm_issue') !== '1'
            ) {
                throw new DomainException('Review and confirm the intake checks before issuing.');
            }
            intakeIssueTicket(
                $pdo,
                $ticketId,
                $userId,
                $state['version'],
                $state['values'],
                $state['evidence'],
            );
            unset($_SESSION['issue_forms'][$formKey]);
            header('Location: ticket.php?id=' . $ticketId, true, 303);
            exit();
        } elseif ($intent === 'edit') {
            $values = $state['values'] ?? $values;
            intakeDiscardEvidence($state['evidence'] ?? []);
            unset(
                $_SESSION['issue_forms'][$formKey]['values'],
                $_SESSION['issue_forms'][$formKey]['evidence'],
            );
        } elseif ($intent === 'review') {
            intakeDiscardEvidence($state['evidence'] ?? []);
            unset(
                $_SESSION['issue_forms'][$formKey]['values'],
                $_SESSION['issue_forms'][$formKey]['evidence'],
            );
            $values = intakeIssueValues($_POST);
            $errors = intakeValidateIssue($pdo, $draft, $values, $userId);
            if (!$errors) {
                $evidence = [];
                try {
                    foreach (['photo', 'signed_form'] as $kind) {
                        $file = $_FILES[$kind] ?? [];
                        $evidence[$kind] = intakeStoreUpload(is_array($file) ? $file : []);
                    }
                } catch (Throwable $error) {
                    intakeDiscardEvidence($evidence);
                    throw $error;
                }
                $_SESSION['issue_forms'][$formKey]['values'] = $values;
                $_SESSION['issue_forms'][$formKey]['evidence'] = $evidence;
                $review = true;
            }
        } else {
            throw new DomainException('Use the intake review buttons to continue.');
        }
    } else {
        $id = requestText($_GET, 'id');
        if (!ctype_digit($id) || (int) $id < 1) {
            unavailablePage('Choose a draft from the workbench.', 400);
        }
        $ticketId = (int) $id;
        $draft = intakeIssueDraft($pdo, $ticketId, $userId);
        // Check the private key before asking the user to upload any files.
        intakeDuplicateFingerprint($draft, $values, date('Y-m-d'));
        if (count($_SESSION['issue_forms'] ?? []) >= 5) {
            $oldest = array_key_first($_SESSION['issue_forms']);
            intakeDiscardEvidence($_SESSION['issue_forms'][$oldest]['evidence'] ?? []);
            unset($_SESSION['issue_forms'][$oldest]);
        }
        $formKey = bin2hex(random_bytes(24));
        $_SESSION['issue_forms'][$formKey] = [
            'ticket_id' => $ticketId,
            'version' => intakeDraftVersion($draft),
            'expires' => time() + 1800,
        ];
    }
    $locations = intakeLocations($pdo, (int) $draft['intake_branch_id']);
} catch (DomainException $error) {
    if (isset($formKey, $_SESSION['issue_forms'][$formKey])) {
        intakeDiscardEvidence($_SESSION['issue_forms'][$formKey]['evidence'] ?? []);
        unset(
            $_SESSION['issue_forms'][$formKey]['evidence'],
            $_SESSION['issue_forms'][$formKey]['values'],
        );
    }
    unavailablePage($error->getMessage() . ' Return to the draft and open issuance again.', 409);
} catch (Throwable $error) {
    error_log('RepairTrack issuance: ' . $error->getMessage());
    if (isset($formKey, $_SESSION['issue_forms'][$formKey]['evidence'])) {
        intakeDiscardEvidence($_SESSION['issue_forms'][$formKey]['evidence']);
        unset(
            $_SESSION['issue_forms'][$formKey]['evidence'],
            $_SESSION['issue_forms'][$formKey]['values'],
        );
    }
    unavailablePage(
        'Ticket issuance is unavailable. Check the private intake storage and key configuration, then reopen the draft. No ticket was issued.',
    );
}
require __DIR__ . '/../views/intake/issue.php';
