const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Verify the enhancement leaves the browser responsible for the real POST.
const handlers = {};
const button = { disabled: false, textContent: 'Login' };
const form = {
  querySelector() {
    return button;
  },
  addEventListener(name, callback) {
    handlers[name] = callback;
  },
};
const context = vm.createContext({
  document: {
    querySelector() {
      return form;
    },
  },
  window: {
    addEventListener(name, callback) {
      handlers[name] = callback;
    },
  },
});
vm.runInContext(fs.readFileSync('js/login.js', 'utf8'), context);
let prevented = false;
handlers.submit({
  preventDefault() {
    prevented = true;
  },
});
assert.equal(prevented, false);
assert.equal(button.disabled, true);
handlers.submit({
  preventDefault() {
    prevented = true;
  },
});
assert.equal(prevented, true);
handlers.pageshow();
assert.equal(button.disabled, false);
assert.equal(button.textContent, 'Login');
console.log('PASS: native login submit, duplicate guard and back navigation.');
