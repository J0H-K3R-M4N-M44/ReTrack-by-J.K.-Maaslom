<?php
declare(strict_types=1);

/** Small authenticated academic intake preview; run with -t course-edp/public. */
require_once __DIR__ . '/../app/preview.php';
edpPreviewHeaders();
edpPreviewSession();

$pdo = null;
$staff = null;
$error = '';
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'intake';
$receipt = null;
$draft = null;
$branches = [];
$catalogs = [];
$customers = [];

function edpPreviewRedirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}
function edpPreviewRequireStaff(?array $staff): array
{
    if (!$staff) edpPreviewRedirect('?page=login');
    return $staff;
}
function edpPreviewPositiveId(mixed $id): int
{
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,17}$/D', $id)) {
        throw new DomainException('Choose a valid record.');
    }
    return (int)$id;
}

try {
    $pdo = edpPreviewDb();
    $staff = edpPreviewCurrentStaff($pdo);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!edpPreviewCsrfValid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            throw new DomainException('Your form expired. Reload and try again.');
        }
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : ''; 
        if ($action === 'login') {
            if ($staff) edpPreviewRedirect('?page=intake');
            $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (strlen($email) > 190 || strlen($password) > 1024 ||
                !edpPreviewLogin($pdo, $email, $password, $_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
                http_response_code(401);
                throw new DomainException('Invalid staff credentials.');
            }
            edpPreviewRedirect('?page=intake');
        }
        edpPreviewRequireStaff($staff);
        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(),'',[
                    'expires'=>time()-3600,'path'=>$params['path'],
                    'domain'=>$params['domain'],'secure'=>$params['secure'],
                    'httponly'=>$params['httponly'],'samesite'=>'Lax',
                ]);
            }
            session_destroy();
            edpPreviewRedirect('?page=login');
        }
        if ($action === 'create_draft') {
            $branchId = edpPreviewPositiveId($_POST['branch_id'] ?? null);
            $types = $_POST['device_type_code'] ?? null;
            $symptoms = $_POST['reported_symptoms'] ?? null;
            $makes = $_POST['make'] ?? null;
            $models = $_POST['model'] ?? null;
            if (!is_array($types) || !is_array($symptoms) || !is_array($makes) || !is_array($models) ||
                count($types) !== count($symptoms) || count($types) !== count($makes) ||
                count($types) !== count($models) || count($types) > 3 || !array_is_list($types)) {
                throw new DomainException('Invalid device entry form.');
            }
            $devices = [];
            foreach ($types as $i => $type) {
                if (!is_string($type) || !is_string($symptoms[$i] ?? null) ||
                    !is_string($makes[$i] ?? null) || !is_string($models[$i] ?? null)) {
                    throw new DomainException('Invalid device data.');
                }
                if (trim($type) === '' && trim($symptoms[$i]) === '' &&
                    trim($makes[$i]) === '' && trim($models[$i]) === '') continue;
                $devices[] = [
                    'device_type_code'=>$type, 'reported_symptoms'=>$symptoms[$i],
                    'make'=>$makes[$i], 'model'=>$models[$i],
                ];
            }
            $ticket = edpCreateIntakeDraft($pdo,(int)$staff['user_id'],$branchId,$devices);
            edpPreviewRedirect('?page=draft&id='.$ticket);
        }
        if ($action === 'issue') {
            $draftId = edpPreviewPositiveId($_POST['draft_id'] ?? null);
            $draftCheck = edpPreviewDraft($pdo,(int)$staff['user_id'],$draftId);
            if (!$draftCheck) {
                http_response_code(404);
                throw new DomainException('Draft not found in your assigned branch.');
            }
            if (($_POST['signed_terms'] ?? null) !== 'yes') {
                throw new DomainException('Confirm the customer signed the intake terms.');
            }
            $mode = $_POST['customer_mode'] ?? null;
            if ($mode === 'existing') {
                $customerId = edpPreviewPositiveId($_POST['customer_id'] ?? null);
                edpPreviewVerifyExistingCustomer($pdo,$customerId,(int)$draftCheck['branch_id']);
                $customerData = null;
            } elseif ($mode === 'new') {
                $name = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
                $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
                $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
                if ($name === '' || mb_strlen($name) > 160 || !preg_match('/^[0-9]{4,15}$/D',$phone) ||
                    ($email !== '' && (strlen($email) > 190 || !filter_var($email,FILTER_VALIDATE_EMAIL)))) {
                    throw new DomainException('Enter a customer name, normalized phone digits, and valid optional email.');
                }
                $customerId = 0;
                $customerData = [$name,$phone,$email === '' ? null : strtolower($email)];
            } else {
                throw new DomainException('Choose a new or returning customer.');
            }
            $relative = edpPreviewStageSignature($_FILES['signature'] ?? []);
            try {
                $pdo->beginTransaction();
                try {
                    if ($customerData !== null) {
                        $stmt = $pdo->prepare('INSERT INTO customers (full_name,phone,email) VALUES (?,?,?)');
                        $stmt->execute($customerData);
                        $customerId = (int)$pdo->lastInsertId();
                    }
                    $issued = edpIssueIntakeDraft($pdo,(int)$staff['user_id'],$draftId,$customerId,
                        edpPreviewPrivateRoot(),$relative);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }
            } catch (Throwable $e) {
                edpPreviewCleanStage($relative);
                throw $e;
            }
            $_SESSION['edp_receipt'] = $issued;
            edpPreviewRedirect('?page=receipt');
        }
        http_response_code(400);
        throw new DomainException('Unknown form action.');
    }
    if ($page !== 'login' && !$staff) edpPreviewRedirect('?page=login');
    if ($page === 'login' && $staff) edpPreviewRedirect('?page=intake');
    if ($page === 'intake') {
        $branches = edpPreviewBranches($pdo,(int)$staff['user_id']);
        $catalogs = edpReferenceCatalogs($pdo);
    } elseif ($page === 'draft') {
        $id = edpPreviewPositiveId($_GET['id'] ?? null);
        $draft = edpPreviewDraft($pdo,(int)$staff['user_id'],$id);
        if (!$draft) {
            http_response_code(404);
            throw new DomainException('This draft is unavailable in your assigned branch.');
        }
        $customers = edpPreviewCustomerMatches($pdo,(int)$draft['branch_id'],is_string($_GET['q'] ?? null)?$_GET['q']:'');
    } elseif ($page === 'receipt') {
        $receipt = $_SESSION['edp_receipt'] ?? null;
        unset($_SESSION['edp_receipt']);
    } elseif ($page !== 'login') {
        http_response_code(404);
        throw new DomainException('Page not found.');
    }
} catch (DomainException $e) {
    $error = $e->getMessage();
    if (http_response_code() < 400) http_response_code(422);
    // Safe redraw of the form after a validation failure. Do not echo SQL details.
    if ($pdo instanceof PDO && $staff && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : ''; 
        if ($action === 'issue') {
            $page = 'draft';
            $id = is_string($_POST['draft_id'] ?? null) && ctype_digit($_POST['draft_id'])
                ? (int)$_POST['draft_id'] : 0;
            $draft = $id > 0 ? edpPreviewDraft($pdo,(int)$staff['user_id'],$id) : null;
            if ($draft) $customers = edpPreviewCustomerMatches($pdo,(int)$draft['branch_id'],'');
        } elseif ($action === 'create_draft') {
            $page = 'intake';
            $branches = edpPreviewBranches($pdo,(int)$staff['user_id']);
            $catalogs = edpReferenceCatalogs($pdo);
        } elseif ($action === 'login') $page = 'login';
    }
} catch (Throwable $e) {
    error_log('EDP academic web preview: '.get_class($e));
    http_response_code(503);
    $error = 'The academic preview is temporarily unavailable.';
}
$csrf = edpPreviewCsrf();
$h = 'edpPreviewEscape';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h(ucfirst($page)) ?> · RepairTrack Academic Preview</title>
<link rel="stylesheet" href="assets/preview.css">
</head>
<body>
<header class="topbar"><div><span class="brand">RepairTrack</span><span class="tag">EDP academic preview · fictional data</span></div>
<?php if ($staff): ?><div class="signed"><span><?= $h($staff['full_name']) ?> · <?= $h($staff['role_code']) ?></span>
<form method="post" action=""><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="plain" type="submit">Sign out</button></form></div><?php endif; ?></header>
<main class="layout">
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= $h($error) ?></div><?php endif; ?>
<?php if ($page === 'login'): ?>
<section class="panel narrow"><p class="eyebrow">Staff authentication</p><h1>Academic workspace</h1>
<p>Sign in using a fictional account created in the isolated academic database.</p>
<form method="post" action="?page=login" class="stack">
<input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<label>Email<input type="email" name="email" autocomplete="username" maxlength="190" required></label>
<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
<button class="primary" type="submit">Sign in</button></form></section>
<?php elseif ($page === 'intake' && $staff): ?>
<nav><a href="?page=intake">Intake</a></nav><section class="panel"><p class="eyebrow">Step 1 of 2</p><h1>Create a repair draft</h1>
<p>Register up to three devices before selecting the customer. Additional devices can be supported in later UI revisions.</p>
<form method="post" action="?page=intake" class="stack"><input type="hidden" name="action" value="create_draft"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<label>Receiving branch<select name="branch_id" required>
<?php foreach ($branches as $b): ?><option value="<?= (int)$b['branch_id'] ?>"><?= $h($b['branch_name']) ?></option><?php endforeach; ?>
</select></label>
<?php for ($i=1; $i<=3; $i++): ?><fieldset class="device"><legend>Device <?= $i ?><?= $i===1?' · required':' · optional' ?></legend>
<div class="grid-two"><label>Type<select name="device_type_code[]" <?= $i===1?'required':'' ?>><option value="">Choose type</option>
<?php foreach ($catalogs['device_types']??[] as $type): ?><option value="<?= $h($type['code']) ?>"><?= $h($type['label']) ?></option><?php endforeach; ?></select></label>
<label>Reported issue<input type="text" name="reported_symptoms[]" maxlength="2000" placeholder="e.g. No display" <?= $i===1?'required':'' ?>></label>
<label>Make<input type="text" name="make[]" maxlength="100" placeholder="Optional"></label><label>Model<input type="text" name="model[]" maxlength="160" placeholder="Optional"></label></div>
</fieldset><?php endfor; ?>
<button class="primary" type="submit" <?= $branches?'':'disabled' ?>>Save draft</button></form></section>
<?php elseif ($page === 'draft' && $draft && $staff): ?>
<nav><a href="?page=intake">← New intake</a></nav><section class="panel"><p class="eyebrow">Step 2 of 2 · <?= $h($draft['branch_name']) ?></p>
<h1>Issue draft #<?= (int)$draft['ticket_id'] ?></h1><p>Review the devices, select an existing customer or register a new customer, then attach the signed intake image.</p>
<div class="device-summary"><?php foreach ($draft['devices'] as $dev): ?><div><strong>Device <?= (int)$dev['sequence_no'] ?></strong> · <?= $h($dev['device_type_code']) ?><?= $dev['model_snapshot']?' · '.$h($dev['model_snapshot']):'' ?>
<p><?= $h($dev['reported_symptoms']) ?></p></div><?php endforeach; ?></div>
<section class="search-panel"><h2>Returning customer lookup</h2><form method="get" action="" class="search-row"><input type="hidden" name="page" value="draft"><input type="hidden" name="id" value="<?= (int)$draft['ticket_id'] ?>">
<label>Search name or phone<input type="search" name="q" value="<?= $h(is_string($_GET['q']??null)?$_GET['q']:'') ?>" minlength="2" maxlength="80" placeholder="Enter at least 2 characters"></label><button type="submit">Search</button></form></section>
<form method="post" action="?page=draft&amp;id=<?= (int)$draft['ticket_id'] ?>" enctype="multipart/form-data" class="stack">
<input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="issue"><input type="hidden" name="draft_id" value="<?= (int)$draft['ticket_id'] ?>">
<fieldset><legend>Customer selection</legend>
<label class="choice"><input type="radio" name="customer_mode" value="new" checked> New customer</label>
<div class="grid-two"><label>Full name<input type="text" name="full_name" maxlength="160" autocomplete="name"></label><label>Phone (digits only)<input type="tel" name="phone" maxlength="15" placeholder="09123456789" inputmode="numeric"></label>
<label>Email (optional)<input type="email" name="email" maxlength="190" autocomplete="email"></label></div>
<label class="choice"><input type="radio" name="customer_mode" value="existing"> Returning customer (select from search results)</label>
<label>Existing customer<select name="customer_id"><option value="">Choose searched customer</option>
<?php foreach ($customers as $customer): ?><option value="<?= (int)$customer['customer_id'] ?>"><?= $h($customer['full_name']) ?> · phone ending <?= $h($customer['phone_suffix']) ?></option><?php endforeach; ?></select></label>
</fieldset>
<fieldset><legend>Signed intake evidence</legend><label>Signed PNG, JPEG or WebP image (max 5 MiB)
<input type="hidden" name="MAX_FILE_SIZE" value="5242880"><input type="file" name="signature" accept="image/png,image/jpeg,image/webp" required></label>
<label class="choice"><input type="checkbox" name="signed_terms" value="yes" required> I confirm the customer signed the CLASSROOM-1 intake terms and authorized this repair intake.</label></fieldset>
<p class="hint">The portal code will be shown once on the confirmation screen. The PIN is the last four digits of the recorded phone and is stored only as a password hash.</p>
<button class="primary" type="submit">Issue repair ticket</button></form></section>
<?php elseif ($page === 'receipt' && is_array($receipt)): ?>
<section class="panel narrow"><p class="eyebrow">Ticket issued</p><h1>Issue successful</h1><p>Copy this receipt before leaving. Tracking credentials are shown on this screen only.</p>
<dl class="receipt"><dt>Ticket Code</dt><dd><?= $h($receipt['ticket_code']??'') ?></dd><dt>Customer portal code</dt><dd><?= $h($receipt['portal_code']??'') ?></dd>
<dt>Devices</dt><dd><?= (int)($receipt['device_count']??0) ?></dd></dl><p class="hint">Customer access also requires the last four digits of the recorded phone. Do not include the PIN on this receipt.</p>
<a class="primary link-primary" href="?page=intake">New intake</a></section>
<?php elseif ($page === 'receipt'): ?>
<section class="panel narrow"><h1>Receipt no longer available</h1><p>For privacy, the issuance confirmation is available only immediately after submitting the form.</p><a href="?page=intake">Return to intake</a></section>
<?php else: ?><section class="panel narrow"><h1>Unavailable</h1><p>The requested academic preview page could not be shown.</p></section><?php endif; ?>
</main>
<footer>Isolated local academic preview. Not connected to the deployed RepairTrack website.</footer>
</body></html>
