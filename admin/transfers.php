<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/console/repository.php';
require_once __DIR__ . '/../app/console/transfers.php';
startAppSession();
$user = requireRoles(['ADMIN','MANAGER','COURIER']);
header('Cache-Control: no-store, private');
$id = (int)$user['user_id'];
$notice = $_SESSION['transfer_notice'] ?? '';
unset($_SESSION['transfer_notice']);
$error = '';
try {
    $pdo = getPDO();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!csrfIsValid(requestText($_POST,'csrf_token'))) unavailablePage('Reopen the manifest and try again.',403);
        try {
            $action = requestText($_POST,'action');
            if ($action==='dispatch') {
                $code = dispatchDevices($pdo,$id,(int)requestText($_POST,'origin'),(int)requestText($_POST,'destination'),requestText($_POST,'codes'));
                $_SESSION['transfer_notice'] = 'Dispatched manifest ' . $code;
            } elseif ($action === 'accept-repair') {
                acceptTransferredRepair($pdo, $id, (int) requestText($_POST, 'manifest_id'),
                    (int) requestText($_POST, 'ticket_id'), (int) requestText($_POST, 'expected_origin'),
                    requestText($_POST, 'accept_reason'), requestText($_POST, 'accept_confirm') === 'yes');
                $_SESSION['transfer_notice'] = 'Receiving branch accepted repair responsibility. Assign a new technician from its queue.';
            } else {
                confirmTransferItem($pdo,$id,(int)requestText($_POST,'item'),requestText($_POST,'expected'),$action,requestText($_POST,'note'));
                $_SESSION['transfer_notice'] = $action==='receive'?'Receipt recorded.':'Missing device recorded.';
            }
            header('Location: transfers.php?' . http_build_query([
                'q'=>$code ?? requestText($_GET,'q'),
                'view'=>$action === 'accept-repair' ? 'history' : requestText($_GET,'view'),
            ]),true,303);
            exit;
        } catch (DomainException $problem) {
            $error = $problem->getMessage();
        }
    }
    $branches = intakeBranches($pdo,$id);
    $allowed = array_map('intval',array_column($branches,'branch_id'));
    $locations = $pdo->query("SELECT l.location_id,l.location_name,b.branch_name FROM storage_locations l JOIN branches b ON b.branch_id=l.branch_id
        WHERE b.is_active=1 AND l.is_active=1 AND l.location_type IN ('BRANCH_BIN','WORKBENCH') ORDER BY b.branch_name,l.location_name")->fetchAll();
    $history = requestText($_GET,'view')==='history';
    $search = requestText($_GET,'q');
    $page = consoleTransfers($pdo,$user,['view'=>$history?'history':'queue','q'=>$search,'page'=>requestText($_GET,'page')]);
} catch (Throwable $problem) {
    error_log('Transfer workspace: '.$problem->getMessage());
    unavailablePage('Transfers are temporarily unavailable. Refresh the manifest before retrying.');
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Device handovers | RepairTrack</title><link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/dashboard.css"><link rel="stylesheet" href="../css/staff.css"></head>
<body class="dashboard-body"><main class="staff-page">
<a href="<?= h(accountHome()) ?>">Back to workspace</a><h1>Device handovers</h1>
<?php if ($notice): ?><p role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<details class="dashboard-card" <?= requestText($_POST,'action')==='dispatch'?'open':'' ?>><summary>Dispatch devices</summary>
<form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<label>Sending branch<select required name="origin"><?php foreach ($branches as $b): ?><option value="<?= (int)$b['branch_id'] ?>" <?= requestText($_POST,'origin')===(string)$b['branch_id']?'selected':'' ?>><?= h($b['branch_name']) ?></option><?php endforeach; ?></select></label>
<label>Receiving location<select required name="destination"><option value="">Choose location</option><?php foreach ($locations as $l): ?><option value="<?= (int)$l['location_id'] ?>" <?= requestText($_POST,'destination')===(string)$l['location_id']?'selected':'' ?>><?= h($l['branch_name'].' · '.$l['location_name']) ?></option><?php endforeach; ?></select></label>
<label>Device item codes, one per line<textarea required name="codes" rows="4"><?= h(requestText($_POST,'codes')) ?></textarea></label>
<button name="action" value="dispatch">Confirm dispatch</button></form></details>
<form method="get"><label>Transfer code<input name="q" value="<?= h($search) ?>"></label>
<label>View<select name="view"><option value="queue">Awaiting receipt</option><option value="history" <?= $history?'selected':'' ?>>History</option></select></label><button>Apply filters</button></form>
<?php foreach ($page['rows'] as $r): ?><section class="dashboard-card"><h2><?= h($r['item_code']) ?></h2>
<p><?= h($r['transfer_code'].' · '.$r['origin'].' → '.$r['destination'].' · '.$r['destination_location']) ?></p>
<p><?= h(str_replace('_',' ', $r['item_status'])) ?></p>
<?php if ($r['receipt_condition_note']): ?><p><?= h($r['receipt_condition_note']) ?></p><?php endif; ?>
<?php if ($history && $r['item_status']==='RECEIVED' &&
    in_array($user['role'], ['ADMIN','MANAGER'], true) &&
    in_array((int)$r['to_branch_id'], $allowed, true) &&
    (int)$r['accountable_branch_id']===(int)$r['from_branch_id']): ?>
<details class="dashboard-card">
<summary>Accept repair responsibility for the whole ticket</summary>
<p>Only accept after every device on this ticket is received on the same manifest.
This changes the accountable branch and clears the old technician assignments.
It does not transfer physical parts or notify the customer automatically.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<input type="hidden" name="manifest_id" value="<?= (int)$r['device_transfer_id'] ?>">
<input type="hidden" name="ticket_id" value="<?= (int)$r['ticket_id'] ?>">
<input type="hidden" name="expected_origin" value="<?= (int)$r['from_branch_id'] ?>">
<label>Reason for accepting this repair
<textarea name="accept_reason" minlength="10" maxlength="500" rows="2" required></textarea></label>
<label><input type="checkbox" name="accept_confirm" value="yes" required>
I verified the complete ticket was received, reviewed its repair status, and will assign a destination technician.</label>
<button name="action" value="accept-repair">Accept ticket at receiving branch</button>
</form></details>
<?php endif; ?>
<?php if (!$history && in_array((int)$r['to_branch_id'],$allowed,true)): ?><form method="post">
<input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="item" value="<?= (int)$r['device_transfer_item_id'] ?>"><input type="hidden" name="expected" value="<?= h($r['item_status']) ?>">
<label>Receipt note (required for a discrepancy or resolution)<input maxlength="500" name="note" <?= $r['item_status']==='EXCEPTION'?'required':'' ?>></label>
<button name="action" value="receive"><?= $r['item_status']==='EXCEPTION'?'Confirm found and received':'Confirm device received' ?></button>
<?php if ($r['item_status']==='IN_TRANSIT'): ?><button name="action" value="missing">Report device missing</button><?php endif; ?></form><?php endif; ?></section><?php endforeach; ?>
<?php if (!$page['total']): ?><p>No matching transfer items.</p><?php else: ?><nav aria-label="Transfer pages">
<?php foreach (['Previous'=>-1,'Next'=>1] as $label=>$delta): $number=$page['page']+$delta; if ($number>=1 && $number<=$page['pages']): ?><a href="?<?= h(http_build_query(['q'=>$search,'view'=>$history?'history':'queue','page'=>$number])) ?>"><?= h($label) ?></a><?php endif; endforeach; ?>
<?= (int)$page['from'] ?>–<?= (int)$page['to'] ?> of <?= (int)$page['total'] ?></nav><?php endif; ?></main></body></html>
