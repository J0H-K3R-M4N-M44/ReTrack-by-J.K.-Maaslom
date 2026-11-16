// A shared link keeps the secret outside server access logs.
// Fill the form, then let the customer choose when to open the repair.
(() => {
  const code = new URLSearchParams(location.hash.slice(1)).get('code');
  if (!code) return;
  history.replaceState(null, '', location.pathname);
  const input = document.querySelector('#tracking-code');
  if (input && /^(?:[a-f0-9]{5}|[a-f0-9]{64})$/i.test(code)) {
    input.value = code;
    input.focus();
  }
})();
