/* RepairTrack demo controller. Rendering, forms and workflow actions are grouped below.
   This browser-only simulation is NOT an authentication or production backend. */
const $ = (s) => document.querySelector(s);
const esc = (v) =>
    String(v ?? '').replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c],
    );
const money = (n) =>
    '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
let role = 'Public',
    verified = false,
    activeTicket = 'RT-1042',
    activeDevice = 0,
    wireframe = false,
    intakeStep = 0,
    intakeDraft = {},
    lastActivity = Date.now(),
    toastTimer;
const ROLE_HOME = {
    Admin: 'overview',
    Technician: 'repairs',
    Cashier: 'finance',
    Collector: 'logistics',
    Customer: 'portal',
    Public: 'home',
};
function tick() {
    lastActivity = Date.now();
}
function toast(text) {
    $('#toast').textContent = text;
    $('#toast').hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => ($('#toast').hidden = true), 4500);
}
function now() {
    return new Date().toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
function record(text, ticket = null, isPublic = false) {
    const event = { at: now(), text, public: isPublic };
    state.audit.unshift({ ...event, role });
    state.alerts.unshift({ text, read: false });
    if (ticket) ticket.events.unshift(event);
    saveDemo();
}
function ticket() {
    return state.tickets.find((t) => t.id === activeTicket) || state.tickets[0];
}
function scope() {
    return state.tickets.filter(
        (t) =>
            (state.branch === 'All branches' || t.branch === state.branch) &&
            (role !== 'Technician' || t.assigned === 'Mika Reyes'),
    );
}
function currentQuote(t) {
    return t.quotes.at(-1);
}
function approvedQuote(t) {
    return [...t.quotes].reverse().find((q) => q.status === 'Approved');
}
function paid(t) {
    return (
        t.payments.reduce((n, p) => n + p.amount, 0) - t.refunds.reduce((n, p) => n + p.amount, 0)
    );
}
function balance(t) {
    return Math.max(0, (t.bill?.total || 0) - paid(t));
}
function badge(text) {
    const color = /Approved|Ready|Paid|Returned|Passed|Received|Completed|Remitted/.test(text)
        ? 'good'
        : /Awaiting|Requested|Draft|Pending|Diagnosing/.test(text)
          ? 'warn'
          : /Repairing|Testing/.test(text)
            ? 'purple'
            : '';
    return `<span class="pill ${color}">${esc(text)}</span>`;
}
function btn(label, action, extra = '', cls = '') {
    return `<button type="button" class="${cls}" data-action="${action}" ${extra}>${label}</button>`;
}
function link(label, route, cls = 'button') {
    return `<a class="${cls}" href="#${route}">${label}</a>`;
}
function empty(text) {
    return `<div class="empty">${text}</div>`;
}
function field(label, name, value = '', type = 'text', required = true) {
    return `<label>${label}<input name="${name}" type="${type}" value="${esc(value)}" ${required ? 'required' : ''} ${type === 'number' ? 'min="0.01" step="0.01"' : ''}></label>`;
}
function area(label, name, value = '', required = true) {
    return `<label>${label}<textarea name="${name}" ${required ? 'required' : ''} maxlength="2000">${esc(value)}</textarea></label>`;
}
function select(label, name, options, value) {
    return `<label>${label}<select name="${name}">${options.map((o) => `<option ${o === value ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select></label>`;
}
function header(title, description = '', action = '') {
    return `<header class="page-heading"><div><p class="eyebrow">${role === 'Customer' ? 'Your repair' : esc(role) + ' workspace'}</p><h1>${title}</h1><p>${description}</p></div>${action}</header>`;
}
function modal(title, body) {
    $('#modal-content').innerHTML =
        `<header class="dialog-header"><h2>${title}</h2>${btn('✕', 'close', 'aria-label="Close dialog"', 'quiet')}</header>${body}`;
    $('#modal').showModal();
}
function formModal(title, kind, body, submit = 'Save demo update') {
    modal(
        title,
        `<form data-form="${kind}" class="stack">${body}<div class="actions">${btn('Cancel', 'close')}<button class="primary" type="submit">${submit}</button></div></form>`,
    );
}
function closeModal() {
    $('#modal').close();
}
function go(route) {
    if (location.hash === '#' + route) render();
    else location.hash = route;
}
function setRole(next) {
    role = next;
    verified = false;
    tick();
    go(ROLE_HOME[role]);
    renderBar();
}
function renderBar() {
    document.body.classList.toggle('wireframe', wireframe);
    $('#demo-bar').innerHTML =
        `<strong class="demo-label">INTERACTIVE DEMO · Sample data only</strong><div class="demo-tools"><label>Explore as <select id="role-picker">${Object.keys(
            ROLE_HOME,
        )
            .map((r) => `<option ${r === role ? 'selected' : ''}>${r}</option>`)
            .join(
                '',
            )}</select></label>${btn(wireframe ? 'Visual design' : 'Wireframe view', 'wireframe')}<a href="responsive.html" class="button quiet" style="min-height:32px;padding:5px 10px;font-size:12px">Screen sizes</a>${btn('Demo guide', 'guide')}${btn('Reset demo', 'reset', '', 'quiet')}</div>`;
}
const navs = {
    Admin: [
        ['overview', 'Overview', '◈'],
        ['repairs', 'Repairs', '▣'],
        ['customers', 'Customers', '◎'],
        ['team', 'Team', '♧'],
        ['inventory', 'Inventory', '▤'],
        ['finance', 'Finance', '₱'],
        ['reports', 'Reports', '▥'],
    ],
    Technician: [
        ['repairs', 'My repairs', '▣'],
        ['messages', 'Messages', '✉'],
        ['parts', 'Parts requests', '▤'],
        ['audit', 'History', '◷'],
    ],
    Cashier: [
        ['overview', 'Overview', '◈'],
        ['repairs', 'Repairs', '▣'],
        ['intake', 'New intake', '＋'],
        ['customers', 'Customers', '◎'],
        ['finance', 'Cash desk', '₱'],
    ],
    Collector: [
        ['logistics', 'My collections', '⌖'],
        ['transfers', 'Handoffs', '⇄'],
        ['finance', 'COD remittance', '₱'],
    ],
};
function navigation(route) {
    return (navs[role] || navs.Admin)
        .map(
            ([path, label, icon]) =>
                `<a href="#${path}" class="${route === path ? 'active' : ''}"><span class="side-icon" aria-hidden="true">${icon}</span>${label}</a>`,
        )
        .join('');
}
function shell(route, html) {
    return `<div class="mobile-nav"><details><summary>Menu · ${esc(role)} · ${esc(state.branch)}</summary><nav>${navigation(route)}${link('Logistics', 'logistics', '')}${link('Alerts', 'alerts', '')}</nav></details></div><div class="shell"><aside class="sidebar"><a class="brand" href="#overview"><span class="brand-mark">R</span>RepairTrack</a><p class="eyebrow">${esc(role)} console</p><nav class="side-nav" aria-label="Main navigation">${navigation(route)}</nav><div class="sidebar-footer"><nav class="side-nav">${role === 'Admin' ? '<a href="#logistics">⌖ Pickup & delivery</a><a href="#transfers">⇄ Branch transfers</a><a href="#settings">⚙ Branches & settings</a>' : ''}<a href="#alerts">Notifications</a><a href="#audit">Activity log</a></nav>${btn('Sign out', 'logout', '', 'quiet')}</div></aside><div class="workspace"><header class="topbar"><div><strong>${role === 'Technician' ? 'Mika Reyes' : role === 'Collector' ? 'Taylor · Collector' : 'RepairTrack staff'}</strong><small> / ${esc(role)}</small></div><div class="row"><label class="check">Branch<select id="branch-picker">${(state.singleStore ? [BRANCHES[0]] : role === 'Admin' ? ['All branches', ...BRANCHES] : BRANCHES).map((b) => `<option ${state.branch === b ? 'selected' : ''}>${b}</option>`).join('')}</select></label>${link('Alerts ' + state.alerts.filter((a) => !a.read).length, 'alerts', 'button quiet')}</div></header><main id="content" class="content">${html}</main></div></div>`;
}
function publicNav() {
    const links = ['Home', 'About', 'FAQ', 'Contacts', 'Services']
        .map((s) => `<a href="#home" data-section="${s.toLowerCase()}">${s}</a>`)
        .join('');
    return `<header class="public-nav"><a class="brand" href="#home"><span class="brand-mark">R</span>RepairTrack</a><nav>${links}</nav>${link('Staff login', 'login', 'button quiet')}</header><details class="mobile-nav"><summary>Navigation</summary><nav>${links}</nav></details>`;
}
function home() {
    return `${publicNav()}<main id="content"><section class="hero" id="home"><video muted autoplay loop playsinline poster="assets/video-poster.jpg"><source src="assets/login-bg.mp4" type="video/mp4"></video><div><p class="eyebrow">Computer repair, without the guesswork</p><h1>Your device.<br>Your updates.<br><span class="hero-accent">All in one place.</span></h1><p>Know what is happening, where your computer is, and what comes next.</p><div class="row section">${link('Request device pickup', 'pickup', 'button quiet')}${btn('Pause background', 'video', '', 'quiet')}</div></div><section class="card"><p class="eyebrow">Track a repair</p><h2>Let’s find your device</h2><form data-form="track" class="stack">${field('Tracking code', 'code', 'DEMO-1042')}<button class="primary">View my repair</button></form><div class="row section">${btn('Scan QR', 'scan')}${btn('Try sample QR', 'qr')}</div><small>Use DEMO-1042 for the main demonstration.</small></section></section><div class="container"><section id="about"><p class="eyebrow">The repair journey</p><h2>A clear next step, from check-in to return</h2><div class="grid three section">${[
        ['01', 'Check in', 'Bring your device or request a confirmed collection.'],
        ['02', 'Review the work', 'Discuss findings and approve itemized costs.'],
        ['03', 'Receive your device', 'Follow updates, then collect or arrange delivery.'],
    ]
        .map(
            ([n, t, p]) =>
                `<article class="card"><div class="kicker-number">${n}</div><h3>${t}</h3><p class="muted">${p}</p></article>`,
        )
        .join(
            '',
        )}</div></section><section id="services" class="section"><p class="eyebrow">Services</p><h2>Repairs and upgrades that fit your device</h2><div class="grid">${[
        ['Diagnostics', 'Power, performance, display and overheating issues.'],
        ['Upgrades', 'Compatible memory and storage with approved catalog pricing.'],
    ]
        .map(([t, p]) => `<article class="card"><h3>${t}</h3><p class="muted">${p}</p></article>`)
        .join(
            '',
        )}</div></section><section id="faq" class="card section"><h2>Common questions</h2>${[
        [
            'Can I see live GPS?',
            'Location shows confirmed custody and branch handoffs. This demonstration does not provide live GPS.',
        ],
        [
            'How do I pay?',
            'Pay physical cash at the branch or on delivery. Costs require approval before additional work.',
        ],
        [
            'Can I cancel or add an upgrade?',
            'Send a verified request. Staff reviews feasibility, completed work and disclosed costs before acting.',
        ],
        [
            'Is pickup available everywhere?',
            'A branch confirms service coverage, safe access, fees and the schedule. A map pin alone cannot confirm service.',
        ],
    ]
        .map(([q, a]) => `<details><summary>${q}</summary><p class="muted">${a}</p></details>`)
        .join(
            '',
        )}</section><section id="contacts" class="section"><h2>Your nearby repair team</h2><div class="grid">${BRANCHES.map((b, i) => `<article class="card"><h3>${b}</h3><p class="muted">Sample ${i ? 'north district' : 'town center'} address<br>Monday–Saturday, 9 AM–6 PM</p><p>hello@repairtrack.example</p>${link('Request pickup', 'pickup', 'button quiet')}</article>`).join('')}</div></section></div></main><footer class="footer"><strong>RepairTrack</strong><span>Demonstration only. All names, prices and records are samples.</span></footer>`;
}
function login() {
    return `${publicNav()}<main id="content" class="container narrow"><section class="card"><p class="eyebrow">One staff entry point</p><h1>Welcome back</h1><p class="muted">Preview a workspace. No real account or password is needed.</p><form data-form="login" class="stack">${field('Demo email', 'email', 'staff@example.test', 'email')}${select('Demo role', 'role', ['Admin', 'Technician', 'Cashier', 'Collector'], 'Admin')}<button class="primary">Open demo workspace</button><small>Production permissions come from the server. This selector is for presentation only.</small></form></section></main>`;
}
function pickup() {
    return `${publicNav()}<main id="content" class="container narrow">${header('Request device pickup', 'Your branch will confirm the location, schedule and collection fee.')}<form data-form="pickup" class="card form-grid">${field('Your name', 'name')}${field('Phone or email', 'contact')}${field('Device', 'device')}${select('Service area', 'area', ['Within service area', 'Nearby meeting point', 'Outside service area'])}<div class="wide">${area('What did you notice?', 'problem')}</div><div class="wide">${area('Address and landmark', 'address')}</div>${field('Preferred date', 'date', '', 'date')}${select('Preferred branch', 'branch', state.singleStore ? [BRANCHES[0]] : BRANCHES)}<div class="notice wide">Map and coverage are simulated. No live location is collected.</div><label class="check wide"><input type="checkbox" required>I agree that staff must confirm the fee and schedule.</label><div class="actions wide"><button class="primary">Send demo pickup request</button></div></form></main>`;
}
function overview() {
    const ts = scope();
    return (
        header(
            'A clear view of your branch',
            'Start with the records that need a decision.',
            link('New intake', 'intake', 'button primary'),
        ) +
        `<div class="grid four">${[
            [
                'Active devices',
                ts.flatMap((t) => t.devices).filter((d) => d.status !== 'Returned').length,
            ],
            ['Draft intakes', ts.filter((t) => !t.issued).length],
            ['Awaiting approval', ts.filter((t) => currentQuote(t)?.status === 'Pending').length],
            [
                'Ready for return',
                ts.flatMap((t) => t.devices).filter((d) => d.status === 'Ready for pickup').length,
            ],
        ]
            .map(
                ([l, n]) =>
                    `<section class="card"><small>${l}</small><div class="metric">${n}</div><span class="muted">Current branch selection</span></section>`,
            )
            .join(
                '',
            )}</div><div class="grid section"><section class="card"><h2>Next actions</h2><div class="list">${ts
            .slice(0, 4)
            .map((t) => ticketCard(t))
            .join(
                '',
            )}</div></section><section class="card"><h2>Recent activity</h2>${timeline(state.audit.slice(0, 5))}${state.audit.length ? '' : empty('Your demo actions will appear here.')}<div class="actions">${link('View all repairs', 'repairs')}</div></section></div>`
    );
}
function ticketCard(t) {
    return `<article class="list-item"><div><h3>${esc(t.id)} · ${esc(t.devices[0].name)}</h3><p>${esc(t.customer)} · ${esc(t.branch)}</p>${badge(t.issued ? t.devices[0].status : 'Draft')} <small>${t.devices.length} device${t.devices.length > 1 ? 's' : ''}</small></div>${btn('Open', 'open-ticket', `data-id="${t.id}"`)}</article>`;
}
function repairs() {
    return (
        header(
            role === 'Technician' ? 'My repair queue' : 'Repair workbench',
            'Each device keeps its own status, custody record and costs.',
            role !== 'Technician' ? link('New intake', 'intake', 'button primary') : '',
        ) +
        `<div class="row card section"><label style="flex:1">Find a repair<input id="repair-search" placeholder="Ticket, customer or device"></label><label>Status<select id="repair-filter"><option>All statuses</option><option>Draft</option>${REPAIR_STAGES.map((s) => `<option>${s}</option>`).join('')}</select></label></div><section class="list section" id="repair-list">${scope().map(ticketCard).join('') || empty('No repairs in this branch.')}</section>`
    );
}
function timeline(events) {
    return `<ol class="timeline">${events.map((e) => `<li><small>${esc(e.at)}</small><p>${esc(e.text)}</p></li>`).join('')}</ol>`;
}
function intake() {
    const d = intakeDraft;
    return (
        header(
            'New device intake',
            'Capture essentials first. Review with the customer before issuing a ticket.',
        ) +
        `<div class="narrow"><ol class="stepper">${['Customer', 'Device & condition', 'Review'].map((s, i) => `<li class="${i === intakeStep ? 'active' : ''}">${i + 1} ${s}</li>`).join('')}</ol><form data-form="intake" class="card form-grid">${
            intakeStep === 0
                ? `<div class="wide">${select('Existing customer', 'existing', ['New customer', ...state.customers.map((c) => c.name)], d.existing || 'New customer')}<small>Select a match to fill contact details.</small></div>${field('Customer name', 'customer', d.customer || '')}${field('Phone or email', 'contact', d.contact || '')}${select('Receiving branch', 'branch', state.singleStore ? [BRANCHES[0]] : BRANCHES, d.branch || state.branch)}${select('Arrival', 'arrival', ['Walk-in', 'Pickup', 'Mail'], d.arrival || 'Walk-in')}`
                : intakeStep === 1
                  ? `${field('Device brand and model', 'device', d.device || '')}${field('Serial number (optional)', 'serial', d.serial || '', 'text', false)}<div class="wide">${area('Reported problem', 'problem', d.problem || '')}</div><div class="wide">${area('Visible condition', 'condition', d.condition || '')}</div>${field('Accessories or “None”', 'accessories', d.accessories || '')}${field('Condition photo (demo filename, optional)', 'photo', d.photo || '', 'text', false)}<label class="check wide"><input type="checkbox" name="ack" ${d.ack ? 'checked' : ''}>Customer confirms condition and acknowledges the service terms.</label>`
                  : `<div class="wide"><h2>Confirm these details</h2><dl class="summary">${[
                        ['Customer', d.customer],
                        ['Contact', d.contact],
                        ['Branch', d.branch],
                        ['Arrival', d.arrival],
                        ['Device', d.device],
                        ['Problem', d.problem],
                        ['Condition', d.condition],
                        ['Accessories', d.accessories],
                        ['Serial', d.serial || 'Not supplied'],
                        ['Acknowledgment', d.ack ? 'Confirmed' : 'Still required to issue'],
                    ]
                        .map(([k, v]) => `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>`)
                        .join(
                            '',
                        )}</dl></div><div class="notice wide">Save as draft first. Issuance adds tracking access after the checks pass. Photo files here are sample labels, not uploads or signatures.</div>`
        }<div class="actions wide">${intakeStep ? btn('Back', 'intake-back') : link('Cancel', 'repairs')}<button class="primary">${intakeStep === 2 ? 'Save intake draft' : 'Continue'}</button></div></form></div>`
    );
}
// Ticket screen: status, custody and payment are deliberately independent.
function repairDetail() {
    const t = ticket(),
        d = t.devices[activeDevice] || t.devices[0];
    return (
        header(
            t.id + ' · ' + esc(t.customer),
            t.issued
                ? 'Manage the device, publish progress and record the next action.'
                : 'Draft intake. Complete the issue checks before sharing tracking access.',
            link('Back to repairs', 'repairs', 'button quiet'),
        ) +
        `${!t.issued ? `<div class="notice">Draft: customer tracking is unavailable. ${btn('Review & issue', 'issue')}</div>` : ''}<div class="row card"><label style="flex:1">Device<select id="device-picker">${t.devices.map((x, i) => `<option value="${i}" ${i === activeDevice ? 'selected' : ''}>${i + 1}. ${esc(x.name)}</option>`).join('')}</select></label>${badge(d.status)}${btn('Add device', 'add-device')}${t.issued ? btn('Tracking receipt', 'receipt') : ''}</div><div class="grid section"><section class="card"><p class="eyebrow">Device overview</p><h2>${esc(d.name)}</h2><dl class="summary"><div><dt>Reported problem</dt><dd>${esc(d.problem)}</dd></div><div><dt>Confirmed location</dt><dd>${esc(t.location)} · ${esc(t.custody)}</dd></div><div><dt>Visible condition</dt><dd>${esc(t.condition)}</dd></div><div><dt>Accessories</dt><dd>${esc(t.accessories)}</dd></div><div><dt>Assigned technician</dt><dd>${esc(t.assigned || 'Unassigned')}</dd></div><div><dt>Diagnosis</dt><dd>${esc(d.diagnosis || 'Not recorded')}</dd></div></dl><div class="actions">${role === 'Admin' ? btn('Assign technician', 'assign') : ''}${btn('Record diagnosis', 'diagnose')}${btn('Update status', 'status', '', 'primary')}</div></section><section class="card"><h2>Work and costs</h2><p class="muted">Choose catalog parts and services. A changed quote needs fresh approval.</p><div class="stack">${btn('Build quotation', 'quote')}${btn('Request parts', 'request-part')}${btn('Record test results', 'test')}${btn('Publish customer update', 'publish')}</div><small>Ready for pickup requires passed tests. Cash collection remains a separate action.</small></section></div><div class="grid section"><section class="card"><h2>Quotations</h2>${quotesHTML(t, false)}</section><section class="card"><h2>Device history</h2>${timeline(t.events.slice(0, 7))}<div class="actions">${btn('Internal note', 'note')}${role !== 'Technician' ? btn('Transfer / delivery', 'transfer') : ''}</div>${t.notes.length ? `<details><summary>Internal notes (${t.notes.length})</summary>${t.notes.map((n) => `<p>${esc(n)}</p>`).join('')}</details>` : ''}</section></div><section class="card section"><h2>Customer requests</h2>${requestsHTML(t, false)}</section>`
    );
}
function quotesHTML(t, customer) {
    return t.quotes.length
        ? t.quotes
              .map(
                  (q) =>
                      `<article class="card section"><div class="row between"><h3>Quote v${q.version} · ${esc(t.devices[q.device || 0]?.name)}</h3>${badge(q.status)}</div><table><thead><tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>${q.items.map((i) => `<tr><td>${esc(i.name)}</td><td>${i.qty}</td><td>${money(i.price * i.qty)}</td></tr>`).join('')}</tbody></table><p class="amount section">${money(q.total)}</p>${customer && q.status === 'Pending' ? `<div class="row">${btn('Approve quote', 'approve-quote', `data-version="${q.version}"`, 'primary')}${btn('Decline', 'decline-quote', `data-version="${q.version}"`)}</div>` : ''}</article>`,
              )
              .join('')
        : empty('No quotation yet. Diagnosis comes first.');
}
function requestsHTML(t, customer) {
    return t.requests.length
        ? t.requests
              .map(
                  (r, i) =>
                      `<article class="list-item section"><div><h3>${esc(r.type)}</h3><p>${esc(r.text)}</p>${badge(r.status)}</div>${!customer && r.status === 'Requested' ? btn('Review request', 'review-request', `data-index="${i}"`) : ''}</article>`,
              )
              .join('')
        : empty('No change requests.');
}
function portal() {
    const t = ticket();
    const tab = location.hash.split('/')[1] || 'overview';
    const tabs = ['Overview', 'Location', 'Messages', 'Requests', 'Costs'];
    let html = '';
    if (tab === 'overview')
        html = `<div class="grid"><section class="card"><p class="eyebrow">Your devices</p><div class="list">${t.devices.map((d) => `<article class="list-item"><div><h3>${esc(d.name)}</h3><p>${esc(d.problem)}</p>${badge(d.status)}</div></article>`).join('')}</div></section><section class="card"><p class="eyebrow">What happens next</p><h2>${currentQuote(t)?.status === 'Pending' ? 'Your quotation is ready' : t.devices.every((d) => d.status === 'Returned') ? 'Your devices are back with you' : 'Your team will keep you informed'}</h2><p class="muted">${currentQuote(t)?.status === 'Pending' ? 'Review the itemized costs before work proceeds.' : 'Check the latest published update below, or contact your repair team.'}</p>${link('View costs', 'portal/costs', 'button primary')}</section></div><section class="card section"><h2>Repair timeline</h2>${timeline(t.events.filter((e) => e.public))}</section>`;
    if (tab === 'location')
        html = `<div class="grid"><section class="card"><p class="eyebrow">Last confirmed custody</p><h2>${esc(t.location)}</h2><div class="location-map"><strong>⌖ ${esc(t.custody)}</strong></div><p class="muted section">Schematic view. No live GPS or internal shelf information.</p></section><section class="card"><h2>Location updates</h2>${timeline(t.events.filter((e) => e.public && /branch|handoff|transit|deliver|return|received/i.test(e.text)))}</section></div>`;
    if (['messages', 'requests', 'costs'].includes(tab) && !verified)
        html = `<section class="card narrow"><p class="eyebrow">Extra verification</p><h2>Confirm access to your repair</h2><p class="muted">Tracking shows basic progress. Messages, costs and decisions need a separate PIN.</p><form data-form="verify" class="stack">${field('Demo PIN', 'pin', '', 'password')}<button class="primary">Verify demo access</button><small>Demo PIN: 2468. This is simulated verification only.</small></form></section>`;
    else if (tab === 'messages') html = chatHTML(t, true);
    else if (tab === 'requests')
        html = `<section class="card"><h2>Ask for a change</h2><p class="muted">Staff reviews compatibility, completed work and disclosed charges before acting.</p><div class="row">${['Upgrade', 'Customization', 'Cancellation'].map((type) => btn(type, 'customer-request', `data-type="${type}"`)).join('')}</div>${requestsHTML(t, true)}</section>`;
    else if (tab === 'costs')
        html = `<div class="grid"><section class="card"><h2>Itemized quotations</h2>${quotesHTML(t, true)}</section><section class="card"><h2>Payment summary</h2><dl class="summary"><div><dt>Final bill</dt><dd>${t.bill ? money(t.bill.total) : 'Not issued yet'}</dd></div><div><dt>Cash received, net refunds</dt><dd>${money(paid(t))}</dd></div><div><dt>Balance</dt><dd>${t.bill ? money(balance(t)) : 'Pending final bill'}</dd></div><div><dt>Payment methods</dt><dd>Cash at counter or on delivery</dd></div></dl>${t.payments.map((p) => `<p class="section">Receipt ${esc(p.id)} · ${money(p.amount)} · ${esc(p.method)}</p>`).join('')}<p class="muted section">No online payment is available.</p></section></div>`;
    return `${publicNav()}<main id="content" class="container">${header('Your repair, at a glance', `${esc(t.id)} · ${t.devices.length} device${t.devices.length > 1 ? 's' : ''} · ${esc(t.branch)}`, badge(verified ? 'Verified demo session' : 'Tracking access'))}<nav class="tabs">${tabs.map((s) => link(s, 'portal/' + s.toLowerCase(), tab === s.toLowerCase() ? 'active' : '')).join('')}</nav>${html}</main>`;
}
function chatHTML(t, customer) {
    return `<section class="card"><h2>${customer ? 'Your repair team' : 'Ticket conversation'} · ${esc(t.id)}</h2><div class="chat">${t.messages.map((m) => `<div class="bubble ${m.by === 'Customer' ? 'customer' : ''}"><small>${esc(m.by)}</small><p>${esc(m.text)}</p></div>`).join('') || empty('No messages yet.')}</div><form data-form="message" class="stack">${area('Message', 'message')}<button class="primary">Send demo message</button></form><small>Messages stay in this browser. No SMS or email is sent.</small></section>`;
}
function messages() {
    return (
        header('Messages', 'Conversations stay with the ticket when staff change.') +
        `<div class="row section">${select(
            'Ticket',
            'message-ticket',
            scope().map((t) => t.id),
            activeTicket,
        )}</div><div class="section">${chatHTML(ticket(), false)}</div>`
    );
}
function customers() {
    return (
        header('Customers', 'Search before creating a duplicate record.') +
        `<section class="card"><label>Search customers<input id="customer-filter" placeholder="Name or contact"></label><div id="customer-list" class="list section">${state.customers.map((c) => `<article class="list-item"><div><h3>${esc(c.name)}</h3><p>${esc(c.contact)}</p></div>${btn('Start intake', 'customer-intake', `data-name="${esc(c.name)}"`)}</article>`).join('')}</div></section>`
    );
}
function team() {
    return (
        header(
            'Team and assignments',
            'Balance work within the selected branch.',
            btn('Add demo staff', 'add-staff', '', 'primary'),
        ) +
        `<div class="grid three">${state.staff
            .filter((s) => state.branch === 'All branches' || s.branch === state.branch)
            .map(
                (s) =>
                    `<section class="card"><h2>${esc(s.name)}</h2><p class="muted">${esc(s.role)} · ${esc(s.branch)}</p>${badge(s.active ? 'Active' : 'Inactive')}<p class="metric">${state.tickets.filter((t) => t.assigned === s.name).length}</p><small>Assigned tickets</small><div class="actions">${btn(s.active ? 'Deactivate' : 'Activate', 'toggle-staff', `data-name="${esc(s.name)}"`)}</div></section>`,
            )
            .join('')}</div>`
    );
}
function inventory() {
    return (
        header(
            'Parts inventory',
            'Every receipt, use and transfer creates a movement record.',
            btn('Receive stock', 'receive-stock', '', 'primary'),
        ) +
        `<div class="grid"><section class="card"><h2>Branch stock</h2><div class="list">${state.stock
            .filter((s) => state.branch === 'All branches' || s.branch === state.branch)
            .map(
                (s) =>
                    `<article class="list-item"><div><h3>${esc(CATALOG.find((c) => c.id === s.id).name)}</h3><p>${esc(s.branch)} · ${money(CATALOG.find((c) => c.id === s.id).price)} catalog price</p>${badge(s.qty < 3 ? 'Low stock' : 'Available')}</div><strong class="metric">${s.qty}</strong></article>`,
            )
            .join(
                '',
            )}</div><div class="actions">${btn('Transfer parts', 'stock-transfer')}</div></section><section class="card"><h2>Parts requests</h2>${partsHTML()}<h2 class="section">Movement history</h2>${timeline(state.stockLog.slice(0, 8))}${state.stockLog.length ? '' : empty('No stock movements yet.')}</section></div>`
    );
}
function partsHTML() {
    const rows = scope().flatMap((t) => t.parts.map((p, i) => ({ t, p, i })));
    return rows.length
        ? rows
              .map(
                  ({ t, p, i }) =>
                      `<article class="list-item section"><div><h3>${esc(p.name)} × ${p.qty}</h3><p>${t.id} · Device ${(p.device || 0) + 1}</p>${badge(p.status)}</div>${p.status === 'Requested' && role === 'Admin' ? btn('Approve & reserve', 'approve-part', `data-id="${t.id}" data-index="${i}"`) : p.status === 'Reserved' ? btn('Record usage', 'consume-part', `data-id="${t.id}" data-index="${i}"`) : ''}</article>`,
              )
              .join('')
        : empty('No parts requests.');
}
function parts() {
    return (
        header('Parts requests', 'Select the repair before requesting a compatible catalog part.') +
        `<section class="card">${partsHTML()}</section>`
    );
}
function logistics() {
    return (
        header(
            role === 'Collector' ? 'My collections' : 'Pickup and delivery',
            'Confirm service coverage and record each physical handoff.',
            link('Preview pickup form', 'pickup'),
        ) +
        `<section class="list">${
            state.pickups
                .filter((p) => state.branch === 'All branches' || p.branch === state.branch)
                .map(
                    (p) =>
                        `<article class="card"><div class="row between"><h2>${esc(p.id)} · ${esc(p.name)}</h2>${badge(p.status)}</div><p>${esc(p.device)} · ${esc(p.problem)}</p><p class="muted">${esc(p.address)} · ${esc(p.date)}<br>${esc(p.branch)} · Collector: ${esc(p.collector)}</p><small>Proposed pickup fee: ${money(p.fee)}. Separate approval required.</small><div class="actions">${p.status === 'Requested' ? btn('Review coverage & fee', 'pickup-confirm', `data-id="${p.id}"`) : p.status === 'Confirmed' ? btn('Record collection', 'pickup-collect', `data-id="${p.id}"`, 'primary') : p.status === 'Collected' ? btn('Receive at branch', 'pickup-receive', `data-id="${p.id}"`) : ''}${['Requested', 'Confirmed'].includes(p.status) ? btn('Failed / cancel', 'pickup-fail', `data-id="${p.id}"`) : ''}</div></article>`,
                )
                .join('') || empty('No pickups in this branch.')
        }</section>`
    );
}
function transfers() {
    return (
        header('Device custody', 'Device transfers and parts movements use separate records.') +
        `<section class="list">${state.transfers.map((x, i) => `<article class="card"><div class="row between"><h2>${x.ticket} · ${esc(x.type)}</h2>${badge(x.status)}</div><p>${esc(x.from)} to ${esc(x.to)}</p><p class="muted">${esc(x.evidence || 'Handoff evidence pending')}</p><div class="actions">${x.status === 'In transit' ? btn('Confirm handoff', 'receive-transfer', `data-index="${i}"`, 'primary') : ''}</div></article>`).join('') || empty('Open a repair and choose Transfer / delivery to start a handoff.')}</section>`
    );
}
function finance() {
    return (
        header(
            role === 'Collector' ? 'COD remittance' : 'Cash desk',
            'Approved costs, final bills and physical cash remain separate records.',
        ) +
        `<div class="grid three"><section class="card"><small>Final bills</small><div class="metric">${money(scope().reduce((n, t) => n + (t.bill?.total || 0), 0))}</div></section><section class="card"><small>Net cash received</small><div class="metric">${money(scope().reduce((n, t) => n + paid(t), 0))}</div></section><section class="card"><small>COD awaiting remittance</small><div class="metric">${money(
            state.tickets
                .flatMap((t) => t.payments)
                .filter((p) => p.method === 'COD' && !p.remitted)
                .reduce((n, p) => n + p.amount, 0),
        )}</div></section></div><section class="list section">${scope()
            .map(
                (t) =>
                    `<article class="card"><div class="row between"><h2>${t.id} · ${esc(t.customer)}</h2>${badge(t.bill ? (balance(t) === 0 ? 'Paid' : 'Unpaid') : 'No final bill')}</div><p class="muted">Bill ${t.bill ? money(t.bill.total) : 'pending'} · Net collected ${money(paid(t))} · Balance ${t.bill ? money(balance(t)) : 'pending'}</p><div class="actions">${!t.bill ? btn('Generate bill', 'bill', `data-id="${t.id}"`) : balance(t) > 0 ? btn('Record cash', 'cash', `data-id="${t.id}"`, 'primary') : ''}${paid(t) > 0 ? btn('Receipt', 'cash-receipt', `data-id="${t.id}"`) : ''}${paid(t) > 0 && role !== 'Collector' ? btn('Refund', 'refund', `data-id="${t.id}"`) : ''}${t.bill && balance(t) === 0 && t.devices.some((d) => d.status === 'Ready for pickup') ? btn('Release device', 'release', `data-id="${t.id}"`) : ''}${t.payments.some((p) => p.method === 'COD' && !p.remitted) ? btn('Confirm COD remittance', 'remit', `data-id="${t.id}"`) : ''}</div></article>`,
            )
            .join('')}</section>`
    );
}
function reports() {
    const devices = scope().flatMap((t) => t.devices);
    const cats = ['Cooling', 'Storage', 'Memory', 'Power', 'Software', 'Not recorded'];
    return (
        header(
            'Operational trends',
            'Sample data only. Diagnosis counts reflect technician entries.',
        ) +
        `<div class="row card"><strong>All demo records</strong><small>Reports summarize all current demo records.</small></div><div class="grid section"><section class="card"><h2>Recorded causes</h2><p class="muted">Sample size: ${devices.length} devices</p><div class="bars">${cats
            .map((c) => {
                let n = devices.filter(
                    (d) => d.category === c || (!d.category && c === 'Not recorded'),
                ).length;
                return `<div class="bar-row"><span>${c}</span><div class="bar" style="width:${devices.length ? (n / devices.length) * 100 : 0}%"></div><strong>${n}</strong></div>`;
            })
            .join(
                '',
            )}</div></section><section class="card"><h2>Upgrade demand</h2><div class="metric">${
            scope()
                .flatMap((t) => t.requests)
                .filter((r) => r.type === 'Upgrade').length
        }</div><p class="muted">Customer upgrade requests</p><h2 class="section">Branch workload</h2>${BRANCHES.map((b) => `<p>${b}: ${state.tickets.filter((t) => t.branch === b).length} tickets</p>`).join('')}<small>Production reporting will add date ranges, turnaround time and repeat repair analysis.</small></section></div>`
    );
}
function settings() {
    return (
        header('Branches and settings', 'Change demo policies in one place.') +
        `<div class="grid"><section class="card"><h2>Store setup</h2><form data-form="settings" class="stack">${select('Business setup', 'setup', ['Multiple stores', 'Single store'], state.singleStore ? 'Single store' : 'Multiple stores')}<label class="check"><input type="checkbox" name="photos" ${state.settings.photosRequired ? 'checked' : ''}>Require a condition photo label before issuance</label><p class="muted">Inactive sessions expire after 60 minutes. Background rendering never resets the activity timer.</p><button class="primary">Save demo settings</button></form></section><section class="card"><h2>Branch directory</h2>${(state.singleStore ? [BRANCHES[0]] : BRANCHES).map((b) => `<h3>${b}</h3><p class="muted">Configured service area · Counter cash and COD</p>`).join('')}<p class="notice">Demo PINs and role switching demonstrate the workflow. Production requires server authentication, branch checks and protected tracking credentials.</p></section></div>`
    );
}
function alerts() {
    return (
        header(
            'Notifications',
            'Demo updates stay inside this application.',
            btn('Mark all read', 'read-alerts'),
        ) +
        `<section class="list">${state.alerts.map((a) => `<article class="list-item"><p>${esc(a.text)}</p>${badge(a.read ? 'Read' : 'New')}</article>`).join('') || empty('No notifications.')}</section>`
    );
}
function audit() {
    return (
        header('Activity history', 'A record of decisions, status changes and handoffs.') +
        `<section class="card">${timeline(state.audit)}${state.audit.length ? '' : empty('No demo activity yet.')}</section>`
    );
}
function render() {
    renderBar();
    const route = (location.hash.slice(1) || 'home').split('/')[0];
    const pages = {
        home,
        login,
        pickup,
        portal,
        overview,
        repairs,
        intake,
        repair: repairDetail,
        messages,
        customers,
        team,
        inventory,
        parts,
        logistics,
        transfers,
        finance,
        reports,
        settings,
        alerts,
        audit,
    };
    const publics = ['home', 'login', 'pickup', 'portal'];
    if (!publics.includes(route) && ['Public', 'Customer'].includes(role)) {
        role = 'Admin';
        renderBar();
    }
    $('#app').innerHTML = publics.includes(route)
        ? (pages[route] || home)()
        : shell(route, (pages[route] || overview)());
    if (route === 'home' && matchMedia('(prefers-reduced-motion: reduce)').matches) {
        $('video')?.pause();
        const b = $('[data-action=video]');
        if (b) b.textContent = 'Play background';
    }
    if (route === 'portal' && !ticket().issued) {
        go('home');
        toast('This draft has no tracking access yet.');
    }
    document.title = 'RepairTrack · ' + (route.charAt(0).toUpperCase() + route.slice(1)) + ' demo';
}
// Form dialogs gather a decision before actions mutate sample state.
let context = {};
function action(name, el) {
    tick();
    const t = ticket(),
        d = t.devices[activeDevice] || t.devices[0];
    if (el?.dataset.id) {
        context.id = el.dataset.id;
    } else context.id = t.id;
    switch (name) {
        case 'close':
            closeModal();
            break;
        case 'wireframe':
            wireframe = !wireframe;
            renderBar();
            break;
        case 'logout':
            role = 'Public';
            verified = false;
            go('home');
            toast('Demo session signed out.');
            break;
        case 'guide':
            modal(
                'Your presentation route',
                `<ol class="stack"><li>Start on Home. Enter <strong>DEMO-1042</strong>.</li><li>Explore tracking. Verify Messages or Costs with PIN <strong>2468</strong>.</li><li>Switch to Technician. Open RT-1042, record diagnosis and build a catalog quote.</li><li>Switch to Customer. Approve the quote in Costs.</li><li>Switch back to Technician. Repair, record tests and mark ready.</li><li>Use Admin for parts approvals, branches, requests and transfers.</li><li>Use Cashier to generate a bill, record cash and release devices.</li><li>Open Pickup and Collector to demonstrate collection and COD.</li></ol><p class="notice">Each device in a multi-device ticket needs its own diagnosis, quote and test. Reset restores all samples.</p>`,
            );
            break;
        case 'reset':
            formModal(
                'Reset the demo?',
                'reset',
                '<p>This clears only the sample data in this browser and restores the starting scenario.</p>',
                'Reset sample data',
            );
            break;
        case 'video':
            {
                const v = $('video');
                if (v.paused) {
                    v.play();
                    el.textContent = 'Pause background';
                } else {
                    v.pause();
                    el.textContent = 'Play background';
                }
            }
            break;
        case 'scan':
            scanQR();
            break;
        case 'qr':
        case 'receipt':
            showReceipt(t);
            break;
        case 'open-ticket':
            activeTicket = el.dataset.id;
            activeDevice = 0;
            go('repair');
            break;
        case 'customer-intake':
            {
                const c = state.customers.find((c) => c.name === el.dataset.name);
                intakeDraft = { customer: c.name, contact: c.contact, existing: c.name };
                intakeStep = 0;
                go('intake');
            }
            break;
        case 'intake-back':
            rememberIntake();
            intakeStep = Math.max(0, intakeStep - 1);
            render();
            break;
        case 'issue':
            formModal(
                'Review and issue ticket',
                'issue',
                `<p>${esc(t.id)} · ${t.devices.length} device(s)</p><p class="muted">Condition: ${esc(t.condition)}<br>Accessories: ${esc(t.accessories)}</p>${field('Condition photo label (optional by default)', 'photo', t.photo || '', 'text', state.settings.photosRequired)}<label class="check"><input name="ack" type="checkbox" required ${t.ack ? 'checked' : ''}>Customer confirms condition and service terms</label><p class="notice">This demo acknowledgment is not a legal signature. Production must meet the applied schema and approved policy.</p>`,
                'Issue demo tracking code',
            );
            break;
        case 'add-device':
            if (t.bill) {
                toast('A billed ticket cannot accept another device. Start a new intake.');
                break;
            }
            formModal(
                'Add another device',
                'add-device',
                field('Device name', 'name') +
                    field('Reported problem', 'problem') +
                    field('Serial (optional)', 'serial', '', 'text', false),
                'Add device',
            );
            break;
        case 'assign':
            formModal(
                'Assign technician',
                'assign',
                select(
                    'Technician',
                    'assigned',
                    state.staff
                        .filter((s) => s.active && s.role === 'Technician' && s.branch === t.branch)
                        .map((s) => s.name),
                    t.assigned,
                ),
                'Assign',
            );
            break;
        case 'diagnose':
            if (!t.issued) {
                toast('Issue the intake first.');
                break;
            }
            formModal(
                'Record diagnosis',
                'diagnose',
                area('Findings', 'diagnosis', d.diagnosis || '') +
                    select(
                        'Cause category',
                        'category',
                        ['Cooling', 'Storage', 'Memory', 'Power', 'Software'],
                        d.category,
                    ) +
                    area('Internal note (optional)', 'note', '', false),
                'Save findings',
            );
            break;
        case 'status':
            formModal(
                'Update repair stage',
                'status',
                select('Next stage', 'status', REPAIR_STAGES, d.status) +
                    area('Reason / progress note', 'note') +
                    '<small>Approval, parts and testing gates apply to this device.</small>',
                'Update status',
            );
            break;
        case 'quote':
            if (!t.issued || !d.diagnosis) {
                toast('Issue the ticket and record diagnosis before preparing a quote.');
                break;
            }
            if (t.bill) {
                toast(
                    'The final bill is already issued. New scope needs a new intake in this demo.',
                );
                break;
            }
            formModal(
                'Build an itemized quote',
                'quote',
                `<p>${esc(d.name)} · Catalog prices are fixed in this demo.</p>${CATALOG.map((c) => `<label class="check"><input type="checkbox" name="item" value="${c.id}"><span>${esc(c.name)}<br><small>${money(c.price)} × 1</small></span></label>`).join('')}<p class="notice">Publishing a revision requires customer approval before the changed work.</p>`,
                'Send quote for approval',
            );
            break;
        case 'approve-quote':
        case 'decline-quote':
            if (!verified || role !== 'Customer') {
                toast('Verify customer access first.');
                break;
            }
            context.version = +el.dataset.version;
            formModal(
                name === 'approve-quote' ? 'Approve itemized costs?' : 'Decline this quotation?',
                name,
                `<p>Quote v${context.version}. ${name === 'approve-quote' ? 'This authorizes the listed work and prices.' : 'Staff will arrange an as-is return and review any previously disclosed charges.'}</p>`,
                'Confirm decision',
            );
            break;
        case 'request-part':
            formModal(
                'Request a compatible part',
                'request-part',
                select(
                    'Catalog part',
                    'part',
                    CATALOG.filter((c) => c.type === 'Part').map((c) => c.name),
                ) +
                    field('Quantity', 'qty', '1', 'number') +
                    area('Compatibility / reason', 'reason'),
                'Request part',
            );
            break;
        case 'approve-part':
        case 'consume-part':
            {
                const target = state.tickets.find((x) => x.id === el.dataset.id);
                const p = target.parts[+el.dataset.index];
                const stock = state.stock.find((s) => s.id === p.id && s.branch === target.branch);
                if (name === 'approve-part') {
                    if (role !== 'Admin') {
                        toast('Use the Admin demo console to approve inventory.');
                        break;
                    }
                    if (stock.qty < p.qty) {
                        toast('Insufficient stock. Receive stock before reserving.');
                        break;
                    }
                    stock.qty -= p.qty;
                    p.status = 'Reserved';
                    state.stockLog.unshift({
                        at: now(),
                        text: `Reserved ${p.qty} ${p.name} for ${target.id}`,
                    });
                } else {
                    const q = deviceQuote(target, p.device || 0);
                    if (q?.status !== 'Approved') {
                        toast('Approve the device quotation before consuming parts.');
                        break;
                    }
                    p.status = 'Consumed';
                    state.stockLog.unshift({
                        at: now(),
                        text: `Used ${p.qty} ${p.name} for ${target.id}`,
                    });
                }
                record(`${target.id}: part ${p.status.toLowerCase()}`, target);
                render();
            }
            break;
        case 'test':
            formModal(
                'Record test results',
                'test',
                select('Test outcome', 'result', ['Passed', 'Failed']) +
                    area('Tests performed and results', 'notes'),
                'Save test result',
            );
            break;
        case 'publish':
            formModal(
                'Customer progress update',
                'publish',
                area('Customer-facing update', 'text') +
                    '<small>Use clear language. Internal notes belong in the separate staff field.</small>',
                'Publish demo update',
            );
            break;
        case 'note':
            formModal(
                'Internal staff note',
                'note',
                area('Private note', 'text'),
                'Save internal note',
            );
            break;
        case 'customer-request':
            if (!verified) {
                toast('Verify access first.');
                break;
            }
            context.requestType = el.dataset.type;
            formModal(
                context.requestType + ' request',
                'customer-request',
                area('Tell the repair team what you need', 'text') +
                    '<small>A request does not immediately change costs, work status or custody.</small>',
                'Send request',
            );
            break;
        case 'review-request':
            context.index = +el.dataset.index;
            formModal(
                'Review customer request',
                'review-request',
                `<p>${esc(t.requests[context.index].text)}</p>${select('Decision', 'decision', ['Accepted for review', 'Declined', 'Cancellation approved'])}${area('Explanation and disclosed charges', 'explanation')}`,
                'Record decision',
            );
            break;
        case 'transfer':
            formModal(
                'Record device dispatch',
                'transfer',
                select('Movement type', 'type', ['Branch transfer', 'Customer delivery']) +
                    select(
                        'Destination',
                        'destination',
                        BRANCHES.filter((b) => b !== t.branch),
                    ) +
                    area('Courier and handoff evidence', 'evidence'),
                'Record dispatch',
            );
            break;
        case 'receive-transfer':
            context.index = +el.dataset.index;
            formModal(
                'Confirm physical handoff',
                'receive-transfer',
                area('Receiver and handoff evidence', 'evidence') +
                    '<small>Customer delivery requires a settled balance. Use Cash desk to record COD first.</small>',
                'Confirm receipt',
            );
            break;
        case 'add-staff':
            formModal(
                'Add sample staff',
                'add-staff',
                field('Name', 'name') +
                    select('Role', 'role', ['Technician', 'Cashier', 'Collector']) +
                    select('Branch', 'branch', BRANCHES),
                'Add staff',
            );
            break;
        case 'toggle-staff':
            {
                const s = state.staff.find((s) => s.name === el.dataset.name);
                s.active = !s.active;
                record(`${s.name} ${s.active ? 'activated' : 'deactivated'}`);
                render();
            }
            break;
        case 'receive-stock':
        case 'stock-transfer':
            formModal(
                name === 'receive-stock' ? 'Receive parts stock' : 'Transfer store inventory',
                name,
                select(
                    'Part',
                    'part',
                    CATALOG.filter((c) => c.type === 'Part').map((c) => c.name),
                ) +
                    select(
                        name === 'receive-stock' ? 'Receiving branch' : 'From branch',
                        'branch',
                        BRANCHES,
                    ) +
                    field('Quantity', 'qty', '1', 'number') +
                    (name === 'stock-transfer'
                        ? select('To branch', 'to', BRANCHES, BRANCHES[1])
                        : '') +
                    area('Reference / reason', 'reason'),
                'Record stock movement',
            );
            break;
        case 'pickup-confirm':
            {
                const p = state.pickups.find((p) => p.id === context.id);
                formModal(
                    'Confirm pickup coverage',
                    'pickup-confirm',
                    `<p>${esc(p.address)}</p>${field('Collector', 'collector', 'Taylor')}${field('Agreed collection fee', 'fee', p.fee, 'number')}<label class="check"><input type="checkbox" name="confirmed" required>Customer accepts the fee, schedule and accessible meeting point</label>`,
                    'Confirm collection',
                );
            }
            break;
        case 'pickup-collect':
            formModal(
                'Collect device',
                'pickup-collect',
                area('Device condition and accessories', 'condition') +
                    field('Customer / collector handoff reference', 'evidence'),
                'Record collection',
            );
            break;
        case 'pickup-receive':
            {
                const p = state.pickups.find((p) => p.id === context.id);
                intakeDraft = {
                    customer: p.name,
                    contact: p.contact,
                    device: p.device,
                    problem: p.problem,
                    branch: p.branch,
                    arrival: 'Pickup',
                    condition: p.condition || '',
                    accessories: 'To confirm',
                    pickupId: p.id,
                };
                intakeStep = 1;
                go('intake');
            }
            break;
        case 'pickup-fail':
            formModal(
                'Pickup exception',
                'pickup-fail',
                select('Outcome', 'status', ['Failed attempt', 'Cancelled']) +
                    area('Reason / next action', 'reason'),
                'Save exception',
            );
            break;
        case 'bill':
            {
                const target = state.tickets.find((t) => t.id === context.id);
                if (
                    !target.devices.every((d) =>
                        ['Ready for pickup', 'Returned', 'Cancelled'].includes(d.status),
                    )
                ) {
                    toast(
                        'All devices must finish testing or complete an approved cancellation review before billing.',
                    );
                    break;
                }
                const total = approvedTotal(target) + (target.pickupFee || 0);
                if (!total && !target.devices.every((d) => d.returnAsIs)) {
                    toast('No approved charges. Review quotes before generating a bill.');
                    break;
                }
                target.bill = { total };
                record(`${target.id}: final bill ${money(total)}`, target, true);
                render();
            }
            break;
        case 'cash':
            {
                const target = state.tickets.find((t) => t.id === context.id);
                formModal(
                    'Record physical cash',
                    'cash',
                    `<p>Outstanding: ${money(balance(target))}</p>${field('Amount received', 'amount', balance(target), 'number')}${select('Collection method', 'method', ['Counter cash', 'COD'])}<label class="check"><input type="checkbox" required>I physically received this cash (demo)</label>`,
                    'Issue cash receipt',
                );
            }
            break;
        case 'cash-receipt':
            showCashReceipt(state.tickets.find((t) => t.id === context.id));
            break;
        case 'refund':
            formModal(
                'Record cash refund',
                'refund',
                field('Refund amount', 'amount', '', 'number') +
                    area('Reason and authorizing staff', 'reason'),
                'Record physical refund',
            );
            break;
        case 'release':
            formModal(
                'Return devices to customer',
                'release',
                area('Receiver and handover evidence', 'evidence'),
                'Confirm device return',
            );
            break;
        case 'remit':
            formModal(
                'Confirm COD remittance',
                'remit',
                area('Cashier and remittance reference', 'evidence'),
                'Confirm cash handoff',
            );
            break;
        case 'read-alerts':
            state.alerts.forEach((a) => (a.read = true));
            saveDemo();
            render();
            break;
    }
}
function deviceQuote(t, index) {
    return [...t.quotes].reverse().find((q) => (q.device || 0) === index);
}
function approvedTotal(t) {
    return t.devices.reduce((sum, d, i) => {
        const q = [...t.quotes]
            .reverse()
            .find((q) => (q.device || 0) === i && q.status === 'Approved');
        return sum + (q?.total || 0);
    }, 0);
}
function rememberIntake() {
    const f = $('[data-form=intake]');
    if (!f) return;
    const values = Object.fromEntries(new FormData(f));
    intakeDraft = { ...intakeDraft, ...values };
    if (intakeStep === 1) intakeDraft.ack = f.elements.ack.checked;
}
function requireGate(ok, message) {
    if (!ok) throw Error(message);
}
function submit(kind, form) {
    const f = new FormData(form),
        v = Object.fromEntries(f),
        t = state.tickets.find((t) => t.id === context.id) || ticket(),
        d = t.devices[activeDevice] || t.devices[0];
    tick();
    try {
        switch (kind) {
            case 'track': {
                const found = state.tickets.find(
                    (t) => t.issued && t.code.toUpperCase() === v.code.trim().toUpperCase(),
                );
                requireGate(found, 'Code not found. Try DEMO-1042 or check the receipt.');
                activeTicket = found.id;
                role = 'Customer';
                verified = false;
                go('portal');
                return;
            }
            case 'login':
                setRole(v.role);
                return;
            case 'verify':
                requireGate(v.pin === ticket().pin, 'That PIN did not match. Demo PIN: 2468.');
                verified = true;
                render();
                toast('Demo access verified.');
                return;
            case 'reset':
                state = seedDemo();
                saveDemo();
                role = 'Public';
                verified = false;
                activeTicket = 'RT-1042';
                activeDevice = 0;
                intakeDraft = {};
                intakeStep = 0;
                closeModal();
                go('home');
                render();
                toast('Sample data restored.');
                return;
            case 'pickup': {
                const id = 'PU-' + (101 + state.pickups.length);
                state.pickups.push({
                    ...v,
                    id,
                    status: 'Requested',
                    collector: 'Unassigned',
                    fee: 150,
                });
                record(`${id}: pickup request awaiting staff confirmation`);
                modal(
                    'Pickup request received',
                    `<h2>${id}</h2><p>Your request awaits coverage, fee and schedule confirmation. The device is not yet in branch custody.</p>${btn('Return home', 'close')}`,
                );
                form.reset();
                return;
            }
            case 'intake':
                rememberIntake();
                if (intakeStep < 2) {
                    intakeStep++;
                    render();
                    return;
                }
                {
                    const n = intakeDraft;
                    const id = 'RT-' + state.sequence++;
                    const item = {
                        id,
                        code: 'DEMO-' + id.slice(3),
                        pin: '2468',
                        customer: n.customer,
                        contact: n.contact,
                        branch: n.branch,
                        arrival: n.arrival,
                        issued: false,
                        condition: n.condition,
                        accessories: n.accessories,
                        photo: n.photo,
                        ack: n.ack,
                        location: n.branch,
                        custody: 'At branch',
                        assigned: '',
                        devices: [
                            {
                                name: n.device,
                                serial: n.serial,
                                problem: n.problem,
                                status: 'Received',
                                diagnosis: '',
                                category: 'Not recorded',
                                tested: false,
                            },
                        ],
                        quotes: [],
                        payments: [],
                        refunds: [],
                        messages: [],
                        requests: [],
                        events: [],
                        parts: [],
                        notes: [],
                        bill: null,
                        pickupFee: n.pickupId
                            ? state.pickups.find((p) => p.id === n.pickupId)?.fee || 0
                            : 0,
                    };
                    state.tickets.push(item);
                    if (
                        !state.customers.some(
                            (c) => c.name === n.customer && c.contact === n.contact,
                        )
                    )
                        state.customers.push({ name: n.customer, contact: n.contact });
                    if (n.pickupId) {
                        state.pickups.find((p) => p.id === n.pickupId).status =
                            'Received at branch';
                    }
                    record(`${id}: intake draft saved`, item);
                    activeTicket = id;
                    activeDevice = 0;
                    intakeDraft = {};
                    intakeStep = 0;
                    go('repair');
                    toast('Draft saved. Review and issue when the checks are complete.');
                    return;
                }
            case 'issue':
                requireGate(
                    t.condition && t.accessories,
                    'Record condition and accessories first.',
                );
                requireGate(v.ack === 'on', 'Customer acknowledgment is required.');
                requireGate(
                    !state.settings.photosRequired || v.photo.trim(),
                    'A photo label is required by this demo policy.',
                );
                t.ack = true;
                t.photo = v.photo;
                t.issued = true;
                record(`${t.id}: devices received and tracking issued at ${t.branch}`, t, true);
                break;
            case 'add-device':
                t.devices.push({
                    name: v.name,
                    serial: v.serial,
                    problem: v.problem,
                    status: 'Received',
                    diagnosis: '',
                    category: 'Not recorded',
                    tested: false,
                });
                record(`${t.id}: additional device recorded`, t, true);
                break;
            case 'assign':
                requireGate(v.assigned, 'Add an active technician for this branch first.');
                t.assigned = v.assigned;
                record(`${t.id}: assigned to ${v.assigned}`, t);
                break;
            case 'diagnose':
                d.diagnosis = v.diagnosis;
                d.category = v.category;
                d.status = 'Diagnosing';
                if (v.note) t.notes.push(v.note);
                record(`${t.id}: diagnosis recorded for ${d.name}`, t);
                break;
            case 'status': {
                requireGate(t.issued, 'Issue the ticket first.');
                const target = v.status;
                requireGate(
                    target !== 'Returned',
                    'Use the cash desk and physical handover to return a device.',
                );
                const allowed = {
                    Received: ['Diagnosing'],
                    Diagnosing: ['Awaiting approval', 'Repairing'],
                    'Awaiting approval': ['Awaiting parts', 'Repairing', 'Ready for pickup'],
                    'Awaiting parts': ['Repairing'],
                    Repairing: ['Testing', 'Awaiting parts'],
                    Testing: ['Repairing', 'Ready for pickup'],
                    'Ready for pickup': ['Repairing'],
                };
                requireGate(
                    (allowed[d.status] || []).includes(target),
                    'Choose the next valid stage. Use diagnosis, quotation and testing actions to complete required steps.',
                );
                if (['Repairing', 'Awaiting parts', 'Testing'].includes(target))
                    requireGate(
                        deviceQuote(t, activeDevice)?.status === 'Approved',
                        'This device needs an approved current quotation.',
                    );
                if (target === 'Repairing')
                    requireGate(
                        !t.parts.some(
                            (p) => (p.device || 0) === activeDevice && p.status === 'Requested',
                        ),
                        'Resolve the requested parts first.',
                    );
                if (target === 'Ready for pickup')
                    requireGate(
                        d.tested,
                        'Record passed tests first, or use the approved cancellation/decline return process.',
                    );
                d.status = target;
                record(`${d.name}: ${target}. ${v.note}`, t, true);
                break;
            }
            case 'quote': {
                const ids = f.getAll('item');
                requireGate(ids.length > 0, 'Select at least one catalog item.');
                const items = ids.map((id) => ({ ...CATALOG.find((c) => c.id === id), qty: 1 }));
                const prior = deviceQuote(t, activeDevice);
                if (prior?.status === 'Pending') prior.status = 'Superseded';
                t.quotes.push({
                    version: t.quotes.length + 1,
                    device: activeDevice,
                    status: 'Pending',
                    items,
                    total: items.reduce((n, i) => n + i.price * i.qty, 0),
                });
                d.status = 'Awaiting approval';
                d.tested = false;
                record(`${d.name}: quotation ready for customer approval`, t, true);
                break;
            }
            case 'approve-quote':
            case 'decline-quote': {
                requireGate(
                    verified && role === 'Customer',
                    'Verify customer access before deciding.',
                );
                const q = t.quotes.find((q) => q.version === context.version);
                requireGate(q?.status === 'Pending', 'This quotation already has a decision.');
                q.status = kind === 'approve-quote' ? 'Approved' : 'Declined';
                const dev = t.devices[q.device || 0];
                if (q.status === 'Approved')
                    dev.status = t.parts.some(
                        (p) => (p.device || 0) === (q.device || 0) && p.status === 'Requested',
                    )
                        ? 'Awaiting parts'
                        : 'Repairing';
                else {
                    dev.status = 'Awaiting approval';
                    t.requests.push({
                        type: 'Declined quote return',
                        text: 'Customer declined quote. Review as-is return and previously disclosed charges.',
                        status: 'Requested',
                    });
                }
                record(`${t.id}: customer ${q.status.toLowerCase()} quote v${q.version}`, t, true);
                break;
            }
            case 'request-part': {
                const part = CATALOG.find((c) => c.name === v.part);
                const qty = Number(v.qty);
                requireGate(Number.isInteger(qty) && qty > 0, 'Enter a positive whole quantity.');
                t.parts.push({
                    id: part.id,
                    name: part.name,
                    qty,
                    device: activeDevice,
                    reason: v.reason,
                    status: 'Requested',
                });
                record(`${t.id}: ${part.name} requested`, t);
                break;
            }
            case 'test':
                requireGate(
                    ['Repairing', 'Testing'].includes(d.status),
                    'Only test a device after approved repair work.',
                );
                d.tested = v.result === 'Passed';
                d.status = d.tested ? 'Testing' : 'Repairing';
                record(`${d.name}: testing ${v.result.toLowerCase()}. ${v.notes}`, t, true);
                break;
            case 'publish':
                record(v.text, t, true);
                break;
            case 'note':
                t.notes.push(v.text);
                record(`${t.id}: internal note added`, t);
                break;
            case 'message': {
                const target = ticket();
                requireGate(role !== 'Customer' || verified, 'Verify customer access first.');
                target.messages.push({
                    by: role === 'Customer' ? 'Customer' : 'Repair team',
                    text: v.message,
                });
                record(`${target.id}: new message`, target);
                render();
                toast('Demo message added. No external message sent.');
                return;
            }
            case 'customer-request':
                requireGate(verified, 'Verify customer access first.');
                t.requests.push({ type: context.requestType, text: v.text, status: 'Requested' });
                record(`${t.id}: ${context.requestType.toLowerCase()} requested`, t, true);
                break;
            case 'review-request': {
                const r = t.requests[context.index];
                requireGate(r.status === 'Requested', 'This request has already been reviewed.');
                if (v.decision === 'Cancellation approved') {
                    requireGate(
                        ['Cancellation', 'Declined quote return'].includes(r.type),
                        'Use cancellation approval only for cancellation or declined-quote return.',
                    );
                    requireGate(
                        !t.bill,
                        'Review billed charges at the cash desk before cancellation.',
                    );
                    t.devices
                        .filter((d) => d.status !== 'Returned')
                        .forEach((d) => {
                            d.status = 'Ready for pickup';
                            d.returnAsIs = true;
                        });
                    r.status = 'Approved for as-is return';
                } else r.status = v.decision;
                r.response = v.explanation;
                record(`${t.id}: ${r.type} ${r.status.toLowerCase()}. ${v.explanation}`, t, true);
                break;
            }
            case 'transfer':
                requireGate(t.issued, 'Issue the ticket first.');
                requireGate(t.custody !== 'In transit', 'Complete the existing transfer first.');
                if (v.type === 'Customer delivery')
                    requireGate(
                        t.devices.every((d) => d.status === 'Ready for pickup'),
                        'All devices must be ready before dispatch.',
                    );
                state.transfers.push({
                    ticket: t.id,
                    type: v.type,
                    from: t.location,
                    to: v.type === 'Customer delivery' ? 'Customer' : v.destination,
                    evidence: v.evidence,
                    status: 'In transit',
                });
                t.custody = 'In transit';
                record(`${t.id}: dispatched from ${t.location}; in transit`, t, true);
                break;
            case 'receive-transfer': {
                const x = state.transfers[context.index],
                    target = state.tickets.find((t) => t.id === x.ticket);
                if (x.type === 'Customer delivery') {
                    requireGate(
                        target.bill && balance(target) === 0,
                        'Settle the bill with counter cash or COD before confirming delivery.',
                    );
                    target.devices.forEach((d) => (d.status = 'Returned'));
                    target.custody = 'Returned';
                } else {
                    target.branch = x.to;
                    target.custody = 'At branch';
                    target.assigned = '';
                }
                target.location = x.to;
                x.status = 'Received';
                x.evidence = v.evidence;
                record(`${target.id}: handoff confirmed at ${x.to}`, target, true);
                break;
            }
            case 'add-staff':
                requireGate(
                    !state.staff.some((s) => s.name === v.name),
                    'That sample staff name already exists.',
                );
                state.staff.push({ ...v, active: true });
                record(`Staff added: ${v.name}`);
                break;
            case 'receive-stock':
            case 'stock-transfer': {
                const part = CATALOG.find((c) => c.name === v.part),
                    qty = Number(v.qty);
                requireGate(Number.isInteger(qty) && qty > 0, 'Enter a positive whole quantity.');
                const source = state.stock.find((s) => s.id === part.id && s.branch === v.branch);
                if (kind === 'stock-transfer') {
                    requireGate(v.branch !== v.to, 'Choose a different receiving branch.');
                    requireGate(source.qty >= qty, 'Insufficient available stock.');
                    source.qty -= qty;
                    state.stock.find((s) => s.id === part.id && s.branch === v.to).qty += qty;
                } else source.qty += qty;
                const text = `${kind === 'stock-transfer' ? 'Transferred' : 'Received'} ${qty} ${part.name}. ${v.reason}`;
                state.stockLog.unshift({ at: now(), text });
                record(text);
                break;
            }
            case 'pickup-confirm': {
                const p = state.pickups.find((p) => p.id === context.id);
                requireGate(v.confirmed === 'on', 'Confirm the customer agreement.');
                p.status = 'Confirmed';
                p.collector = v.collector;
                p.fee = Number(v.fee);
                record(`${p.id}: coverage, fee and schedule confirmed`);
                break;
            }
            case 'pickup-collect': {
                const p = state.pickups.find((p) => p.id === context.id);
                p.status = 'Collected';
                p.condition = v.condition;
                p.evidence = v.evidence;
                record(`${p.id}: collector received the device`);
                break;
            }
            case 'pickup-fail': {
                const p = state.pickups.find((p) => p.id === context.id);
                p.status = v.status;
                p.reason = v.reason;
                record(`${p.id}: ${v.status}. ${v.reason}`);
                break;
            }
            case 'cash': {
                const amount = Number(v.amount);
                requireGate(
                    t.bill && amount > 0 && amount <= balance(t),
                    'Enter an amount above zero and no greater than the balance.',
                );
                t.payments.push({
                    id: 'RC-' + (state.audit.length + 100),
                    amount,
                    method: v.method,
                    remitted: v.method !== 'COD',
                    at: now(),
                });
                record(`${t.id}: ${money(amount)} cash received by ${v.method}`, t, true);
                break;
            }
            case 'refund': {
                const amount = Number(v.amount);
                requireGate(
                    amount > 0 && amount <= paid(t),
                    'Refund must not exceed net cash received.',
                );
                t.refunds.push({ amount, reason: v.reason, at: now() });
                record(`${t.id}: physical refund ${money(amount)}. ${v.reason}`, t, true);
                break;
            }
            case 'release':
                requireGate(t.bill && balance(t) === 0, 'Settle the bill before release.');
                requireGate(
                    t.custody !== 'In transit',
                    'Use transfer handoff confirmation while the device is in transit.',
                );
                requireGate(
                    t.devices.every((d) => ['Ready for pickup', 'Returned'].includes(d.status)),
                    'Every device must be ready before this ticket-level handover.',
                );
                t.devices.forEach((d) => (d.status = 'Returned'));
                t.location = 'Customer';
                t.custody = 'Returned';
                record(`${t.id}: devices returned. ${v.evidence}`, t, true);
                break;
            case 'remit':
                requireGate(
                    role === 'Cashier' || role === 'Admin',
                    'A cashier or administrator confirms cash remittance.',
                );
                t.payments.filter((p) => p.method === 'COD').forEach((p) => (p.remitted = true));
                record(`${t.id}: COD remittance confirmed. ${v.evidence}`, t);
                break;
            case 'settings':
                state.singleStore = v.setup === 'Single store';
                state.settings.photosRequired = v.photos === 'on';
                if (state.singleStore) state.branch = BRANCHES[0];
                record('Demo branch settings updated');
                break;
        }
        closeModal();
        saveDemo();
        render();
        toast('Demo update saved in this browser.');
    } catch (error) {
        toast(error.message);
    }
}
function showReceipt(t) {
    const url = new URL(location.href);
    url.hash = 'track/' + encodeURIComponent(t.code);
    modal(
        'Tracking receipt',
        `<p class="eyebrow">${esc(t.id)}</p><h2>${esc(t.customer)}</h2><p>${t.devices.map((d) => esc(d.name)).join('<br>')}</p><p class="amount">${esc(t.code)}</p><div id="qr-output" class="qr"></div><p class="muted section">Scan to open this demo repair. Separate demo PIN: 2468.</p><div class="actions">${btn('Print receipt', 'print')}${link('Open tracking', 'track/' + t.code, 'button primary')}</div>`,
    );
    if (typeof qrcode === 'function') {
        const qr = qrcode(0, 'M');
        qr.addData(url.href);
        qr.make();
        $('#qr-output').innerHTML = qr.createImgTag(5, 8, 'Tracking QR code');
    } else $('#qr-output').textContent = t.code;
}
function showCashReceipt(t) {
    modal(
        'Cash receipt',
        `<p class="eyebrow">Demo document</p><h2>${esc(t.id)} · ${esc(t.customer)}</h2><p>Final bill: ${money(t.bill?.total || 0)}</p>${t.payments.map((p) => `<p>${esc(p.id)}<br>${esc(p.at)} · ${esc(p.method)}<br><strong>${money(p.amount)}</strong></p>`).join('')}<p>Refunds: ${money(t.refunds.reduce((n, r) => n + r.amount, 0))}</p><p class="amount">Balance ${money(balance(t))}</p><small>This is a sample receipt, not a tax invoice.</small><div class="actions">${btn('Print', 'print')}</div>`,
    );
}
let cameraStream = null,
    scanTimer = null;
async function scanQR() {
    if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
        modal(
            'QR entry',
            `<p>Your browser does not support camera scanning here. Open a receipt QR link with your phone camera, or type the code.</p><form data-form="track" class="stack">${field('Tracking code', 'code', 'DEMO-1042')}<button class="primary">Track repair</button></form>`,
        );
        return;
    }
    modal(
        'Scan tracking QR',
        '<video id="camera" autoplay playsinline style="width:100%"></video><p>Point your camera at a RepairTrack demo receipt.</p>',
    );
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });
        $('#camera').srcObject = cameraStream;
        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        scanTimer = setInterval(async () => {
            const video = $('#camera');
            if (!video) return;
            try {
                const codes = await detector.detect(video);
                if (codes.length) {
                    const url = new URL(codes[0].rawValue);
                    if (url.hash.startsWith('#track/')) {
                        closeModal();
                        go(url.hash.slice(1));
                    }
                }
            } catch {}
        }, 700);
    } catch {
        toast('Camera unavailable. Use manual tracking code entry.');
        closeModal();
    }
}
$('#modal').addEventListener('close', () => {
    cameraStream?.getTracks().forEach((t) => t.stop());
    cameraStream = null;
    clearInterval(scanTimer);
});
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-action]');
    if (el) {
        if (el.dataset.action === 'print') {
            window.print();
            return;
        }
        action(el.dataset.action, el);
    }
    const anchor = e.target.closest('[data-section]');
    if (anchor) {
        e.preventDefault();
        go('home');
        setTimeout(
            () =>
                document.getElementById(anchor.dataset.section)?.scrollIntoView({
                    behavior: matchMedia('(prefers-reduced-motion: reduce)').matches
                        ? 'instant'
                        : 'smooth',
                }),
            30,
        );
    }
});
document.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-form]');
    if (form) {
        e.preventDefault();
        submit(form.dataset.form, form);
    }
});
document.addEventListener('change', (e) => {
    tick();
    if (e.target.id === 'role-picker') setRole(e.target.value);
    if (e.target.id === 'branch-picker') {
        state.branch = e.target.value;
        saveDemo();
        render();
    }
    if (e.target.id === 'device-picker') {
        activeDevice = +e.target.value;
        render();
    }
    if (e.target.name === 'message-ticket') {
        activeTicket = e.target.value;
        render();
    }
    if (e.target.name === 'existing') {
        const c = state.customers.find((c) => c.name === e.target.value);
        if (c) {
            const f = e.target.form;
            f.elements.customer.value = c.name;
            f.elements.contact.value = c.contact;
        }
    }
    if (e.target.id === 'repair-filter') filterRepairs();
});
function filterRepairs() {
    const q = ($('#repair-search')?.value || '').toLowerCase(),
        status = $('#repair-filter')?.value || 'All statuses';
    $('#repair-list').innerHTML =
        scope()
            .filter(
                (t) =>
                    JSON.stringify([t.id, t.customer, t.devices.map((d) => d.name)])
                        .toLowerCase()
                        .includes(q) &&
                    (status === 'All statuses' ||
                        (status === 'Draft' && !t.issued) ||
                        t.devices.some((d) => d.status === status)),
            )
            .map(ticketCard)
            .join('') || empty('No matching repairs.');
}
document.addEventListener('input', (e) => {
    if (e.target.id === 'repair-search') filterRepairs();
    if (e.target.id === 'customer-filter') {
        const q = e.target.value.toLowerCase();
        $('#customer-list')
            .querySelectorAll('article')
            .forEach((a) => (a.hidden = !a.textContent.toLowerCase().includes(q)));
    }
});
function handleRoute() {
    const route = location.hash.slice(1);
    if (route.startsWith('track/')) {
        const code = decodeURIComponent(route.slice(6));
        const t = state.tickets.find((t) => t.issued && t.code === code);
        if (t) {
            activeTicket = t.id;
            role = 'Customer';
            verified = false;
            go('portal');
        } else {
            go('home');
            toast('Tracking link unavailable. Contact the branch.');
        }
        return;
    }
    render();
}
window.addEventListener('hashchange', handleRoute);
['pointerdown', 'keydown', 'touchstart'].forEach((type) =>
    document.addEventListener(type, tick, { passive: true }),
);
setInterval(() => {
    if (role !== 'Public' && Date.now() - lastActivity >= state.settings.idleMinutes * 60000) {
        role = 'Public';
        verified = false;
        go('home');
        toast(
            'The demo session expired after 60 minutes of inactivity. Saved sample records remain.',
        );
    }
}, 30000);
handleRoute();
