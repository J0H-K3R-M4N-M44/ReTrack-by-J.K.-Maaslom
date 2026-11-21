// A private link fills the form without submitting it or retaining the code in the URL.
(() => {
  const form = document.querySelector('#tracking-form');
  if (!form) return;

  const input = form.elements.tracking_code;
  const pin = form.elements.portal_pin;
  const code = new URLSearchParams(location.hash.slice(1)).get('code');
  if (code) {
    history.replaceState(null, '', location.pathname);
    if (/^(?:[a-f0-9]{5}|[a-f0-9]{64})$/i.test(code)) {
      input.value = code;
      input.focus();
    }
  }

  function updateRequirements() {
    pin.required = /^[a-f0-9]{5}$/i.test(input.value);
    input.setCustomValidity(input.validity.patternMismatch
      ? 'Enter your five-character tracking code using 0–9 and A–F, or your original 64-character code.'
      : '');
  }

  form.addEventListener('input', updateRequirements);
  updateRequirements();
})();
