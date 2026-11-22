<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
// Table and column names come from this script. All row values are bound.
function seedRow(PDO $pdo, string $table, array $row): int
{
    $columns = implode(', ', array_keys($row));
    $marks = implode(', ', array_fill(0, count($row), '?'));
    $pdo->prepare("INSERT INTO $table ($columns) VALUES ($marks)")->execute(array_values($row));
    return (int) $pdo->lastInsertId();
}

// The caller owns the transaction and selects the installation or isolated test database.
function seedSimulation(PDO $pdo): string
{
    $exists = $pdo->query("SELECT branch_id FROM branches WHERE branch_code = 'SIM-SOGOD'");
    if ($exists->fetchColumn()) {
        throw new RuntimeException(
            'SIM-SOGOD already exists. Seeding stopped to preserve existing records.',
        );
    }
    $branch = seedRow($pdo, 'branches', [
        'branch_code' => 'SIM-SOGOD',
        'branch_name' => 'Sogod Central (simulation)',
    ]);
    $locations = [];
    foreach (
        [
            'INTAKE' => ['Receiving', 'BRANCH_BIN'],
            'BENCH' => ['Repair bench', 'WORKBENCH'],
            'PICKUP' => ['Pickup counter', 'BRANCH_BIN'],
        ]
        as $code => [$name, $type]
    ) {
        $locations[$code] = seedRow($pdo, 'storage_locations', [
            'branch_id' => $branch,
            'location_code' => $code,
            'location_name' => $name,
            'location_type' => $type,
        ]);
    }
    $staff = [];
    $password = bin2hex(random_bytes(12));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    foreach (
        [
            'MANAGER' => 'Leah Cruz',
            'TECHNICIAN' => 'Noel Ramos',
            'INTAKE_AGENT' => 'Ana Lim',
            'CASHIER' => 'Mira Santos',
        ]
        as $role => $name
    ) {
        $staff[$role] = seedRow($pdo, 'users', [
            'role' => $role,
            'full_name' => $name,
            'email' => strtolower($role) . '@sim.example.test',
            'password_hash' => $hash,
        ]);
        seedRow($pdo, 'user_branch_assignments', [
            'user_id' => $staff[$role],
            'branch_id' => $branch,
            'is_default' => 1,
        ]);
    }
    $models = [
        ['Laptop', 'Acer', 'Aspire 5', 'Fan rattles under load', 'Cooling fan service', 1450],
        [
            'Phone',
            'Samsung',
            'Galaxy A54',
            'Charging stops when cable moves',
            'Charging port service',
            950,
        ],
        [
            'Desktop',
            'Lenovo',
            'ThinkCentre M70',
            'Shuts down after warming up',
            'Cooling system service',
            1250,
        ],
    ];
    $firstNames = ['Lina', 'Paolo', 'Maya', 'Carlo', 'Rosa', 'Eli'];
    $lastNames = ['Reyes', 'Tan', 'Garcia', 'Flores'];
    $stages = ['RECEIVED', 'DIAGNOSING', 'IN_PROGRESS', 'TESTING', 'READY_FOR_PICKUP', 'COMPLETED'];
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    for ($i = 0; $i < 24; $i++) {
        // Five open devices fit one technician's queue. Older repairs have been returned.
        $stage = min($i, 5);
        $issued = $now->modify('-' . ($i + 1) . ' days');
        $time = static fn(int $hours): string => $issued
            ->modify("+$hours hours")
            ->format('Y-m-d H:i:s');
        [$type, $make, $model, $symptom, $service, $price] = $models[$i % count($models)];
        $name = $firstNames[$i % 6] . ' ' . $lastNames[intdiv($i, 6)];
        $customer = seedRow($pdo, 'customers', [
            'full_name' => $name,
            'normalized_name' => strtolower($name),
            'customer_notes' => 'Simulation record',
        ]);
        $phone = '0900000' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT);
        seedRow($pdo, 'customer_contacts', [
            'customer_id' => $customer,
            'contact_type' => 'PHONE',
            'contact_value' => $phone,
            'normalized_value' => $phone,
            'is_primary' => 1,
        ]);
        $code = sprintf('SIM-RT-%03d', $i + 1);
        $closed = $stage === 5;
        $lookup = $pdo->prepare('SELECT 1 FROM repair_tickets WHERE portal_code = ?');
        do {
            $portalCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $lookup->execute([$portalCode]);
        } while ($lookup->fetchColumn());
        $ticket = seedRow($pdo, 'repair_tickets', [
            'ticket_code' => $code,
            'record_stage' => 'ISSUED',
            'case_state' => $closed ? 'CLOSED' : 'OPEN',
            'closure_outcome' => $closed ? 'ALL_COMPLETED' : null,
            'closed_at' => $closed ? $time(10) : null,
            'customer_id' => $customer,
            'intake_branch_id' => $branch,
            'accountable_branch_id' => $branch,
            'accepted_by_user_id' => $staff['INTAKE_AGENT'],
            'arrival_method' => 'WALK_IN',
            'intake_date' => $issued->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d'),
            'issued_at' => $time(0),
            'created_at' => $time(0),
            'terms_accepted' => 1,
            'terms_signed_at' => $time(0),
            'terms_signature_path' => 'assets/demo/acknowledgment.svg',
            'portal_code' => $portalCode,
            'portal_pin_hash' => password_hash(substr($phone, -4), PASSWORD_DEFAULT),
        ]);
        $device = seedRow($pdo, 'ticket_devices', [
            'ticket_id' => $ticket,
            'child_sequence' => 1,
            'item_code' => $code . '-01',
            'device_type_snapshot' => $type,
            'make_snapshot' => $make,
            'model_snapshot' => $model,
            'serial_number_snapshot' => $code . '-SN',
            'reported_symptoms' => $symptom,
            'condition_notes' => 'Light wear on casing',
            'accessory_checklist_completed' => 1,
            'repair_status' => $stages[$stage],
            'repair_started_at' => $stage >= 2 ? $time(4) : null,
            'assigned_technician_id' => $staff['TECHNICIAN'],
            'repair_branch_id' => $branch,
            'physical_location_id' => $closed
                ? null
                : $locations[$stage === 4 ? 'PICKUP' : ($stage === 0 ? 'INTAKE' : 'BENCH')],
            'custody_state' => $closed ? 'WITH_CUSTOMER' : 'AT_LOCATION',
            'return_handover_at' => $closed ? $time(10) : null,
            'return_handover_by' => $closed ? $staff['CASHIER'] : null,
            'created_at' => $time(0),
            'updated_at' => $time(2 * $stage),
        ]);
        foreach (array_slice($stages, 0, $stage + 1) as $step => $status) {
            seedRow($pdo, 'ticket_status_events', [
                'ticket_device_id' => $device,
                'from_status' => $stages[$step - 1] ?? null,
                'to_status' => $status,
                'actor_user_id' =>
                    $step === 0
                        ? $staff['INTAKE_AGENT']
                        : ($step === 5
                            ? $staff['CASHIER']
                            : $staff['TECHNICIAN']),
                'created_at' => $time(2 * $step),
                'is_customer_visible' => 1,
                'public_summary' => ucfirst(strtolower(str_replace('_', ' ', $status))),
            ]);
        }
        if ($stage >= 2) {
            $quote = seedRow($pdo, 'repair_quotes', [
                'ticket_device_id' => $device,
                'version_no' => 1,
                'quote_status' => 'APPROVED',
                'labor_amount' => $price,
                'total_amount' => $price,
                'prepared_by_user_id' => $staff['MANAGER'],
                'sent_at' => $time(2),
                'created_at' => $time(2),
            ]);
            seedRow($pdo, 'repair_quote_decisions', [
                'quote_id' => $quote,
                'decision' => 'APPROVED',
                'decision_source' => 'SIMULATED_DEMO',
                'customer_name_at_decision' => $name,
                'decision_at' => $time(3),
            ]);
            $line = seedRow($pdo, 'repair_quote_items', [
                'quote_id' => $quote,
                'item_type' => 'LABOR',
                'description' => $service,
                'quantity' => 1,
                'unit_price' => $price,
                'line_total' => $price,
            ]);
        }
        if ($stage >= 4) {
            $bill = seedRow($pdo, 'repair_bills', [
                'ticket_id' => $ticket,
                'bill_number' => $code . '-B',
                'bill_status' => 'ISSUED',
                'subtotal' => $price,
                'total_amount' => $price,
                'prepared_by_user_id' => $staff['MANAGER'],
                'issued_at' => $time(8),
                'created_at' => $time(8),
            ]);
            seedRow($pdo, 'repair_bill_items', [
                'bill_id' => $bill,
                'ticket_device_id' => $device,
                'quote_item_id' => $line,
                'item_type' => 'LABOR',
                'description' => $service,
                'quantity' => 1,
                'unit_price' => $price,
                'line_total' => $price,
            ]);
            seedRow($pdo, 'repair_bill_payments', [
                'bill_id' => $bill,
                'amount' => $closed ? $price : 300,
                'request_key' => bin2hex(random_bytes(32)),
                'received_by_user_id' => $staff['CASHIER'],
                'received_at' => $time(9),
            ]);
        }
    }
    return $password;
}
