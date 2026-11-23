<?php
declare(strict_types=1);

require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/catalogs.php';

/**
 * Create an unissued draft before the customer has been entered.
 * Caller must authenticate the staff member and validate session/CSRF before invoking.
 * This is a domain function, not a public POST endpoint.
 *
 * @param array<int,array{device_type_code:string,reported_symptoms:string,
 *     make?:string,model?:string,serial?:string,condition_notes?:string,accessories?:string}> $devices
 */
function edpIntakePrepareDevices(array $devices): array
{
    if (!$devices || count($devices) > 25 || !array_is_list($devices)) {
        throw new DomainException('Provide between 1 and 25 intake devices.');
    }
    $prepared = [];
    foreach ($devices as $device) {
        if (!is_array($device)) throw new DomainException('Invalid intake device.');
        $type = trim((string)($device['device_type_code'] ?? ''));
        $symptoms = trim((string)($device['reported_symptoms'] ?? ''));
        $make = trim((string)($device['make'] ?? ''));
        $model = trim((string)($device['model'] ?? ''));
        $serial = trim((string)($device['serial'] ?? ''));
        $condition = trim((string)($device['condition_notes'] ?? ''));
        $accessories = trim((string)($device['accessories'] ?? ''));
        $defect = trim((string)($device['defect_code'] ?? ''));
        if ($type === '' || strlen($type) > 30 || $symptoms === '' || strlen($symptoms) > 2000 ||
            strlen($make) > 100 || strlen($model) > 160 || strlen($serial) > 190 ||
            strlen($condition) > 2000 || strlen($accessories) > 255 || strlen($defect) > 32) {
            throw new DomainException('A device type, reported problem or intake field is invalid.');
        }
        $prepared[] = [$type,$symptoms,$make,$model,$serial,$condition,$accessories,$defect];
    }
    return $prepared;
}

/** Check catalogs in the same transaction that writes the device and defect. */
function edpIntakeCheckCatalogs(PDO $pdo, array $prepared): void
{
    $types = $pdo->query('SELECT device_type_code FROM device_types WHERE is_active=1')
        ->fetchAll(PDO::FETCH_COLUMN);
    $defects = $pdo->query('SELECT defect_code FROM defect_types WHERE is_active=1')
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($prepared as $device) {
        if (!in_array($device[0], $types, true) ||
            ($device[7] !== '' && !in_array($device[7], $defects, true))) {
            throw new DomainException('Choose active device and defect categories.');
        }
    }
}

/**
 * Save a device-first draft. A unique browser request key makes retries return
 * the original draft rather than generating a second ticket.
 */
