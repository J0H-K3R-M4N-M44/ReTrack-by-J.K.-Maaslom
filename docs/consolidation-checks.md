# Consolidation checks

## Bugs fixed before consolidation

- Opening a staff demo URL no longer silently changes a public or customer role to Admin; it opens the demo login instead.
- A malformed percent-encoded tracking link now returns home with a message instead of throwing a URI error.
- Opening another ticket through a code or tracking URL resets the selected device and customer verification.
- PHP login can submit when the optional SweetAlert CDN is unavailable. Missing-input errors still use the inline message and focus the appropriate field.
- Login audit handlers now read the email field actually supplied by the form, instead of an undefined username field.
- Browser-side password validation no longer trims meaningful spaces from passwords.

## Verification

`tests/demo-regression.mjs` reproduced the unwanted Admin switch before the fix. After the fix it passed direct-route and malformed-link checks, 128 staff screen renders (16 screens, four roles, two viewport sizes), overpayment rejection, full-balance payment and tracking verification/device reset. Screen rendering tests check that pages load without JavaScript exceptions; they are not proof of production authorization or complete visual correctness.

`tests/login-regression.cjs` passed login submission without SweetAlert, duplicate-submit prevention, back-navigation recovery and missing-email handling.

Formatting and JavaScript/PHP parsing were checked. Both database SQL scripts and the third-party QR library remain unchanged from the latest readability branch. PHP execution, MySQL integration, live mail delivery, Maps API access and camera scanning were not tested in this environment. The browser prototype is not production authentication.

## Repeat locally

From the repository root:

```sh
npm install --no-save --package-lock=false prettier@3.9.10 @prettier/plugin-php@0.26.0 playwright-core@1.65.0
npx prettier --check "**/*.{php,js,mjs,cjs,css,html}"
node tests/login-regression.cjs
node tests/demo-regression.mjs
```

The browser test uses installed Chrome. To use a different Chromium executable, set the `CHROME_PATH` environment variable to its path before running the test. No real email or payment is sent by these tests.
