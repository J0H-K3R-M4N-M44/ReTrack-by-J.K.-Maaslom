// Native details provides a tap/click fallback without JavaScript.
// Mouse hover, keyboard focus and Escape behave consistently on trends and help tips.
document.querySelectorAll('.trend-point, .rt-help-tip').forEach((point) => {
  const summary = point.querySelector('summary');
  if (!summary) return;

  point.addEventListener('pointerenter', (event) => {
    if (event.pointerType === 'mouse') point.open = true;
  });

  point.addEventListener('pointerleave', (event) => {
    if (event.pointerType === 'mouse' && !point.contains(document.activeElement)) {
      point.open = false;
    }
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

// Collapsed desktop navigation keeps its labels available on hover and focus.
// The floating label is decorative; the link retains its own accessible name and title.
(() => {
  const root = document.documentElement;
  const links = document.querySelectorAll('.rt-side a[data-tooltip]');
  if (!links.length) return;

  const tooltip = document.createElement('div');
  tooltip.className = 'rt-floating-tooltip';
  tooltip.setAttribute('role', 'tooltip');
  tooltip.setAttribute('aria-hidden', 'true');
  tooltip.hidden = true;
  document.body.append(tooltip);

  let activeLink = null;

  function isCollapsed() {
    return root.dataset.rail === 'narrow' ||
      (root.dataset.rail === 'auto' && window.innerWidth <= 1279);
  }

  function hideTooltip(link) {
    if (link && activeLink !== link) return;
    tooltip.hidden = true;
    activeLink = null;
  }

  function showTooltip(link) {
    if (!isCollapsed() || !link.dataset.tooltip) return;
    activeLink = link;
    tooltip.textContent = link.dataset.tooltip;
    tooltip.hidden = false;

    const bounds = link.getBoundingClientRect();
    const tipBounds = tooltip.getBoundingClientRect();
    const left = Math.min(bounds.right + 10, window.innerWidth - tipBounds.width - 10);
    const top = Math.max(10, Math.min(
      bounds.top + (bounds.height - tipBounds.height) / 2,
      window.innerHeight - tipBounds.height - 10
    ));

    tooltip.style.left = Math.max(10, left) + 'px';
    tooltip.style.top = top + 'px';
  }

  links.forEach((link) => {
    link.addEventListener('pointerenter', (event) => {
      if (event.pointerType === 'mouse') showTooltip(link);
    });
    link.addEventListener('pointerleave', (event) => {
      if (event.pointerType === 'mouse' && !link.contains(document.activeElement)) {
        hideTooltip(link);
      }
    });
    link.addEventListener('focus', () => {
      if (link.matches(':focus-visible')) showTooltip(link);
    });
    link.addEventListener('blur', () => hideTooltip(link));
  });

  window.addEventListener('resize', () => hideTooltip());
  window.addEventListener('scroll', () => hideTooltip(), true);
})();
