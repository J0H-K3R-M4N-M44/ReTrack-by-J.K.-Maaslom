<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/console/ownership.php';

function ownershipCheck(bool $condition, string $why): void
{
    if (!$condition) {
        throw new RuntimeException($why);
    }
}

$aligned = consoleOwnershipDetails([
    'accountable_branch_id' => 3,
    'repair_branch_id' => 3,
    'branch_name' => 'Main Shop',
    'repair_branch_name' => 'Main Shop',
    'ticket_code' => 'PRIVATE-DO-NOT-DISPLAY',
]);
ownershipCheck(!$aligned['review_required'], 'Aligned issued device flagged as divergent.');
ownershipCheck($aligned['ticket_branch'] === 'Main Shop' &&
    $aligned['repair_branch'] === 'Main Shop', 'Incorrect labels.');
ownershipCheck(!str_contains(implode(' ', array_map('strval', $aligned)), 'PRIVATE-'),
    'A ticket code leaked into ownership labels.');

$missing = consoleOwnershipDetails([
    'accountable_branch_id' => 3,
    'repair_branch_id' => null,
    'branch_name' => 'Main Shop',
]);
ownershipCheck($missing['review_required'] &&
    $missing['repair_branch'] === 'Not verified',
    'Missing branch appears valid.');

$unresolved = consoleOwnershipDetails([
    'accountable_branch_id' => 3,
    'repair_branch_id' => 7,
    'branch_name' => 'Main Shop',
    'repair_branch_name' => '',
]);
ownershipCheck($unresolved['review_required'] &&
    $unresolved['repair_branch'] === 'Not verified',
    'Unresolved foreign key incorrectly shown.');

$divergent = consoleOwnershipDetails([
    'accountable_branch_id' => 3,
    'repair_branch_id' => 7,
    'branch_name' => 'Main Shop',
    'repair_branch_name' => 'Other Shop',
]);
ownershipCheck($divergent['review_required'] &&
    $divergent['repair_branch'] === 'Other Shop' &&
    $divergent['ticket_branch'] === 'Main Shop',
    'Cross-branch snapshot incorrectly classified as aligned.');
ownershipCheck(!str_contains(strtolower($divergent['message']), 'approved'),
    'Diagnostic label implied authorization.');
echo "PASS: read-only ownership labels, missing/other branch handling and no ticket identifier leak.\n";
