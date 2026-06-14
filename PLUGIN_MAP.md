# YOleotard Checkout Plugin Map

Last updated: 2026-06-06

## Purpose

This file is the navigation map for the YOleotard checkout plugin. Before any new development task, Codex must read `PROJECT_CONTEXT.md`, `PLUGIN_MAP.md`, `DEVELOPMENT_LOG.md`, `KNOWN_ISSUES.md`, `KNOWN_WORKING_FEATURES.md`, and `AUDIT_REMEDIATION_MAP.md`.

Rules for future work:

- Do not change existing behavior unless the current task explicitly requires it.
- New functionality should be placed in a separate PHP/JS/CSS file and included/enqueued from the main plugin file, so `yoleotard-checkout-invoice.php` does not keep growing.
- New backend functionality should gradually move into separate classes, preferably under `includes/`.
- If an existing function must be worked on, first consider moving the related block into a separate included file, then make the change there.
- Runtime behavior changes should update the plugin header version and append a concise entry to the single `CHANGELOG.txt` file.
- Do not create test ZIP archives unless the user explicitly requests one.
- Test archive packaging rule for this host: store local ZIPs in `plugin-archives/` and keep that folder excluded from git. Before a new exact-name ZIP is generated, preserve the existing `plugin-archives/yoleotard-checkout-invoice.zip` as the version it contains, for example `plugin-archives/yoleotard-checkout-invoice-v4.0.62.zip`, so the previous test package is not overwritten. The installable ZIP file itself must be named exactly `yoleotard-checkout-invoice.zip`, and inside it the only top-level plugin folder must be `yoleotard-checkout-invoice/`. Internal ZIP paths must use forward slashes (`/`), not Windows backslashes (`\`). This host may create a duplicate plugin folder if the ZIP filename includes a test/version suffix, and may create flat files with `\` in their names if the ZIP is built incorrectly.
- After each task, update `DEVELOPMENT_LOG.md`.
- If code structure, hooks, files, settings, AJAX actions, REST routes, or frontend behavior changed, update this map.
- After each task, check syntax and basic runtime logic as much as possible in the local environment.
- After each completed code change, save a rollback point in the repository when `.git` is available: check status, add the task files, create a clear commit, and push to GitHub when credentials allow it. If git or push is not available, record that limitation in `DEVELOPMENT_LOG.md` and keep the changed-file list precise.

## File Structure

- `yoleotard-checkout-invoice.php`
  Main WordPress plugin file. Contains plugin metadata, compatibility fallbacks, class `YO_Checkout_Invoice_Plugin`, admin settings, top-level admin menu, purchase report page, frontend modal rendering, AJAX handlers, REST webhooks, shipping logic, payment finalization, invoice generation, order-access checks, promo GIF media setting, bank-invoice Card Default marking hook, server availability response fields for storefront reserved-card sync, and thin wrappers for extracted services. Current visible version in the plugin header is `4.0.69`.

- `includes/class-yo-checkout-product-catalog.php`
  Server-side product catalog service added in Phase 1. Resolves trusted product data by stable `product_id` from the configured YOOtheme product source page, currently the same page ID used by sold-item auto-hide. When a product is resolved, checkout order snapshots use the server-resolved title, current price, original price, product discount, weight, and image. If a product cannot be resolved during the transition, checkout keeps the sanitized browser payload for compatibility and records `product_catalog_status` / `product_catalog_summary` diagnostics. v4.0.53 limits reads to small product-specific fragments and does not unserialize/JSON-encode the full YOOtheme meta tree during checkout creation. v4.0.54 also trusts title-derived matching when the stored Builder data has no literal `product_id` but the found card title generates the requested canonical product ID.

- `includes/class-yo-checkout-sold-items.php`
  Sold-item hiding and invoice-reservation service. Owns YOOtheme product availability checks, auto-hide after successful card payment, bank-invoice YOOtheme Panel Style `Card Default` marking after successful invoice email, sold-item/admin log writing, KeyCRM-aware order labels in logs, YOOtheme Builder status/style updates, safe page backup, and optional frontend fallback script rendering. v4.0.69 writes the Builder field as `props.panel_style = default` and ignores the old erroneous hidden `props.style = default` value for availability so a visible manual change back to `None` can unlock the card.

- `includes/class-yo-checkout-product-identity.php`
  Checkout-owned product identity service. Sanitizes provided DOM/feed product IDs and generates stable fallback product IDs from card titles when a YOOtheme card has no `id`, `data-feed-id`, or `data-product-id`. Fallback IDs follow the feed-style title slug and remove trailing `for height ...` text before slugging. Frontend checkout also mirrors `data-feed-id` into the card `id` when `id` is missing. Used by order sanitization, cart item storage, product-ID-aware reservation checks, availability checks, and sold-item matching.

- `includes/class-yo-checkout-promo.php`
  Promo code service. Owns promo configuration checks, expiration checks, discount calculation, AJAX promo application, recalculating local checkout totals after promo changes, and applying promo data during order creation.

- `includes/class-yo-checkout-google-reviews.php`
  Google Customer Reviews service. Owns footer script rendering for survey opt-in and optional merchant badge while preserving the frontend callback name `window.YOCheckoutGoogleReviews`.

- `includes/class-yo-checkout-monobank.php`
  Monobank payment service. Owns Monobank invoice creation, live/test token selection, per-order token-mode persistence, invoice status requests with the same saved token mode, local invoice-to-order mapping helpers, webhook `X-Sign` verification through the cached Monobank merchant public key, webhook amount/currency checks where provider fields are available, and webhook handling. Shared payment finalization remains in the main plugin.

- `includes/class-yo-checkout-keycrm.php`
  KeyCRM service. Owns KeyCRM marker/reuse lookup, buyer/order creation and update, product synchronization helpers, payment records, order comments, paid-card order creation, and raw KeyCRM API requests. The main plugin keeps thin wrapper methods so existing checkout/payment flows continue to call the same method names.

- `includes/class-yo-checkout-email.php`
  Customer email service. Owns shared HTML email rendering, product thumbnail blocks, bank invoice email sending, paid-card email sending, email headers, and email-specific sent/error meta updates. The main plugin keeps thin wrapper methods so existing invoice/card flows continue to call the same method names.

- `includes/class-yo-checkout-western-bid.php`
  Western Bid card-payment service. Owns Western Bid credentials access, local invoice/reference mapping, auto-submit payment form generation, return-page parent message, notify/webhook hash verification, paid amount/currency/status checks, safe notify meta snapshots, and queueing the shared deferred payment finalizer after verified completed payments.

- `includes/class-yo-checkout-order-access.php`
  Guest order-access service. Owns local checkout access-token generation, server-side token hashing, posted-token reading, legacy no-token compatibility, and reusable response fields for protected public AJAX order actions.

- `includes/class-yo-checkout-purchase-report.php`
  Admin purchase report service. Owns checkout snapshot persistence and the WordPress admin purchases table. The report reads local `yo_invoice_order` posts, shows customer/cart/payment/provider/order data, keeps expandable JSON snapshots for the data saved before payment, and supports status filtering plus 10/20/50 records per page pagination.

- `assets/yo-checkout.js`
  Frontend checkout logic. Adds buy/cart buttons, manages cart state in browser storage, stores local order access tokens, sends protected AJAX order tokens, product reservations, permanent invoice-reserved card state for YOOtheme Card Default product cards, server-availability sync that greys and disables product cards which the backend already considers unavailable, customer form flow, promo code application, whole-badge promo GIF tooltip created through the UIkit JavaScript API when the customer hovers, focuses, taps, or clicks the badge, shipping option selection, card/bank payment actions, passes selected payment method and terms confirmation before Step 3, payment polling, success step, and Google Reviews opt-in trigger. Enqueued with `filemtime()` as the script version so browser/cache layers receive the latest diagnostics and payment logic after plugin updates.

- `assets/yo-checkout.css`
  Frontend checkout styles for modal steps, cart, payment iframe, receipts, mobile behavior, promo/reservation badges, invoice-reserved overlay badges, promo GIF tooltip display, notifications, related UI, and the v4.0.66 desktop override that widens and compacts the YOOtheme `body.home .yo-height-filter` Ready-to-Ship filter without changing mobile filter rules.

- `CHANGELOG.txt`
  Single append-only version notes file for plugin functional changes. Current visible version in plugin header is `4.0.69`.

- `README.md`
  Russian-language public project overview. Describes what the plugin does, supported checkout flows, integrations, admin areas, protected working zones, test-candidate zones, verification commands, archive rules, and development rules.

- `plugin-archives/`
  Local ignored folder for generated plugin ZIP files. Do not commit this folder or its contents. Keep `yoleotard-checkout-invoice.zip` as the upload/install filename, and keep versioned local history copies such as `yoleotard-checkout-invoice-v4.0.62.zip` before overwriting the exact-name ZIP with a newer build.

- `WESTERN_BID_MIGRATION_MAP.md`
  Prepared implementation map for replacing WayForPay with Western Bid. Contains required code touchpoints, new settings/routes/meta, verification plan, and security notes. Does not store Western Bid secret credentials.

- `AUDIT_REMEDIATION_MAP.md`
  Staged implementation map based on the 2026-06-03 holistic audit. Defines the required security, payment-integrity, state-management, privacy, maintainability, and test-hardening phases that should be completed before adding unrelated new functionality. Its primary rule is to preserve confirmed working checkout behavior while improving internal authority and reliability.

- `REGRESSION_BASELINE.md`
  Phase 0 baseline for regression checks. Documents the stable v4.0.51 checkout endpoints, request/response fields, expected card/bank invoice flows, totals behavior, live-test matrix, and release criteria before later audit remediation phases change runtime logic.

- `PROJECT_MANAGER_START_GUIDE.md`
  Process template for onboarding a new manager or starting a similar plugin project. Defines how to structure project documents, development plans, task sequencing, verification, versioning, live testing, archives, and repository rollback points.

- `PROJECT_GOVERNANCE.md`
  Top-level project governance model. Defines intake, classification, agent selection, analysis, implementation permission, verification, documentation, live testing, release, archive, version, Git, blocking, and rollback gates.

- `.agents/`
  Project agent system. `project-orchestrator-agent.md` is the main coordinator for task intake, phase control, agent selection, blockers, implementation handoff, verification, documentation, and release flow. `README.md` defines global agent rules, workflow, blocking rules, and handoff format. The folder includes analysis and domain agents for checkout logic, payment integrity, order totals, KeyCRM sync, email/invoices, product identity/auto-hide, security/privacy, frontend checkout state, QA regression, release packaging, and documentation curation.

- `PROJECT_CONTEXT.md`
  Project-level context and required rules for future Codex work.

- `DEVELOPMENT_LOG.md`
  Current development history, verification notes, and repository rollback points.

- `KNOWN_ISSUES.md`
  Known limitations, environment blockers, and risky areas to watch.

- `KNOWN_WORKING_FEATURES.md`
  Confirmed working plugin areas that should not be changed without necessity.

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
- `admin_menu` -> `admin_menu()` registers the top-level `YOleotard Checkout` menu, `Settings` submenu, and `Purchases Report` submenu.
- `admin_init` -> `register_settings()`
- `admin_init` -> `ensure_shipping_tables()`
- `admin_post_yo_shipping_import_csv` -> `admin_import_shipping_csv()`
- `admin_post_yo_shipping_import_zone_rates` -> `admin_import_shipping_zone_rates()`
- `admin_post_yo_shipping_clear_rates` -> `admin_clear_shipping_rates()`
- `admin_notices` -> `dompdf_admin_notice()`
- `admin_post_yo_checkout_install_dompdf` -> `install_dompdf()`

Admin pages:

- `settings_page()` -> top-level checkout settings page with existing tabs.
- `purchase_report_page()` -> delegates the purchases table to `includes/class-yo-checkout-purchase-report.php`.

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
- `yo_checkout_western_bid_form` -> `ajax_western_bid_form()`
- `yo_checkout_western_bid_return` -> `ajax_western_bid_return()`

Each AJAX action is registered for both logged-in and guest users.

REST routes:

- `POST /wp-json/yoleotard/v1/mono-webhook` -> `mono_webhook()`
- `GET /wp-json/yoleotard/v1/wayforpay-form` -> `wayforpay_form()`
- `POST /wp-json/yoleotard/v1/wayforpay-webhook` -> `wayforpay_webhook()`
- `POST /wp-json/yoleotard/v1/western-bid-webhook` -> `western_bid_webhook()`

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
- Google Customer Reviews script output: delegated to `includes/class-yo-checkout-google-reviews.php` through `render_google_customer_reviews_scripts()`.
- Checkout modal HTML: lines 1025-1080.
- Security nonce helper: line 1082.
- Promo-code helpers and AJAX: delegated to `includes/class-yo-checkout-promo.php` through wrapper methods around lines 1109-1123 and AJAX action `ajax_apply_promo_code()`.
- Product reservation helpers and AJAX: lines 1114-1221.
- Cart availability AJAX: lines 1223-1271.
- Order creation AJAX: stores an order snapshot after `sanitize_order_input()` applies the server-side product catalog to cart items when possible. Product price/title/discount/weight browser fields remain accepted only as compatibility fallback during Phase 1.
- Shipping option update AJAX: lines 1510-1530.
- Payability checks and card-payment start: lines 1532-1636.
- Bank invoice creation AJAX: validates payable items, writes checkout-debug trace lines, removes invalid/expired promo data from stale local orders, recalculates bank totals, then generates or reuses the bank invoice by cart hash.
- Final order status and payment status polling AJAX: lines 1674-1822.
- REST route registration and payment webhooks: lines 1824-1950.
- WayForPay helpers: lines 1964-2100.
- Checkout input sanitization and order data helpers: lines 2102-2315.
- KeyCRM marker/reuse/deduplication lookup logic: lines 2318-2755.
- Product catalog, KeyCRM wrappers, create/update/request/product sync logic: order input now delegates product resolution to `includes/class-yo-checkout-product-catalog.php`; KeyCRM sync continues to read the stored order/cart snapshot.
- Country normalization and shipping calculations: lines 3304-3770.
- Shipping, bank total, and card fee data: lines 3770-3859.
- KeyCRM payment/comment updates: lines 3861-3884.
- Successful card payment finalization: lines 3886-4024.
- Auto-hide sold items after payment and logs: delegated to `includes/class-yo-checkout-sold-items.php` through wrapper methods around lines 4036-4041.
- Title matching and YOOtheme content modification helpers: service-owned in `includes/class-yo-checkout-sold-items.php`; legacy private helper copies still exist in the main file and can be removed in a later cleanup.
- Frontend fallback sold-item hider script: delegated to `includes/class-yo-checkout-sold-items.php`; legacy inline copy remains below the delegating `return` for transition safety.
- Unpaid-order cancellation: lines 4715-4719.
- Bank details and invoice generation: lines 4721-5061.
- Customer email rendering and outgoing bank/paid emails: delegated to `includes/class-yo-checkout-email.php` through wrapper methods near the end of the main file.

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
5. Manages cart UI, duplicate prevention, item removal, local cart persistence, cart cleanup if unavailable. If a product card has `data-feed-id` but no DOM `id`, checkout copies that feed ID into `id` and uses the same value as `data-product-id`; only cards without any DOM/feed ID receive a deterministic fallback generated from the title using feed-style slug rules without the trailing height suffix. Cart items, reservations, reservation release, and Step 3 payment checks use `product_id` as the primary identity key; title matching is only a compatibility fallback.
6. Applies and displays promo code discounts.
7. Reserves products after add-to-cart and refreshes reservation badges from server.
8. Submits customer/order details through `yo_checkout_create_order`.
9. Shows receipt/shipping choices and lets the customer choose card or bank transfer.
10. Starts card payment through `yo_checkout_start_card_payment`.
11. Starts bank invoice by first saving the current cart through `yo_checkout_create_order`, then calling `yo_checkout_create_bank_invoice`.
12. Bank invoice confirmation shows the invoice links plus a UIkit confirmation card with KeyCRM order number, waiting-for-payment status, expected bank-transfer timing, and next-step processing notes.
13. Polls payment state with `yo_checkout_check_payment_status`.
14. After payment, waits for final KeyCRM order number using `yo_checkout_final_order_status`, then shows success step.
15. Clears cart and hides purchased products locally after successful payment/invoice flow.

Important frontend entry points:

- `post(action, formData)` adds `action` and `YOCheckout.nonce`, then calls `YOCheckout.ajaxUrl`.
- Click on `.yo-main-buy-btn` adds/reserves product and opens checkout.
- Submit `#yo-pay-form` creates or updates local order.
- Click `#yo-pay-card` starts card payment.
- Click `#yo-pay-bank` creates bank invoice with a frontend/server debug ID. If an AJAX connection or parse error occurs, the alert shows action, HTTP status, debug ID, local ID, KeyCRM marker, cart count, and a response excerpt.
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

- Settings tab: active Monobank token mode, Monobank live X-Token, Monobank test X-Token, and card fee percent.
- When a Monobank invoice is created, the selected token mode is saved to order meta `mono_token_mode`; later status polling for that invoice uses the saved mode so switching the admin setting does not break existing payment checks.
- Card start, invoice status requests, invoice mapping, webhook signature verification, and webhook handling are delegated to `includes/class-yo-checkout-monobank.php`.
- Webhook: `mono_webhook()` now verifies the raw request body against the `X-Sign` header using the Monobank public key from `/api/merchant/pubkey`, cached per live/test token mode. Invalid or missing signatures are rejected without marking the order paid. If the cached key fails verification, the service refreshes the public key once and retries. For signed paid webhooks, provider `ccy` and `amount` / `finalAmount` are compared with the stored Monobank card total when those fields are present. Authenticated invoice status polling remains the recovery path when webhook delivery is delayed or rejected.
- Multi-item card payments use a short Monobank payment label such as `custom leotard x5` for `merchantPaymInfo.destination`, `comment`, and `basketOrder.name` so provider length limits are not exceeded.
- Frontend opens Monobank `pageUrl` in a separate payment window/tab instead of the Step 3 iframe, while the original checkout modal remains open and polls for payment status/final order completion. Monobank return URL points back to the site homepage with `yo_checkout_return=card`; the popup notifies the opener checkout and closes itself.
- Shared successful-payment finalization remains in the main plugin.

Western Bid:

- Settings tab: Western Bid login, secret key, currency, payment gate, fee percent, country routing, and test provider forcing.
- Card start: `start_western_bid_payment()`, delegated to `includes/class-yo-checkout-western-bid.php`.
- Form rendering: `ajax_western_bid_form()` auto-submits a hidden form to `https://shop.westernbid.info`.
- Stripe/PayPal buyer handoff: checkout passes full customer contact/address fields, ISO-2 country code, and provider aliases while keeping the full local card total in `amount`. Delivery is included in that local total and is not sent as a second provider shipping charge, reducing the chance that Stripe asks for the full delivery address again or reports a different paid amount.
- Return page: `ajax_western_bid_return()` posts `yo_western_bid_return` to the opener/top checkout window and to the parent frame for legacy compatibility.
- Webhook: `western_bid_webhook()` verifies Western Bid hash, `wb_result=VERIFIED`, `payment_status=Completed`, amount, and currency before marking a local order paid. Western Bid may prefix the original checkout invoice with the merchant login in notify payloads, for example `{wb_login}-YO-WB-...`; webhook handling verifies the hash against the received full invoice but maps the order by both the full and normalized local invoice. Stripe notify payloads from Western Bid may sign the raw `mc_gross` string such as `1` instead of normalized `1.00`, and may send an empty `mc_currency`; the handler verifies the hash against raw/normalized amount candidates, then still compares the normalized amount strictly and treats an empty currency as the configured Western Bid currency. Western Bid form preparation and webhook verification write safe `checkout-debug` lines to the existing Hiding/admin log without logging the secret key.
- Country routing: `is_western_bid_country()` uses `western_bid_countries`; `wayforpay_*` settings/handlers remain only as a temporary legacy rollback layer.
- Frontend behavior: card provider payment pages are opened in a separate window/tab instead of `#yo-payment-frame`, because PayPal, Stripe Checkout, and some provider/card-country checks do not reliably run inside iframes. The modal stays on Step 3, shows a waiting/open-payment message, and continues polling. Provider return pages notify the opener checkout using `postMessage`; same-site card returns such as Monobank homepage return are detected by `yo_checkout_return=card` and the popup attempts to close itself.
- Shared successful-payment finalization remains unchanged: after a verified Western Bid notify, the service queues `queue_deferred_payment_finalizer()`, and Step 4 opens only after `yo_checkout_final_order_status` confirms KeyCRM and paid email completion. Western Bid polling/final status accepts either the original local invoice or the merchant-prefixed notify invoice. Canceled/failed Western Bid returns stop polling and send the user back to payment-method selection.

KeyCRM:

- Settings tab: token, source/currency/tag/status IDs, payment method IDs.
- KeyCRM marker lookup/reuse, buyer/order create/update, product sync, comments, payments, and raw API requests are delegated to `includes/class-yo-checkout-keycrm.php`.
- `yoleotard-checkout-invoice.php` keeps wrapper methods around the service for compatibility with existing checkout, bank invoice, card finalization, and unpaid-order flows.
- Current behavior: after successful card payment, the frontend treats `paid:true` as payment received only, then waits on `yo_checkout_final_order_status` until the backend confirms paid status, a real KeyCRM order ID, `keycrm_after_payment_done`, and paid email delivery before showing Step 4.
- Card payment polling uses an active session token so stale timeout/focus/iframe callbacks cannot open Step 4 after the customer goes back and starts a different cart.
- Step 1 order save no longer trusts stored KeyCRM order IDs from browser cookies/localStorage; the real card KeyCRM order is created only after successful provider payment.
- Bank invoice flow now saves the current frontend cart before invoice creation, ignores stale browser/cart KeyCRM markers for the bank invoice intent, uses frontend click guarding plus server-side invoice lock/cart hash reuse, and strips invalid/expired promo data from stale local order meta before totals are calculated. Active lock responses return a retryable `preparing` state instead of a customer-facing error.
- Product card `id` / `data-feed-id` values are preserved as `product_id` in cart items and sent to KeyCRM as product SKU when available.
- Bank invoice Step 3 diagnostics write `checkout-debug` lines into the existing admin log shown under the Hiding section and into PHP `error_log`.
- Existing KeyCRM product rows are matched by row ID/title/image/position before order update so repeated invoice requests update rows instead of appending duplicates.

Email:

- Customer email rendering and sending are delegated to `includes/class-yo-checkout-email.php`.
- The main plugin still decides when a bank invoice or paid-card email should be sent.
- Paid-card emails still set `paid_email_sent=1` only after the customer email succeeds.
- Bank invoice emails now return a customer-send success flag; `bank_invoice_email_sent` and `bank_invoice_email_sent_hash` are updated only when the customer email succeeds. Failed customer sends write `bank_invoice_email_error` and can be retried because the cart-hash sent marker is not advanced.

Dompdf:

- Used for PDF invoice generation.
- If missing, plugin can still generate HTML invoice and shows admin notice.

Google Customer Reviews:

- Script rendered in footer.
- Success step may call `window.YOCheckoutGoogleReviews()` with order payload.

YOOtheme:

- Product cards are discovered from YOOtheme-generated DOM.
- After purchase, plugin attempts to hide sold product cards both server-side in YOOtheme content and frontend-side as fallback. Matching is intentionally strict: current-card frontend hiding uses the product identity key, and backend Builder auto-hide logs each disabled matched item.
- Backend auto-hide avoids long YOOtheme content/text blobs for disabling decisions and limits updates to one Builder item per purchased product identity. Multi-item orders are processed one purchased product identity at a time so one matched Builder item cannot prevent the remaining purchased items from being disabled. Because YOOtheme/Builder storage may not contain checkout product IDs, the Builder search must continue to match by product title/model/height; `product_id` is used for cart/order/reservation identity and deduplication.
- When a product card has a stable DOM `id` or `data-feed-id`, auto-hide, reservation ownership, reservation release, and availability/payment checks prefer that `product_id` before falling back to title matching.
- Sold-item logs should display the real KeyCRM order number when `order_id` is available, with local WordPress order ID shown only as a technical reference.

## Development Notes

- The main PHP file is large. Prefer extracting new areas into `includes/*.php` and loading them from the main file.
- For frontend additions, prefer new files under `assets/` and enqueue/localize them from PHP.
- When building a test ZIP, first preserve any existing `plugin-archives/yoleotard-checkout-invoice.zip` as the version it contains, for example `plugin-archives/yoleotard-checkout-invoice-v4.0.62.zip`, so the previous test package remains available. Then create the new upload ZIP at `plugin-archives/yoleotard-checkout-invoice.zip` with explicit forward-slash arc names such as `yoleotard-checkout-invoice/includes/file.php`. Prefer Python `zipfile` or another ZIP tool that lets arc names be normalized. Do not use PowerShell `Compress-Archive` directly for the installable ZIP. Exclude `.git`, old ZIP archives, temporary package folders, `plugin-archives/`, and unrelated local files. Keep the version in the plugin header and `CHANGELOG.txt`, not in the installable ZIP filename.
- After building a test ZIP, verify: path is `plugin-archives/yoleotard-checkout-invoice.zip`; first entry is `yoleotard-checkout-invoice/`; no ZIP entry contains `\`; local extraction creates real `assets/` and `includes/` directories.
- If touching payment, KeyCRM, or sold-item hiding, preserve idempotency. Many methods use post meta flags/locks to avoid duplicate payments, emails, and status updates.
- If touching AJAX, always verify nonce handling and guest-user behavior.
- If touching checkout pricing, check product price, promo discount, shipping, card fee, bank total, and KeyCRM payment amount together.
- If touching shipping, check all sources: structured DB rows, structured settings text, fallback country table, Nova Post API fallback, and currency conversion.
- When shipping is disabled, `shipping_data()` persists `shipping_cost_eur = 0.00` and clears selected-delivery metadata so later KeyCRM/email reads cannot reuse an older shipping amount.

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
