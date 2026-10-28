/* Sample data and policy choices live here. No production credentials or real customers. */
const DEMO_VERSION = 'repairtrack-demo-v1';
const CATALOG = [
    { id: 'diagnostic', name: 'Computer diagnostic', type: 'Service', price: 350, stock: null },
    { id: 'clean', name: 'Cleaning and thermal service', type: 'Service', price: 650, stock: null },
    { id: 'ram', name: '8 GB DDR4 memory', type: 'Part', price: 1600, stock: 8 },
    { id: 'ssd', name: '500 GB SATA SSD', type: 'Part', price: 2300, stock: 5 },
    { id: 'install', name: 'Upgrade installation', type: 'Service', price: 450, stock: null },
];
const REPAIR_STAGES = [
    'Received',
    'Diagnosing',
    'Awaiting approval',
    'Awaiting parts',
    'Repairing',
    'Testing',
    'Ready for pickup',
    'Returned',
];
const BRANCHES = ['Central Branch', 'North Branch'];
function seedDemo() {
    return {
        sequence: 1045,
        branch: 'Central Branch',
        singleStore: false,
        staff: [
            { name: 'Mika Reyes', role: 'Technician', branch: 'Central Branch', active: true },
            { name: 'Sam Cruz', role: 'Technician', branch: 'North Branch', active: true },
            { name: 'Jamie Lee', role: 'Cashier', branch: 'Central Branch', active: true },
        ],
        customers: [
            { name: 'Alex Santos', contact: 'alex@example.test' },
            { name: 'Dana Lim', contact: 'dana@example.test' },
            { name: 'Chris Tan', contact: 'chris@example.test' },
        ],
        tickets: [
            {
                id: 'RT-1042',
                code: 'DEMO-1042',
                pin: '2468',
                customer: 'Alex Santos',
                contact: 'alex@example.test',
                branch: 'Central Branch',
                issued: true,
                arrival: 'Walk-in',
                accessories: 'Charger received',
                condition: 'Minor scratches on lid',
                ack: true,
                photo: 'Condition-photo-demo.jpg',
                location: 'Central Branch',
                custody: 'At branch',
                assigned: 'Mika Reyes',
                priority: 'Normal',
                devices: [
                    {
                        name: 'Lenovo ThinkPad T14',
                        serial: 'DEMO-T14',
                        problem: 'Shuts down during class',
                        status: 'Diagnosing',
                        diagnosis: '',
                        category: 'Not recorded',
                        tested: false,
                    },
                    {
                        name: 'Desktop computer',
                        serial: '',
                        problem: 'Memory upgrade requested',
                        status: 'Received',
                        diagnosis: '',
                        category: 'Not recorded',
                        tested: false,
                    },
                ],
                quotes: [],
                payments: [],
                refunds: [],
                messages: [
                    {
                        by: 'Repair team',
                        text: 'We received both devices. We will confirm the findings and costs before starting work.',
                    },
                ],
                requests: [],
                events: [
                    {
                        at: '24 Oct, 9:00 AM',
                        text: 'Devices received at Central Branch',
                        public: true,
                    },
                ],
                parts: [],
                notes: [],
                bill: null,
            },
            {
                id: 'RT-1043',
                code: 'DEMO-1043',
                pin: '2468',
                customer: 'Dana Lim',
                contact: 'dana@example.test',
                branch: 'North Branch',
                issued: true,
                arrival: 'Pickup',
                accessories: 'No accessories',
                condition: 'No visible damage',
                ack: true,
                location: 'North Branch',
                custody: 'At branch',
                assigned: 'Sam Cruz',
                priority: 'Normal',
                devices: [
                    {
                        name: 'Acer Aspire 5',
                        problem: 'Slow startup',
                        status: 'Awaiting parts',
                        diagnosis: 'Storage wear',
                        category: 'Storage',
                        tested: false,
                    },
                ],
                quotes: [
                    {
                        version: 1,
                        status: 'Approved',
                        items: [
                            { id: 'ssd', name: '500 GB SATA SSD', price: 2300, qty: 1 },
                            { id: 'install', name: 'Upgrade installation', price: 450, qty: 1 },
                        ],
                        total: 2750,
                    },
                ],
                payments: [],
                refunds: [],
                messages: [],
                requests: [],
                events: [
                    {
                        at: '23 Oct, 2:10 PM',
                        text: 'Customer approved storage replacement',
                        public: true,
                    },
                ],
                parts: [
                    { id: 'ssd', name: '500 GB SATA SSD', qty: 1, status: 'Requested', device: 0 },
                ],
                notes: [],
                bill: null,
            },
            {
                id: 'RT-1044',
                code: 'DEMO-1044',
                pin: '2468',
                customer: 'Chris Tan',
                contact: 'chris@example.test',
                branch: 'Central Branch',
                issued: true,
                arrival: 'Walk-in',
                accessories: 'Charger received',
                condition: 'No visible damage',
                ack: true,
                location: 'Central Branch',
                custody: 'At branch',
                assigned: 'Mika Reyes',
                priority: 'Normal',
                devices: [
                    {
                        name: 'HP Pavilion',
                        problem: 'Runs hot',
                        status: 'Ready for pickup',
                        diagnosis: 'Dust accumulation',
                        category: 'Cooling',
                        tested: true,
                    },
                ],
                quotes: [
                    {
                        version: 1,
                        status: 'Approved',
                        items: [
                            {
                                id: 'clean',
                                name: 'Cleaning and thermal service',
                                price: 650,
                                qty: 1,
                            },
                        ],
                        total: 650,
                    },
                ],
                payments: [],
                refunds: [],
                messages: [],
                requests: [],
                events: [
                    {
                        at: '24 Oct, 10:15 AM',
                        text: 'Testing passed. Device ready for pickup.',
                        public: true,
                    },
                ],
                parts: [],
                notes: [],
                bill: { total: 650 },
            },
        ],
        stock: BRANCHES.flatMap((branch) =>
            CATALOG.filter((p) => p.type === 'Part').map((p) => ({
                id: p.id,
                branch,
                qty: p.stock,
            })),
        ),
        stockLog: [],
        pickups: [
            {
                id: 'PU-101',
                name: 'Morgan Yu',
                contact: 'morgan@example.test',
                device: 'ASUS laptop',
                problem: 'No display',
                address: 'Sample town center, near barangay hall',
                area: 'Within service area',
                date: '2026-10-26',
                branch: 'Central Branch',
                status: 'Requested',
                collector: 'Unassigned',
                fee: 150,
            },
        ],
        transfers: [],
        audit: [],
        alerts: [{ text: 'RT-1044 is ready for cash collection and handover', read: false }],
        settings: { idleMinutes: 60, photosRequired: false },
    };
}
function loadDemo() {
    try {
        const raw = localStorage.getItem(DEMO_VERSION);
        return raw ? JSON.parse(raw) : seedDemo();
    } catch {
        return seedDemo();
    }
}
let state = loadDemo();
function saveDemo() {
    try {
        localStorage.setItem(DEMO_VERSION, JSON.stringify(state));
    } catch {
        /* Demo still works in memory when browser storage is unavailable. */
    }
}
