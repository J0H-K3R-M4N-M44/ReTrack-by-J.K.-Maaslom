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
        event.preventDefault();

        if (isLoggingOut) return;
        isLoggingOut = true;

        const metric = document.querySelector('.transfer-metric');
        const status = document.querySelector('.transfer-status');
        const startedAt = performance.now();
        const duration = 650;

        document.body.classList.add('transitioning', 'logging-out');
        if (status) status.textContent = 'Ending session';

        const updateProgress = (currentTime) => {
            const progress = Math.min((currentTime - startedAt) / duration * 100, 100);

            if (metric) metric.style.width = `${progress}%`;

            if (progress < 100) {
                requestAnimationFrame(updateProgress);
                return;
            }

            if (status) status.textContent = 'Redirecting to login';
            setTimeout(() => {
                window.location.href = logoutLink.href;
            }, 200);
        };

        requestAnimationFrame(updateProgress);
    });
};

const setupAuthViews = () => {
    const views = {
        login: document.getElementById('login-view'),
        recovery: document.getElementById('recovery-view')
    };
    const container = document.querySelector('.login-content');
    const links = document.querySelectorAll('[data-auth-view]');
    let isMorphing = false;

    if (!container || !views.login || !views.recovery) return;

    const showView = (viewName, updateHistory = true, animate = true) => {
        const activeView = views[viewName] ? viewName : 'login';
        const nextView = views[activeView];
        const currentView = Object.values(views).find((view) => !view.hidden);

        if (currentView === nextView) return;

        if (animate && isMorphing) return;

        if (!animate) {
            Object.entries(views).forEach(([name, view]) => {
                view.hidden = name !== activeView;
            });
        } else {
            isMorphing = true;
            const currentHeight = container.offsetHeight;
            container.style.height = `${currentHeight}px`;
            container.classList.add('is-morphing');

            if (currentView) {
                currentView.hidden = true;
                currentView.classList.remove('is-entering');
            }

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
            const url = activeView === 'recovery' ? 'forgot_password.php' : 'login.php';
            history.pushState({ view: activeView }, '', url);
        }
    };

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            showView(link.dataset.authView, true, true);
        });
    });

    window.addEventListener('popstate', () => {
        showView(location.pathname.endsWith('forgot_password.php') ? 'recovery' : 'login', false, true);
    });

    showView(location.pathname.endsWith('forgot_password.php') ? 'recovery' : 'login', false, false);
};

document.addEventListener('DOMContentLoaded', () => {
    fadeInPage();
    setupLogout();
    setupAuthViews();
});
