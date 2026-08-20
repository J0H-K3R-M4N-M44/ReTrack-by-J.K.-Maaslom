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
            info: {
                glow: '#4db7ff',
                accent: '#79d6ff'
            },
            warning: {
                glow: '#ffcc4d',
                accent: '#ffd76a'
            },
            error: {
                glow: '#ff4d6d',
                accent: '#ff7a90'
            },
            success: {
                glow: '#34d399',
                accent: '#7ef0b1'
            }
        };

        const tone = toneMap[type] || toneMap.info;

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

    // ========================================
    // FORM SUBMISSION VALIDATION
    // ========================================
    
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

        // Disable button during submission
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Checking...';
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
        input.addEventListener('focus', () => {
            input.parentElement.style.borderColor = 'rgba(212, 175, 102, 0.7)';
            input.parentElement.style.boxShadow = '0 0 0 3px rgba(212, 175, 102, 0.12)';
        });

        input.addEventListener('blur', () => {
            input.parentElement.style.borderColor = 'rgba(255,255,255,0.15)';
            input.parentElement.style.boxShadow = 'inset 0 1px 0 rgba(255,255,255,0.1)';
        });
    });
});
