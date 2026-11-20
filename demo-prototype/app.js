/* RepairTrack demo controller. Rendering, forms and workflow actions are grouped below.
   This browser-only simulation is NOT an authentication or production backend. */
const $ = (s) => document.querySelector(s);
const esc = (v) =>
  String(v ?? '').replace(
    /[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
  );
const money = (n) =>
  '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
let publicTicketId = null;
let role = 'Public',
  verified = false,
  activeTicket = 'RT-1042',
  activeDevice = 0,
  wireframe = false,
  intakeStep = 0,
  intakeDraft = {},
  intakeDevice = 0,
  lastActivity = Date.now(),
  toastTimer;
const ROLE_HOME = {
  Admin: 'overview',
  Technician: 'overview',
  Cashier: 'overview',
  Collector: 'overview',
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
  return new Date().toISOString();
}
function displayTime(value) {
  // Older sample events already contain a display label.
  if (!/^\d{4}-\d{2}-\d{2}T/.test(value)) return value || '';
  return new Date(value).toLocaleString('en-PH', {timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short'});
}
function resetIntake() {
  intakeStep = 0;
  intakeDevice = 0;
  intakeDraft = {};
}
function focusIntakeStep() {
  document.querySelector('.stepper [aria-current="step"]')?.focus();
}
// Save one workflow event to the demo history, notifications and optional ticket.
const feedGroups = ['Repairs', 'Conversations', 'Cash desk', 'Collections', 'Custody', 'Administration'];
const feedSelection = { alerts: 'All', audit: 'All' };

function record(text, ticket = null, isPublic = false, group = 'Repairs', branch = state.branch, custody = false) {
  const event = { at: now(), text, public: isPublic, role, actorId: demoAccounts[role], group,
    custody: custody || group === 'Collections' || group === 'Custody',
    ticketId: ticket?.id, branch: ticket?.branch || branch };
  state.audit.unshift(event);
  // Keep routine updates in history. Notify only for a handoff or a conversation.
  if (['Conversations', 'Cash desk', 'Collections'].includes(group) || group === 'Repairs' && role === 'Customer') {
    state.alerts.unshift({ ...event, readBy: [] });
  }
  if (ticket) ticket.events.unshift(event);
  saveDemo();
}

function visibleEvent(event) {
  if (state.branch !== 'All branches' && event.branch !== state.branch) return false;
  if (role === 'Admin') return true;
  if (!event.group || !event.branch) return false;
  if (role === 'Cashier') return event.group === 'Cash desk';
  if (role === 'Collector') return event.group === 'Collections';
  const assigned = state.tickets.find((t) => t.id === event.ticketId)?.assigned;
  return role === 'Technician' && assigned === 'Mika Reyes' &&
    ['Repairs', 'Conversations'].includes(event.group);
}

// The role selector represents one fixed sample account per staff role.
const demoAccounts = { Admin: 'staff-admin', Technician: 'staff-mika', Cashier: 'staff-jamie', Collector: 'staff-taylor' };
function inbox() {
  return state.alerts.filter((event) => visibleEvent(event) && (event.actorId ? event.actorId !== demoAccounts[role] : event.role !== role));
}

function isUnread(event) {
  return !event.read && !event.readBy?.includes(demoAccounts[role]) && !event.readBy?.includes(role);
}

function ticket() {
  return state.tickets.find((t) => t.id === activeTicket) || state.tickets[0];
}
// Filter the displayed tickets by branch and the demo technician assignment.
function scope() {
  return state.tickets.filter(
    (t) =>
      (state.branch === 'All branches' || t.branch === state.branch) &&
      (role !== 'Technician' || t.assigned === 'Mika Reyes')
  );
}
function currentQuote(t) {
  return t.quotes.at(-1);
}
function approvedQuote(t) {
  return [...t.quotes].reverse().find((q) => q.status === 'Approved');
}
// Refunds reduce the amount already paid toward this ticket.
function centavos(amount) {
  return Math.round(Number(amount) * 100);
}
function paid(t) {
  const receipts = t.payments.reduce((total, payment) => total + centavos(payment.amount), 0);
  const refunds = t.refunds.reduce((total, refund) => total + centavos(refund.amount), 0);
  return (receipts - refunds) / 100;
}
function balance(t) {
  return Math.max(0, centavos(t.bill?.total || 0) - centavos(paid(t))) / 100;
}
// Map workflow status words to the shared badge colors.
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
  return `<header class="page-heading"><div><p class="eyebrow">${title === 'Request device pickup' ? 'Pickup service' : role === 'Customer' ? 'Your repair' : esc(role) + ' workspace'}</p><h1>${title}</h1><p>${description}</p></div>${action}</header>`;
}
function modal(title, body) {
  $('#modal-content').innerHTML =
    `<header class="dialog-header"><h2 id="dialog-title">${title}</h2>${btn('✕', 'close', 'aria-label="Close dialog"', 'quiet')}</header>${body}`;
  $('#modal').showModal();
}
function formModal(title, kind, body, submit = 'Save demo update') {
  modal(
    title,
    `<form data-form="${kind}" class="stack">${body}<div class="actions">${btn('Cancel', 'close')}<button class="primary" type="submit">${submit}</button></div></form>`
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
  resetIntake();
  if ($('#modal').open) closeModal();
  feedSelection.alerts = 'All';
  feedSelection.audit = 'All';
  repairReturn = null;
  publicTicketId = null;
  role = next;
  if (role !== 'Admin' && state.branch === 'All branches') state.branch = BRANCHES[0];
  verified = false;
  tick();
  if (role === 'Customer') {
    const sample = state.tickets.find(t => t.id === activeTicket && t.issued) || state.tickets.find(t => t.issued);
    if (sample) publicTicketId = activeTicket = sample.id;
  }
  go(ROLE_HOME[role]);
  renderBar();
}
function renderBar() {
  document.body.classList.toggle('wireframe', wireframe);
  const controlsOpen = $('#demo-bar details')?.open;
  $('#demo-bar').innerHTML =
    `<details ${controlsOpen ? 'open' : ''}><summary>Demo controls</summary><div class="demo-tools"><label>Explore as <select id="role-picker">${Object.keys(
      ROLE_HOME
    )
      .map((r) => `<option ${r === role ? 'selected' : ''}>${r}</option>`)
      .join(
        ''
      )}</select></label>${btn('Demo guide', 'guide')}${btn('Reset demo', 'reset', '', 'quiet')}</div></details>`;
}
const navs = {
  Admin: [
    ['overview', 'Overview', '◈'],
    ['repairs', 'Repairs', '▣'],
    ['customers', 'Customers', '◎'],
    ['team', 'Team', '♙'],
    ['inventory', 'Inventory', '▤'],
    ['finance', 'Finance', '₱'],
    ['reports', 'Reports', '▥'],
  ],
  Technician: [
    ['overview', 'Overview', '◈'],
    ['repairs', 'My repairs', '▣'],
    ['messages', 'Messages', '✉'],
    ['parts', 'Parts requests', '▤'],
  ],
  Cashier: [
    ['overview', 'Overview', '◈'],
    ['repairs', 'Repairs', '▣'],
    ['intake', 'New intake', '＋'],
    ['customers', 'Customers', '◎'],
    ['finance', 'Cash desk', '₱'],
  ],
  Collector: [
    ['overview', 'Overview', '◈'],
    ['logistics', 'My collections', '⌖'],
    ['transfers', 'Handoffs', '⇄'],
    ['finance', 'COD remittance', '₱'],
  ],
};
function navigation(route) {
  const links = [...(navs[role] || [])];
  if (role === 'Admin') {
    links.push(['logistics', 'Pickup & delivery', '⌖'], ['transfers', 'Branch transfers', '⇄'], ['settings', 'Branch settings', '⚙']);
  }
  links.push(['alerts', 'Notifications', '♢'], ['audit', 'Activity history', '◷']);
  const icons = {
    team: '<circle cx="9" cy="8" r="3"/><path d="M2 20v-2a7 7 0 0 1 14 0v2m0-15a3 3 0 0 1 0 6m3 3a5 5 0 0 1 3 5"/>',
    alerts: '<path d="M4 17h16l-2-3V9a6 6 0 0 0-12 0v5zm6 3a2 2 0 0 0 4 0"/>',
    messages: '<path d="M3 4h18v13H9l-6 4z"/>'
  };
  function navIcon(path, fallback) {
    return icons[path] ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">${icons[path]}</svg>` : fallback;
  }
  function navLink([path, label, icon]) {
    return `<a href="#${path}" ${route === path ? 'aria-current="page"' : ''} class="${route === path ? 'active' : ''}"><span class="side-icon" aria-hidden="true">${navIcon(path, icon)}</span>${label}</a>`;
  }
  if (role !== 'Admin') return links.map(navLink).join('');
  const occasional = ['team', 'reports', 'settings'];
  const daily = links.filter(([path]) => !occasional.includes(path));
  const more = links.filter(([path]) => occasional.includes(path));
  return daily.map(navLink).join('') + `<details class="admin-more" ${occasional.includes(route) ? 'open' : ''}><summary>Management &amp; reports</summary>${more.map(navLink).join('')}</details>`;

}
function shell(route, html) {
  return `<div class="mobile-nav"><details><summary>Menu · ${esc(role)} · ${esc(state.branch)}</summary><nav aria-label="Mobile workspace">${navigation(route)}</nav>${btn('Sign out', 'logout', '', 'quiet')}</details></div><div class="shell"><aside class="sidebar"><a class="brand" href="#${ROLE_HOME[role]}"><span class="brand-mark" aria-hidden="true">R</span>RepairTrack</a><p class="eyebrow">${esc(role)} console</p><nav class="side-nav" aria-label="Main navigation">${navigation(route)}</nav><div class="sidebar-footer">${btn('Sign out', 'logout', '', 'quiet')}</div></aside><div class="workspace"><header class="topbar"><div><strong>${role === 'Technician' ? 'Mika Reyes' : role === 'Collector' ? 'Taylor · Collector' : 'RepairTrack staff'}</strong><small> / ${esc(role)}</small></div><div class="row"><label class="check">Viewing branch<select id="branch-picker">${(state.singleStore ? [BRANCHES[0]] : role === 'Admin' ? ['All branches', ...BRANCHES] : BRANCHES).map((b) => `<option ${state.branch === b ? 'selected' : ''}>${b}</option>`).join('')}</select></label>${link('Alerts ' + inbox().filter(isUnread).length, 'alerts', 'button quiet')}</div></header><main id="content" tabindex="-1" class="content">${html}</main></div></div>`;
}
function publicNav() {
  const links = ['Home', 'About', 'FAQ', 'Contacts', 'Services']
    .map((s) => `<a href="#home" data-section="${s.toLowerCase()}">${s}</a>`)
    .join('');
  return `<header class="public-nav"><a class="brand" href="#home"><span class="brand-mark" aria-hidden="true">R</span>RepairTrack</a><nav>${links}</nav>${link('Staff login', 'login', 'button quiet')}</header><details class="mobile-nav"><summary>Navigation</summary><nav>${links}</nav></details>`;
}
function home() {
  return `${publicNav()}<main id="content" tabindex="-1"><section class="hero" id="home"><video muted autoplay loop playsinline poster="assets/video-poster.jpg"><source src="assets/login-bg.mp4" type="video/mp4"></video><div><p class="eyebrow">Computer repair, without the guesswork</p><h1>Your device.<br>Your updates.<br><span class="hero-accent">All in one place.</span></h1><p>Know what is happening, where your computer is, and what comes next.</p><div class="row section">${link('Request device pickup', 'pickup', 'button quiet')}${btn('Pause background', 'video', '', 'quiet')}</div></div><section class="card"><p class="eyebrow">Track a repair</p><h2>Let’s find your device</h2><form data-form="track" class="stack">${field('Tracking code', 'code', 'DEMO-1042')}<button class="primary">View my repair</button></form><div class="row section">${btn('Scan QR', 'scan')}${btn('Try sample QR', 'qr')}</div><small>Use DEMO-1042 for the main demonstration.</small></section></section><div class="container"><section id="about"><p class="eyebrow">The repair journey</p><h2>A clear next step, from check-in to return</h2><div class="grid three section">${[
    ['01', 'Check in', 'Bring your device or request a confirmed collection.'],
    ['02', 'Review the work', 'Discuss findings and approve itemized costs.'],
    ['03', 'Receive your device', 'Follow updates, then collect or arrange delivery.'],
  ]
    .map(
      ([n, t, p]) =>
        `<article class="card"><div class="kicker-number">${n}</div><h3>${t}</h3><p class="muted">${p}</p></article>`
    )
    .join(
      ''
    )}</div></section><section id="services" class="section"><p class="eyebrow">Services</p><h2>Repairs and upgrades that fit your device</h2><div class="grid">${[
    ['Diagnostics', 'Power, performance, display and overheating issues.'],
    ['Upgrades', 'Compatible memory and storage with approved catalog pricing.'],
  ]
    .map(([t, p]) => `<article class="card"><h3>${t}</h3><p class="muted">${p}</p></article>`)
    .join('')}</div></section><section id="faq" class="card section"><h2>Common questions</h2>${[
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
      ''
    )}</section><section id="contacts" class="section"><h2>Your nearby repair team</h2><div class="grid">${BRANCHES.map(branchMapCard).join('')}</div></section></div></main><footer class="footer"><strong>RepairTrack</strong><span>Demonstration only. All names, prices and records are samples.</span></footer>`;
}
function login() {
  return `${publicNav()}<main id="content" tabindex="-1" class="container narrow"><section class="card"><p class="eyebrow">One staff entry point</p><h1>Welcome back</h1><p class="muted">Preview a workspace. No real account or password is needed.</p><form data-form="login" class="stack">${field('Demo email', 'email', 'staff@example.test', 'email')}${select('Demo role', 'role', ['Admin', 'Technician', 'Cashier', 'Collector'], 'Admin')}<button class="primary">Open demo workspace</button><small>Production permissions come from the server. This selector is for presentation only.</small></form></section></main>`;
}
function pickup() {
  return `${publicNav()}<main id="content" tabindex="-1" class="container narrow">${header('Request device pickup', 'Your branch will confirm the location, schedule and collection fee.')}<form data-form="pickup" class="card form-grid">${field('Your name', 'name')}${field('Phone or email', 'contact')}${field('Device', 'device')}${select('Service area', 'area', ['Within service area', 'Nearby meeting point', 'Outside service area'])}<div class="wide">${area('What did you notice?', 'problem')}</div><div class="wide">${area('Address and landmark', 'address')}</div>${field('Preferred date', 'date', '', 'date')}${select('Preferred branch', 'branch', state.singleStore ? [BRANCHES[0]] : BRANCHES)}<div class="notice wide">Map and coverage are simulated. No live location is collected.</div><label class="check wide"><input type="checkbox" required>I agree that staff must confirm the fee and schedule.</label><div class="actions wide"><button class="primary">Send demo pickup request</button></div></form></main>`;
}
function workGroups() {
  const tickets = scope();
  const issued = tickets.filter((t) => t.issued);
  const deviceTasks = (matches, label) => issued.flatMap((t) =>
    t.devices.flatMap((d, index) => matches(d, t)
      ? [{ label, detail: t.id + ' · ' + d.name, ticket: t.id, index }]
      : []));
  const ticketTasks = (matches, label, destination) => tickets.filter(matches)
    .map((t) => ({ label, detail: t.id + ' · ' + t.customer, destination: destination(t) }));
  const pickupTasks = (status, label) => state.pickups
    .filter((p) => p.branch === state.branch && p.status === status)
    .map((p) => ({ label, detail: p.id + ' · ' + p.device, destination: 'logistics/job/' + encodeURIComponent(p.id) }));
  switch (role) {
    case 'Admin':
      return [
        ['Needs assignment', deviceTasks((d, t) => !t.assigned && d.status !== 'Returned', 'Assign technician')],
        ['Pending quotes', deviceTasks((d, t) => deviceQuote(t, t.devices.indexOf(d))?.status === 'Pending', 'Review quote status')],
      ];
    case 'Technician':
      return [
        ['Ready to work', deviceTasks((d) => ['Received', 'Diagnosing', 'Repairing', 'Testing'].includes(d.status), 'Open assigned device')],
        ['Waiting for approval or parts', deviceTasks((d) => ['Awaiting approval', 'Awaiting parts'].includes(d.status), 'Check blocker')],
      ];
    case 'Cashier':
      return [
        ['Unpaid bills', ticketTasks((t) => t.issued && t.bill && balance(t) > 0, 'Collect payment', (t) => 'finance/ticket/' + encodeURIComponent(t.id))],
        ['Ready for release', ticketTasks(canRelease, 'Release device', (t) => 'finance/ticket/' + encodeURIComponent(t.id))],
        ['Draft intakes', tickets.filter((t) => !t.issued).map((t) => ({ label: 'Review draft', detail: t.id + ' · ' + t.customer, ticket: t.id, index: 0 }))],
      ];
    case 'Collector':
      return [
        ['Ready to collect', pickupTasks('Confirmed', 'Open pickup')],
        ['Awaiting handoff', pickupTasks('Collected', 'Open handoff')],
        ['Awaiting confirmation', pickupTasks('Requested', 'Review pickup request')],
      ];
    default:
      return [];
  }
}

function workRow(task) {
  const action = task.ticket
    ? btn('Open device', 'open-ticket', `data-id="${esc(task.ticket)}" data-device="${task.index}"`)
    : link(task.label, task.destination);
  return `<article class="list-item"><div><strong>${esc(task.label)}</strong><p>${esc(task.detail)}</p></div>${action}</article>`;
}

function overview() {
  const groups = workGroups();
  const section = location.hash.split('/')[1];
  if (section && groups[Number(section.slice(1))] && /^g[0-9]+$/.test(section)) {
    const [label, tasks] = groups[Number(section.slice(1))];
    return header(label, esc(state.branch), link('Back to overview', 'overview')) +
      `<section class="list">${pageOf(tasks, 'overview/' + section, workRow, 'All caught up.')}</section>`;
  }
  const firstTasks = groups.flatMap(([, tasks]) => tasks.slice(0, 1));
  const remaining = groups.flatMap(([, tasks]) => tasks.slice(1));
  const preview = [...firstTasks, ...remaining].slice(0, 4);
  const updates = state.audit.filter(visibleEvent).slice(0, 5);
  return header(role + ' overview', esc(state.branch)) +
    `<div class="grid three">${groups.map(([label, tasks], index) =>
      `<a class="card" href="#overview/g${index}"><strong>${esc(label)}</strong><div class="metric">${tasks.length}</div></a>`).join('')}</div>
    <div class="grid section"><section class="card"><h2>Needs attention</h2><div class="list">${preview.map(workRow).join('') || empty('All caught up.')}</div></section>
    <section class="card"><h2>Recent activity</h2>${updates.length ? timeline(updates) : empty('No recent activity.')}${link('View activity history', 'audit')}</section></div>`;
}
function ticketCard(t) {
  return `<article class="list-item"><div><h3>${esc(t.id)} · ${esc(t.devices[0].name)}</h3><p>${esc(t.customer)} · ${esc(t.branch)}</p>${t.issued ? [...new Set(t.devices.map((device) => device.status))].map(badge).join(' ') : badge('Draft')} <small>${t.devices.length} device${t.devices.length > 1 ? 's' : ''}</small></div>${btn('Open', 'open-ticket', `data-id="${esc(t.id)}" aria-label="Open repair ${esc(t.id)}"`)}</article>`;
}
// Static lists use the route page. Search results keep their page beside the filter.
function pageOf(rows, route, draw, emptyMessage, requested) {
  const routeParts = location.hash.slice(1).split('/');
  const routePage = routeParts.pop();
  if (requested === undefined && routeParts.join('/') === route) requested = Number(routePage);
  const size = 10;
  const pages = Math.max(1, Math.ceil(rows.length / size));
  const page = Math.max(1, Math.min(Number.isInteger(requested) ? requested : 1, pages));
  const start = (page - 1) * size;
  if (!rows.length) return empty(emptyMessage);
  const control = (label, target) => ['repairs', 'customers', 'finance'].includes(route)
    ? btn(label, 'list-page', `data-page="${target}"`)
    : link(label, route + '/' + target);
  return rows.slice(start, start + size).map(draw).join('') +
    `<nav class="actions" aria-label="Results pages">
      ${page > 1 ? control('Previous', page - 1) : ''}
      <span role="status">Showing ${start + 1}–${Math.min(start + size, rows.length)} of ${rows.length} · Page ${page} of ${pages}</span>
      ${page < pages ? control('Next', page + 1) : ''}
    </nav>`;
}

function repairs() {
  return (
    header(
      role === 'Technician' ? 'My repair queue' : 'Repair workbench',
      'Each device keeps its own status, custody record and costs.',
      role !== 'Technician' ? link('New intake', 'intake', 'button primary') : ''
    ) +
    `<div class="row card section"><label style="flex:1">Find a repair<input id="repair-search" placeholder="Ticket, customer or device"></label><label>Status<select id="repair-filter"><option>All statuses</option><option>Draft</option>${REPAIR_STAGES.map((s) => `<option>${s}</option>`).join('')}</select></label></div><section class="list section" id="repair-list">${pageOf(scope(), 'repairs', ticketCard, 'No repairs in this branch.')}</section>`
  );
}
function timeline(events) {
  return `<ol class="timeline">${events.map((e) => `<li><small>${esc(displayTime(e.at))}</small><p>${esc(e.text)}</p></li>`).join('')}</ol>`;
}
function customerPicker(selected) {
  const options = state.customers.map(c =>
    `<option value="${esc(c.id)}" ${c.id === selected ? 'selected' : ''}>${esc(c.name)} · ${esc(c.contact)}</option>`);
  return `<label>Existing customer<select name="existing"><option value="">New customer</option>${options.join('')}</select></label>`;
}
function intake() {
  const d = intakeDraft;
  const devices = d.devices || [];
  const item = devices[intakeDevice] || {};
  const branches = state.singleStore || role !== 'Admin' ? [state.branch === 'All branches' ? BRANCHES[0] : state.branch] : BRANCHES;
  const receiving = branches.includes(d.branch) ? d.branch : (branches.includes(state.branch) ? state.branch : branches[0]);
  return (
    header(
      'New device intake',
      'Capture essentials first. Review with the customer before issuing a ticket.'
    ) +
    `<div class="narrow"><ol class="stepper">${['Customer', 'Device & condition', 'Review'].map((s, i) => `<li class="${i === intakeStep ? 'active' : ''}" ${i === intakeStep ? 'aria-current="step" tabindex="-1"' : ''}>${i + 1} ${s}</li>`).join('')}</ol><form data-form="intake" class="card form-grid">${
      intakeStep === 0
        ? `<div class="wide">${customerPicker(d.existing)}<small>Select a match to fill contact details.</small></div>${field('Customer name', 'customer', d.customer || '')}${field('Phone or email', 'contact', d.contact || '')}${branches.length === 1 ? `<div><strong>Receiving branch</strong><p>${esc(receiving)}</p><input type="hidden" name="branch" value="${esc(receiving)}"></div>` : select('Receiving branch', 'branch', branches, receiving)}${select('Arrival', 'arrival', ['Walk-in', 'Pickup', 'Mail'], d.arrival || 'Walk-in')}`
        : intakeStep === 1
          ? `<h2 class="wide">Device ${intakeDevice + 1}</h2>${field('Device brand and model', 'device', item.device || '')}${field('Serial number (optional)', 'serial', item.serial || '', 'text', false)}<div class="wide">${area('Reported problem', 'problem', item.problem || '')}</div><div class="wide">${area('Visible condition', 'condition', item.condition || '')}</div>${field('Accessories or “None”', 'accessories', item.accessories || '')}${field('Condition photo (demo filename, optional)', 'photo', item.photo || '', 'text', false)}<label class="check wide"><input type="checkbox" name="ack" ${item.ack ? 'checked' : ''}>Customer confirms condition and acknowledges the service terms.</label>`
          : `<div class="wide"><h2>Confirm these details</h2><dl class="summary">${[
              ['Customer', d.customer],
              ['Contact', d.contact],
              ['Branch', d.branch],
              ['Arrival', d.arrival],
            ]
              .map(([k, v]) => `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>`)
              .join(
                ''
              )}</dl></div><section class="wide" aria-label="Intake devices">
      <h2>${devices.length} device${devices.length === 1 ? '' : 's'} on this ticket</h2>
      ${devices.map((entry, index) => `<article class="list-item"><div><h3>${index + 1}. ${esc(entry.device)}</h3>
        <p>${entry.serial ? 'Serial: ' + esc(entry.serial) : 'Serial not recorded'}${entry.photo ? ' · Photo: ' + esc(entry.photo) : ''}</p><p>${esc(entry.problem)} · ${esc(entry.condition)}</p><small>Accessories: ${esc(entry.accessories)} · ${entry.ack ? 'Acknowledged' : 'Acknowledgment pending'}</small></div>
        ${btn('Edit device ' + (index + 1), 'intake-device', `data-index="${index}"`)}</article>`).join('')}
      <div class="actions">${btn('Add another device', 'intake-device', `data-index="${devices.length}"`)}</div></section><div class="notice wide">Save as draft first. Issuance adds tracking access after the checks pass. Photo files here are sample labels, not uploads or signatures.</div>`
    }<div class="actions wide">${intakeStep ? btn('Back', 'intake-back') : link('Cancel', 'repairs')}<button class="primary">${intakeStep === 2 ? 'Save intake draft' : 'Continue'}</button></div></form></div>`
  );
}
// Ticket screen: status, custody and payment are deliberately independent.
let repairBack = 'repairs';
function repairDetail() {
  const t = ticket(),
    d = t.devices[activeDevice] || t.devices[0];
  const stageActions = {
    Received: ['Record diagnosis', 'diagnose'],
    Diagnosing: ['Record diagnosis', 'diagnose'],
    Repairing: ['Update repair status', 'status'],
    Testing: ['Record test results', 'test'],
  };
  const next = t.issued && stageActions[d.status];
  return (
    header(
      t.id + ' · ' + esc(t.customer),
      t.issued
        ? 'Manage the device, publish progress and record the next action.'
        : 'Draft intake. Complete the issue checks before sharing tracking access.',
      link('Back to queue', repairBack, 'button quiet')
    ) +
    `${!t.issued ? `<div class="notice">Draft: customer tracking is unavailable. ${btn('Review & issue', 'issue')}</div>` : ''}<div class="row card"><label style="flex:1">Device<select id="device-picker">${t.devices.map((x, i) => `<option value="${i}" ${i === activeDevice ? 'selected' : ''}>${esc(x.itemCode || t.id + '-' + String(i + 1).padStart(2, '0'))} · ${esc(x.name)}</option>`).join('')}</select></label>${badge(d.status)}${next ? btn(next[0], next[1], '', 'primary') : ''}${btn('Add device', 'add-device')}${t.issued ? btn('Tracking receipt', 'receipt') : ''}</div><div class="grid section"><section class="card"><p class="eyebrow">Device overview</p><h2>${esc(d.name)}</h2><dl class="summary"><div><dt>Reported problem</dt><dd>${esc(d.problem)}</dd></div><div><dt>Confirmed location</dt><dd>${esc(t.location)} · ${esc(t.custody)}</dd></div><div><dt>Visible condition</dt><dd>${esc(d.condition ?? t.condition)}</dd></div><div><dt>Accessories</dt><dd>${esc(d.accessories ?? t.accessories)}</dd></div><div><dt>Assigned technician</dt><dd>${esc(t.assigned || 'Unassigned')}</dd></div><div><dt>Diagnosis</dt><dd>${esc(d.diagnosis || 'Not recorded')}</dd></div></dl><div class="actions">${role === 'Admin' ? btn('Assign technician', 'assign') : ''}${next?.[1] !== 'diagnose' ? btn('Record diagnosis', 'diagnose') : ''}${next?.[1] !== 'status' ? btn('Update status', 'status') : ''}</div></section><section class="card"><h2>Work and costs</h2><p class="muted">Choose catalog parts and services. A changed quote needs fresh approval.</p><div class="stack">${btn('Build quotation', 'quote')}${btn('Request parts', 'request-part')}${next?.[1] !== 'test' ? btn('Record test results', 'test') : ''}${btn('Publish customer update', 'publish')}</div><small>Ready for pickup requires passed tests. Cash collection remains a separate action.</small></section></div><div class="grid section"><section class="card"><h2>Quotations</h2>${quotesHTML(t, false)}</section><section class="card"><h2>Device history</h2>${timeline(t.events.filter((event) => event.public || visibleEvent(event)).slice(0, 7))}<div class="actions">${btn('Internal note', 'note')}${role !== 'Technician' ? btn('Transfer / delivery', 'transfer') : ''}</div>${t.notes.length && (role === 'Admin' || role === 'Technician' && t.assigned === 'Mika Reyes') ? `<details><summary>Internal notes (${t.notes.length})</summary>${t.notes.map((n) => `<p>${esc(n)}</p>`).join('')}</details>` : ''}</section></div><section class="card section"><h2>Customer requests</h2>${requestsHTML(t, false)}</section>`
  );
}
function quotesHTML(t, customer) {
  return t.quotes.length
    ? t.quotes
        .map(
          (q) =>
            `<article class="card section"><div class="row between"><h3>Quote v${q.version} · ${esc(t.devices[q.device || 0]?.name)}</h3>${badge(q.status)}</div><table><thead><tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>${q.items.map((i) => `<tr><td>${esc(i.name)}</td><td>${i.qty}</td><td>${money(i.price * i.qty)}</td></tr>`).join('')}</tbody></table><p class="amount section">${money(q.total)}</p>${customer && q.status === 'Pending' ? `<div class="row">${btn('Approve quote', 'approve-quote', `data-version="${q.version}"`, 'primary')}${btn('Decline', 'decline-quote', `data-version="${q.version}"`)}</div>` : ''}</article>`
        )
        .join('')
    : empty('No quotation yet. Diagnosis comes first.');
}
function requestsHTML(t, customer) {
  return t.requests.length
    ? t.requests
        .map(
          (r, i) =>
            `<article class="list-item section"><div><h3>${esc(r.type)}</h3><p>${esc(r.text)}</p>${badge(r.status)}</div>${!customer && r.status === 'Requested' ? btn('Review request', 'review-request', `data-index="${i}"`) : ''}</article>`
        )
        .join('')
    : empty('No change requests.');
}
function portal() {
  const t = ticket();
  const tab = location.hash.split('/')[1] || 'overview';
  const tabs = ['Overview', 'Location', 'Messages', 'Requests', 'Costs', 'Alerts'];
  if (!tabs.some(label => label.toLowerCase() === tab)) {
    location.replace('#portal/overview');
    return '';
  }
  let html = '';
  if (tab === 'overview') {
    const devices = t.devices.map((d, index) => {
      const pending = deviceQuote(t, index)?.status === 'Pending';
      const ready = d.status === 'Ready for pickup';
      const next = pending ? link('Review quote', 'portal/costs', 'button primary')
        : ready ? `<p>${t.bill && balance(t) > 0 ? 'Balance due before pickup.' : 'Contact the branch to arrange pickup.'}</p>`
        : `<p>${d.status === 'Returned' ? 'Returned to you.' : 'Your repair team will post the next update.'}</p>`;
      return `<article class="list-item"><div><h3>${esc(d.name)}</h3>${badge(d.status)}${next}</div></article>`;
    });
    html = `<div class="grid"><section class="card"><h2>Your devices</h2><div class="list">${devices.join('')}</div></section>
      <section class="card"><h2>Repair history</h2>${timeline(t.events.filter((event) => event.public))}</section></div>`;
  }
  if (tab === 'location')
    html = `<div class="grid"><section class="card"><p class="eyebrow">Last confirmed custody</p><h2>${esc(t.location)}</h2><p>${esc(t.custody)}</p>${BRANCHES.includes(t.location) ? branchMapCard(t.location) : '<p>Device is away from a branch. See the last confirmed handoff below.</p>'}</section><section class="card"><h2>Location updates</h2>${timeline(t.events.filter((e) => e.public && e.custody))}</section></div>`;
  if (['messages', 'requests', 'costs', 'alerts'].includes(tab) && !verified)
    html = `<section class="card narrow"><p class="eyebrow">Extra verification</p><h2>Confirm access to your repair</h2><p class="muted">Tracking shows basic progress. Messages, costs and decisions need a separate PIN.</p><form data-form="verify" class="stack">${field('Demo PIN', 'pin', '', 'password')}<button class="primary">Verify demo access</button><small>Demo PIN: 2468. This is simulated verification only.</small></form></section>`;
  else if (tab === 'messages') html = chatHTML(t, true);
  else if (tab === 'requests')
    html = `<section class="card"><h2>Ask for a change</h2><p class="muted">Staff reviews compatibility, completed work and disclosed charges before acting.</p><div class="row">${['Upgrade', 'Customization', 'Cancellation'].map((type) => btn(type, 'customer-request', `data-type="${type}"`)).join('')}</div>${requestsHTML(t, true)}</section>`;
  else if (tab === 'costs')
    html = `<div class="grid"><section class="card"><h2>Itemized quotations</h2>${quotesHTML(t, true)}</section><section class="card"><h2>Payment summary</h2><dl class="summary"><div><dt>Final bill</dt><dd>${t.bill ? money(t.bill.total) : 'Not issued yet'}</dd></div><div><dt>Cash received, net refunds</dt><dd>${money(paid(t))}</dd></div><div><dt>Balance</dt><dd>${t.bill ? money(balance(t)) : 'Pending final bill'}</dd></div><div><dt>Payment methods</dt><dd>Cash at counter or on delivery</dd></div></dl>${t.payments.map((p) => `<p class="section">Receipt ${esc(p.id)} · ${money(p.amount)} · ${esc(p.method)}</p>`).join('')}<p class="muted section">No online payment is available.</p></section></div>`;
  if (tab === 'alerts' && verified) html = ticketAlertPanel(t);
  return `${publicNav()}<main id="content" tabindex="-1" class="container">${header('Your repair, at a glance', `${esc(t.id)} · ${t.devices.length} device${t.devices.length > 1 ? 's' : ''} · ${esc(t.branch)}`, badge(verified ? 'Verified demo session' : 'Tracking access'))}<nav class="tabs" aria-label="Repair sections">${tabs.map((s) =>  `<a href="#portal/${s.toLowerCase()}" class="${tab === s.toLowerCase() ? 'active' : ''}" ${tab === s.toLowerCase() ? 'aria-current="page"' : ''}>${s}</a>`).join('')}</nav>${html}</main>`;
}
function chatHTML(t, customer) {
  return `<section class="card"><h2>${customer ? 'Your repair team' : 'Ticket conversation'} · ${esc(t.id)}</h2><div class="chat">${t.messages.map((m) => `<div class="bubble ${m.by === 'Customer' ? 'customer' : ''}"><small>${esc(m.by)}</small><p>${esc(m.text)}</p></div>`).join('') || empty('No messages yet.')}</div><form data-form="message" class="stack">${area('Message', 'message')}<button class="primary">Send demo message</button></form><small>Messages stay in this browser. No SMS or email is sent.</small></section>`;
}
function messages() {
  if (!['Admin', 'Technician'].includes(role)) return header('Messages', 'Conversations are available to the assigned repair team.');
  const tickets = scope();
  if (!tickets.length) return header('Messages', 'No assigned repairs in this branch.');
  if (!tickets.some((item) => item.id === activeTicket)) activeTicket = tickets[0].id;
  return (
    header('Messages', 'Conversations stay with the ticket when staff change.') +
    `<div class="row section">${select(
      'Ticket',
      'message-ticket',
      scope().map((t) => t.id),
      activeTicket
    )}</div><div class="section">${chatHTML(ticket(), false)}</div>`
  );
}
function customerCard(c) {
  return `<article class="list-item"><div><h3>${esc(c.name)}</h3><p>${esc(c.contact)}</p></div>${btn('Start intake', 'customer-intake', `data-customer="${esc(c.id)}"`)}</article>`;
}
function customers() {
  return (
    header('Customers', 'Search before creating a duplicate record.') +
    `<section class="card"><label>Search customers<input id="customer-filter" placeholder="Name or contact"></label><button type="button" id="clear-customer-search" hidden>Clear search</button><div id="customer-list" class="list section">${pageOf(state.customers, 'customers', customerCard, 'No customers yet.')}</div></section>`
  );
}
function team() {
  return (
    header(
      'Team and assignments',
      'Balance work within the selected branch.',
      btn('Add demo staff', 'add-staff', '', 'primary')
    ) +
    `<div class="grid three">${pageOf(state.staff
      .filter((s) => state.branch === 'All branches' || s.branch === state.branch)
      , 'team',
        (s) =>
          `<section class="card"><h2>${esc(s.name)}</h2><p class="muted">${esc(s.role)} · ${esc(s.branch)}</p>${badge(s.active ? 'Active' : 'Inactive')}<p class="metric">${state.tickets.filter((t) => t.issued && t.branch === s.branch && t.assigned === s.name && t.devices.some((d) => d.status !== 'Returned')).length}</p><small>Open assigned tickets</small><div class="actions">${btn(s.active ? 'Deactivate' : 'Activate', 'toggle-staff', `data-name="${esc(s.name)}"`)}</div></section>`
      , 'No staff in this branch.')}</div>`
  );
}
function inventory() {
  return (
    header(
      'Parts inventory',
      'Every receipt, use and transfer creates a movement record.',
      btn('Receive stock', 'receive-stock', '', 'primary')
    ) +
    `<div class="grid"><section class="card"><h2>Branch stock</h2><div class="list">${pageOf(state.stock
      .filter((s) => state.branch === 'All branches' || s.branch === state.branch)
      , 'inventory/stock',
        (s) =>
          `<article class="list-item"><div><h3>${esc(CATALOG.find((c) => c.id === s.id).name)}</h3><p>${esc(s.branch)} · ${money(CATALOG.find((c) => c.id === s.id).price)} catalog price</p>${badge(s.qty < 3 ? 'Low stock' : 'Available')}</div><strong class="metric">${s.qty}</strong></article>`
      , 'No stock in this branch.')}</div><div class="actions">${btn('Transfer parts', 'stock-transfer')}</div></section><section class="card"><h2>Parts requests</h2>${partsHTML()}<h2 class="section">Movement history</h2>${pageOf(state.stockLog.filter((event) => state.branch === 'All branches' || event.branches?.includes(state.branch)),
      'inventory/movements', (event) => timeline([event]), 'No stock movements in this view.')}</section></div>`
  );
}
function partsHTML() {
  const rows = scope().flatMap((t) => t.parts.map((p, i) => ({ t, p, i })));
  const route = location.hash.startsWith('#inventory') ? 'inventory/requests' : 'parts';
  return pageOf(rows, route,
    ({ t, p, i }) =>
            `<article class="list-item section"><div><h3>${esc(p.name)} × ${p.qty}</h3><p>${t.id} · Device ${(p.device || 0) + 1}</p>${badge(p.status)}</div>${p.status === 'Requested' && role === 'Admin' ? btn('Approve & reserve', 'approve-part', `data-id="${t.id}" data-index="${i}"`) : p.status === 'Reserved' ? btn('Record usage', 'consume-part', `data-id="${t.id}" data-index="${i}"`) : ''}</article>`, 'No parts requests.');
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
      link('Preview pickup form', 'pickup')
    ) +
    `<section class="list">${
      pageOf(state.pickups
        .filter((p) => state.branch === 'All branches' || p.branch === state.branch)
        .filter((p) => !location.hash.startsWith('#logistics/job/') ||
          location.hash === '#logistics/job/' + encodeURIComponent(p.id)), 'logistics',
          (p) =>
            `<article class="card"><div class="row between"><h2>${esc(p.id)} · ${esc(p.name)}</h2>${badge(p.status)}</div><p>${esc(p.device)} · ${esc(p.problem)}</p><p class="muted">${esc(p.address)} · ${esc(p.date)}<br>${esc(p.branch)} · Collector: ${esc(p.collector)}</p><small>Proposed pickup fee: ${money(p.fee)}. Separate approval required.</small><div class="actions">${p.status === 'Requested' ? btn('Review coverage & fee', 'pickup-confirm', `data-id="${p.id}"`) : p.status === 'Confirmed' ? btn('Record collection', 'pickup-collect', `data-id="${p.id}"`, 'primary') : p.status === 'Collected' ? btn('Receive at branch', 'pickup-receive', `data-id="${p.id}"`) : ''}${['Requested', 'Confirmed'].includes(p.status) ? btn('Failed / cancel', 'pickup-fail', `data-id="${p.id}"`) : ''}</div></article>`, 'No pickups in this branch.')
    }</section>`
  );
}
function transfers() {
  return (
    header('Device custody', 'Device transfers and parts movements use separate records.') +
    `<section class="list">${pageOf(state.transfers.map((transfer, index) => ({ transfer, index }))
      .filter(({ transfer }) => state.branch === 'All branches' ||
        [transfer.from, transfer.to].includes(state.branch)), 'transfers',
      ({ transfer: x, index: i }) => `<article class="card"><div class="row between"><h2>${x.ticket} · ${esc(x.type)}</h2>${badge(x.status)}</div><p>${esc(x.from)} to ${esc(x.to)}</p><p class="muted">${esc(x.evidence || 'Handoff evidence pending')}</p><div class="actions">${x.status === 'In transit' ? btn('Confirm handoff', 'receive-transfer', `data-index="${i}"`, 'primary') : ''}</div></article>`, 'No handoffs for this branch.')}</section>`
  );
}
function releaseBlocker(t) {
  if (!t.issued) return 'Issue the ticket before handover.';
  if (!t.bill || balance(t) > 0) return 'Settle the bill before release.';
  if (t.custody === 'In transit') return 'Confirm the transfer handoff before release.';
  const waiting = t.devices.filter((d) => !['Ready for pickup', 'Returned'].includes(d.status));
  if (waiting.length) return 'Waiting for ' + waiting.map((d) => d.name + ' (' + d.status + ')').join(', ') + '.';
  if (!t.devices.some((d) => d.status === 'Ready for pickup')) return 'All devices have already been returned.';
  return '';
}
function canRelease(t) {
  return !releaseBlocker(t);
}

function finance() {
  return (
    header(
      role === 'Collector' ? 'COD remittance' : 'Cash desk',
      'Approved costs, final bills and physical cash remain separate records.'
    ) +
    `<div class="grid three"><section class="card"><small>Final bills</small><div class="metric">${money(scope().reduce((n, t) => n + (t.bill?.total || 0), 0))}</div></section><section class="card"><small>Net cash received</small><div class="metric">${money(scope().reduce((n, t) => n + paid(t), 0))}</div></section><section class="card"><small>COD awaiting remittance</small><div class="metric">${money(
      scope()
        .flatMap((t) => t.payments)
        .filter((p) => p.method === 'COD' && !p.remitted)
        .reduce((n, p) => n + p.amount, 0)
    )}</div></section></div><section class="card section"><label>Find by ticket code<input type="search" id="finance-search"></label><div id="finance-list" class="list section"></div></section>`
  );
}
function financeRow(t) {
  const id = `data-id="${esc(t.id)}"`;
  let primary = '';
  const pendingRemittance = t.payments.some((p) => p.method === 'COD' && !p.remitted);
  if (role === 'Collector') primary = '<p>Awaiting cashier confirmation</p>';
  else if (pendingRemittance) primary = btn('Confirm COD remittance', 'remit', id, 'primary');
  else if (canRelease(t)) primary = btn('Release device', 'release', id, 'primary');
  else if (!t.bill) primary = btn('Generate bill', 'bill', id, 'primary');
  else if (balance(t) > 0) primary = btn('Collect cash', 'cash', id, 'primary');
  return `<article class="card"><div class="row between"><h2>${t.id} · ${esc(t.customer)}</h2>${badge(t.bill ? (balance(t) === 0 ? 'Paid' : 'Unpaid') : 'No final bill')}</div><p class="muted">Bill ${t.bill ? money(t.bill.total) : 'pending'} · Net collected ${money(paid(t))} · Balance ${t.bill ? money(balance(t)) : 'pending'}</p><p>${role !== 'Collector' ? esc(releaseBlocker(t)) : ''}</p><div class="actions">${primary}${pendingRemittance && role !== 'Collector' && canRelease(t) ? btn('Release device', 'release', id) : ''}${paid(t) > 0 ? btn('Receipt', 'cash-receipt', id) : ''}${paid(t) > 0 && role !== 'Collector' ? btn('Refund', 'refund', id) : ''}</div></article>`;
}

let financePage = 1;
function filterFinance(page = 1) {
  financePage = page;
  const code = $('#finance-search').value.trim().toLowerCase();
  const rows = scope().filter((t) => t.issued && t.id.toLowerCase().includes(code) &&
    (role !== 'Collector' || t.payments.some((p) => p.method === 'COD' && !p.remitted)));
  $('#finance-list').innerHTML = pageOf(rows, 'finance', financeRow, 'No matching tickets.', page);
}
function reports() {
  const devices = scope().flatMap((t) => t.devices);
  const cats = ['Cooling', 'Storage', 'Memory', 'Power', 'Software', 'Not recorded'];
  return (
    header('Operational trends', 'Sample data only. Diagnosis counts reflect technician entries.') +
    `<div class="row card"><strong>${esc(state.branch)}</strong><small>Diagnosis and upgrade figures follow the selected branch.</small></div><div class="grid section"><section class="card"><h2>Recorded causes</h2><p class="muted">Sample size: ${devices.length} devices</p><div class="bars">${cats
      .map((c) => {
        let n = devices.filter(
          (d) => d.category === c || (!d.category && c === 'Not recorded')
        ).length;
        return `<div class="bar-row"><span>${c}</span><div class="bar" style="width:${devices.length ? (n / devices.length) * 100 : 0}%"></div><strong>${n}</strong></div>`;
      })
      .join('')}</div></section><section class="card"><h2>Upgrade demand</h2><div class="metric">${
      scope()
        .flatMap((t) => t.requests)
        .filter((r) => r.type === 'Upgrade').length
    }</div><p class="muted">Customer upgrade requests</p><h2 class="section">Workload across all branches</h2>${BRANCHES.map((b) => `<p>${b}: ${state.tickets.filter((t) => t.branch === b).length} tickets</p>`).join('')}<small>Production reporting will add date ranges, turnaround time and repeat repair analysis.</small></section></div>`
  );
}
function settings() {
  return (
    header('Branches and settings', 'Change demo policies in one place.') +
    `<div class="grid"><section class="card"><h2>Store setup</h2><form data-form="settings" class="stack">${select('Business setup', 'setup', ['Multiple stores', 'Single store'], state.singleStore ? 'Single store' : 'Multiple stores')}<label class="check"><input type="checkbox" name="photos" ${state.settings.photosRequired ? 'checked' : ''}>Require a condition photo label before issuance</label><p class="muted">Inactive sessions expire after 60 minutes. Background rendering never resets the activity timer.</p><button class="primary">Save demo settings</button></form></section><section class="card"><h2>Branch directory</h2>${(state.singleStore ? [BRANCHES[0]] : BRANCHES).map((b) => `<h3>${b}</h3><p class="muted">Configured service area · Counter cash and COD</p>`).join('')}<p class="notice">Demo PINs and role switching demonstrate the workflow. Production requires server authentication, branch checks and protected tracking credentials.</p></section></div>`
  );
}
function feedRows(kind) {
  const rows = kind === 'alerts' ? inbox() : state.audit.filter(visibleEvent);
  const selected = feedSelection[kind];
  return rows.filter((event) => role !== 'Admin' || selected === 'All' ||
    (event.group || 'Administration') === selected);
}

function activitySection(kind) {
  const notifications = kind === 'alerts';
  const rows = feedRows(kind);
  const filter = role === 'Admin' ? select('Activity type', 'feed-group',
    ['All', ...feedGroups], feedSelection[kind]) : '';
  const mark = notifications && rows.some(isUnread)
    ? btn('Mark all matching as read', 'read-alerts') : '';
  const items = pageOf(rows, kind, (event) => `<article class="list-item"><div><small>${esc(event.group || 'Earlier activity')} · ${esc(displayTime(event.at || ''))}</small><p>${esc(event.text)}</p></div>${notifications ? badge(isUnread(event) ? 'New' : 'Read') : ''}</article>`,
    notifications ? 'No notifications in this view.' : 'No activity in this view.');
  return header(notifications ? 'Notifications' : 'Activity history',
    notifications ? 'Messages and handoffs for your workspace.' : 'Changes recorded in the selected branch.', mark) +
    `<section class="card">${filter}<div class="list section">${items}</div></section>`;
}

function alerts() {
  return activitySection('alerts');
}
function audit() {
  return activitySection('audit');
}
// Choose the page for the current URL hash and rebuild its visible content.
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
  if (!Object.hasOwn(pages, route)) {
    go(ROLE_HOME[role]);
    return;
  }
  if (route === 'portal' && (role !== 'Customer' || !state.tickets.some((t) => t.id === publicTicketId && t.issued))) {
    go('home');
    toast('Enter the tracking code from your receipt to open a repair.');
    return;
  }
  if (route === 'portal') activeTicket = publicTicketId;
  if (!publics.includes(route) && ['Public', 'Customer'].includes(role)) {
    // A direct URL must not select a staff role on the visitor's behalf.
    go('login');
    return;
  }
  const financeQuery = $('#finance-search')?.value;
  $('#app').innerHTML = publics.includes(route)
    ? (pages[route] || home)()
    : shell(route, (pages[route] || overview)());
  if (route === 'finance') {
    const [, target, code] = location.hash.split('/');
    $('#finance-search').value = financeQuery ?? (target === 'ticket' ? (code || '') : '');
    filterFinance(financeQuery === undefined ? 1 : financePage);
  }
  if (route === 'repairs' && repairReturn?.branch === state.branch) {
    $('#repair-search').value = repairReturn.query;
    $('#repair-filter').value = repairReturn.status;
    filterRepairs(repairReturn.page);
  }
  if (route === 'home' && matchMedia('(prefers-reduced-motion: reduce)').matches) {
    $('video')?.pause();
    const b = $('[data-action=video]');
    if (b) b.textContent = 'Play background';
  }
  document.title = 'RepairTrack · ' + (route.charAt(0).toUpperCase() + route.slice(1)) + ' demo';
}
// Form dialogs gather a decision before actions mutate sample state.
let context = {};
// Handle buttons through their data-action names so rebuilt pages keep working.
function action(name, el) {
  tick();
  const t = ticket(),
    d = t.devices[activeDevice] || t.devices[0];
  if (el?.dataset.id) {
    context.id = el.dataset.id;
  } else context.id = t.id;
  switch (name) {
    case 'list-page':
      if ($('#repair-search')) filterRepairs(Number(el.dataset.page));
      else if ($('#finance-search')) filterFinance(Number(el.dataset.page));
      else if ($('#customer-filter')) filterCustomers(Number(el.dataset.page));
      const results = document.querySelector('#repair-list, #customer-list, #finance-list');
      const range = results?.querySelector('[role="status"]');
      range?.setAttribute('tabindex', '-1');
      range?.focus();
      break;
    case 'close':
      closeModal();
      break;
    case 'wireframe':
      wireframe = !wireframe;
      renderBar();
      break;
    case 'logout':
      resetIntake();
      role = 'Public';
      verified = false;
      go('home');
      toast('Demo session signed out.');
      break;
    case 'guide':
      modal(
        'Your presentation route',
        `<ol class="stack"><li>Start on Home. Enter <strong>DEMO-1042</strong>.</li><li>Explore tracking. Verify Messages or Costs with PIN <strong>2468</strong>.</li><li>Switch to Technician. Open RT-1042, record diagnosis and build a catalog quote.</li><li>Switch to Customer. Approve the quote in Costs.</li><li>Switch back to Technician. Repair, record tests and mark ready.</li><li>Use Admin for parts approvals, branches, requests and transfers.</li><li>Use Cashier to generate a bill, record cash and release devices.</li><li>Open Pickup and Collector to demonstrate collection and COD.</li></ol><p class="notice">Each device in a multi-device ticket needs its own diagnosis, quote and test. Reset restores all samples.</p>`
      );
      break;
    case 'reset':
      formModal(
        'Reset the demo?',
        'reset',
        '<p>This clears only the sample data in this browser and restores the starting scenario.</p>',
        'Reset sample data'
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
      if ($('#repair-search')) repairReturn = {
        branch: state.branch, query: $('#repair-search').value,
        status: $('#repair-filter').value, page: repairPage,
      };
      repairBack = location.hash.slice(1) || 'repairs';
      activeTicket = el.dataset.id;
      activeDevice = Number(el.dataset.device || 0);
      go('repair');
      break;
    case 'customer-intake':
      {
        resetIntake();
        const c = state.customers.find((c) => c.id === el.dataset.customer);
        intakeDraft = { customer: c.name, contact: c.contact, existing: c.id };
        intakeStep = 0;
        go('intake');
      }
      break;
    case 'intake-device':
      intakeDevice = Number(el.dataset.index);
      intakeStep = 1;
      render();
      focusIntakeStep();
      break;
    case 'intake-back':
      intakeStep = intakeStep === 1 && intakeDraft.devices?.length ? 2 : 0;
      render();
      focusIntakeStep();
      break;
    case 'issue':
      formModal(
        'Review and issue ticket',
        'issue',
        `<p>${esc(t.id)} · ${t.devices.length} device(s)</p><p class="muted">Condition: ${esc(t.condition)}<br>Accessories: ${esc(t.accessories)}</p>${field('Condition photo label (optional by default)', 'photo', t.photo || '', 'text', state.settings.photosRequired)}<label class="check"><input name="ack" type="checkbox" required ${t.ack ? 'checked' : ''}>Customer confirms condition and service terms</label><p class="notice">This demo acknowledgment is not a legal signature. Production must meet the applied schema and approved policy.</p>`,
        'Issue demo tracking code'
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
        'Add device'
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
          t.assigned
        ),
        'Assign'
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
            d.category
          ) +
          area('Internal note (optional)', 'note', '', false),
        'Save findings'
      );
      break;
    case 'status':
      formModal(
        'Update repair stage',
        'status',
        select('Next stage', 'status', REPAIR_STAGES, d.status) +
          area('Reason / progress note', 'note') +
          '<small>Approval, parts and testing gates apply to this device.</small>',
        'Update status'
      );
      break;
    case 'quote':
      if (!t.issued || !d.diagnosis) {
        toast('Issue the ticket and record diagnosis before preparing a quote.');
        break;
      }
      if (t.bill) {
        toast('The final bill is already issued. New scope needs a new intake in this demo.');
        break;
      }
      formModal(
        'Build an itemized quote',
        'quote',
        `<p>${esc(d.name)} · Catalog prices are fixed in this demo.</p>${CATALOG.map((c) => `<label class="check"><input type="checkbox" name="item" value="${c.id}"><span>${esc(c.name)}<br><small>${money(c.price)} × 1</small></span></label>`).join('')}<p class="notice">Publishing a revision requires customer approval before the changed work.</p>`,
        'Send quote for approval'
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
        'Confirm decision'
      );
      break;
    case 'request-part':
      formModal(
        'Request a compatible part',
        'request-part',
        select(
          'Catalog part',
          'part',
          CATALOG.filter((c) => c.type === 'Part').map((c) => c.name)
        ) +
          field('Quantity', 'qty', '1', 'number') +
          area('Compatibility / reason', 'reason'),
        'Request part'
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
            branches: [target.branch],
            text: `Reserved ${p.qty} ${p.name} for ${target.id}`,
          });
        } else {
          const q = deviceQuote(target, p.device || 0);
          if (q?.status !== 'Approved') {
            toast('Approve the device quotation before consuming parts.');
            break;
          }
          p.status = 'Consumed';
          state.stockLog.unshift({ at: now(), branches: [target.branch], text: `Used ${p.qty} ${p.name} for ${target.id}` });
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
        'Save test result'
      );
      break;
    case 'publish':
      formModal(
        'Customer progress update',
        'publish',
        area('Customer-facing update', 'text') +
          '<small>Use clear language. Internal notes belong in the separate staff field.</small>',
        'Publish demo update'
      );
      break;
    case 'note':
      formModal('Internal staff note', 'note', area('Private note', 'text'), 'Save internal note');
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
        'Send request'
      );
      break;
    case 'review-request':
      context.index = +el.dataset.index;
      formModal(
        'Review customer request',
        'review-request',
        `<p>${esc(t.requests[context.index].text)}</p>${select('Decision', 'decision', ['Accepted for review', 'Declined', 'Cancellation approved'])}${area('Explanation and disclosed charges', 'explanation')}`,
        'Record decision'
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
            BRANCHES.filter((b) => b !== t.branch)
          ) +
          area('Courier and handoff evidence', 'evidence'),
        'Record dispatch'
      );
      break;
    case 'receive-transfer':
      context.index = +el.dataset.index;
      formModal(
        'Confirm physical handoff',
        'receive-transfer',
        area('Receiver and handoff evidence', 'evidence') +
          '<small>Customer delivery requires a settled balance. Use Cash desk to record COD first.</small>',
        'Confirm receipt'
      );
      break;
    case 'add-staff':
      formModal(
        'Add sample staff',
        'add-staff',
        field('Name', 'name') +
          select('Role', 'role', ['Technician', 'Cashier', 'Collector']) +
          select('Branch', 'branch', BRANCHES),
        'Add staff'
      );
      break;
    case 'toggle-staff':
      {
        const s = state.staff.find((s) => s.name === el.dataset.name);
        s.active = !s.active;
        record(`${s.name} ${s.active ? 'activated' : 'deactivated'}`, null, false, 'Administration', s.branch);
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
          CATALOG.filter((c) => c.type === 'Part').map((c) => c.name)
        ) +
          select(
            name === 'receive-stock' ? 'Receiving branch' : 'From branch',
            'branch',
            BRANCHES
          ) +
          field('Quantity', 'qty', '1', 'number') +
          (name === 'stock-transfer' ? select('To branch', 'to', BRANCHES, BRANCHES[1]) : '') +
          area('Reference / reason', 'reason'),
        'Record stock movement'
      );
      break;
    case 'pickup-confirm':
      {
        const p = state.pickups.find((p) => p.id === context.id);
        formModal(
          'Confirm pickup coverage',
          'pickup-confirm',
          `<p>${esc(p.address)}</p>${field('Collector', 'collector', 'Taylor')}${field('Agreed collection fee', 'fee', p.fee, 'number')}<label class="check"><input type="checkbox" name="confirmed" required>Customer accepts the fee, schedule and accessible meeting point</label>`,
          'Confirm collection'
        );
      }
      break;
    case 'pickup-collect':
      formModal(
        'Collect device',
        'pickup-collect',
        area('Device condition and accessories', 'condition') +
          field('Customer / collector handoff reference', 'evidence'),
        'Record collection'
      );
      break;
    case 'pickup-receive':
      {
        const p = state.pickups.find((p) => p.id === context.id);
        resetIntake();
        intakeDraft = {
          customer: p.name,
          contact: p.contact,
          devices: [{device: p.device, problem: p.problem, condition: p.condition || '', accessories: ''}],
          branch: p.branch,
          arrival: 'Pickup',
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
        'Save exception'
      );
      break;
    case 'bill':
      {
        const target = state.tickets.find((t) => t.id === context.id);
        if (
          !target.devices.every((d) =>
            ['Ready for pickup', 'Returned', 'Cancelled'].includes(d.status)
          )
        ) {
          toast(
            'All devices must finish testing or complete an approved cancellation review before billing.'
          );
          break;
        }
        const total = approvedTotal(target) + (target.pickupFee || 0);
        if (!total && !target.devices.every((d) => d.returnAsIs)) {
          toast('No approved charges. Review quotes before generating a bill.');
          break;
        }
        target.bill = { total };
        record(`${target.id}: final bill ${money(total)}`, target, true, 'Cash desk');
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
          'Issue cash receipt'
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
        'Record physical refund'
      );
      break;
    case 'release': {
      const target = state.tickets.find((item) => item.id === context.id) || t;
      const blocker = releaseBlocker(target);
      if (blocker) {
        toast(blocker);
        break;
      }
      formModal(
        'Return devices to customer',
        'release',
        area('Receiver and handover evidence', 'evidence'),
        'Confirm device return'
      );
      break;
    }
    case 'remit':
      formModal(
        'Confirm COD remittance',
        'remit',
        area('Cashier and remittance reference', 'evidence'),
        'Confirm cash handoff'
      );
      break;
    case 'read-alerts':
      feedRows('alerts').filter(isUnread).forEach((event) => {
        event.readBy = [...(event.readBy || []), demoAccounts[role]];
      });
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
    const q = [...t.quotes].reverse().find((q) => (q.device || 0) === i && q.status === 'Approved');
    return sum + (q?.total || 0);
  }, 0);
}
// Keep the entered intake values before moving to another step.
function rememberIntake() {
  const f = $('[data-form=intake]');
  if (!f) return;
  const values = Object.fromEntries(new FormData(f));
  if (intakeStep === 1) {
    intakeDraft.devices ||= [];
    intakeDraft.devices[intakeDevice] = { ...values, ack: f.elements.ack.checked };
  } else if (intakeStep === 0) {
    intakeDraft = { ...intakeDraft, ...values };
  }
}
function requireGate(ok, message) {
  if (!ok) throw Error(message);
}
// Read a submitted form and apply the matching demo workflow update.
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
          (t) => t.issued && t.code.toUpperCase() === v.code.trim().toUpperCase()
        );
        requireGate(found, 'Code not found. Try DEMO-1042 or check the receipt.');
        publicTicketId = activeTicket = found.id;
        activeDevice = 0;
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
        assignCustomerIds();
        saveDemo();
        role = 'Public';
        verified = false;
        activeTicket = 'RT-1042';
        activeDevice = 0;
        resetIntake();
        closeModal();
        go('home');
        render();
        toast('Sample data restored.');
        return;
      case 'pickup': {
        // Compute the date at the branch even when the visitor is abroad.
        const today = new Intl.DateTimeFormat('en-CA', {
          timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'
        }).format(new Date());
        for (const name of ['name', 'device', 'address', 'problem', 'contact', 'date']) {
          v[name] = v[name].trim();
          let error = v[name] ? '' : 'Enter a value for this field.';
          if (name === 'date' && v.date < today) error = 'Choose today or a future pickup date.';
          if (name === 'contact' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.contact)
            && !/^\+?[\d() .-]+$/.test(v.contact)) error = 'Enter a phone number or email address.';
          if (name === 'contact' && !v.contact.includes('@') && !/^\d{7,15}$/.test(v.contact.replace(/\D/g, '')))
            error = 'Enter a phone number with 7 to 15 digits or an email address.';
          if (error) {
            form.elements[name].setCustomValidity(error);
            form.elements[name].reportValidity();
            return;
          }
        }
        const id = 'PU-' + (101 + state.pickups.length);
        state.pickups.push({ ...v, id, status: 'Requested', collector: 'Unassigned', fee: 150 });
        record(`${id}: pickup request awaiting staff confirmation`, null, false, 'Collections', v.branch);
        modal(
          'Pickup request received',
          `<h2>${id}</h2><p>Your request awaits coverage, fee and schedule confirmation. The device is not yet in branch custody.</p>${link('Return home', 'home')}`
        );
        form.reset();
        return;
      }
      case 'intake':
        rememberIntake();
        if (intakeStep < 2) {
          intakeStep++;
          render();
          focusIntakeStep();
          return;
        }
        {
          const n = intakeDraft;
          const first = n.devices[0];
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
            condition: first.condition,
            accessories: first.accessories,
            photo: first.photo,
            ack: first.ack,
            location: n.branch,
            custody: 'At branch',
            assigned: '',
            devices: n.devices.map((device, index) => ({
              itemCode: id + '-' + String(index + 1).padStart(2, '0'),
              name: device.device,
              serial: device.serial,
              problem: device.problem,
              condition: device.condition,
              accessories: device.accessories,
              photo: device.photo,
              ack: device.ack,
              status: 'Received',
              diagnosis: '',
              category: 'Not recorded',
              tested: false,
            })),
            quotes: [],
            payments: [],
            refunds: [],
            messages: [],
            requests: [],
            events: [],
            parts: [],
            notes: [],
            bill: null,
            pickupFee: n.pickupId ? state.pickups.find((p) => p.id === n.pickupId)?.fee || 0 : 0,
          };
          state.tickets.push(item);
          if (!state.customers.some((c) => c.name === n.customer && c.contact === n.contact))
            state.customers.push({ id: nextCustomerId(), name: n.customer, contact: n.contact });
          if (n.pickupId) {
            state.pickups.find((p) => p.id === n.pickupId).status = 'Received at branch';
          }
          record(`${id}: intake draft saved`, item);
          activeTicket = id;
          activeDevice = 0;
          intakeDraft = {};
          intakeDevice = 0;
          intakeStep = 0;
          go('repair');
          toast('Draft saved. Review and issue when the checks are complete.');
          return;
        }
      case 'issue':
        requireGate(t.condition && t.accessories, 'Record condition and accessories first.');
        requireGate(v.ack === 'on', 'Customer acknowledgment is required.');
        requireGate(
          !state.settings.photosRequired || v.photo.trim(),
          'A photo label is required by this demo policy.'
        );
        t.ack = true;
        t.photo = v.photo;
        t.issued = true;
        record(`${t.id}: devices received and tracking issued at ${t.branch}`, t, true, 'Custody');
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
          'Use the cash desk and physical handover to return a device.'
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
          'Choose the next valid stage. Use diagnosis, quotation and testing actions to complete required steps.'
        );
        if (['Repairing', 'Awaiting parts', 'Testing'].includes(target))
          requireGate(
            deviceQuote(t, activeDevice)?.status === 'Approved',
            'This device needs an approved current quotation.'
          );
        if (target === 'Repairing')
          requireGate(
            !t.parts.some((p) => (p.device || 0) === activeDevice && p.status === 'Requested'),
            'Resolve the requested parts first.'
          );
        if (target === 'Ready for pickup')
          requireGate(
            d.tested,
            'Record passed tests first, or use the approved cancellation/decline return process.'
          );
        d.status = target;
        queueTicketAlert(t, 'Repair stage updated to ' + target + '.');
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
        queueTicketAlert(t, 'Your quotation is ready for review.');
        record(`${d.name}: quotation ready for customer approval`, t, true);
        break;
      }
      case 'approve-quote':
      case 'decline-quote': {
        requireGate(verified && role === 'Customer', 'Verify customer access before deciding.');
        const q = t.quotes.find((q) => q.version === context.version);
        requireGate(q?.status === 'Pending', 'This quotation already has a decision.');
        q.status = kind === 'approve-quote' ? 'Approved' : 'Declined';
        const dev = t.devices[q.device || 0];
        if (q.status === 'Approved')
          dev.status = t.parts.some(
            (p) => (p.device || 0) === (q.device || 0) && p.status === 'Requested'
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
          'Only test a device after approved repair work.'
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
        record(`${t.id}: internal note added`, t, false, 'Conversations');
        break;
      case 'message': {
        const target = ticket();
        requireGate(role !== 'Customer' || verified, 'Verify customer access first.');
        target.messages.push({
          by: role === 'Customer' ? 'Customer' : 'Repair team',
          text: v.message,
        });
        record(`${target.id}: new message`, target, false, 'Conversations');
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
            'Use cancellation approval only for cancellation or declined-quote return.'
          );
          requireGate(!t.bill, 'Review billed charges at the cash desk before cancellation.');
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
            'All devices must be ready before dispatch.'
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
        record(`${t.id}: dispatched from ${t.location}; in transit`, t, true, 'Collections');
        break;
      case 'receive-transfer': {
        const x = state.transfers[context.index],
          target = state.tickets.find((t) => t.id === x.ticket);
        if (x.type === 'Customer delivery') {
          requireGate(
            target.bill && balance(target) === 0,
            'Settle the bill with counter cash or COD before confirming delivery.'
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
        record(`${target.id}: handoff confirmed at ${x.to}`, target, true, 'Collections');
        break;
      }
      case 'add-staff':
        requireGate(
          !state.staff.some((s) => s.name === v.name),
          'That sample staff name already exists.'
        );
        state.staff.push({ ...v, active: true });
        record(`Staff added: ${v.name}`, null, false, 'Administration', v.branch);
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
        state.stockLog.unshift({ at: now(), text, branches: [v.branch, v.to].filter(Boolean) });
        record(text, null, false, 'Administration', v.branch);
        break;
      }
      case 'pickup-confirm': {
        const p = state.pickups.find((p) => p.id === context.id);
        requireGate(v.confirmed === 'on', 'Confirm the customer agreement.');
        p.status = 'Confirmed';
        p.collector = v.collector;
        p.fee = Number(v.fee);
        record(`${p.id}: coverage, fee and schedule confirmed`, null, false, 'Collections', p.branch);
        break;
      }
      case 'pickup-collect': {
        const p = state.pickups.find((p) => p.id === context.id);
        p.status = 'Collected';
        p.condition = v.condition;
        p.evidence = v.evidence;
        record(`${p.id}: collector received the device`, null, false, 'Collections', p.branch);
        break;
      }
      case 'pickup-fail': {
        const p = state.pickups.find((p) => p.id === context.id);
        p.status = v.status;
        p.reason = v.reason;
        record(`${p.id}: ${v.status}. ${v.reason}`, null, false, 'Collections', p.branch);
        break;
      }
      case 'cash': {
        const amount = Number(v.amount);
        requireGate(
          t.bill && amount > 0 && amount <= balance(t),
          'Enter an amount above zero and no greater than the balance.'
        );
        t.payments.push({
          id: 'RC-' + (state.audit.length + 100),
          amount,
          method: v.method,
          remitted: v.method !== 'COD',
          at: now(),
        });
        record(`${t.id}: ${money(amount)} cash received by ${v.method}`, t, true, 'Cash desk');
        break;
      }
      case 'refund': {
        const amount = Number(v.amount);
        requireGate(amount > 0 && amount <= paid(t), 'Refund must not exceed net cash received.');
        t.refunds.push({ amount, reason: v.reason, at: now() });
        record(`${t.id}: physical refund ${money(amount)}. ${v.reason}`, t, true, 'Cash desk');
        break;
      }
      case 'release':
        requireGate(canRelease(t), releaseBlocker(t));
        t.devices.forEach((d) => (d.status = 'Returned'));
        t.location = 'Customer';
        t.custody = 'Returned';
        record(`${t.id}: devices returned. ${v.evidence}`, t, true, 'Cash desk', t.branch, true);
        break;
      case 'remit':
        requireGate(
          role === 'Cashier' || role === 'Admin',
          'A cashier or administrator confirms cash remittance.'
        );
        t.payments.filter((p) => p.method === 'COD').forEach((p) => (p.remitted = true));
        record(`${t.id}: COD remittance confirmed. ${v.evidence}`, t, false, 'Cash desk');
        break;
      case 'settings':
        state.singleStore = v.setup === 'Single store';
        state.settings.photosRequired = v.photos === 'on';
        if (state.singleStore) state.branch = BRANCHES[0];
        record('Demo branch settings updated', null, false, 'Administration', state.branch);
        break;
    }
    closeModal();
    const saved = saveDemo();
    render();
    toast(saved ? 'Demo update saved in this browser.' : 'Updated for this tab. Browser storage is unavailable.');
  } catch (error) {
    toast(error.message);
  }
}
function showReceipt(t) {
  const url = new URL(location.href);
  url.hash = 'track/' + encodeURIComponent(t.code);
  modal(
    'Tracking receipt',
    `<p class="eyebrow">${esc(t.id)}</p><h2>${esc(t.customer)}</h2><p>${t.devices.map((d) => esc(d.name)).join('<br>')}</p><p class="amount">${esc(t.code)}</p><div id="qr-output" class="qr"></div><p class="muted section">Scan to open this demo repair. Separate demo PIN: 2468.</p><div class="actions">${btn('Print receipt', 'print')}${link('Open tracking', 'track/' + t.code, 'button primary')}</div>`
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
    `<p class="eyebrow">Demo document</p><h2>${esc(t.id)} · ${esc(t.customer)}</h2><p>Final bill: ${money(t.bill?.total || 0)}</p>${t.payments.map((p) => `<p>${esc(p.id)}<br>${esc(displayTime(p.at))} · ${esc(p.method)}<br><strong>${money(p.amount)}</strong></p>`).join('')}<p>Refunds: ${money(t.refunds.reduce((n, r) => n + r.amount, 0))}</p><p class="amount">Balance ${money(balance(t))}</p><small>This is a sample receipt, not a tax invoice.</small><div class="actions">${btn('Print', 'print')}</div>`
  );
}
let cameraStream = null,
  scanTimer = null;
