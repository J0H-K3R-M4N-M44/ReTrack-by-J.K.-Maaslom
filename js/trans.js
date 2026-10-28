const startLoginTransition = (form, submitButton) => {
    if (submitButton) submitButton.textContent = 'Securing access...';

    repairAlert.loading(
        'Validating credentials',
        'Please wait while your login is processed.',
        5000,
        () => form.submit()
    );
};

const fadeInPage = () => {
    requestAnimationFrame(() => {
        document.body.classList.add('page-fade-in');
    });
};

const setupLogout = () => {
    const logoutLink = document.getElementById('logout-link');
    if (!logoutLink) return;

    let isLoggingOut = false;

    logoutLink.addEventListener('click', (event) => {
        // Keep the normal link navigation if SweetAlert did not load.
        if (typeof Swal === 'undefined') {
            return;
        }

        event.preventDefault();
        if (isLoggingOut) return;
        isLoggingOut = true;

        console.log('Dashboard logout requested.', {
            destination: logoutLink.href
        });

        repairAlert.loading(
            'Ending session',
            'Please wait while your session is closed.',
            2000,
            () => {
                window.location.href = logoutLink.href;
            }
        );
    });
};

const setupAuthViews = () => {
    const loginView = document.getElementById('login-view');
    const recoveryView = document.getElementById('recovery-view');
    const container = document.querySelector('.login-content');
    let isMorphing = false;

    if (!container || !loginView || !recoveryView) return;

    const views = { login: loginView, recovery: recoveryView };

    const showView = (viewName, updateHistory = true, animate = true) => {
        const activeName = views[viewName] ? viewName : 'login';
        const nextView = views[activeName];
        const currentView = Object.values(views).find((view) => !view.hidden);

        if (currentView === nextView || (animate && isMorphing)) return;

        if (!animate) {
            Object.entries(views).forEach(([name, view]) => {
                view.hidden = name !== activeName;
            });
        } else {
            isMorphing = true;
            container.style.height = `${container.offsetHeight}px`;
            container.classList.add('is-morphing');

            if (currentView) currentView.hidden = true;
            nextView.hidden = false;
            nextView.classList.add('is-entering');

            requestAnimationFrame(() => {
                container.style.height = `${nextView.offsetHeight}px`;
            });

            setTimeout(() => {
                Object.values(views).forEach((view) => {
                    view.hidden = view !== nextView;
                    view.classList.remove('is-entering');
                });
                container.style.height = '';
                container.classList.remove('is-morphing');
                isMorphing = false;
            }, 380);
        }

        if (updateHistory) {
            const url = activeName === 'recovery' ? 'forgot_password.php' : 'login.php';
            history.pushState({ view: activeName }, '', url);
        }
    };

    container.addEventListener('click', (event) => {
        const link = event.target.closest('[data-auth-view]');

        if (link && isMorphing) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    container.addEventListener('click', (event) => {
        const link = event.target.closest('[data-auth-view]');

        if (!link) return;

        event.preventDefault();
        showView(link.dataset.authView);
    });

    window.addEventListener('popstate', () => {
        const viewName = location.pathname.endsWith('forgot_password.php') ? 'recovery' : 'login';
        showView(viewName, false);
    });

    const initialView = location.pathname.endsWith('forgot_password.php') ? 'recovery' : 'login';
    showView(initialView, false, false);
};

document.addEventListener('DOMContentLoaded', () => {
    fadeInPage();
    setupLogout();
    setupAuthViews();

    if (document.body.classList.contains('dashboard-body')) {
        console.info('Dashboard interactions initialized.');
    }
});
