'use strict';
// Demonstrate event delegation without any inline handlers forbidden by the CSP.
document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-print-receipt]');
  if (!button) return;
  window.print();
  document.dispatchEvent(new CustomEvent('repairtrack:receipt-print-requested', {
    detail: { kind: 'cash-receipt' }
  }));
});
