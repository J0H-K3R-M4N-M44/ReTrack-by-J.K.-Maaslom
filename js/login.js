document.addEventListener('DOMContentLoaded', () => {
    // Event source: the browser fires DOMContentLoaded after the page is ready.
    // This handler then connects the login form to the rest of the event flow.
    // Core login elements
    const form = document.getElementById('admin-login-form');
    if (!form) return;

    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton?.textContent || 'Login';
    let isSubmitting = false;

    // Keep the inline message visible while the alert is shown, then hide it
    // ten seconds after the SweetAlert has faded away.
    const hideMessageLater = () => {
        const messageArea = document.getElementById('message-area');
        if (!messageArea) return;

        setTimeout(() => {
            messageArea.hidden = true;
        }, 2000);
    };

    // Event source: submitting the form can come from a button click or Enter.
    // The form listener handles both cases in one place.
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (isSubmitting) return;

        // Publish a custom application event for notification subscribers.
        NotificationCenter.publish('login:attempt');

        const usernameValue = username.value.trim();
        const passwordValue = password.value.trim();

        if (!usernameValue || !passwordValue) {
            const missing = [];
            if (!usernameValue) missing.push('Username');
            if (!passwordValue) missing.push('Password');
            const text = `Please fill in: ${missing.join(', ')}.`;

            // Validation failed, so publish the failure before showing the alert.
            NotificationCenter.publish('login:failed', { message: text });
            repairAlert.open('warning', 'Missing required fields', text, hideMessageLater);

            if (!usernameValue) {
                username.focus();
            } else {
                password.focus();
            }
            return;
        }

        // Validation passed. The transition script now handles the loading UI.
        NotificationCenter.publish('login:success');

        isSubmitting = true;
        if (submitButton) submitButton.disabled = true;
        startLoginTransition(form, submitButton);
    });

    // Reset the button if the page is restored from browser history.
    window.addEventListener('pageshow', () => {
        isSubmitting = false;

        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
    });

    // Notification: PHP redirects back here with error=1 after a failed login.
    const url = new URL(window.location.href);

    if (url.searchParams.get('error') === '1') {
        repairAlert.open(
            'error',
            'Access denied',
            'The username or password is incorrect.',
            hideMessageLater
        );
        url.searchParams.delete('error');
        window.history.replaceState({}, document.title, url);
    }
});
