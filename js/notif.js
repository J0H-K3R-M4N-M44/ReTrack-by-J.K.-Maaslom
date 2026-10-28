const repairAlert = {
    tones: {
        warning:    { glow: '#ffcc4d', accent: '#ffd76a' },
        error:      { glow: '#ff4d6d', accent: '#ff7a90' },
        success:    { glow: '#4dff88', accent: '#7affb0' },
        info:       { glow: '#4da6ff', accent: '#7ab8ff' }
    },

    blurPageFocus() {
        const activeElement = document.activeElement;

        if (activeElement instanceof HTMLElement && activeElement !== document.body) {
            activeElement.blur();
        }
    },

    open(type, title, text, onClose) {
        const tone = this.tones[type] || this.tones.error;

        console.log('SweetAlert popup requested.', {
            popupType: 'message',
            alertType: type,
            title,
            text
        });

        handleLoginNotification({
            alertType: type,
            title,
            text,
            mode: 'message'
        });

        this.blurPageFocus();

        return Swal.fire({
            icon: type,
            title,
            text,
            confirmButtonText: 'Continue',
            customClass: {
                popup: 'tech-swal',
                confirmButton: 'tech-swal-button',
                title: 'tech-swal-title',
                htmlContainer: 'tech-swal-text'
            },
            buttonsStyling: false,
            backdrop: 'rgba(10, 17, 22, 0.6)',
            returnFocus: false,
            timer: 3000,
            timerProgressBar: true,
            showClass: {
                popup: 'swal2-show animate__animated animate__fadeInUp'
            },
            hideClass: {
                popup: 'swal2-hide animate__animated animate__fadeOutDown'
            },
            didOpen: () => {
                const popup = document.querySelector('.tech-swal');
                if (!popup) return;

                popup.dataset.tone = type;
                popup.style.setProperty('--tone-glow', tone.glow);
                popup.style.setProperty('--tone-accent', tone.accent);
                popup.style.transform = 'scale(0.96)';

                requestAnimationFrame(() => {
                    popup.style.transition = 'transform 0.25s ease';
                    popup.style.transform = 'scale(1)';
                });
            },
            didClose: () => {
                if (onClose) onClose();
            }
        });
    },

    loading(title, text, duration = 5000, onComplete) {
        console.log('SweetAlert popup requested.', {
            popupType: 'loading',
            alertType: 'info',
            title,
            text,
            duration
        });

        handleLoginNotification({
            alertType: 'info',
            title,
            text,
            mode: 'loading'
        });

        this.blurPageFocus();

        return Swal.fire({
            title,
            text,
            customClass: {
                popup: 'tech-swal tech-swal-loading',
                title: 'tech-swal-title',
                htmlContainer: 'tech-swal-text'
            },
            buttonsStyling: false,
            backdrop: 'rgba(10, 17, 22, 0.6)',
            returnFocus: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            timer: duration,
            timerProgressBar: true,
            didOpen: () => {
                Swal.showLoading();

                const popup = document.querySelector('.tech-swal-loading');
                if (!popup) return;

                popup.dataset.tone = 'info';
                popup.style.setProperty('--tone-glow', this.tones.info.glow);
                popup.style.setProperty('--tone-accent', this.tones.info.accent);
            },
            didClose: () => {
                if (onComplete) onComplete();
            }
        });
    }
};

let loginAttemptCount = 0;

const handleLoginValidation = (eventData) => {
    const missingFields = [];

    if (eventData.username === '(empty)') missingFields.push('Username');
    if (!eventData.passwordProvided) missingFields.push('Password');

    const isValid = missingFields.length === 0;
    const status = isValid ? 'succeeded' : 'failed';
    const message = isValid
        ? ''
        : `Please fill in: ${missingFields.join(', ')}.`;

    console.log('Handler 1: Login validation.', {
        valid: isValid,
        status,
        username: eventData.username,
        message
    });

};

const handleLoginSubmissionAudit = (eventData) => {
    loginAttemptCount += 1;

    const status = eventData.passwordProvided ? 'credentials provided' : 'password missing';

    console.log('Handler 2: Login submission and audit.', {
        attemptNumber: loginAttemptCount,
        username: eventData.username,
        formId: eventData.formId,
        submittedAt: eventData.submittedAt,
        status
    });
};

const handleLoginNotification = ({ alertType, title, text, mode }) => {
    console.log('Handler 3: SweetAlert notification handled.', {
        source: 'SweetAlert',
        alertType,
        mode,
        title,
        text
    });
};

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

NotificationCenter.subscribe('login:submitted', handleLoginValidation);
NotificationCenter.subscribe('login:submitted', handleLoginSubmissionAudit);

const renderLoginMessage = (messageArea, text, tone = 'info') => {
    const message = String(text ?? '').trim();
    messageArea.textContent = message;
    messageArea.hidden = !message;
    messageArea.dataset.tone = tone;
};

const handleLoginStatusMessage = (messageArea, { status, message = '' }) => {
    if (status === 'attempted') {
        renderLoginMessage(messageArea, 'Processing login attempt...', 'info');
        return;
    }

    if (status === 'failed') {
        renderLoginMessage(messageArea, message, 'error');
        return;
    }

    if (status === 'succeeded') {
        renderLoginMessage(messageArea, 'Validating credentials...', 'info');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const messageArea = document.getElementById('message-area');
    if (!messageArea) return;

    NotificationCenter.subscribe('login:status', (detail) => {
        handleLoginStatusMessage(messageArea, detail);
    });
});
