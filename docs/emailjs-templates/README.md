# EmailJS template source files

These files are ready-to-paste HTML bodies for the four EmailJS templates documented in [emailjs-notifications.md](../emailjs-notifications.md).

## Create the templates in EmailJS

For each template:
1. Open EmailJS Dashboard → Email Templates → Create New Template.
2. Set **To Email** to `{{to_email}}`.
3. Set **To Name** to `{{to_name}}`.
4. Set **Subject** to `{{subject}}` (or use a fixed subject listed below).
5. Open the HTML/code editor and paste the matching file's complete contents.
6. Save and send a test using synthetic data before connecting it to RepairTrack.
7. Copy the generated template ID into the matching local `emailjs_templates` config value.

| File | Suggested subject | Required template ID config |
|---|---|---|
| [password-reset.html](password-reset.html) | RepairTrack password reset | `password_reset` |
| [customer-update.html](customer-update.html) | Repair update for {{ticket_code}} | `customer_update` |
| [customer-payment.html](customer-payment.html) | Payment information for {{ticket_code}} | `customer_payment` |
| [staff-alert.html](staff-alert.html) | RepairTrack staff alert: {{event_type}} | `staff_alert` |

## Variable names

All templates expect `to_email`, `to_name`, and `subject` for recipient configuration.

- Password reset: `reset_url`, `expires_minutes`.
- Customer update: `customer_name`, `ticket_code`, `device_label`, `status`, `message`, `branch_name`, `branch_phone`.
- Customer payment: `customer_name`, `ticket_code`, `payment_amount`, `remaining_balance`, `branch_name`.
- Staff alert: `event_type`, `title`, `message`, `ticket_code`, `branch_name`, `target_url`.

The HTML is template source only; creating files in GitHub does not create templates inside your EmailJS account. You must paste and save them in the dashboard and copy the resulting template IDs into local configuration. Do not paste private keys into template code or commit them.

## Security and test data

- Use the EmailJS public key only where the EmailJS client library requires it. Keep the private key server-side in ignored local config, if used.
- The password reset link is a one-time PHP-generated URL. Do not substitute an arbitrary token or use a static link.
- Customer templates must receive only customer-safe fields. Never pass technician internal notes, customer portal PINs, passwords, reset tokens outside the reset URL, or private evidence.
- Staff template recipients must be selected by RepairTrack's server-side role/branch routing, not by a user-editable template variable.
- Test with synthetic names, ticket codes, and email addresses first.
