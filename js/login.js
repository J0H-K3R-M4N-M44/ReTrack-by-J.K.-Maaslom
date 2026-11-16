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

// Optional password reveal; the ordinary form still works without JavaScript.
const passwordInput = document.querySelector('#password');
const revealPassword = document.querySelector('#show-password');
const capsLock = document.querySelector('#caps-lock');
if (passwordInput && revealPassword) {
  revealPassword.hidden = false;
  revealPassword.addEventListener('click', () => {
    const show = passwordInput.type === 'password';
    passwordInput.type = show ? 'text' : 'password';
    revealPassword.textContent = show ? 'Hide password' : 'Show password';
    revealPassword.setAttribute('aria-pressed', String(show));
  });
  passwordInput.addEventListener('keyup', (event) => {
    if (capsLock) capsLock.hidden = !event.getModifierState('CapsLock');
  });
  passwordInput.addEventListener('blur', () => {
    if (capsLock) capsLock.hidden = true;
  });
}
