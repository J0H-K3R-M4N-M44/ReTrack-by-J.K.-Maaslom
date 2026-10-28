(() => {
    const menuButton = document.querySelector('.menu-toggle');
    const menu = document.querySelector('#site-nav');
    const progress = document.querySelector('.scroll-progress span');
    const video = document.querySelector('.hero-video');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (menuButton && menu) {
        menuButton.addEventListener('click', () => {
            const isOpen = menu.classList.toggle('is-open');
            menuButton.setAttribute('aria-expanded', String(isOpen));
            menuButton.querySelector('i').className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        });
        menu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                menu.classList.remove('is-open');
                menuButton.setAttribute('aria-expanded', 'false');
                menuButton.querySelector('i').className = 'fa-solid fa-bars';
            });
        });
    }

    if (video && reduceMotion) {
        video.pause();
        video.removeAttribute('autoplay');
    }

    const revealItems = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && !reduceMotion) {
        const observer = new IntersectionObserver((entries, currentObserver) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    currentObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.14 });
        revealItems.forEach((item) => observer.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    }

    const updateProgress = () => {
        if (!progress) return;
        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        const amount = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 0;
        progress.style.width = Math.min(100, Math.max(0, amount)) + '%';
    };
    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();
})();
