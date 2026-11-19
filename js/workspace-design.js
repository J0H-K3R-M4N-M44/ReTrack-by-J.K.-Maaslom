// Apply preferences before the page paints. Storage is optional in private browsing.
(() => {
  const root = document.documentElement;
  const system = matchMedia('(prefers-color-scheme: light)');
  const settings = {theme: 'system', surface: 'glass', density: 'comfortable', rail: 'auto'};
  try {
    Object.assign(settings, JSON.parse(localStorage.getItem('repairtrack.appearance') || '{}'));
  } catch {}
  function apply() {
    root.dataset.theme = settings.theme === 'system' ? (system.matches ? 'linen' : 'ember') : settings.theme;
    root.dataset.surface = settings.surface;
    root.dataset.density = settings.density;
    root.dataset.rail = settings.rail;
  }
  function save() {
    apply();
    try { localStorage.setItem('repairtrack.appearance', JSON.stringify(settings)); } catch {}
  }
  apply();
  system.addEventListener('change', apply);
  document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('#rt-menu');
    const search = document.querySelector('#rt-search');
    const links = [...menu.querySelectorAll('nav a')];
    document.querySelectorAll('[data-rt-setting]').forEach(select => {
      const key = select.dataset.rtSetting;
      select.value = settings[key];
      select.onchange = () => { settings[key] = select.value; save(); };
    });
    document.querySelector('#rt-rail').onclick = () => {
      const narrow = document.querySelector('.rt-side').offsetWidth < 100;
      settings.rail = narrow ? 'wide' : 'narrow';
      save();
    };
    search.oninput = () => {
      const query = search.value.trim().toLowerCase();
      links.forEach(link => { link.hidden = !link.textContent.toLowerCase().includes(query); });
      document.querySelector('#rt-no-results').hidden = links.some(link => !link.hidden);
    };
    function openMenu() { menu.open = true; search.focus(); }
    document.querySelector('#rt-more').onclick = event => { event.preventDefault(); openMenu(); };
    document.addEventListener('keydown', event => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault(); openMenu();
      } else if (event.key === 'Escape' && menu.open) {
        menu.open = false; menu.querySelector('summary').focus();
      }
    });
    document.addEventListener('click', event => {
      if (menu.open && !menu.contains(event.target) && !event.target.closest('#rt-more')) menu.open = false;
    });
  });
})();
