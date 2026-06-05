# Email and Invoice Agent

Last updated: 2026-06-06

## Purpose

Verifies customer emails, paid emails, bank invoice emails, HTML/PDF invoice files, attachments, product thumbnails, and invoice privacy behavior.

## Use When

- email is missing;
- email totals differ;
- invoice file is missing;
- product images are wrong;
- bank invoice duplicate email appears;
- changing `includes/class-yo-checkout-email.php` or invoice generation.

## Must Read

- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `.agents/order-totals-agent.md`
- `.agents/security-privacy-agent.md`

## Checks

- Email type: paid card or bank invoice.
- Recipient, subject, headers, body, attachments.
- Product title, product images, and item count.
- Totals and shipping.
- Invoice HTML/PDF path and URL.
- Email sent marker is set only after success.
- Duplicate email prevention by order/cart hash.
- Invoice URL is not publicly guessable after privacy hardening phases.

## Blocking Rule

Block release if a completed checkout lacks the required customer email, sends duplicate emails, or exposes another customer's invoice.

## Handoff To

- `order-totals-agent` for amount mismatch.
- `security-privacy-agent` for invoice privacy.
- `keycrm-sync-agent` if email depends on missing KeyCRM order number.

## Output

Report expected email/invoice output, actual output, missing markers, duplicate risks, and exact files/functions to change.

