document.addEventListener('DOMContentLoaded', () => {
    // ========================================
    // FORM & BUTTON REFERENCES
    // ========================================
    
    const form = document.getElementById('admin-login-form');

    if (!form) return;

    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : 'Login';

    // ========================================
    // LOADING ANIMATION FUNCTION
    // ========================================
    
    const startTransfer = () => {
        // Show transition state
        document.body.classList.add('transitioning');
        form.classList.add('is-transferring');

        // Update button
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Securing access...';
        }

        // Get progress elements
        const metric = document.querySelector('.transfer-metric');
        const status = document.querySelector('.transfer-status');

        // Reset progress bar
        if (metric) metric.style.width = '0%';
        if (status) status.textContent = 'Establishing secure link';

        // Animate progress bar
        let progress = 0;
        const interval = setInterval(() => {
            progress += 9;
            if (metric) metric.style.width = Math.min(progress, 100) + '%';

            // Complete animation and submit
            if (progress >= 100) {
                clearInterval(interval);
                if (status) status.textContent = 'Redirecting to dashboard';
                
                setTimeout(() => {
                    form.submit();
                }, 400);
            }
        }, 120);
    };

    // ========================================
    // INTERCEPT FORM SUBMIT
    // ========================================
    
    form.addEventListener('submit', (event) => {
        const username = document.getElementById('username');
        const password = document.getElementById('password');

        // Validate fields exist
        if (!username || !password) return;

        const usernameValue = username.value.trim();
        const passwordValue = password.value.trim();

        // Don't show animation if fields are empty
        if (!usernameValue || !passwordValue) {
            event.preventDefault();
            return;
        }

        // Prevent normal submit and show transition
        event.preventDefault();
        startTransfer();
    });

    // ========================================
    // RESTORE BUTTON ON PAGE RETURN
    // ========================================
    
    const restoreButton = () => {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    };

    window.addEventListener('pageshow', restoreButton);
    window.addEventListener('beforeunload', restoreButton);
});