async function scanQR() {
  if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
    modal(
      'QR entry',
      `<p>Your browser does not support camera scanning here. Open a receipt QR link with your phone camera, or type the code.</p><form data-form="track" class="stack">${field('Tracking code', 'code', 'DEMO-1042')}<button class="primary">Track repair</button></form>`
    );
    return;
  }
  modal(
    'Scan tracking QR',
    '<video id="camera" autoplay playsinline style="width:100%"></video><p>Point your camera at a RepairTrack demo receipt.</p>'
  );
  const preview = $('#camera');
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment' },
    });
    if (!$('#modal').open || $('#camera') !== preview) {
      stream.getTracks().forEach((track) => track.stop());
      return;
    }
    cameraStream = stream;
    preview.srcObject = stream;
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
    if ($('#camera') !== preview || !$('#modal').open) return;
    toast('Camera unavailable. Use manual tracking code entry.');
    closeModal();
  }
}
$('#modal').addEventListener('close', () => {
  cameraStream?.getTracks().forEach((t) => t.stop());
  cameraStream = null;
  clearInterval(scanTimer);
});
// Listen on the document because page buttons are replaced during rendering.
document.addEventListener('click', (e) => {
  const routeLink = e.target.closest('a[href^="#"]');
  if (routeLink?.closest('dialog')) closeModal();
  if (routeLink?.hash === '#intake') {
    resetIntake();
    if (location.hash === '#intake') render();
  }
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
          behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
        }),
      30
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
    const c = state.customers.find(c => c.id === e.target.value);
    e.target.form.elements.customer.value = c?.name || '';
    e.target.form.elements.contact.value = c?.contact || '';
  }
  if (e.target.name === 'feed-group') {
    const kind = location.hash.split('/')[0] === '#alerts' ? 'alerts' : 'audit';
    feedSelection[kind] = e.target.value;
    render();
    document.querySelector('[name="feed-group"]')?.focus();
  }
  if (e.target.id === 'repair-filter') filterRepairs();
});
function filterCustomers(page = 1) {
  const query = $("#customer-filter").value.trim().toLowerCase();
  const matches = state.customers.filter((customer) =>
    [customer.name, customer.contact].join(' ').toLowerCase().includes(query));
  $("#customer-list").innerHTML = pageOf(matches, 'customers', customerCard, 'No customers match your search.', page);
  $("#clear-customer-search").hidden = !query;
}

