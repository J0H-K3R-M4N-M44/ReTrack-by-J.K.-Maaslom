// Native browser validation runs first; the server checks the credentials.
const loginForm = document.querySelector('#admin-login-form');
if (loginForm) {
  const button = loginForm.querySelector('[type="submit"]');
  loginForm.addEventListener('submit', (event) => {
    if (button.disabled) {
      event.preventDefault();
      return;
    }
    button.disabled = true;
    button.textContent = 'Signing in…';
  });
  window.addEventListener('pageshow', () => {
    button.disabled = false;
    button.textContent = 'Login';
  });
}