function edpCreateIntakeDraft(PDO $pdo, int $actorId, int $branchId, array $devices,
    ?string $requestKey = null): int
{
    $prepared = edpIntakePrepareDevices($devices);
    if ($requestKey !== null && !preg_match('/^[0-9a-f]{64}$/D', $requestKey)) {
        throw new DomainException('Invalid draft request key.');
    }
    return edpInTransaction($pdo, function () use ($pdo,$actorId,$branchId,$prepared,$requestKey): int {
        edpActiveBranchStaff($pdo,$actorId,$branchId,['ADMIN','MANAGER','INTAKE_AGENT']);
        edpIntakeCheckCatalogs($pdo,$prepared);
        $find = $pdo->prepare('SELECT ticket_id, branch_id, created_by_user_id
            FROM repair_tickets WHERE create_request_key=? FOR UPDATE');
        if ($requestKey !== null) {
            $find->execute([$requestKey]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ((int)$existing['branch_id'] !== $branchId ||
                    (int)$existing['created_by_user_id'] !== $actorId) {
                    throw new DomainException('This draft request belongs to a different session.');
                }
                return (int)$existing['ticket_id'];
            }
        }
        $stmt = $pdo->prepare("INSERT INTO repair_tickets
            (branch_id,created_by_user_id,create_request_key,ticket_state) VALUES (?,?,?,'DRAFT')");
        try {
            $stmt->execute([$branchId,$actorId,$requestKey]);
        } catch (PDOException $error) {
            // A concurrent replay may win the unique key; recover only *that* key.
            if ($requestKey === null || (int)($error->errorInfo[1] ?? 0) !== 1062) throw $error;
            $find->execute([$requestKey]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if (!$existing || (int)$existing['branch_id'] !== $branchId ||
                (int)$existing['created_by_user_id'] !== $actorId) throw $error;
            return (int)$existing['ticket_id'];
        }
        $ticketId = (int)$pdo->lastInsertId();
        $add = $pdo->prepare("INSERT INTO ticket_devices
            (ticket_id,branch_id,sequence_no,device_type_code,make_snapshot,model_snapshot,
             serial_snapshot,status_code,reported_symptoms,condition_notes,accessories)
            VALUES (?,?,?,?,?,?,?,'DRAFT',?,?,?)");
        $defect = $pdo->prepare('INSERT INTO ticket_defects (ticket_device_id,defect_code) VALUES (?,?)');
        foreach ($prepared as $index => $device) {
            [$type,$symptoms,$make,$model,$serial,$condition,$accessories,$defectCode] = $device;
            $add->execute([$ticketId,$branchId,$index+1,$type,$make ?: null,$model ?: null,
                $serial ?: null,$symptoms,$condition ?: null,$accessories ?: null]);
            if ($defectCode !== '') $defect->execute([(int)$pdo->lastInsertId(),$defectCode]);
        }
        return $ticketId;
    });
}

/** Draft owner or branch manager may amend every saved device before issue. */
function edpEditIntakeDraft(PDO $pdo, int $actorId, int $ticketId, array $devices): void
{
    $prepared = edpIntakePrepareDevices($devices);
    edpInTransaction($pdo, function () use ($pdo,$actorId,$ticketId,$prepared): void {
        $q = $pdo->prepare('SELECT branch_id,created_by_user_id,ticket_state
            FROM repair_tickets WHERE ticket_id=? FOR UPDATE');
        $q->execute([$ticketId]);
        $ticket = $q->fetch(PDO::FETCH_ASSOC);
        if (!$ticket || $ticket['ticket_state'] !== 'DRAFT') {
            throw new DomainException('This draft cannot be edited.');
        }
        $role = edpActiveBranchStaff($pdo,$actorId,(int)$ticket['branch_id'],
            ['ADMIN','MANAGER','INTAKE_AGENT']);
        if ((int)$ticket['created_by_user_id'] !== $actorId &&
            !in_array($role,['ADMIN','MANAGER'],true)) {
            throw new DomainException('Only the draft author or branch manager can edit it.');
        }
        edpIntakeCheckCatalogs($pdo,$prepared);
        $q = $pdo->prepare('SELECT ticket_device_id FROM ticket_devices
            WHERE ticket_id=? ORDER BY sequence_no FOR UPDATE');
        $q->execute([$ticketId]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) !== count($prepared)) {
            throw new DomainException('Edit each existing device; adding or removing requires a new draft.');
        }
        $update = $pdo->prepare("UPDATE ticket_devices SET device_type_code=?,
            reported_symptoms=?,make_snapshot=?,model_snapshot=?
            WHERE ticket_device_id=? AND status_code='DRAFT'");
        $remove = $pdo->prepare('DELETE FROM ticket_defects WHERE ticket_device_id=?');
        $addDefect = $pdo->prepare('INSERT INTO ticket_defects (ticket_device_id,defect_code) VALUES (?,?)');
        foreach ($ids as $index=>$id) {
            [$type,$symptoms,$make,$model,$serial,$condition,$accessories,$defectCode] = $prepared[$index];
            $update->execute([$type,$symptoms,$make?:null,$model?:null,$id]);
            $remove->execute([$id]);
            if ($defectCode !== '') $addDefect->execute([$id,$defectCode]);
        }
        $pdo->prepare("INSERT INTO audit_events (actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'repair_ticket',?,'INTAKE_DRAFT_EDITED')")->execute([$actorId,$ticketId]);
    });
}

/** An abandoned draft is not an issued or cancelled repair; its history remains. */
function edpAbandonIntakeDraft(PDO $pdo, int $actorId, int $ticketId): void
{
    edpInTransaction($pdo, function () use ($pdo,$actorId,$ticketId): void {
        $q = $pdo->prepare('SELECT branch_id,created_by_user_id,ticket_state
            FROM repair_tickets WHERE ticket_id=? FOR UPDATE');
        $q->execute([$ticketId]);
        $ticket = $q->fetch(PDO::FETCH_ASSOC);
        if (!$ticket || $ticket['ticket_state'] !== 'DRAFT') throw new DomainException('Draft is no longer available.');
        $role = edpActiveBranchStaff($pdo,$actorId,(int)$ticket['branch_id'],
            ['ADMIN','MANAGER','INTAKE_AGENT']);
        if ((int)$ticket['created_by_user_id'] !== $actorId &&
            !in_array($role,['ADMIN','MANAGER'],true)) {
            throw new DomainException('Only the draft author or branch manager can abandon it.');
        }
        $pdo->prepare("UPDATE repair_tickets SET ticket_state='ABANDONED' WHERE ticket_id=?")
            ->execute([$ticketId]);
        $pdo->prepare("INSERT INTO audit_events (actor_user_id,entity_type,entity_id,action_code)
            VALUES (?,'repair_ticket',?,'INTAKE_DRAFT_ABANDONED')")->execute([$actorId,$ticketId]);
    });
}

/**
 * Verify that a signature image exists inside a server-configured private directory.
 * It must be an already-stored file; never pass the browser's original filename.
 *
 * @return array{path:string,mime:string,size:int,sha256:string}
 */
function edpVerifiedSignature(string $privateRoot, string $relativePath): array
{
    if (!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\.(?:png|jpg|jpeg|webp)$~D', $relativePath)) {
        throw new DomainException('Invalid protected signature identifier.');
    }
    $root = realpath($privateRoot);
    $path = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) ||
        !is_file($path) || !is_readable($path)) {
        throw new DomainException('Signed intake image was not found in private storage.');
    }
    $size = filesize($path);
    if ($size === false || $size < 1 || $size > 5242880) {
        throw new DomainException('Signature image size is invalid.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg','image/png','image/webp'],true) || @getimagesize($path) === false) {
        throw new DomainException('Signature must be a valid supported image.');
    }
    $sha = hash_file('sha256',$path);
    if ($sha === false) {
        throw new DomainException('Signature could not be verified.');
    }
    return ['path'=>$relativePath,'mime'=>$mime,'size'=>$size,'sha256'=>$sha];
}

/**
 * Issue an existing academic draft, fully atomically.
 * Signature data is checked against private storage, not accepted on trust.
 * Returns only the public tracking code and internal receipt identifiers—never a PIN.
 */
function edpIssueIntakeDraft(PDO $pdo, int $actorId, int $ticketId, int $customerId,
    string $privateRoot, string $signaturePath, string $termsVersion = 'CLASSROOM-1',
    ?string $confirmedPhone = null): array
{
    $signature = edpVerifiedSignature($privateRoot,$signaturePath);
    if ($termsVersion === '' || strlen($termsVersion) > 40) {
        throw new DomainException('Invalid intake terms version.');
    }
    return edpInTransaction($pdo, function () use ($pdo,$actorId,$ticketId,$customerId,$signature,$termsVersion,$confirmedPhone): array {
        $stmt = $pdo->prepare('SELECT ticket_id,customer_id,branch_id,ticket_state
            FROM repair_tickets WHERE ticket_id=? FOR UPDATE');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket || $ticket['ticket_state'] !== 'DRAFT') {
            throw new DomainException('This draft is no longer available for issuance.');
        }
        $branchId = (int)$ticket['branch_id'];
        edpActiveBranchStaff($pdo,$actorId,$branchId,['ADMIN','MANAGER','INTAKE_AGENT']);
        if ($ticket['customer_id'] !== null && (int)$ticket['customer_id'] !== $customerId) {
            throw new DomainException('The draft belongs to a different customer.');
        }
        $stmt = $pdo->prepare('SELECT customer_id, phone FROM customers WHERE customer_id=? FOR UPDATE');
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer || !preg_match('/^[0-9]{4,15}$/D',(string)$customer['phone'])) {
            throw new DomainException('A valid recorded customer phone is required.');
        }
        if ($confirmedPhone !== null && !hash_equals((string)$customer['phone'], $confirmedPhone)) {
            throw new DomainException('Customer contact changed. Confirm their recorded phone again.');
        }
        $stmt = $pdo->prepare('SELECT ticket_device_id,customer_id,customer_device_id,
            sequence_no,status_code,device_type_code,make_snapshot,model_snapshot,serial_snapshot,
            reported_symptoms FROM ticket_devices WHERE ticket_id=? ORDER BY sequence_no FOR UPDATE');
        $stmt->execute([$ticketId]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$devices) {
            throw new DomainException('Add a device before issuing a ticket.');
        }
        $activeTypes = $pdo->query('SELECT device_type_code FROM device_types WHERE is_active=1')
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($devices as $device) {
            if ($device['status_code'] !== 'DRAFT' || $device['customer_device_id'] !== null ||
                ($device['customer_id'] !== null && (int)$device['customer_id'] !== $customerId) ||
                !in_array($device['device_type_code'],$activeTypes,true) ||
                trim((string)$device['reported_symptoms']) === '') {
                throw new DomainException('Complete each draft device before issuance.');
            }
        }
        $stmt = $pdo->prepare("SELECT location_id FROM storage_locations
            WHERE branch_id=? AND location_code='COUNTER' AND is_active=1 FOR UPDATE");
        $stmt->execute([$branchId]);
        $locationId = $stmt->fetchColumn();
        if (!$locationId) {
            throw new DomainException('A receiving counter location is required.');
        }
        $ticketCode = null;
        $portalCode = null;
        $pinHash = password_hash(substr((string)$customer['phone'],-4), PASSWORD_DEFAULT);
        $issue = $pdo->prepare("UPDATE repair_tickets
            SET customer_id=?, ticket_code=?, portal_code=?, portal_pin_hash=?,ticket_state='OPEN',
                issued_at=UTC_TIMESTAMP(),terms_version=?,terms_signed_at=UTC_TIMESTAMP(),terms_signature_path=?
            WHERE ticket_id=? AND ticket_state='DRAFT'");
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $ticketCode = 'RT-' . strtoupper(bin2hex(random_bytes(6)));
            $portalCode = strtoupper(substr(bin2hex(random_bytes(3)),0,5));
            try {
                $issue->execute([$customerId,$ticketCode,$portalCode,$pinHash,$termsVersion,
                    $signature['path'],$ticketId]);
                break;
            } catch (PDOException $error) {
                if ((int)($error->errorInfo[1] ?? 0) !== 1062) {
                    throw $error;
                }
                $ticketCode = null;
                $portalCode = null;
            }
        }
        if ($ticketCode === null || $portalCode === null || $issue->rowCount() !== 1) {
            throw new DomainException('Unable to generate unique tracking credentials.');
        }
        $createDevice = $pdo->prepare('INSERT INTO customer_devices
            (customer_id,device_type_code,brand,model,serial_number) VALUES (?,?,?,?,?)');
        $completeDevice = $pdo->prepare("UPDATE ticket_devices SET customer_id=?,customer_device_id=?,
            item_code=?,status_code='RECEIVED',custody_state='AT_LOCATION',location_id=?
            WHERE ticket_device_id=? AND status_code='DRAFT'");
        $initialEvent = $pdo->prepare("INSERT INTO ticket_status_events
            (ticket_device_id,from_status,to_status,actor_user_id,public_summary,is_customer_visible,created_at)
            VALUES (?,'DRAFT','RECEIVED',?,'Received for assessment',1,UTC_TIMESTAMP())");
        foreach ($devices as $device) {
            $createDevice->execute([$customerId,$device['device_type_code'],
                $device['make_snapshot'],$device['model_snapshot'],$device['serial_snapshot']]);
            $customerDeviceId = (int)$pdo->lastInsertId();
            $itemCode = $ticketCode . '-' . str_pad((string)$device['sequence_no'],2,'0',STR_PAD_LEFT);
            $completeDevice->execute([$customerId,$customerDeviceId,$itemCode,(int)$locationId,
                (int)$device['ticket_device_id']]);
            if ($completeDevice->rowCount() !== 1) {
                throw new DomainException('The intake device changed while issuing.');
            }
            $initialEvent->execute([(int)$device['ticket_device_id'],$actorId]);
        }
        $pdo->prepare("INSERT INTO media_assets
            (customer_id,ticket_id,asset_category,stored_path,mime_type,size_bytes,
             sha256_hash,uploaded_by_user_id,uploaded_at)
            VALUES (?,?,'SIGNATURE',?,?,?,?,?,UTC_TIMESTAMP())")
            ->execute([$customerId,$ticketId,$signature['path'],$signature['mime'],
                $signature['size'],$signature['sha256'],$actorId]);
        $pdo->prepare("INSERT INTO audit_events
            (actor_user_id,entity_type,entity_id,action_code,created_at)
            VALUES (?,'repair_ticket',?,'INTAKE_ISSUED',UTC_TIMESTAMP())")
            ->execute([$actorId,$ticketId]);
        return ['ticket_id'=>$ticketId,'ticket_code'=>$ticketCode,
            'portal_code'=>$portalCode,'device_count'=>count($devices)];
    });
}
