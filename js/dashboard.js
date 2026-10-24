document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('[data-dashboard-tab]');
    const panels = document.querySelectorAll('[data-dashboard-panel]');

    if (tabs.length === 0 || panels.length === 0) {
        return;
    }

    const showPanel = (panelName) => {
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.dashboardPanel !== panelName;
        });

        tabs.forEach((tab) => {
            const isActive = tab.dataset.dashboardTab === panelName;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-current', isActive ? 'page' : 'false');
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            showPanel(tab.dataset.dashboardTab);
        });
    });

    showPanel('overview');
});
