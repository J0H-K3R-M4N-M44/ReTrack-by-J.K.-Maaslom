// Keep ordinary navigation visible without JavaScript. On phones, reuse those
// same links in a native dialog instead of maintaining a second menu.
const smallScreen = window.matchMedia('(max-width: 760px)');
document.querySelectorAll('[data-navigation]').forEach((panel) => {
  const button = document.querySelector(`[data-navigation-toggle="${panel.id}"]`);
  if (!button || typeof HTMLDialogElement === 'undefined') return;
  const sidebar = panel.dataset.navigation === 'sidebar';
  const marker = document.createComment('Navigation belongs here on desktop');
  panel.before(marker);
  const dialog = document.createElement('dialog');
  dialog.className = 'navigation-dialog';
  dialog.setAttribute('aria-label', sidebar ? 'Admin navigation' : 'Workspace navigation');
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'secondary-btn navigation-close';
  close.textContent = 'Close navigation';
  dialog.append(close);
  document.body.append(dialog);
  let collapsed = false;
  try {
    collapsed = localStorage.getItem('repairtrack.sidebar.collapsed') === 'true';
  } catch {
    /* Storage is optional. */
  }

  function updateNavigation() {
    if (dialog.open) dialog.close();
    if (smallScreen.matches) {
      dialog.append(panel);
      panel.hidden = false;
      button.hidden = false;
      button.textContent = sidebar ? 'Open navigation' : 'Workspace menu';
      button.setAttribute('aria-expanded', 'false');
    } else {
      marker.after(panel);
      panel.hidden = sidebar && collapsed;
      button.hidden = !sidebar;
      button.textContent = collapsed ? 'Show navigation' : 'Hide navigation';
      button.setAttribute('aria-expanded', String(!panel.hidden));
    }
  }
  button.addEventListener('click', () => {
    if (smallScreen.matches) {
      dialog.showModal();
      button.setAttribute('aria-expanded', 'true');
      close.focus();
    } else {
      collapsed = !collapsed;
      try {
        localStorage.setItem('repairtrack.sidebar.collapsed', String(collapsed));
      } catch {
        /* Keep working without storage. */
      }
      updateNavigation();
    }
  });
  // Keep Tab inside the menu even in browsers that allow focus to reach chrome.
  dialog.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab') return;
    const links = [...dialog.querySelectorAll('a[href], button:not([disabled])')].filter(
      (element) => element.getClientRects().length > 0
    );
    const first = links[0];
    const last = links[links.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => {
    button.setAttribute('aria-expanded', String(!smallScreen.matches && !panel.hidden));
    if (!button.hidden) button.focus();
  });
  smallScreen.addEventListener('change', updateNavigation);
  updateNavigation();
});

// Native account menus still work without scripts; add dismissal conveniences.
document.querySelectorAll('.console-account').forEach((account) => {
  account.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && account.open) {
      account.open = false;
      account.querySelector('summary').focus();
      event.preventDefault();
    }
  });
  document.addEventListener('click', (event) => {
    if (!account.contains(event.target)) account.open = false;
  });
});
