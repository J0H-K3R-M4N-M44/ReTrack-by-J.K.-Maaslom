// Native details supports taps without JavaScript. Mouse and keyboard users get
// the same date/count. Escape dismisses the tooltip without moving focus.
document.querySelectorAll('.trend-point').forEach((point) => {
  const summary = point.querySelector('summary');
  point.addEventListener('pointerenter', (event) => {
    if (event.pointerType === 'mouse') point.open = true;
  });
  point.addEventListener('pointerleave', (event) => {
    if (event.pointerType === 'mouse' && !point.contains(document.activeElement))
      point.open = false;
  });
  summary.addEventListener('focus', () => {
    if (summary.matches(':focus-visible')) point.open = true;
  });
  point.addEventListener('focusout', (event) => {
    if (!point.contains(event.relatedTarget)) point.open = false;
  });
  point.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      point.open = false;
      event.preventDefault();
    }
  });
});
