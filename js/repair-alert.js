const repairAlert = {
    tones: {
        warning:    { glow: '#ffcc4d', accent: '#ffd76a' },
        error:      { glow: '#ff4d6d', accent: '#ff7a90' },
        success:    { glow: '#4dff88', accent: '#7affb0' },
        info:       { glow: '#4da6ff', accent: '#7ab8ff' }
    },

    open(type, title, text) {
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
            }
        });
    }
};
