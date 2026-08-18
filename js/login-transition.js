document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('admin-login-form');

    if (!form) return;

    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : 'Login';

    const startTransfer = () => {
        document.body.classList.add('transitioning');
        form.classList.add('is-transferring');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Securing access...';
        }

        const metric = document.querySelector('.transfer-metric');
        const status = document.querySelector('.transfer-status');

        if (metric) metric.style.width = '0%';
        if (status) status.textContent = 'Establishing secure link';

        let progress = 0;
        const interval = setInterval(() => {
            progress += 9;
            if (metric) metric.style.width = Math.min(progress, 100) + '%';

            if (progress >= 100) {
                clearInterval(interval);
                if (status) status.textContent = 'Redirecting to dashboard';
                setTimeout(() => {
                    form.submit();
                }, 400);
            }
        }, 120);
    };

    form.addEventListener('submit', (event) => {
        const username = document.getElementById('username');
        const password = document.getElementById('password');

        if (!username || !password) return;

        const usernameValue = username.value.trim();
        const passwordValue = password.value.trim();

        if (!usernameValue || !passwordValue) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        startTransfer();
    });

    const restoreButton = () => {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    };

    window.addEventListener('pageshow', restoreButton);
    window.addEventListener('beforeunload', restoreButton);
});
