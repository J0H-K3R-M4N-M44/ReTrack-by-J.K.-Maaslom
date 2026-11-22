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
function edpCreateIntakeDraft(PDO $pdo, int $actorId, int $branchId, array $devices): int
{
    if (!$devices || count($devices) > 25 || !array_is_list($devices)) {
        throw new DomainException('Provide between 1 and 25 intake devices.');
    }
    $prepared = [];
    foreach ($devices as $device) {
        if (!is_array($device)) {
            throw new DomainException('Invalid intake device.');
        }
        $type = trim((string)($device['device_type_code'] ?? ''));
        $symptoms = trim((string)($device['reported_symptoms'] ?? ''));
        if ($type === '' || strlen($type) > 30 || $symptoms === '' || strlen($symptoms) > 2000) {
            throw new DomainException('Select a device type and describe the reported problem.');
        }
        $make = trim((string)($device['make'] ?? ''));
        $model = trim((string)($device['model'] ?? ''));
        $serial = trim((string)($device['serial'] ?? ''));
        $condition = trim((string)($device['condition_notes'] ?? ''));
        $accessories = trim((string)($device['accessories'] ?? ''));
        if (strlen($make) > 100 || strlen($model) > 160 || strlen($serial) > 190 ||
            strlen($condition) > 2000 || strlen($accessories) > 255) {
            throw new DomainException('An intake field is too long.');
        }
        $prepared[] = [$type,$symptoms,$make,$model,$serial,$condition,$accessories];
    }

    return edpInTransaction($pdo, function () use ($pdo,$actorId,$branchId,$prepared): int {
        edpActiveBranchStaff($pdo,$actorId,$branchId,['ADMIN','MANAGER','INTAKE_AGENT']);
        $types = $pdo->query('SELECT device_type_code FROM device_types WHERE is_active=1')
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($prepared as $record) {
            if (!in_array($record[0],$types,true)) {
                throw new DomainException('Choose an active device type.');
            }
        }
        $stmt = $pdo->prepare("INSERT INTO repair_tickets
            (branch_id,created_by_user_id,ticket_state) VALUES (?,?,'DRAFT')");
        $stmt->execute([$branchId,$actorId]);
        $ticketId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO ticket_devices
            (ticket_id,branch_id,sequence_no,device_type_code,make_snapshot,model_snapshot,
             serial_snapshot,status_code,reported_symptoms,condition_notes,accessories)
            VALUES (?,?,?,?,?,?,?,'DRAFT',?,?,?)");
        foreach ($prepared as $index => $record) {
            [$type,$symptoms,$make,$model,$serial,$condition,$accessories] = $record;
            $stmt->execute([$ticketId,$branchId,$index+1,$type,$make ?: null,$model ?: null,
                $serial ?: null,$symptoms,$condition ?: null,$accessories ?: null]);
        }
        return $ticketId;
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
    string $privateRoot, string $signaturePath, string $termsVersion = 'CLASSROOM-1'): array
{
    $signature = edpVerifiedSignature($privateRoot,$signaturePath);
    if ($termsVersion === '' || strlen($termsVersion) > 40) {
        throw new DomainException('Invalid intake terms version.');
    }
    return edpInTransaction($pdo, function () use ($pdo,$actorId,$ticketId,$customerId,$signature,$termsVersion): array {
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