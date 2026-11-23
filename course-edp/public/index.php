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
$drafts = [];

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

/** Read a small repeated device form without accepting arbitrary nested values. */
function edpPreviewPostedDevices(array $post): array
{
    $fields = ['device_type_code','reported_symptoms','make','model','defect_code'];
    $types = $post['device_type_code'] ?? null;
    if (!is_array($types) || count($types) > 25 || !array_is_list($types)) {
        throw new DomainException('Invalid device entry form.');
    }
    $rows = [];
    foreach ($fields as $field) {
        if ($field === 'defect_code' && !isset($post[$field])) continue;
        if (!isset($post[$field]) || !is_array($post[$field]) ||
            count($post[$field]) !== count($types) || !array_is_list($post[$field])) {
            throw new DomainException('Invalid repeated device fields.');
        }
    }
    foreach ($types as $i=>$type) {
        $device = [];
        foreach ($fields as $field) {
            $value = $post[$field][$i] ?? '';
            if (!is_string($value)) throw new DomainException('Invalid device data.');
            $device[$field] = trim($value);
        }
        if (implode('', $device) === '') continue;
        if ($device['defect_code'] === '') {
            throw new DomainException('Choose a defect category (or Other) for each device.');
        }
        $rows[] = $device;
    }
    return $rows;
}

