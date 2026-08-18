document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('admin-login-form');

    if (!form) return;

    const username = document.getElementById('username');
    const password = document.getElementById('password');

    const showAlert = (type, title, text) => {
        Swal.fire({
            icon: type,
            title,
            text,
            confirmButtonText: 'Continue',
            customClass: {
                popup: 'tech-swal',
                confirmButton: 'tech-swal-button'
            },
            buttonsStyling: false,
            backdrop: 'rgba(10, 17, 22, 0.5)',
            showClass: {
                popup: 'swal2-show animate__animated animate__fadeInUp'
            },
            hideClass: {
                popup: 'swal2-hide animate__animated animate__fadeOutDown'
            },
            didOpen: () => {
                const popup = document.querySelector('.tech-swal');
                if (popup) {
                    popup.style.transform = 'scale(0.96)';
                    requestAnimationFrame(() => {
                        popup.style.transition = 'transform 0.25s ease';
                        popup.style.transform = 'scale(1)';
                    });
                }
            }
        });
    };

    form.addEventListener('submit', (event) => {
        const usernameValue = username.value.trim();
        const passwordValue = password.value.trim();

        if (!usernameValue || !passwordValue) {
            event.preventDefault();
            const emptyFields = [];

            if (!usernameValue) emptyFields.push('Username');
            if (!passwordValue) emptyFields.push('Password');

            showAlert(
                'warning',
                'Empty field detected',
                `Please fill in: ${emptyFields.join(', ')}.`
            );

            if (!usernameValue) username.focus();
            else password.focus();
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Checking...';
    });

    if (window.location.search.includes('error=1')) {
        showAlert('error', 'Access denied', 'The username or password is incorrect.');
    }

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
