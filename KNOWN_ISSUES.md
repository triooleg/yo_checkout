# YOleotard Checkout Known Issues

Codex must read this file before making any code change.

This file tracks known risks, limitations, and unresolved technical debt.

## Active Issues

### Main PHP file is very large

Status: known technical debt.

Details:

- `yoleotard-checkout-invoice.php` contains most backend behavior: settings, AJAX, REST webhooks, KeyCRM, shipping, payment finalization, invoice generation, emails, and sold-item hiding.
- This makes changes harder to review and increases the risk of accidental regressions.

Handling:

- Do not rewrite the full file at once.
- New backend features should be added as separate classes, preferably under `includes/`.
- Existing logic should be extracted gradually when a task touches that area.

### PHP CLI may be unavailable locally

Status: environment limitation.

Details:

- A previous `php -l yoleotard-checkout-invoice.php` check failed because `php` was not available in PATH.

Handling:

- Try `php -l` after each PHP change.
- If PHP is still unavailable, record the failed check in `DEVELOPMENT_LOG.md`.
- Install or expose PHP CLI in PATH to enable proper syntax checks.

### GitHub CLI is unavailable locally

Status: environment limitation.

Details:

- `gh` was not recognized in the current PowerShell environment.

Handling:

- Use normal `git commit` and `git push` for rollback points when credentials are available.
- Install GitHub CLI only if PR creation, GitHub auth inspection, or GitHub issue/PR workflows are needed.

## Watch Areas

- Payment finalization must remain idempotent. Repeated webhooks or polling must not create duplicate KeyCRM payments, emails, or status updates.
- Checkout totals must remain consistent across frontend cart, backend order meta, KeyCRM payloads, bank invoices, card fees, shipping, and emails.
- YOOtheme sold-item hiding must stay precise and avoid hiding whole product grids or unrelated content.
- Guest checkout AJAX endpoints must keep nonce and buyer/session handling intact.
