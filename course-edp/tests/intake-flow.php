<?php
declare(strict_types=1);

/** Isolated fictional end-to-end intake domain test, all MySQL rows rolled back. */
if (PHP_SAPI !== 'cli' || getenv('REPAIRTRACK_EDP_TEST_DB') !== 'repairtrack_edp') {
    fwrite(STDERR,"Run only on an isolated repairtrack_edp test database.\n");
    exit(2);
}
require_once __DIR__ . '/../app/intake.php';
$pdo = new PDO('mysql:host=' . (getenv('REPAIRTRACK_EDP_TEST_HOST') ?: '127.0.0.1') .
    ';dbname=repairtrack_edp;charset=utf8mb4',
    getenv('REPAIRTRACK_EDP_TEST_USER') ?: 'root', getenv('MYSQL_PWD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
$pdo->exec("SET time_zone='+00:00'");
function intakePass(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message."\n";
}
function intakeReject(callable $action, string $message): void
{
    try { $action(); } catch (DomainException $e) { intakePass(true,$message); return; }
    throw new RuntimeException('FAIL (accepted): '.$message);
}
function intakeAdd(PDO $pdo, string $sql, array $params = []): int
{
    $s=$pdo->prepare($sql);$s->execute($params);return (int)$pdo->lastInsertId();
}
$privateRoot=sys_get_temp_dir().'/edp-intake-'.bin2hex(random_bytes(8));
if (!mkdir($privateRoot.'/signed',0700,true)) throw new RuntimeException('Cannot create test storage');
$signature='signed/receipt.png';
$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL4WQAAAABJRU5ErkJggg==',true);
if (!is_string($bytes) || file_put_contents($privateRoot.'/'.$signature,$bytes) !== strlen($bytes)) {
    throw new RuntimeException('Cannot write fictional signature');
}
try {
    $catalogs=edpReferenceCatalogs($pdo);
    intakePass(count($catalogs['device_types']) >= 6 && count($catalogs['defect_types']) >= 9 &&
        count($catalogs['repair_statuses']) === 10,'catalogs return seeded constrained categories');
    intakeReject(fn()=>edpVerifiedSignature($privateRoot,'../outside.png'),
        'path traversal is rejected');
    intakeReject(fn()=>edpVerifiedSignature($privateRoot,'signed/missing.png'),
        'missing signature file is rejected');
    intakePass(edpVerifiedSignature($privateRoot,$signature)['mime']==='image/png',
        'actual private image content and MIME are verified');

    $branch=(int)$pdo->query("SELECT branch_id FROM branches WHERE branch_code='SOGOD-DEMO'")->fetchColumn();
    if (!$branch) throw new RuntimeException('Fictional Sogod branch seed missing');
    $pdo->beginTransaction();
    try {
        $suffix=bin2hex(random_bytes(5));
        $intake=intakeAdd($pdo,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES ('Fictional Intake',?,?,'INTAKE_AGENT')",
            ['intake-'.$suffix.'@example.test',password_hash('SyntheticOnly!',PASSWORD_DEFAULT)]);
        $cashier=intakeAdd($pdo,"INSERT INTO users(full_name,email,password_hash,role_code) VALUES ('Fictional Cashier',?,?,'CASHIER')",
            ['cashier-'.$suffix.'@example.test',password_hash('SyntheticOnly!',PASSWORD_DEFAULT)]);
        $pdo->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES(?,?)')->execute([$intake,$branch]);
        $pdo->prepare('INSERT INTO user_branch_assignments(user_id,branch_id) VALUES(?,?)')->execute([$cashier,$branch]);
        $customer=intakeAdd($pdo,"INSERT INTO customers(full_name,phone) VALUES('Fictional Repair Owner','09123456789')");
        $other=intakeAdd($pdo,"INSERT INTO customers(full_name,phone) VALUES('Fictional Other Owner','09998887777')");
        intakePass(count(edpIntakeLocations($pdo,$intake,$branch))===3,'intake sees own branch locations');
        intakePass(edpIntakeLocations($pdo,$cashier,$branch)===[], 'cashier cannot load restricted intake locations');
        intakeReject(fn()=>edpCreateIntakeDraft($pdo,$cashier,$branch,[[
            'device_type_code'=>'LAPTOP','reported_symptoms'=>'No display']]),
            'cashier cannot create draft');
        intakeReject(fn()=>edpCreateIntakeDraft($pdo,$intake,$branch,[[
            'device_type_code'=>'UNKNOWN','reported_symptoms'=>'No display']]),
            'inactive or unknown device catalog entry rejected');
        intakeReject(fn()=>edpCreateIntakeDraft($pdo,$intake,$branch,[[
            'device_type_code'=>'LAPTOP','reported_symptoms'=>'  ']]),
            'blank reported defect rejected before database write');

        $initialDevices = [
            ['device_type_code'=>'LAPTOP','defect_code'=>'NO_POWER','reported_symptoms'=>'Will not power on',
             'make'=>'Example','model'=>'Book One','accessories'=>'Charger'],
            ['device_type_code'=>'DESKTOP','defect_code'=>'OVERHEATING',
             'reported_symptoms'=>'Frequent overheating','model'=>'Tower Two'],
        ];
        $requestKey = str_repeat('c',64);
        $ticket=edpCreateIntakeDraft($pdo,$intake,$branch,$initialDevices,$requestKey);
        intakePass(edpCreateIntakeDraft($pdo,$intake,$branch,$initialDevices,$requestKey)===$ticket,
            'retrying a draft request reuses the original ticket ID');
        intakeReject(fn()=>edpCreateIntakeDraft($pdo,$intake,$branch,[
            ['device_type_code'=>'LAPTOP','defect_code'=>'NOT_A_DEFECT',
             'reported_symptoms'=>'Needs diagnosis']]), 'invalid defect category rejected');
        edpEditIntakeDraft($pdo,$intake,$ticket,[
            ['device_type_code'=>'LAPTOP','defect_code'=>'NO_DISPLAY','reported_symptoms'=>'Screen stays black',
             'make'=>'Example','model'=>'Book One','accessories'=>'Charger'],
            ['device_type_code'=>'DESKTOP','defect_code'=>'OVERHEATING',
             'reported_symptoms'=>'Frequent overheating','model'=>'Tower Two'],
        ]);
        $findDefects=$pdo->prepare('SELECT f.defect_code FROM ticket_defects f
            JOIN ticket_devices d ON d.ticket_device_id=f.ticket_device_id
            WHERE d.ticket_id=? AND d.sequence_no=1');
        $findDefects->execute([$ticket]);
        intakePass($findDefects->fetchColumn()==='NO_DISPLAY',
            'editing a draft replaces its controlled defect reference');
        $abandon = edpCreateIntakeDraft($pdo,$intake,$branch,$initialDevices);
        edpAbandonIntakeDraft($pdo,$intake,$abandon);
        $q=$pdo->prepare('SELECT ticket_state,portal_code FROM repair_tickets WHERE ticket_id=?');
        $q->execute([$abandon]);$gone=$q->fetch(PDO::FETCH_ASSOC);
        intakePass($gone['ticket_state']==='ABANDONED' && $gone['portal_code']===null,
            'abandoned draft retains history without issuing tracking credentials');
        intakeReject(fn()=>edpEditIntakeDraft($pdo,$intake,$abandon,$initialDevices),
            'abandoned draft cannot be edited');
        intakeReject(fn()=>edpIssueIntakeDraft($pdo,$intake,$abandon,$customer,$privateRoot,$signature),
            'abandoned draft cannot be issued');
        $q=$pdo->prepare('SELECT ticket_state,customer_id,ticket_code FROM repair_tickets WHERE ticket_id=?');
        $q->execute([$ticket]);$draft=$q->fetch(PDO::FETCH_ASSOC);
        intakePass($draft['ticket_state']==='DRAFT' && $draft['customer_id']===null &&
            $draft['ticket_code']===null,'device-first intake draft has no customer or issued code');
        $q=$pdo->prepare('SELECT COUNT(*) FROM ticket_devices WHERE ticket_id=? AND status_code=\'DRAFT\'');
        $q->execute([$ticket]);intakePass((int)$q->fetchColumn()===2,'draft contains two unsaved devices');
        intakeReject(fn()=>edpIssueIntakeDraft($pdo,$cashier,$ticket,$customer,$privateRoot,$signature),
            'cashier cannot issue customer repair credentials');
        intakeReject(fn()=>edpIssueIntakeDraft($pdo,$intake,$ticket,999999,$privateRoot,$signature),
            'nonexistent customer cannot be used at issue');
        intakeReject(fn()=>edpIssueIntakeDraft($pdo,$intake,$ticket,$customer,$privateRoot,'signed/no-file.png'),
            'nonexistent private signature cannot issue a ticket');
        $result=edpIssueIntakeDraft($pdo,$intake,$ticket,$customer,$privateRoot,$signature);
        intakePass($result['ticket_id']===$ticket && $result['device_count']===2 &&
            preg_match('/^[0-9A-F]{5}$/D',$result['portal_code'])===1 &&
            str_starts_with($result['ticket_code'],'RT-') && !array_key_exists('pin',$result),
            'issued ticket has distinct code, five-hex portal code, and no exposed PIN');
        $q=$pdo->prepare('SELECT customer_id,portal_pin_hash,ticket_state,terms_signature_path FROM repair_tickets WHERE ticket_id=?');
        $q->execute([$ticket]);$issued=$q->fetch(PDO::FETCH_ASSOC);
        intakePass((int)$issued['customer_id']===$customer && $issued['ticket_state']==='OPEN' &&
            password_verify('6789',$issued['portal_pin_hash']) && $issued['terms_signature_path']===$signature,
            'issue saves owner, hashed phone-suffix PIN and protected signature reference');
        $q=$pdo->prepare("SELECT COUNT(*) FROM ticket_devices WHERE ticket_id=? AND customer_id=?
            AND item_code IS NOT NULL AND customer_device_id IS NOT NULL
            AND status_code='RECEIVED' AND custody_state='AT_LOCATION' AND location_id IS NOT NULL");
        $q->execute([$ticket,$customer]);intakePass((int)$q->fetchColumn()===2,
            'both issued devices gain owned records and intake custody');
        $q=$pdo->prepare("SELECT COUNT(*) FROM ticket_status_events e JOIN ticket_devices d
            ON d.ticket_device_id=e.ticket_device_id WHERE d.ticket_id=?
            AND e.from_status='DRAFT' AND e.to_status='RECEIVED' AND e.is_customer_visible=1");
        $q->execute([$ticket]);intakePass((int)$q->fetchColumn()===2,
            'each issued device gets a visible initial status event');
        $q=$pdo->prepare("SELECT COUNT(*) FROM media_assets WHERE ticket_id=? AND customer_id=?
            AND asset_category='SIGNATURE' AND mime_type='image/png'");
        $q->execute([$ticket,$customer]);intakePass((int)$q->fetchColumn()===1,
            'intake signature registered under customer and ticket with verified MIME');
        intakeReject(fn()=>edpIssueIntakeDraft($pdo,$intake,$ticket,$other,$privateRoot,$signature),
            'issued ticket cannot be issued again for another customer');
        intakePass(true,'all data remains inside a rollback-only fictional fixture');
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
} finally {
    @unlink($privateRoot.'/'.$signature);
    @rmdir($privateRoot.'/signed');
    @rmdir($privateRoot);
}
