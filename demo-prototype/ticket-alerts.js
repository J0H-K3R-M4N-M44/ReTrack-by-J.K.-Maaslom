// Enable only when serving this demo on localhost with the optional PHP mail relay.
const USE_LOCAL_EMAIL_RELAY = false;

function ticketAlertPanel(t) {
  const channel = t.alertChannel || 'Off';
  const options = ['Off', 'Email', 'SMS']
    .map((value) => '<option' + (value === channel ? ' selected' : '') + '>' + value + '</option>')
    .join('');
  const history = (t.deliveryAlerts || [])
    .map(
      (alert) =>
        '<li>' +
        esc(alert.channel) +
        ' · ' +
        esc(alert.status) +
        ' · ' +
        esc(new Date(alert.at).toLocaleString()) +
        '<br>' +
        esc(alert.message) +
        '</li>'
    )
    .join('');
  return (
    '<section class="card"><h2>Repair alerts</h2><p>Choose updates for this repair. SMS runs as a preview. Email uses a preview unless the presenter enables the local mail relay.</p><label>Alert channel <select id="ticket-alert-channel">' +
    options +
    '</select></label><ul>' +
    (history || '<li>No alerts for this repair yet.</li>') +
    '</ul></section>'
  );
}

document.addEventListener('change', (event) => {
  if (event.target.id !== 'ticket-alert-channel' || role !== 'Customer' || !verified) return;
  if (!['Off', 'Email', 'SMS'].includes(event.target.value)) return;
  ticket().alertChannel = event.target.value;
  saveDemo();
});

// Always keep a preview; send email only when the optional local relay is enabled.
function queueTicketAlert(t, summary) {
  if (!['SMS', 'Email'].includes(t.alertChannel)) return;
  // Summaries come from the workflow, never from staff notes or chat text.
  const alert = {
    channel: t.alertChannel,
    message: t.id + ': ' + summary,
    at: new Date().toISOString(),
    status: 'Demo preview',
  };
  t.deliveryAlerts = [alert, ...(t.deliveryAlerts || [])].slice(0, 30);
  if (alert.channel !== 'Email' || !USE_LOCAL_EMAIL_RELAY) return;
  alert.status = 'Sending';
  fetch('demo-email.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ticket: t.id, summary }),
  })
    .then((response) => {
      if (!response.ok) throw Error();
      alert.status = 'Accepted by mail server';
    })
    .catch(() => {
      alert.status = 'Email unavailable';
    })
    .finally(() => {
      saveDemo();
      if (role === 'Customer' && verified && location.hash === '#portal/alerts') render();
    });
}
