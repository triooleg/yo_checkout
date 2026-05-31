# YOleotard Checkout Plugin Map

Last updated: 2026-05-31

## Purpose

This file is the navigation map for the YOleotard checkout plugin. Before any new development task, Codex must read `PROJECT_CONTEXT.md`, `PLUGIN_MAP.md`, `DEVELOPMENT_LOG.md`, and `KNOWN_ISSUES.md`.

Rules for future work:

- Do not change existing behavior unless the current task explicitly requires it.
- New functionality should be placed in a separate PHP/JS/CSS file and included/enqueued from the main plugin file, so `yoleotard-checkout-invoice.php` does not keep growing.
- New backend functionality should gradually move into separate classes, preferably under `includes/`.
- If an existing function must be worked on, first consider moving the related block into a separate included file, then make the change there.
- After each task, update `DEVELOPMENT_LOG.md`.
- If code structure, hooks, files, settings, AJAX actions, REST routes, or frontend behavior changed, update this map.
- After each task, check syntax and basic runtime logic as much as possible in the local environment.
- After each completed code change, save a rollback point in the repository when `.git` is available: check status, add the task files, create a clear commit, and push to GitHub when credentials allow it. If git or push is not available, record that limitation in `DEVELOPMENT_LOG.md` and keep the changed-file list precise.

## File Structure

- `yoleotard-checkout-invoice.php`
  Main WordPress plugin file. Contains plugin metadata, compatibility fallbacks, class `YO_Checkout_Invoice_Plugin`, admin settings, frontend modal rendering, AJAX handlers, REST webhooks, KeyCRM integration, shipping logic, payment finalization, YOOtheme sold-item hiding, invoice generation, and email sending.

- `assets/yo-checkout.js`
  Frontend checkout logic. Adds buy/cart buttons, manages cart state in browser storage, product reservations, customer form flow, promo code application, shipping option selection, card/bank payment actions, payment polling, success step, and Google Reviews opt-in trigger.

- `assets/yo-checkout.css`
  Frontend checkout styles for modal steps, cart, payment iframe, receipts, mobile behavior, promo/reservation badges, notifications, and related UI.

- `CHANGELOG-v*.txt`
  Version notes from previous plugin updates. Current visible version in plugin header is `4.0.25`.

- `PROJECT_CONTEXT.md`
  Project-level context and required rules for future Codex work.

- `DEVELOPMENT_LOG.md`
  Current development history, verification notes, and repository rollback points.

- `KNOWN_ISSUES.md`
  Known limitations, environment blockers, and risky areas to watch.

- `WORK_HISTORY.md`
  Legacy initial mapping/history file kept for continuity. Use `DEVELOPMENT_LOG.md` for new entries.

## Main PHP Class

Class: `YO_Checkout_Invoice_Plugin`

Constants:

- `OPT = yo_checkout_invoice_settings`
  WordPress option key for plugin settings.
- `CPT = yo_invoice_order`
  Custom post type for local checkout orders.
- `NS = yoleotard/v1`
  REST namespace for payment routes.

## WordPress Hooks

Registered in `__construct()` near the top of `yoleotard-checkout-invoice.php`.

Admin and setup:

- `init` -> `register_cpt()`
- `admin_menu` -> `admin_menu()`
- `admin_init` -> `register_settings()`
- `admin_init` -> `ensure_shipping_tables()`
- `admin_post_yo_shipping_import_csv` -> `admin_import_shipping_csv()`
- `admin_post_yo_shipping_import_zone_rates` -> `admin_import_shipping_zone_rates()`
- `admin_post_yo_shipping_clear_rates` -> `admin_clear_shipping_rates()`
- `admin_notices` -> `dompdf_admin_notice()`
- `admin_post_yo_checkout_install_dompdf` -> `install_dompdf()`

Frontend:

- `wp_enqueue_scripts` -> `enqueue()`
- `wp_footer` -> `render_modal()`
- `wp_footer` priority 50 -> `render_sold_items_hider()`
- `wp_footer` priority 60 -> `render_google_customer_reviews_scripts()`

AJAX actions:

