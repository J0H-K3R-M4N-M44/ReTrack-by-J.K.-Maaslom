# EmailJS template source files

These are the two HTML bodies to paste into the EmailJS dashboard. They cover all RepairTrack email use cases while staying within a two-template account limit.

## Templates

1. **[password-reset.html](password-reset.html)** — password recovery only.
2. **[notification.html](notification.html)** — customer repair updates, customer payment notices, and optional staff alerts.

## Dashboard setup

For each template:

1. Open EmailJS Dashboard → Email Templates → Create New Template.
2. Set **To Email** to `{{to_email}}`.
3. Set **To Name** to `{{to_name}}`.
4. Set **Subject** to `{{subject}}`.
5. Paste the complete matching HTML file into the template body and save.
6. Send a test with synthetic data before connecting it to RepairTrack.
7. Copy the generated template ID into the matching local `emailjs_templates` config value.

| File | Suggested subject | Config key |
|---|---|---|
| [password-reset.html](password-reset.html) | RepairTrack account password reset | `password_reset` |
| [notification.html](notification.html) | Dynamic: `{{subject}}` | `notification` |

## Notification template modes

The PHP sender continues to identify the logical message type in the outbox, then maps all notification types to the one `notification` template ID.

| Mode | Rendering flag | Main fields |
|---|---|---|
| Customer update | `show_customer_update` | `customer_name`, `ticket_code`, `device_label`, `status`, `message`, `branch_name`, `branch_phone` |
| Customer payment | `show_customer_payment` | `customer_name`, `ticket_code`, `payment_amount`, `remaining_balance`, `branch_name` |
| Staff alert | `show_staff_alert` | `event_type`, `title`, `message`, `ticket_code`, `branch_name`, `target_url` |

PHP derives and adds the active mode flag from each outbox row's logical template key immediately before sending. All three modes also receive `to_email`, `to_name`, and `subject`. Older queued rows do not need to be rewritten.

## Security and test data

- Keep the EmailJS private key server-side in the ignored local `config/config.php`, if used. Never put it in a template, browser code, screenshot, or commit.
- The password reset link is a one-time PHP-generated URL. Never substitute a static token or send it through the notification template.
- Customer email is queued only when the customer has an explicit email-update consent timestamp.
- Customer messages must contain only customer-safe fields. Never pass a portal PIN, internal notes, passwords, reset tokens outside the reset URL, private evidence, or staff-only information.
- Staff recipients are selected by RepairTrack's server-side role/branch routing, not by a user-editable template variable.
- Use synthetic names, ticket codes, and email addresses for all tests.

The HTML files are source only. Adding them to GitHub does not create or update templates inside your EmailJS account. You must paste and save both templates in the dashboard and copy their IDs into local configuration.
