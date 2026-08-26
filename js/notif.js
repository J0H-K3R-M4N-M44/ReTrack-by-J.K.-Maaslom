// SweetAlert notifications. repairAlert.open() is called by login.js
// when validation or server authentication fails.
const repairAlert = {
    tones: {
        warning:    { glow: '#ffcc4d', accent: '#ffd76a' },
        error:      { glow: '#ff4d6d', accent: '#ff7a90' },
        success:    { glow: '#4dff88', accent: '#7affb0' },
        info:       { glow: '#4da6ff', accent: '#7ab8ff' }
    },

    open(type, title, text, onClose) {
        const tone = this.tones[type] || this.tones.error;

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

    // The timer is asynchronous: SweetAlert calls onComplete after it expires.
    loading(title, text, duration = 5000, onComplete) {
        return Swal.fire({
            title,
            text,
            customClass: {
                popup: 'tech-swal tech-swal-loading',
                title: 'tech-swal-title',
                htmlContainer: 'tech-swal-text'
            },
            buttonsStyling: false,
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

// Custom event system: login.js publishes events and these subscribers react.
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

document.addEventListener('DOMContentLoaded', () => {
    // This listener connects the notification handlers after the message area exists.
    const messageArea = document.getElementById('message-area');
    if (!messageArea) return;

    const setMessage = (text, tone = 'info') => {
        const message = text.trim();
        messageArea.textContent = message;
        messageArea.hidden = !message;
        messageArea.dataset.tone = tone;
    };

    NotificationCenter.subscribe('login:attempt', () => {
        setMessage('Processing login attempt...', 'info');
    });

    NotificationCenter.subscribe('login:success', () => {
        setMessage('Validating credentials...', 'info');
    });

    NotificationCenter.subscribe('login:failed', ({ message }) => {
        setMessage(message, 'error');
    });
});
