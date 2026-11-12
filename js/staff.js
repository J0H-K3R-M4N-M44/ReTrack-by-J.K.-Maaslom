// Forms and review work without JavaScript. These are optional conveniences.
const summary = document.querySelector('[data-error-summary]');
if (summary) summary.focus();
const copyButton = document.querySelector('[data-copy-link]');
copyButton?.addEventListener('click', async () => {
  const input = document.querySelector('#activation-link');
  const status = document.querySelector('[data-copy-status]');
  try {
    await navigator.clipboard.writeText(new URL(input.value, location.href).href);
    status.textContent = 'Link copied. Share it privately with this staff member.';
  } catch {
    input.focus();
    input.select();
    status.textContent = 'Link selected. Use Copy on your keyboard or device.';
  }
});