- `yo_checkout_create_order` -> `ajax_create_order()`
- `yo_checkout_apply_promo_code` -> `ajax_apply_promo_code()`
- `yo_checkout_update_shipping_option` -> `ajax_update_shipping_option()`
- `yo_checkout_validate_cart_items` -> `ajax_validate_cart_items()`
- `yo_checkout_reserve_item` -> `ajax_reserve_item()`
- `yo_checkout_release_reservation` -> `ajax_release_reservation()`
- `yo_checkout_get_reservations` -> `ajax_get_reservations()`
- `yo_checkout_start_card_payment` -> `ajax_start_card_payment()`
- `yo_checkout_create_bank_invoice` -> `ajax_create_bank_invoice()`
- `yo_checkout_check_payment_status` -> `ajax_check_payment_status()`
- `yo_checkout_final_order_status` -> `ajax_final_order_status()`
- `yo_checkout_wayforpay_form` -> `ajax_wayforpay_form()`
- `yo_checkout_wayforpay_return` -> `ajax_wayforpay_return()`

Each AJAX action is registered for both logged-in and guest users.

REST routes:

- `POST /wp-json/yoleotard/v1/mono-webhook` -> `mono_webhook()`
- `GET /wp-json/yoleotard/v1/wayforpay-form` -> `wayforpay_form()`
- `POST /wp-json/yoleotard/v1/wayforpay-webhook` -> `wayforpay_webhook()`

Scheduled actions:

- `yo_checkout_check_unpaid_order` -> `check_unpaid_order()`
- `yo_checkout_deferred_payment_finalizer` -> `deferred_payment_finalizer()`

## PHP Functional Areas

Line numbers are approximate and should be refreshed after larger edits.

- Plugin bootstrap and mbstring fallbacks: lines 1-38.
- Constructor and hook registration: lines 39-90.
- Default settings and country list: lines 93-241.
- Custom post type and admin menu: lines 244-256.
- Shipping DB table, CSV parsing, admin import/clear: lines 259-509.
- Settings registration, Dompdf install/status, settings sanitization: lines 513-588.
- Admin settings page and fields: lines 607-921.
- Frontend enqueue and localized `YOCheckout` config: lines 922-957.
- Google Customer Reviews script output: lines 959-1023.
- Checkout modal HTML: lines 1025-1080.
- Security nonce helper: line 1082.
- Promo-code helpers and AJAX: lines 1086-1110 and 1273-1316.
- Product reservation helpers and AJAX: lines 1114-1221.
- Cart availability AJAX: lines 1223-1271.
- Order creation AJAX: lines 1318-1508.
- Shipping option update AJAX: lines 1510-1530.
- Payability checks and card-payment start: lines 1532-1636.
- Bank invoice creation AJAX: lines 1638-1672.
- Final order status and payment status polling AJAX: lines 1674-1822.
- REST route registration and payment webhooks: lines 1824-1950.
- WayForPay helpers: lines 1964-2100.
- Checkout input sanitization and order data helpers: lines 2102-2315.
- KeyCRM marker/reuse/deduplication lookup logic: lines 2318-2755.
- KeyCRM create/update/request/product sync logic: lines 2757-3295.
- Country normalization and shipping calculations: lines 3304-3770.
- Shipping, bank total, and card fee data: lines 3770-3859.
- KeyCRM payment/comment updates: lines 3861-3884.
- Successful card payment finalization: lines 3886-4024.
- Auto-hide sold items after payment and logs: lines 4026-4114.
- Title matching and YOOtheme content modification helpers: lines 4116-4613.
- Frontend fallback sold-item hider script: lines 4611-4713.
- Unpaid-order cancellation: lines 4715-4719.
- Bank details and invoice generation: lines 4721-5061.
- Email helpers and outgoing bank/paid emails: lines 5063-5240.

## Frontend JS Flow

File: `assets/yo-checkout.js`

High-level behavior:

1. On `DOMContentLoaded`, initializes local state: selected product, cart items, current order/payment state, payment polling, reservation clock offset.
2. Creates persistent browser IDs in localStorage:
   - `yo_checkout_buyer_id_v1`
   - `yo_checkout_session_id_v1`
   - `yo_checkout_local_order_id_v1`
   - `yo_checkout_keycrm_order_id_v1`
   - `yo_checkout_cart_keycrm_marker_v1`
   - `yo_checkout_cart_v1`
3. Adds checkout/cart buttons to product cards found in YOOtheme markup.
4. Reads product data from card DOM: title, image, price, sale price, weight.
5. Manages cart UI, duplicate prevention, item removal, local cart persistence, cart cleanup if unavailable.
6. Applies and displays promo code discounts.
7. Reserves products after add-to-cart and refreshes reservation badges from server.
8. Submits customer/order details through `yo_checkout_create_order`.
9. Shows receipt/shipping choices and lets the customer choose card or bank transfer.
10. Starts card payment through `yo_checkout_start_card_payment`.
11. Starts bank invoice through `yo_checkout_create_bank_invoice`.
12. Polls payment state with `yo_checkout_check_payment_status`.
13. After payment, waits for final KeyCRM order number using `yo_checkout_final_order_status`, then shows success step.
14. Clears cart and hides purchased products locally after successful payment/invoice flow.

