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

### PHP CLI availability was restored

Status: resolved environment note.

Details:

- A previous `php -l yoleotard-checkout-invoice.php` check failed because `php` was not available in PATH.
- On 2026-05-31, PHP was found at `D:\Projects\php-8.5.6-nts-Win32-vs17-x64\php.exe`.
- `php -l yoleotard-checkout-invoice.php` passed with no syntax errors.

Handling:

- Try `php -l` after each PHP change.
- If PHP becomes unavailable again, record the failed check in `DEVELOPMENT_LOG.md`.

### GitHub CLI is unavailable locally

Status: environment limitation.

Details:

- `gh` was not recognized in the current PowerShell environment.

Handling:

- Use normal `git commit` and `git push` for rollback points when credentials are available.
- Install GitHub CLI only if PR creation, GitHub auth inspection, or GitHub issue/PR workflows are needed.

### Sold-item auto-hide previously logged local IDs and backed up too much meta

Status: fixed in current code, monitor after next live Monobank purchase.

Details:

- Older admin logs used local WordPress checkout post IDs like `local order #21953`, which looked arbitrary compared with the intended KeyCRM order number.
- Auto-hide backed up the full page meta array before every first order-specific hide, including previous `_yo_checkout_autohide_backup_*` entries. On a YOOtheme page with repeated purchases this could recursively grow backup meta and increase timeout/memory risk before the final hide-result log was written.

Handling:

- Sold-item hiding now lives in `includes/class-yo-checkout-sold-items.php`.
- Logs now prefer KeyCRM `order_id`, formatted as `Order #KEYCRM (local #ID)`.
- Backup now excludes previous auto-hide backup meta.
- Watch the next real successful card purchase for a final log line such as `Updated post_content.`, `Updated meta: ...`, or `YOOtheme Builder JSON grid item was not found...`.

### Bank invoice checkout can hit stale KeyCRM buyer IDs

Status: fixed in current code, monitor next live invoice test.

Details:

- The bank invoice path can reuse an existing unpaid KeyCRM order from local/session/cart markers.
- If the reused local draft contains an old/deleted KeyCRM `buyer_id`, KeyCRM rejects the order update with `buyer.id is invalid`.
- Card payment did not show this after the recent change because the successful-card path creates a fresh KeyCRM buyer/order after payment.

Handling:

- KeyCRM update/create now detects invalid `buyer.id`, clears the stale local `buyer_id`, creates a new KeyCRM buyer from the current checkout data, and retries once.
- If the retry still fails, the KeyCRM error remains visible so the next issue is not hidden.
- Test with bank invoice after uploading the next ZIP: expected result is invoice generation, KeyCRM order creation/update, and no browser alert containing `buyer.id is invalid`.

### Repeated bank invoice requests could duplicate emails and KeyCRM product rows

Status: fixed in v4.0.28-v4.0.30, monitor next multi-item invoice test.

Details:

- A repeated `yo_checkout_create_bank_invoice` request could send a second bank invoice email for the same KeyCRM order.
- When the same KeyCRM order was updated again, product rows could be appended because existing KeyCRM product row IDs were not attached before `PUT /order/{id}`.
- This was visible with a multi-item invoice order: one email showed one item, a later email showed two items, and KeyCRM displayed a duplicated first item.

Handling:

- Frontend bank invoice click now has an in-progress guard.
- Before creating a bank invoice, the frontend now saves the current cart/order data through `yo_checkout_create_order`, so an older local order cannot be reused with stale product rows.
- Backend bank invoice creation now uses a short lock, cart hash reuse, and per-hash email marker.
- Active lock responses now return a retryable `preparing` state instead of a visible error alert.
- KeyCRM update now matches existing product rows before update and avoids unsafe zero-quantity row deletion.

### Expired promo discount could survive in a stored cart/order

Status: fixed in v4.0.30, monitor after promo date changes.

Details:

- Browser localStorage could keep `applied_cart_promo` from a previous day.
- A reused local bank-invoice order could still contain old `promo_code_applied` and per-item promo discount meta after the promo expiration date.
- This could make a new invoice show a promo discount after the promo was no longer active.

Handling:

- Frontend cart saving/loading and item discount calculation now require the current localized promo setting to be active.
- Bank invoice creation now removes invalid or expired promo data from the local order before bank totals, invoice files, email, and KeyCRM payment data are generated.

## Watch Areas

- Payment finalization must remain idempotent. Repeated webhooks or polling must not create duplicate KeyCRM payments, emails, or status updates.
- Checkout totals must remain consistent across frontend cart, backend order meta, KeyCRM payloads, bank invoices, card fees, shipping, and emails.
- YOOtheme sold-item hiding must stay precise and avoid hiding whole product grids or unrelated content.
- Guest checkout AJAX endpoints must keep nonce and buyer/session handling intact.
