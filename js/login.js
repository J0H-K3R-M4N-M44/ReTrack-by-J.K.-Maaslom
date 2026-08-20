document.addEventListener('DOMContentLoaded', () => {
    // ========================================
    // FORM & INPUT ELEMENTS
    // ========================================
    
    const form = document.getElementById('admin-login-form');

    if (!form) return;

    const username = document.getElementById('username');
    const password = document.getElementById('password');

    // ========================================
    // REUSABLE ALERT FUNCTION
    // ========================================
    
    const showAlert = (type, title, text) => {
        const toneMap = {
            warning: {
                glow: '#ffcc4d',
                accent: '#ffd76a'
            },
            error: {
                glow: '#ff4d6d',
                accent: '#ff7a90'
            }
        };

        const tone = toneMap[type] || toneMap.error;

        Swal.fire({
            // Alert appearance
            icon: type,
            title,
            text,
            confirmButtonText: 'Continue',
            
            // Styling classes
            customClass: {
                popup: 'tech-swal',
                confirmButton: 'tech-swal-button',
                title: 'tech-swal-title',
                htmlContainer: 'tech-swal-text'
            },
            
            // Disable default styling
            buttonsStyling: false,
            backdrop: 'rgba(10, 17, 22, 0.6)',
            
            // Animations
            showClass: {
                popup: 'swal2-show animate__animated animate__fadeInUp'
            },
            hideClass: {
                popup: 'swal2-hide animate__animated animate__fadeOutDown'
            },
            
            // Custom tone styling and scale animation
            didOpen: () => {
                const popup = document.querySelector('.tech-swal');
                if (popup) {
                    popup.dataset.tone = type;
                    popup.style.setProperty('--tone-glow', tone.glow);
                    popup.style.setProperty('--tone-accent', tone.accent);
                    popup.style.transform = 'scale(0.96)';
                    
                    requestAnimationFrame(() => {
                        popup.style.transition = 'transform 0.25s ease';
                        popup.style.transform = 'scale(1)';
                    });
                }
            }
        });
    };

    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton?.textContent || 'Login';
    let isSubmitting = false;

    const startTransfer = () => {
        if (isSubmitting) return;

        isSubmitting = true;
        document.body.classList.add('transitioning');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Securing access...';
        }

        const metric = document.querySelector('.transfer-metric');
        const status = document.querySelector('.transfer-status');
        const startedAt = performance.now();
        const duration = 650;

        if (metric) metric.style.width = '0%';
        if (status) status.textContent = 'Establishing secure link';

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

    // Validate fields and start the login transition.
    form.addEventListener('submit', (event) => {
        const usernameValue = username.value.trim();
        const passwordValue = password.value.trim();

        // Check for empty fields
        if (!usernameValue || !passwordValue) {
            event.preventDefault();
            const emptyFields = [];

            if (!usernameValue) emptyFields.push('Username');
            if (!passwordValue) emptyFields.push('Password');

            // Show warning alert
            showAlert(
                'warning',
                'Missing required fields',
                `Please fill in: ${emptyFields.join(', ')}.`
            );

            // Focus first empty field
            if (!usernameValue) username.focus();
            else password.focus();
            return;
        }

        event.preventDefault();
        startTransfer();
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
    
    if (window.location.search.includes('error=1')) {
        showAlert('error', 'Access denied', 'The username or password is incorrect.');
    }

    // ========================================
    // INPUT FOCUS STYLING
    // ========================================
    
    [username, password].forEach((input) => {
        // Focus the field when the mouse enters its input area.
        input.parentElement.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') {
                input.focus();
            }
        });
    });
});