// The app uses hashes for routes. Skip within this page without starting a route.
document.querySelector(".skip").addEventListener("click", (event) => {
  event.preventDefault();
  document.getElementById("content")?.focus();
});

document.addEventListener("click", (event) => {
  if (event.target.closest("#clear-customer-search")) {
    const search = $("#customer-filter");
    search.value = "";
    filterCustomers();
    search.focus();
  }
});

let repairReturn = null;
let repairPage = 1;
function filterRepairs(page = 1) {
  repairPage = page;
  const q = ($('#repair-search')?.value || '').trim().toLowerCase(),
    status = $('#repair-filter')?.value || 'All statuses';
  const matches = scope()
      .filter(
        (t) =>
          JSON.stringify([t.id, t.customer, t.devices.map((d) => d.name)])
            .toLowerCase()
            .includes(q) &&
          (status === 'All statuses' ||
            (status === 'Draft' && !t.issued) ||
            (t.issued && t.devices.some((d) => d.status === status)))
      );
  $('#repair-list').innerHTML = pageOf(matches, 'repairs', ticketCard, 'No matching repairs.', page);

}
document.addEventListener('input', (e) => {
  if (e.target.form?.dataset.form === 'pickup') e.target.setCustomValidity('');
  if (e.target.id === 'repair-search') filterRepairs();
  if (e.target.id === 'finance-search') filterFinance();
  if (e.target.id === "customer-filter") filterCustomers();
});
// Read the URL hash whenever the user opens a page or uses browser navigation.
function handleRoute(event) {
  if (event?.oldURL && new URL(event.oldURL).hash === '#intake') resetIntake();
  if ($('#modal').open) closeModal();
  const route = location.hash.slice(1);
  if (route.startsWith('track/')) {
    let code;
    try {
      code = decodeURIComponent(route.slice(6));
    } catch {
      go('home');
      toast('Tracking link unavailable. Contact the branch.');
      return;
    }
    const t = state.tickets.find((t) => t.issued && t.code === code);
    if (t) {
      publicTicketId = activeTicket = t.id;
      activeDevice = 0;
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
  if (event) $('#content')?.focus({preventScroll: true});
}
window.addEventListener('hashchange', handleRoute);
['pointerdown', 'keydown', 'touchstart'].forEach((type) =>
  document.addEventListener(type, tick, { passive: true })
);
setInterval(() => {
  if (role !== 'Public' && Date.now() - lastActivity >= state.settings.idleMinutes * 60000) {
    resetIntake();
    closeModal();
    role = 'Public';
    verified = false;
    go('home');
    toast('The demo session expired after 60 minutes of inactivity. Saved sample records remain.');
  }
}, 30000);
handleRoute();
