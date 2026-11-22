<?php
declare(strict_types=1);
require_once __DIR__ . '/../inventory/reservations.php';

// Lock the operator's branch assignment before changing physical custody.
function transferAuthorize(PDO $pdo, int $user, int $branch): void
{
    $q = $pdo->prepare("SELECT a.user_id FROM user_branch_assignments a
        JOIN users u ON u.user_id=a.user_id JOIN branches b ON b.branch_id=a.branch_id
        WHERE a.user_id=? AND a.branch_id=? AND u.is_active=1 AND b.is_active=1
        AND u.role IN ('ADMIN','MANAGER','COURIER') FOR UPDATE");
    $q->execute([$user, $branch]);
    if (!$q->fetchColumn()) {
        throw new DomainException('This action requires an assignment at the handling branch.');
    }
}

function transferEvent(PDO $pdo, int $device, int $manifest, int $user, string $type,
    ?int $from, ?int $to, string $note): void
{
    $pdo->prepare('INSERT INTO device_custody_events
        (ticket_device_id,device_transfer_id,performed_by_user_id,event_type,from_location_id,to_location_id,note,happened_at)
        VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$device,$manifest,$user,$type,$from,$to,$note]);
}

function dispatchDevices(PDO $pdo, int $user, int $origin, int $destination, string $input): string
{
    $codes = preg_split('/[\s,]+/', strtoupper(trim($input)), -1, PREG_SPLIT_NO_EMPTY);
    if (!$codes || count($codes)>20 || count(array_unique($codes))!==count($codes)) {
        throw new DomainException('Enter 1–20 different device item codes.');
    }
    sort($codes); // Concurrent manifests lock devices in the same order.
    $pdo->beginTransaction();
    try {
        transferAuthorize($pdo,$user,$origin);
        $q = $pdo->prepare("SELECT l.branch_id FROM storage_locations l JOIN branches b ON b.branch_id=l.branch_id
            WHERE l.location_id=? AND l.is_active=1 AND b.is_active=1 AND l.location_type IN ('BRANCH_BIN','WORKBENCH') FOR UPDATE");
        $q->execute([$destination]);
        $to = (int)$q->fetchColumn();
        if (!$to || $to===$origin) throw new DomainException('Choose a receiving location at a different active branch.');
        $q = $pdo->prepare("SELECT location_id FROM storage_locations WHERE location_type='TRANSIT' AND is_active=1
            AND (branch_id=? OR branch_id IS NULL) ORDER BY branch_id DESC,location_id LIMIT 1 FOR UPDATE");
        $q->execute([$origin]);
        $transit = (int)$q->fetchColumn();
        if (!$transit) throw new DomainException('The sending branch needs an active transit location.');
        $code = 'TR-' . strtoupper(bin2hex(random_bytes(6)));
        $pdo->prepare("INSERT INTO device_transfers (transfer_code,from_branch_id,to_branch_id,initiated_by_user_id,dispatched_at)
            VALUES (?,?,?,?,UTC_TIMESTAMP())")->execute([$code,$origin,$to,$user]);
        $manifest = (int)$pdo->lastInsertId();
        foreach ($codes as $item) {
            $q = $pdo->prepare("SELECT d.ticket_device_id,d.ticket_id,d.physical_location_id FROM ticket_devices d
                JOIN repair_tickets t ON t.ticket_id=d.ticket_id JOIN storage_locations l ON l.location_id=d.physical_location_id
                WHERE d.item_code=? AND t.record_stage='ISSUED' AND t.case_state='OPEN'
                AND d.repair_status NOT IN ('COMPLETED','CANCELLED') AND d.custody_state='AT_LOCATION'
                AND l.branch_id=? AND l.is_active=1 AND l.location_type IN ('BRANCH_BIN','WORKBENCH') FOR UPDATE");
            $q->execute([$item,$origin]);
            $row = $q->fetch();
            if (!$row) throw new DomainException('Each device must be open and physically present at the sending branch.');
            $id = (int)$row['ticket_device_id'];
            $from = (int)$row['physical_location_id'];
            // Reservations belong to the sending shelf. Never move a device while
            // its approved parts are still held there; a manager must release the hold first.
            $holds = $pdo->prepare("SELECT r.reservation_id FROM stock_reservations r
                JOIN repair_quote_items i ON i.quote_item_id = r.quote_item_id
                JOIN repair_quotes q ON q.quote_id = i.quote_id
                WHERE q.ticket_device_id = ? LIMIT 1 FOR UPDATE");
            $holds->execute([$id]);
            if ($holds->fetchColumn()) {
                throw new DomainException('Release unused branch part reservations before dispatching this device.');
            }
            // Releasing a hold does not make an approved part available at the receiving branch.
            // Until cross-branch estimate reassignment exists, the part must be installed
            // at the sending branch before this device can be dispatched.
            $pendingPart = $pdo->prepare("SELECT i.quote_item_id FROM repair_quote_items i
                JOIN repair_quotes q ON q.quote_id = i.quote_id
                WHERE q.ticket_device_id = ? AND q.quote_status = 'APPROVED'
                AND q.quote_id = (
                    SELECT latest.quote_id FROM repair_quotes latest
                    WHERE latest.ticket_device_id = q.ticket_device_id
                    ORDER BY latest.version_no DESC, latest.quote_id DESC LIMIT 1
                )
                AND i.tracks_branch_stock = 1");
            $pendingPart->execute([$id]);
            foreach ($pendingPart->fetchAll(PDO::FETCH_COLUMN) as $quoteItemId) {
                if (stockActiveInstallation($pdo, $id, (int) $quoteItemId)) {
                    continue;
                }
                // A not-yet-installed approved part can be procured at the destination
                // only when the entire ticket travels on this manifest. Source holds
                // must already be released, and destination work requires explicit acceptance.
                $allCodes = $pdo->prepare('SELECT item_code FROM ticket_devices
                    WHERE ticket_id = ? ORDER BY ticket_device_id FOR UPDATE');
                $allCodes->execute([(int) $row['ticket_id']]);
                foreach ($allCodes->fetchAll(PDO::FETCH_COLUMN) as $requiredCode) {
                    if (!in_array((string) $requiredCode, $codes, true)) {
                        throw new DomainException(
                            'Transfer every device on this ticket together before moving an uninstalled approved part.'
                        );
                    }
                }
            }
            $pdo->prepare('INSERT INTO device_transfer_items (device_transfer_id,ticket_device_id,from_location_id,transit_location_id,to_location_id)
                VALUES (?,?,?,?,?)')->execute([$manifest,$id,$from,$transit,$destination]);
            $pdo->prepare("UPDATE ticket_devices SET custody_state='IN_TRANSIT',physical_location_id=? WHERE ticket_device_id=?")->execute([$transit,$id]);
            transferEvent($pdo,$id,$manifest,$user,'TRANSFER_DISPATCHED',$from,$transit,'Dispatched');
        }
        $pdo->commit();
        return $code;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function confirmTransferItem(PDO $pdo, int $user, int $item, string $expected, string $action, string $note): void
{
    $note = trim($note);
    if (!in_array($action,['receive','missing'],true) || mb_strlen($note)>500 ||
        (($action==='missing' || $expected==='EXCEPTION') && $note==='')) {
        throw new DomainException('Enter a short explanation for a discrepancy or its resolution.');
    }
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT device_transfer_id FROM device_transfer_items WHERE device_transfer_item_id=?');
        $q->execute([$item]);
        $manifest = (int)$q->fetchColumn();
        $q = $pdo->prepare('SELECT to_branch_id FROM device_transfers WHERE device_transfer_id=? FOR UPDATE');
        $q->execute([$manifest]);
        $branch = (int)$q->fetchColumn();
        transferAuthorize($pdo,$user,$branch);
        $q = $pdo->prepare('SELECT x.*,d.custody_state,d.physical_location_id,l.branch_id,l.is_active FROM device_transfer_items x
            JOIN ticket_devices d ON d.ticket_device_id=x.ticket_device_id JOIN storage_locations l ON l.location_id=x.to_location_id
            WHERE x.device_transfer_item_id=? FOR UPDATE');
        $q->execute([$item]);
        $r = $q->fetch();
        if (!$r || $r['item_status']!==$expected || !in_array($expected,['EXCEPTION','IN_TRANSIT'],true)
            || ($expected==='EXCEPTION' && ($action!=='receive' || $r['custody_state']!=='UNKNOWN' || $r['physical_location_id']!==null))
            || ($expected==='IN_TRANSIT' && ($r['custody_state']!=='IN_TRANSIT' || $r['physical_location_id']!=$r['transit_location_id']))) {
            throw new DomainException('Custody has changed. Reopen the manifest before continuing.');
        }
        $received = $action==='receive';
        if ($received && (!$r['is_active'] || (int)$r['branch_id']!==$branch)) throw new DomainException('Receiving location unavailable.');
        $id = (int)$r['ticket_device_id'];
        $location = $received ? (int)$r['to_location_id'] : null;
        $pdo->prepare("UPDATE device_transfer_items SET item_status=?,receipt_condition_note=?,received_by_user_id=?,
            received_at=IF(?=1,UTC_TIMESTAMP(),NULL) WHERE device_transfer_item_id=?")
            ->execute([$received?'RECEIVED':'EXCEPTION',$note,$received?$user:null,(int)$received,$item]);
        $pdo->prepare('UPDATE ticket_devices SET custody_state=?,physical_location_id=? WHERE ticket_device_id=?')
            ->execute([$received?'AT_LOCATION':'UNKNOWN',$location,$id]);
        transferEvent($pdo,$id,$manifest,$user,$received?'TRANSFER_RECEIVED':'CUSTODY_EXCEPTION',
            $expected==='IN_TRANSIT'?(int)$r['transit_location_id']:null,$location,$note);
        // Locking reads include receipts committed while this operator waited for the manifest.
        $q=$pdo->prepare('SELECT item_status FROM device_transfer_items WHERE device_transfer_id=? FOR UPDATE');
        $q->execute([$manifest]);
        $states=$q->fetchAll(PDO::FETCH_COLUMN);
        $status=in_array('EXCEPTION',$states,true)?'EXCEPTION':(in_array('IN_TRANSIT',$states,true)?'IN_TRANSIT':'RECEIVED');
        $pdo->prepare("UPDATE device_transfers SET status=?,received_confirmed_by_user_id=?,
            received_confirmed_at=IF(?='RECEIVED',UTC_TIMESTAMP(),NULL) WHERE device_transfer_id=?")
            ->execute([$status,$status==='RECEIVED'?$user:null,$status,$manifest]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}