try {
    $pdo = edpPreviewDb();
    $staff = edpPreviewCurrentStaff($pdo);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $postedBytes = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postLimit = edpPreviewIniBytes((string)ini_get('post_max_size'));
        if ($postLimit > 0 && $postedBytes > $postLimit) {
            http_response_code(413);
            throw new DomainException('This submission exceeds the server upload limit. Choose a smaller image.');
        }
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
            $key = $_POST['draft_request_key'] ?? null;
            if (!is_string($key) || !preg_match('/^[0-9a-f]{64}$/D', $key)) {
                throw new DomainException('Reload the draft form and retry.');
            }
            $ticket = edpCreateIntakeDraft($pdo,(int)$staff['user_id'],$branchId,
                edpPreviewPostedDevices($_POST),$key);
            edpPreviewRedirect('?page=draft&id='.$ticket);
        }
        if ($action === 'edit_draft' || $action === 'abandon_draft') {
            $draftId = edpPreviewPositiveId($_POST['draft_id'] ?? null);
            $draftCheck = edpPreviewDraft($pdo,(int)$staff['user_id'],$draftId);
            if (!$draftCheck) {
                http_response_code(404);
                throw new DomainException('Draft not found in your assigned branch.');
            }
            if ($action === 'edit_draft') {
                edpEditIntakeDraft($pdo,(int)$staff['user_id'],$draftId,edpPreviewPostedDevices($_POST));
                edpPreviewRedirect('?page=draft&id='.$draftId);
            }
            edpAbandonIntakeDraft($pdo,(int)$staff['user_id'],$draftId);
            edpPreviewRedirect('?page=drafts');
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
            $confirmedPhone = null;
            if ($mode === 'existing') {
                $customerId = edpPreviewPositiveId($_POST['customer_id'] ?? null);
                $confirmedPhone = is_string($_POST['confirm_phone'] ?? null) ? trim($_POST['confirm_phone']) : '';
                edpPreviewVerifyExistingCustomer($pdo,$customerId,(int)$draftCheck['branch_id'],$confirmedPhone);
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
                        edpPreviewRejectDuplicateCustomer($pdo,(int)$draftCheck['branch_id'],$customerData[1]);
                        $stmt = $pdo->prepare('INSERT INTO customers (full_name,phone,email) VALUES (?,?,?)');
                        $stmt->execute($customerData);
                        $customerId = (int)$pdo->lastInsertId();
                    }
                    $issued = edpIssueIntakeDraft($pdo,(int)$staff['user_id'],$draftId,$customerId,
                        edpPreviewPrivateRoot(),$relative,'CLASSROOM-1',$confirmedPhone);
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
    if ($page === 'drafts') {
        $drafts = edpPreviewDrafts($pdo,(int)$staff['user_id']);
    } elseif ($page === 'intake') {
        $branches = edpPreviewBranches($pdo,(int)$staff['user_id']);
        $catalogs = edpReferenceCatalogs($pdo);
    } elseif ($page === 'draft') {
        $id = edpPreviewPositiveId($_GET['id'] ?? null);
        $draft = edpPreviewDraft($pdo,(int)$staff['user_id'],$id);
        $catalogs = edpReferenceCatalogs($pdo);
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
        } elseif ($action === 'edit_draft' || $action === 'abandon_draft') {
            $page = 'draft';
            $id = is_string($_POST['draft_id'] ?? null) && ctype_digit($_POST['draft_id']) ? (int)$_POST['draft_id'] : 0;
            $draft = $id > 0 ? edpPreviewDraft($pdo,(int)$staff['user_id'],$id) : null;
            $catalogs = edpReferenceCatalogs($pdo);
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
$draftRequestKey = bin2hex(random_bytes(32));
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
<?php if ($staff): ?><nav class="quicknav" aria-label="Intake navigation"><a href="?page=intake">New intake</a><a href="?page=drafts">My drafts</a></nav><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= $h($error) ?></div><?php endif; ?>
<?php if ($page === 'login'): ?>
<section class="panel narrow"><p class="eyebrow">Staff authentication</p><h1>Academic workspace</h1>
<p>Sign in using a fictional account created in the isolated academic database.</p>
<form method="post" action="?page=login" class="stack">
<input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<label>Email<input type="email" name="email" autocomplete="username" maxlength="190" required></label>
<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
<button class="primary" type="submit">Sign in</button></form></section>
<?php elseif ($page === 'drafts' && $staff): ?>
<section class="panel"><p class="eyebrow">Saved work</p><h1>My Drafts</h1>
<p>Resume an unissued ticket to correct its devices, select a customer and issue it. Abandoned drafts are not repair cancellations.</p>
<?php if (!$drafts): ?><p>No unfinished drafts assigned to your account.</p>
<?php else: ?><div class="draft-list">
<?php foreach ($drafts as $row): ?><a class="draft-link" href="?page=draft&amp;id=<?= (int)$row['ticket_id'] ?>">
<strong>Draft #<?= (int)$row['ticket_id'] ?></strong> · <?= $h($row['branch_name']) ?>
<span><?= (int)$row['device_count'] ?> device(s) · <?= $h($row['intake_at']) ?> UTC</span></a><?php endforeach; ?>
</div><?php endif; ?></section>
<?php elseif ($page === 'intake' && $staff): ?>
<nav><a href="?page=intake">Intake</a></nav><section class="panel"><p class="eyebrow">Step 1 of 2</p><h1>Create a repair draft</h1>
<p>Register up to three devices before selecting the customer. Select a predefined defect category or Other, then describe the actual symptoms.</p>
<form method="post" action="?page=intake" class="stack"><input type="hidden" name="action" value="create_draft"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="draft_request_key" value="<?= $h($draftRequestKey) ?>">
<label>Receiving branch<select name="branch_id" required>
<?php foreach ($branches as $b): ?><option value="<?= (int)$b['branch_id'] ?>"><?= $h($b['branch_name']) ?></option><?php endforeach; ?>
</select></label>
<?php for ($i=1; $i<=3; $i++): ?><fieldset class="device"><legend>Device <?= $i ?><?= $i===1?' · required':' · optional' ?></legend>
<div class="grid-two"><label>Type<select name="device_type_code[]" <?= $i===1?'required':'' ?>><option value="">Choose type</option>
<?php foreach ($catalogs['device_types']??[] as $type): ?><option value="<?= $h($type['code']) ?>"><?= $h($type['label']) ?></option><?php endforeach; ?></select></label>
<label>Defect category<select name="defect_code[]" <?= $i===1?'required':'' ?>><option value="">Choose defect</option>
<?php foreach ($catalogs['defect_types']??[] as $defect): ?><option value="<?= $h($defect['code']) ?>"><?= $h($defect['label']) ?></option><?php endforeach; ?></select></label>
<label>Reported issue<input type="text" name="reported_symptoms[]" maxlength="2000" placeholder="e.g. No display" <?= $i===1?'required':'' ?>></label>
<label>Make<input type="text" name="make[]" maxlength="100" placeholder="Optional"></label><label>Model<input type="text" name="model[]" maxlength="160" placeholder="Optional"></label></div>
</fieldset><?php endfor; ?>
<button class="primary" type="submit" <?= $branches?'':'disabled' ?>>Save draft</button></form></section>
<?php elseif ($page === 'draft' && $draft && $staff): ?>
<nav><a href="?page=intake">← New intake</a></nav><section class="panel"><p class="eyebrow">Step 2 of 2 · <?= $h($draft['branch_name']) ?></p>
<h1>Draft #<?= (int)$draft['ticket_id'] ?></h1><p>First correct any device information. Then select a customer and record staff-attested signed intake evidence.</p>
<div class="device-summary"><?php foreach ($draft['devices'] as $dev): ?><div><strong>Device <?= (int)$dev['sequence_no'] ?></strong> · <?= $h($dev['device_type_code']) ?><?= $dev['model_snapshot']?' · '.$h($dev['model_snapshot']):'' ?>
<p><?= $h($dev['reported_symptoms']) ?></p></div><?php endforeach; ?></div>
<section class="search-panel"><h2>Edit saved devices</h2>
<form method="post" action="?page=draft&amp;id=<?= (int)$draft['ticket_id'] ?>" class="stack">
<input type="hidden" name="action" value="edit_draft"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<input type="hidden" name="draft_id" value="<?= (int)$draft['ticket_id'] ?>">
<?php foreach ($draft['devices'] as $device): ?><fieldset class="device"><legend>Device <?= (int)$device['sequence_no'] ?></legend>
<div class="grid-two"><label>Type<select name="device_type_code[]" required>
<?php foreach ($catalogs['device_types']??[] as $type): ?><option value="<?= $h($type['code']) ?>" <?= $type['code']===$device['device_type_code']?'selected':'' ?>><?= $h($type['label']) ?></option><?php endforeach; ?></select></label>
<label>Defect category<select name="defect_code[]" required><option value="">Choose defect</option>
<?php foreach ($catalogs['defect_types']??[] as $defect): ?><option value="<?= $h($defect['code']) ?>" <?= $defect['code']===$device['defect_code']?'selected':'' ?>><?= $h($defect['label']) ?></option><?php endforeach; ?></select></label>
<label>Reported problem<input type="text" name="reported_symptoms[]" maxlength="2000" value="<?= $h($device['reported_symptoms']) ?>" required></label>
<label>Make<input type="text" name="make[]" maxlength="100" value="<?= $h($device['make_snapshot']) ?>"></label>
<label>Model<input type="text" name="model[]" maxlength="160" value="<?= $h($device['model_snapshot']) ?>"></label></div>
</fieldset><?php endforeach; ?>
<button class="primary" type="submit">Save device corrections</button></form>
<form method="post" action="?page=draft&amp;id=<?= (int)$draft['ticket_id'] ?>" class="stack abandon-form">
<input type="hidden" name="action" value="abandon_draft"><input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="draft_id" value="<?= (int)$draft['ticket_id'] ?>">
<button class="plain danger-button" type="submit">Abandon this unissued draft</button></form></section>
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
<label>Confirm full recorded phone for returning customer<input type="tel" name="confirm_phone" inputmode="numeric" autocomplete="off" maxlength="15" placeholder="Enter complete recorded phone"></label>
</fieldset>
<fieldset><legend>Staff-attested signed intake evidence</legend>
<p class="hint">CLASSROOM-1 terms: Staff must explain the reported repair scope, diagnostic assessment, quotation approval before paid repair, and customer review of costs before pickup. The checkbox records the staff member's attestation; image file checks cannot verify a handwritten signature.</p>
<label>Image of signed intake terms (PNG, JPEG or WebP; maximum 5 MiB)
<input type="hidden" name="MAX_FILE_SIZE" value="5242880"><input type="file" name="signature" accept="image/png,image/jpeg,image/webp" required></label>
<label class="choice"><input type="checkbox" name="signed_terms" value="yes" required> As the intake staff member, I attest the customer reviewed and signed the displayed CLASSROOM-1 terms before issuance.</label>
<?php if (edpPreviewUploadCapacity() < 5242880): ?><p class="hint" role="status">This PHP server's upload configuration is below 5 MiB. Ask the administrator to raise upload_max_filesize and post_max_size, or use a smaller image.</p><?php endif; ?></fieldset>
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