Important frontend entry points:

- `post(action, formData)` adds `action` and `YOCheckout.nonce`, then calls `YOCheckout.ajaxUrl`.
- Click on `.yo-main-buy-btn` adds/reserves product and opens checkout.
- Submit `#yo-pay-form` creates or updates local order.
- Click `#yo-pay-card` starts card payment.
- Click `#yo-pay-bank` creates bank invoice.
- `startPaymentPolling()` and related helpers manage card-payment completion.

## Localized Frontend Config

Created in PHP `enqueue()` and exposed as global `window.YOCheckout`.

Important values include:

- `ajaxUrl`
- `nonce`
- `countries`
- Promo settings and badge styles
- Google Reviews settings
- Cart notification settings
- Reservation badge text

When adding frontend features, prefer adding explicit config keys in `enqueue()` instead of hardcoding server-side data in JS.

## Data Storage

WordPress option:

- `yo_checkout_invoice_settings`
  Main plugin settings.

Custom post type:

- `yo_invoice_order`
  Local checkout order records. Order data is stored mostly in post meta.

Custom database table:

- `{$wpdb->prefix}yo_shipping_rates`
  Structured shipping tariff rows imported through admin CSV tools.

Other options:

- `yo_checkout_sold_hidden_titles`
  Titles hidden after successful payment.

Uploads:

- `wp-content/uploads/yoleotard-invoices/`
  Generated invoice HTML/PDF files.
- `wp-content/uploads/yoleotard-checkout/vendor/dompdf/`
  Persistent Dompdf installation target.

Browser storage:

- Customer/session/order/cart marker IDs are saved in localStorage/sessionStorage/cookies by `assets/yo-checkout.js`.

## Integrations

Monobank:

- Settings tab: Monobank token and card fee percent.
- Card start: `start_monobank_payment()`.
- Webhook: `mono_webhook()`.
- Payment status checks feed into shared finalization.

WayForPay:

- Settings tab: merchant login, secret key, currency, fee percent, test modes, country routing.
- Card start: `start_wayforpay_payment()`.
- Form rendering: `wayforpay_form()` / `render_wayforpay_autosubmit_html()`.
- Webhook: `wayforpay_webhook()`.
- Country routing: `is_wayforpay_country()`.

KeyCRM:

- Settings tab: token, source/currency/tag/status IDs, payment method IDs.
- Main flow creates/updates buyer/order and records payments.
- Current v4.0.25 behavior: after successful card payment, the frontend waits for the real KeyCRM order ID before showing Step 4.

Dompdf:

- Used for PDF invoice generation.
- If missing, plugin can still generate HTML invoice and shows admin notice.

Google Customer Reviews:

- Script rendered in footer.
- Success step may call `window.YOCheckoutGoogleReviews()` with order payload.

YOOtheme:

- Product cards are discovered from YOOtheme-generated DOM.
- After purchase, plugin attempts to hide sold product cards both server-side in YOOtheme content and frontend-side as fallback.

## Development Notes

- The main PHP file is large. Prefer extracting new areas into `includes/*.php` and loading them from the main file.
- For frontend additions, prefer new files under `assets/` and enqueue/localize them from PHP.
- If touching payment, KeyCRM, or sold-item hiding, preserve idempotency. Many methods use post meta flags/locks to avoid duplicate payments, emails, and status updates.
- If touching AJAX, always verify nonce handling and guest-user behavior.
- If touching checkout pricing, check product price, promo discount, shipping, card fee, bank total, and KeyCRM payment amount together.
- If touching shipping, check all sources: structured DB rows, structured settings text, fallback country table, Nova Post API fallback, and currency conversion.

## Verification Checklist

Use the checks that fit the change:

- PHP syntax: `php -l yoleotard-checkout-invoice.php`
- JS syntax: `node --check assets/yo-checkout.js`
- CSS sanity: inspect changed selectors and check for missing braces.
- WordPress admin: settings page loads and saves without wiping unrelated settings.
- Frontend: modal opens, cart persists, order form submits, payment method buttons work.
- Payment: no duplicate KeyCRM orders/payments/emails after repeated webhook/polling calls.
- Shipping: country autocomplete, available options, selected option, and totals remain consistent.
- Invoice/email: HTML/PDF invoice paths and email totals match checkout totals.
- Rollback point: if the folder is a git repository, run `git status`, commit the completed task, push to GitHub when possible, and mention the commit hash in `DEVELOPMENT_LOG.md`.
