<?php
declare(strict_types=1);

// Add fictional repairs to the primary database configured for this checkout.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';

function seedInsert(PDO $db, string $sql, array $values): int
{
    $db->prepare($sql)->execute($values);
    return (int) $db->lastInsertId();
}

try {
    $db = getPDO();
    $database = $db->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== 'repairtrack') {
        throw new RuntimeException('This seed is for the repairtrack database. Check config/config.php.');
    }
    $locations = $db->query("SELECT b.branch_code, b.branch_id, MIN(l.location_id) AS location_id
        FROM branches b JOIN storage_locations l ON l.branch_id = b.branch_id
        WHERE b.is_active = 1 AND l.is_active = 1 AND l.location_type IN ('BRANCH_BIN','WORKBENCH')
        GROUP BY b.branch_code, b.branch_id ORDER BY b.branch_code")->fetchAll();
    $code = $argv[1] ?? '';
    $index = array_column($locations, null, 'branch_code');
    if (!isset($index[$code])) {
        echo "Usage: php tools/seed_primary.php BRANCH_CODE\n";
        echo 'Branches with active storage: ' . implode(', ', array_keys($index)) . "\n";
        exit($code === '' ? 0 : 1);
    }
    $branch = $index[$code];
    $branchId = (int) $branch['branch_id'];
    $prefix = 'S-' . $branchId . '-';
    $db->beginTransaction();
    // Serialize repeat runs for this branch. Ticket codes also have a unique index.
    $lock = $db->prepare('SELECT branch_id FROM branches WHERE branch_id = ? FOR UPDATE');
    $lock->execute([$branchId]);
    $exists = $db->prepare('SELECT ticket_id FROM repair_tickets WHERE ticket_code = ?');
    $names = ['Rivera','Santos','Ocampo','Dela Cruz','Villanueva','Fernandez'];
    $models = ['Acer Aspire 5','Lenovo ThinkPad E14 Gen 5','Dell Inspiron 15',
        'ASUS Zenbook 14 OLED UX3402','HP Pavilion Gaming 15','Custom desktop workstation'];
    $issues = ['No display','Battery drains quickly','Keyboard fault','Intermittent charging'];
    $added = 0;
    for ($n = 1; $n <= 24; $n++) {
        $ticketCode = $prefix . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        $exists->execute([$ticketCode]);
        if ($exists->fetchColumn()) {
            continue;
        }
        if (!isset($actor)) {
            // Use a disabled fixture identity so sample actions never appear under real staff.
            $email = 'sample-intake-' . $branchId . '@example.invalid';
            $lookup = $db->prepare('SELECT user_id,role,is_active,full_name FROM users WHERE email = ?');
            $lookup->execute([$email]);
            $old = $lookup->fetch();
            if ($old && ($old['role'] !== 'INTAKE_AGENT' || (int) $old['is_active'] || $old['full_name'] !== 'Sample intake')) {
                throw new RuntimeException('Sample staff email already belongs to another account.');
            }
            $actor = $old ? (int) $old['user_id'] : seedInsert($db,
                "INSERT INTO users (full_name,email,password_hash,role,is_active) VALUES ('Sample intake',?,?,'INTAKE_AGENT',0)",
                [$email,password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
        }
        $name = 'Sample ' . $names[($n-1)%6] . ' ' . $n;
        $customer = seedInsert($db, 'INSERT INTO customers (full_name,normalized_name) VALUES (?,?)',
            [$name,strtolower($name)]);
        $issued = gmdate('Y-m-d H:i:s', time() - $n * 3600);
        $localDay = (new DateTimeImmutable($issued, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');
        $ticket = seedInsert($db, "INSERT INTO repair_tickets
            (ticket_code,record_stage,case_state,customer_id,intake_branch_id,accountable_branch_id,
             accepted_by_user_id,arrival_method,intake_date,issued_at,terms_accepted,terms_signature_path,terms_signed_at)
            VALUES (?,'ISSUED','OPEN',?,?,?,?,'WALK_IN',?,?,1,'sample/no-real-signature',?)",
            [$ticketCode,$customer,$branchId,$branchId,$actor,$localDay,$issued,$issued]);
        $device = seedInsert($db, "INSERT INTO ticket_devices
            (ticket_id,child_sequence,item_code,device_type_snapshot,model_snapshot,reported_symptoms,
             condition_notes,serial_number_snapshot,accessory_checklist_completed,repair_status,physical_location_id,custody_state)
            VALUES (?,1,?,'Computer',?,?,'Sample condition check',?,1,'RECEIVED',?,'AT_LOCATION')",
            [$ticket,$ticketCode.'-01',$models[($n-1)%6],$issues[($n-1)%4],$ticketCode,$branch['location_id']]);
        $db->prepare("INSERT INTO ticket_status_events
            (ticket_device_id,to_status,actor_type,actor_user_id,public_summary,is_customer_visible,created_at)
            VALUES (?,'RECEIVED','USER',?,'Sample device received.',1,?)")->execute([$device,$actor,$issued]);
        $db->prepare("INSERT INTO device_custody_events
            (ticket_device_id,event_type,to_location_id,performed_by_user_id,note,happened_at)
            VALUES (?,'INTAKE_RECEIVED',?,?,'Sample intake',?)")->execute([$device,$branch['location_id'],$actor,$issued]);
        if ($n % 2 === 0) {
            $db->prepare("UPDATE ticket_devices SET repair_status = 'DIAGNOSING' WHERE ticket_device_id = ?")
                ->execute([$device]);
            $db->prepare("INSERT INTO ticket_status_events
                (ticket_device_id,from_status,to_status,actor_type,internal_note,created_at)
                VALUES (?,'RECEIVED','DIAGNOSING','SYSTEM','Sample diagnosis started',UTC_TIMESTAMP())")
                ->execute([$device]);
        }
        $added++;
    }
    $db->commit();
    echo "Added $added sample tickets to $code in $database.\n";
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
