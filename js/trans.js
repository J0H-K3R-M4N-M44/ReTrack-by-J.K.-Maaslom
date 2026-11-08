// Page content stays visible even when JavaScript fails.
// Video is optional decoration and respects the user's motion preference.
document.addEventListener('DOMContentLoaded', () => {
  const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
  function updateVideo() {
    document.querySelectorAll('.bg-video').forEach((video) => {
      if (preference.matches) video.pause();
      else video.play().catch(() => {});
    });
  }
  preference.addEventListener('change', updateVideo);
  updateVideo();
});
