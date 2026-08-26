// Page and loading transitions
const startLoginTransition = (form, submitButton) => {
    if (submitButton) submitButton.textContent = 'Securing access...';

    // Event flow: login.js calls this after validation succeeds. The timer callback
    // submits the form only after the five-second loading notification closes.
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

    // Event source: the user clicks the logout link.
    logoutLink.addEventListener('click', (event) => {
        event.preventDefault();
        if (isLoggingOut) return;
        isLoggingOut = true;

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

    // Capture phase (also called tunneling): this runs while the click travels
    // down toward the link and blocks a second view change during a transition.
    container.addEventListener('click', (event) => {
        const link = event.target.closest('[data-auth-view]');

        if (link && isMorphing) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    // Bubble phase: the click travels back up to the container. One delegated
    // handler can therefore manage both auth-view links.
    container.addEventListener('click', (event) => {
        const link = event.target.closest('[data-auth-view]');

        if (!link) return;

        event.preventDefault();
        showView(link.dataset.authView);
    });

    // Event source: browser back/forward navigation fires popstate on window.
    window.addEventListener('popstate', () => {
        const viewName = location.pathname.endsWith('forgot_password.php') ? 'recovery' : 'login';
        showView(viewName, false);
    });

    const initialView = location.pathname.endsWith('forgot_password.php') ? 'recovery' : 'login';
    showView(initialView, false, false);
};

document.addEventListener('DOMContentLoaded', () => {
    // Event source: initialize transitions after the page markup is available.
    fadeInPage();
    setupLogout();
    setupAuthViews();
});
