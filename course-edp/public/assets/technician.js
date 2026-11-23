/* Progressive enhancement: event delegation + CustomEvent observer for EDP. */
(() => {
  const surface = document;
  surface.addEventListener('change', (event) => {
    if (!(event.target instanceof HTMLSelectElement) ||
        !event.target.matches('[data-repair-status-choice]')) return;
    surface.dispatchEvent(new CustomEvent('repair:status-selected', {
      detail: { status: event.target.selectedOptions[0]?.textContent?.trim() || '' },
    }));
  });
  surface.addEventListener('repair:status-selected', (event) => {
    const preview = surface.querySelector('[data-status-preview]');
    if (!preview) return;
    preview.textContent = event.detail.status
      ? `On save, the repair will move to: ${event.detail.status}. The customer will see this status.`
      : 'The customer timeline will update after you save.';
  });
})();
