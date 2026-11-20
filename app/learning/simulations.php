<?php
declare(strict_types=1);

// These fictional values are rendered into the game for instant browser feedback.
return [
    'CUSTOMER' => [0, 'Sample receipt · RT-1042 · Private code A3F90 · Phone ending in 2468', [
        ['Tracking code', 'text', 'A3F90'],
        ['Portal PIN', 'text', '2468', 'numeric'],
    ], 'View sample repair', 'Laptop · Ready for pickup. Desktop · Testing.'],
    'ADMIN' => [0, 'New staff request · Jamie Lee · Counter cash only · Central Branch', [
        ['Role', ['Manager', 'Cashier', 'Technician'], 'Cashier'],
        ['Branch', ['Central Branch', 'North Branch', 'All branches'], 'Central Branch'],
    ], 'Check sample access', 'Jamie Lee can open Cash Desk at Central Branch.'],
    'MANAGER' => [0, 'Sample queue · RT-1042-01 · Central Branch · Unassigned', [
        ['Technician', ['Sam Cruz · North Branch', 'Mika Reyes · Central Branch · Available'], 'Mika Reyes · Central Branch · Available'],
    ], 'Check sample assignment', 'RT-1042-01 is assigned to Mika Reyes.'],
    'INTAKE_AGENT' => [0, 'Customer at counter · Alex Cruz · Phone ending in 1234', [
        ['Existing customer', ['Alex Cruz · 9876', 'Alex Cruz · 1234', 'Create another customer'], 'Alex Cruz · 1234'],
    ], 'Use sample customer', 'The intake draft is linked to the matching customer.'],
    'TECHNICIAN' => [0, 'Device label on your bench · RT-1042-02 · Laptop', [
        ['Device from your queue', ['RT-1042-01 · Laptop', 'RT-1042-02 · Laptop'], 'RT-1042-02 · Laptop'],
    ], 'Open sample device', 'RT-1042-02 · Reported problem: battery does not charge.'],
    'CASHIER' => [1, 'Sample bill · RT-1042 · Balance ₱800.00 · Cash received ₱500.00', [
        ['Cash received (PHP)', 'text', '500', 'decimal'],
    ], 'Review sample cash payment', 'Cash recorded: ₱500.00. Balance due: ₱300.00.'],
    'COURIER' => [0, 'Package label · RT-1042-02 · Central Branch → North Branch', [
        ['Manifest line', ['RT-1042-01 · North Branch', 'RT-1042-02 · North Branch'], 'RT-1042-02 · North Branch'],
    ], 'Check sample package', 'Package and manifest match.'],
    'VIEWER' => [0, 'Lookup request · RT-1042-02 · Desktop', [
        ['Item code', 'text', 'RT-1042-02'],
    ], 'Find sample device', 'RT-1042-02 · Desktop · In testing at Central Branch.'],
];
