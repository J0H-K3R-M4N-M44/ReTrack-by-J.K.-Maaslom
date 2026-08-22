document.addEventListener('DOMContentLoaded', () => {
    // ========================================
    // FORM & INPUT ELEMENTS
    // ========================================

    const form = document.getElementById('admin-login-form');

    if (!form) return;

    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const messageArea = document.getElementById('message-area');
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton?.textContent || 'Login';
    let isSubmitting = false;

    // ========================================
    // MESSAGE / NOTIFICATION AREA (Step 1)
    // ========================================
    const setMessage = (text, tone = 'info') => {
        if (!messageArea) return;
        const message = text.trim();
        messageArea.textContent = message;
        messageArea.hidden = !message;
        messageArea.dataset.tone = tone;
    };

    // ========================================
    // STEP 6: OBSERVER / NOTIFICATION CENTER
    // Handlers publish what happened; they don't touch the UI
    // directly. Anything can subscribe without the handler knowing.
    // ========================================
    const NotificationCenter = (() => {
        const subscribers = {};

        const subscribe = (eventName, callback) => {
            if (!subscribers[eventName]) subscribers[eventName] = [];
            subscribers[eventName].push(callback);
        };

        const publish = (eventName, detail) => {
            const callbacks = subscribers[eventName] || [];
            callbacks.forEach((callback) => callback(detail));
        };

        return { subscribe, publish };
    })();

    NotificationCenter.subscribe('login:attempt', () => {
        console.log('[NotificationCenter] login:attempt');
        setMessage('Processing login attempt...', 'info');
    });

    NotificationCenter.subscribe('login:success', () => {
        console.log('[NotificationCenter] login:success');
        setMessage('Validating credentials...', 'info');
    });

    NotificationCenter.subscribe('login:failed', ({ message }) => {
        console.log('[NotificationCenter] login:failed');
        setMessage(message, 'error');
    });

    const runProcessing = (mode) => {
        document.body.classList.add('transitioning');
        if (submitButton) submitButton.textContent = 'Securing access...';

        const metric = document.querySelector('.transfer-metric');
        const status = document.querySelector('.transfer-status');

        if (metric) metric.style.width = '0%';
        if (mode === 'sync') {
            if (status) status.textContent = 'Validating';
            const end = performance.now() + 3000;
            while (performance.now() < end) {
                // intentionally blocking — simulates heavy synchronous work
            }
            if (metric) metric.style.width = '100%';
            if (status) status.textContent = 'Redirecting to dashboard';
            setTimeout(() => form.submit(), 200);
            return;
        }

        const startedAt = performance.now();
        const duration = 900;
        if (status) status.textContent = 'Validating';

        const updateProgress = (currentTime) => {
            const progress = Math.min((currentTime - startedAt) / duration * 100, 100);

            if (metric) metric.style.width = `${progress}%`;

            if (progress < 100) {
                requestAnimationFrame(updateProgress);
                return;
            }

            if (status) status.textContent = 'Redirecting to dashboard';
            setTimeout(() => form.submit(), 200);
        };

        requestAnimationFrame(updateProgress);
    };

    const handleLoginTrigger = (event, mode) => {
        event.preventDefault();
        if (isSubmitting) return;

        NotificationCenter.publish('login:attempt', { mode });

        const usernameValue = username.value.trim();
        const passwordValue = password.value.trim();

        if (!usernameValue || !passwordValue) {
            const missing = [];
            if (!usernameValue) missing.push('Username');
            if (!passwordValue) missing.push('Password');
            const text = `Please fill in: ${missing.join(', ')}.`;

            NotificationCenter.publish('login:failed', { reason: 'empty', message: text });
            repairAlert.open('warning', 'Missing required fields', text);

            if (!usernameValue) {
                username.focus();
            } else {
                password.focus();
            }
            return;
        }

        NotificationCenter.publish('login:success', { mode });

        isSubmitting = true;
        if (submitButton) submitButton.disabled = true;

        runProcessing(mode);
    };

    submitButton?.addEventListener('click', (event) => {
        console.log('[Propagation] TARGET phase — button click handler fired');
        handleLoginTrigger(event, 'async');
    });

    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;

        if (event.target === password) {
            handleLoginTrigger(event, 'sync');
        } else {
            handleLoginTrigger(event, 'async');
        }
    });

    form.addEventListener('submit', (event) => event.preventDefault());

    form.addEventListener('click', (event) => {
        if (event.target === submitButton) {
            console.log('[Propagation] CAPTURE phase — reached form container first');
        }
    }, true);

    form.addEventListener('click', (event) => {
        if (event.target === submitButton) {
            console.log('[Propagation] BUBBLE phase — reached form container after the button');
        }
    }, false);

    password.addEventListener('focus', () => {
        if (messageArea && !messageArea.textContent) {
            setMessage('Password must be at least 6 characters.', 'hint');
        }
    });

    password.addEventListener('blur', () => {
        if (messageArea?.dataset.tone === 'hint') setMessage('');
    });

    window.addEventListener('pageshow', () => {
        isSubmitting = false;

        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
    });

    // ========================================
    // CHECK FOR LOGIN ERRORS
    // ========================================

    const url = new URL(window.location.href);

    if (url.searchParams.get('error') === '1') {
        repairAlert.open('error', 'Access denied', 'The username or password is incorrect.');
        url.searchParams.delete('error');
        window.history.replaceState({}, document.title, url);
    }

    // ========================================
    // INPUT FOCUS STYLING
    // ========================================

    [username, password].forEach((input) => {
        input.addEventListener('focus', () => {
            input.removeAttribute('readonly');
        });

        // Focus the field when the mouse enters its input area.
        input.parentElement.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') {
                input.focus();
            }
        });
    });
});
