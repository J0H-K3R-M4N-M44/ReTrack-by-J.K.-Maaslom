const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Simulate a browser without the optional SweetAlert CDN script.
const logs = [];
const context = vm.createContext({
  console: { log: (...args) => logs.push(args) },
  document: { addEventListener() {} },
  window: {},
});
for (const file of ['js/notif.js', 'js/trans.js', 'js/login.js']) {
  vm.runInContext(fs.readFileSync(file, 'utf8'), context, { filename: file });
}
vm.runInContext(
  `
  let submitted = 0;
  let focused = '';
  const form = { id: 'test-login', submit() { submitted++; } };
  const email = { value: 'staff@example.test', focus() { focused = 'email'; } };
  const password = { value: 'correct password', focus() { focused = 'password'; } };
  const button = { disabled: false, textContent: 'Login' };
  const loginState = { isSubmitting: false };
  const event = { preventDefault() {} };
  handleLoginSubmit(event, form, email, password, button, loginState);
  if (submitted !== 1) throw Error('Offline popup library blocked login');
  handleLoginSubmit(event, form, email, password, button, loginState);
  if (submitted !== 1) throw Error('Duplicate submit was accepted');
  handlePageShow(button, 'Login', loginState);
  if (button.disabled || loginState.isSubmitting) throw Error('Back navigation kept login disabled');
  email.value = '';
  handleLoginSubmit(event, form, email, password, button, loginState);
  if (submitted !== 1 || focused !== 'email') throw Error('Missing email validation failed');
  handleServerLoginError();
`,
  context
);
assert.ok(
  logs.some((entry) => entry[1]?.valid === false && entry[1]?.message === 'Please fill in: Email.')
);
console.log('Passed: login without CDN, duplicate submit, back navigation and email validation.');
