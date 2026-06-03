# Western Bid Migration Map

Created: 2026-05-31
Reviewed: 2026-06-02 against working checkout v4.0.43

Purpose: replace the current WayForPay card-payment path with Western Bid while keeping the existing Monobank, bank invoice, KeyCRM, email, finalizer, and sold-item hiding behavior stable.

## 2026-06-02 Review Notes Against v4.0.43

The current checkout version has several live-tested stable areas that must be treated as protected integration boundaries:

- Monobank card payment is working and must remain the default provider for non-Western-Bid-routed countries.
- Bank invoice checkout, KeyCRM creation, customer email, and confirmation UI are working and must not be changed for Western Bid.
- Card-payment Step 4 is now intentionally delayed until `yo_checkout_final_order_status` confirms:
  - local order is paid,
  - real KeyCRM order number exists,
  - `keycrm_after_payment_done=1`,
  - `paid_email_sent=1`.
- Card payment starts with a local draft only. KeyCRM buyer/order creation must happen only after provider-confirmed successful payment.
- Current cart/product identity, reservation, and multi-item auto-hide logic are working and must not be redesigned during Western Bid implementation.
- Customer paid email sending is extracted to `includes/class-yo-checkout-email.php`; Western Bid should use the same `process_successful_card_payment()` path so email behavior stays identical.
- KeyCRM integration is extracted to `includes/class-yo-checkout-keycrm.php`; only provider labels/method IDs should be extended for Western Bid.
- The existing frontend payment polling/session token logic in `assets/yo-checkout.js` must be reused. Western Bid should add a return message type and mapping support, not create a second checkout success path.

Architectural conclusion:

- Western Bid should replace the WayForPay branch as a provider adapter.
- The shared checkout lifecycle must stay:
  `create local draft -> choose provider -> provider invoice/form -> provider notify marks paid -> deferred finalizer -> KeyCRM/email/auto-hide -> final_order_status -> Step 4`.
- Do not call KeyCRM/email/auto-hide directly from a Western Bid return page. The return page should only notify the parent checkout and let polling/final status continue.

## Credentials Handling

- Western Bid login/account was provided in the conversation.
- Western Bid secret key was provided in the conversation.
- Do not commit the secret key to Git.
- Store credentials only in WordPress plugin settings:
  - `western_bid_login`
  - `western_bid_secret_key`
  - optional `western_bid_gate`
  - optional `western_bid_card_fee_percent`
  - optional `western_bid_countries`
- Mask the secret field in the admin UI with password input.

## Western Bid Documentation Summary

Payment form:

- Form action: `https://shop.westernbid.info`
- Method: `POST`
- Charset: `utf-8`
- Main required fields:
  - `wb_login`
  - `wb_hash`
  - `invoice`
  - `email`
  - `phone`
  - `amount`
  - `currency_code`
  - cart item fields: `amount_x`, `item_name_x`, `item_number_x`, `quantity_x`, `url_x`, `description_x`
  - `return`
  - `cancel_return`
  - `notify_url`
- Optional but recommended buyer fields:
  - `first_name`
  - `last_name`
  - `address1`
  - `address2`
  - `country`
  - `city`
  - `state`
  - `zip`
- Optional payment gate:
  - `paypal`
  - `stripe.com`
  - default is `paypal`
- Form hash:
  - `md5(wb_login . secret_key . amount . invoice)`

Payment notification:

- Western Bid sends POST data to `notify_url`.
- Notification hash:
  - `md5(wb_login . wb_result . secret_key . mc_gross . invoice)`
- `wb_result` must be `VERIFIED`.
- `payment_status` must be checked. Successful final paid status is `Completed`.
- Paid amount and currency must be verified against the local order before marking paid.
- Important fields:
  - `invoice`
  - `mc_gross`
  - `mc_currency`
  - `payment_status`
  - `wb_result`
  - `wb_hash`
  - `txn_id`
  - `payer_email`

## Current WayForPay Surface To Replace

Main PHP file:

- Plugin header/name mentions `WayForPay`.
- Default settings:
  - `wayforpay_merchant_login`
  - `wayforpay_secret_key`
  - `wayforpay_currency`
  - `wayforpay_test_credentials_mode`
  - `wayforpay_test_result_mode`
  - `wayforpay_card_fee_percent`
  - `wayforpay_countries`
  - `keycrm_payment_method_wayforpay`
- Admin tab:
  - `wayforpay`
  - WayForPay credentials, currency, fee, country routing, test controls.
- AJAX:
  - `yo_checkout_wayforpay_form`
  - `yo_checkout_wayforpay_return`
- REST:
  - `GET /wp-json/yoleotard/v1/wayforpay-form`
  - `POST /wp-json/yoleotard/v1/wayforpay-webhook`
- Payment start:
  - `start_wayforpay_payment()`
- HTML form:
  - `render_wayforpay_autosubmit_html()`
  - `wayforpay_form()`
  - `ajax_wayforpay_form()`
- Return/webhook:
  - `ajax_wayforpay_return()`
  - `wayforpay_webhook()`
- Status check:
  - `check_wayforpay_status()`
  - WayForPay branch in `ajax_check_payment_status()`
- Signature/credentials:
  - `wayforpay_credentials()`
  - `wayforpay_secret_for_account()`
  - `wayforpay_signature()`
  - `verify_wayforpay_callback()`
  - `wayforpay_should_simulate_test_success()`
- Provider routing:
  - `card_provider_for_order()`
  - `is_wayforpay_country()`
  - test provider options include `wayforpay`
- Fees:
  - `card_fee_percent_for_provider()`
  - `card_fee_data()`
- KeyCRM:
  - `keycrm_payment_method_wayforpay`
  - `keycrm_add_payment()` chooses payment method by provider.
  - Paid label currently uses `WayForPay`.
- Local order meta:
  - `wayforpay_order_reference`
  - `payment_provider = wayforpay`
  - `yo_wayforpay_order_{reference}` option mapping.

Frontend JS:

- Listens for iframe return message:
  - `type === 'yo_wayforpay_return'`
- Calls `checkPaymentOnce()` after WayForPay return message.

## Target Western Bid Architecture

Add a separate service:

- `includes/class-yo-checkout-western-bid.php`

Suggested responsibilities:

- Build Western Bid order reference/invoice ID.
- Build hidden form fields.
- Render auto-submit HTML form to `https://shop.westernbid.info`.
- Verify Western Bid notify POST hash.
- Verify payment status, amount, and currency.
- Normalize Western Bid invoice IDs back to local order IDs.
- Return iframe message to frontend after customer redirect.
- Provide provider labels and setting keys.
- Write only safe diagnostic meta/log values; never log the secret key or full raw sensitive payload.
- Mark the local order as paid and queue the shared deferred finalizer after a valid `Completed` notification.
- Return "waiting" status while no valid Western Bid notification has arrived yet.

Keep main file as composition layer:

- `require_once includes/class-yo-checkout-western-bid.php`
- `private $western_bid_service = null`
- `western_bid_service()` factory method with callbacks into existing plugin:
  - settings
  - get_order_data
  - card_fee_data
  - process_successful_card_payment
  - queue_deferred_payment_finalizer
  - clean_product_title_for_display
  - cart_items_from_order_data if needed
  - shipping_data / card_fee_data if needed for form totals
  - append_auto_hide_log or checkout debug logging for safe operational traces

Do not move Monobank, bank invoice, KeyCRM, email, product identity, reservation, or sold-item hiding logic as part of the Western Bid task.

## New Settings

Replace WayForPay settings with Western Bid settings:

- `western_bid_login`
- `western_bid_secret_key`
- `western_bid_currency` default `EUR`
- `western_bid_gate` default `paypal`, options:
  - `paypal`
  - `stripe.com`
- `western_bid_card_fee_percent`
- `western_bid_countries`
- `western_bid_test_mode` or reuse global plugin test mode carefully.
- `keycrm_payment_method_western_bid`

Admin UI:

- Rename tab from `WayForPay` to `Western Bid`.
- Show:
  - login/account
  - secret key
  - currency
  - gate
  - card service fee percent
  - countries routed to Western Bid
  - KeyCRM payment method ID for Western Bid
  - notify URL and return URL examples
- Keep the global Test mode field, but remove/hide WayForPay-specific test credential/result controls from the visible Western Bid tab.
- Update the "Test card provider" selector from `Force WayForPay` to `Force Western Bid`.

Do not remove old WayForPay option values immediately unless migration is confirmed. Keep sanitization tolerant so saving settings does not wipe unrelated legacy values.

Admin behavior after migration:

- Visible:
  - Western Bid login/account.
  - Western Bid secret key as password input.
  - Western Bid currency.
  - Western Bid payment gate selector.
  - Western Bid card service fee percent.
  - Countries routed to Western Bid.
  - KeyCRM payment method ID for Western Bid.
  - Notify URL and return URL.
  - Test provider selector with `Auto`, `Force Monobank`, `Force Western Bid`.
- Removed from visible UI:
  - WayForPay merchant login field.
  - WayForPay secret key field.
  - WayForPay currency field.
  - WayForPay service fee field.
  - WayForPay country routing field.
  - WayForPay official test credential selector.
  - WayForPay simulated result selector.
  - WayForPay webhook/service URL text.
- Kept internally for transition/rollback only:
  - old `wayforpay_*` settings and old order meta are tolerated by sanitization and lookup code until Western Bid is live-tested.

## Payment Flow Map

1. Customer chooses card payment.
2. Existing `ajax_start_card_payment()` recalculates shipping and fee.
3. Existing `card_provider_for_order()` chooses provider:
   - Monobank for default countries.
   - Western Bid for configured countries.
4. If provider is `western_bid`, call `start_western_bid_payment($local_id)`.
5. `start_western_bid_payment()`:
   - validates login/secret.
   - creates order reference, for example `YO-WB-{local_id}-{time}`.
   - stores local meta:
     - `western_bid_invoice`
     - `payment_provider = western_bid`
     - `payment_type = card`
   - stores option mapping:
     - `yo_western_bid_order_{invoice} = local_id`
   - schedules unpaid-order check.
   - returns JSON:
     - `provider = western_bid`
     - `invoiceId`
     - `pageUrl` for local auto-submit form endpoint
     - `orderId = WEB-{local_id}`
6. Frontend opens `pageUrl` in existing payment iframe.
7. Local endpoint renders HTML form with hidden Western Bid fields and auto-submits to `https://shop.westernbid.info`.
8. Western Bid redirects customer to return/cancel URL.
9. Return URL posts message to parent checkout iframe:
   - new type should be `yo_western_bid_return`
   - frontend should accept both old WayForPay and new Western Bid message during transition.
10. Frontend polling calls existing `yo_checkout_check_payment_status`.
11. Webhook/notify POST marks order paid only after hash, status, amount, and currency checks pass.
12. Webhook queues `queue_deferred_payment_finalizer($local_id, $invoice, 'western_bid_webhook_completed')`.
13. Existing `process_successful_card_payment()` creates KeyCRM order, adds payment, sends paid email, hides sold item.
14. Frontend sees `paid:true`, then waits on `yo_checkout_final_order_status` until KeyCRM/email markers are complete before showing Step 4.

Important:

- Western Bid return/cancel pages must not show Step 4 by themselves.
- If Western Bid redirects the buyer back before the notify POST is received, polling should show waiting/finalizing until the webhook completes.
- If the notify POST never arrives, the order remains unpaid and Step 4 must not open.

## Western Bid Form Field Map

Use local order data:

- `charset`: `utf-8`
- `wb_login`: setting `western_bid_login`
- `wb_hash`: `md5(wb_login . secret_key . amount . invoice)`
- `invoice`: generated Western Bid invoice/reference
- `email`: customer email
- `phone`: customer phone
- `first_name`: first part of full name
- `last_name`: remaining part of full name
- `address1`: shipping address
- `address2`: additional address
- `country`: customer country, preferably ISO-2 if available
- `city`: customer city
- `state`: optional, blank initially unless a state field is added later
- `zip`: ZIP/postal code
- `item_name`: short order summary
- `amount`: total product/card charge amount excluding separate `shipping` if using per docs.
- `shipping`: shipping cost
- `currency_code`: setting `western_bid_currency`, likely `EUR` unless Western Bid requires another currency.
- `return`: AJAX return endpoint URL
- `cancel_return`: AJAX cancel/return endpoint URL
- `notify_url`: REST notify/webhook endpoint URL
- `gate`: setting `western_bid_gate`
- `no_shipping`: likely `0`, unless we want to hide shipping fields.
- `address_override`: use carefully only if country is ISO-2 and address is valid.

For cart items:

- Use `cart_items_from_order_data($d)`.
- For each item `i`:
  - `item_name_i`
  - `item_number_i`
  - `amount_i`
  - `quantity_i = 1`
  - `url_i`
  - `description_i`
- If reliable product URLs are not stored yet, use homepage/product page fallback, but docs say `url_x` is required.
- Keep product count below Western Bid's recommended 30-item limit.

Important amount check:

- Western Bid warns that buyers can manipulate redirect/form amount.
- Notify handler must compare:
  - received `mc_gross`
  - expected local `card_total_amount`
  - received `mc_currency`
  - expected currency

## New Routes And Actions

AJAX:

- `yo_checkout_western_bid_form`
- `yo_checkout_western_bid_return`

REST:

- `POST /wp-json/yoleotard/v1/western-bid-webhook`
- Optional `GET /wp-json/yoleotard/v1/western-bid-form` only if needed, but AJAX form endpoint is enough for iframe HTML.

Transition routes:

- Keep old WayForPay AJAX/REST handlers temporarily during first Western Bid test builds only if existing unpaid WayForPay test orders may still return.
- After Western Bid is confirmed live, remove old WayForPay visible UI and either delete or leave legacy handlers unreachable. Do not remove legacy handlers in the same first integration step if that increases rollback risk.

Local options/meta:

- `western_bid_invoice`
- `western_bid_txn_id`
- `western_bid_payment_status`
- `western_bid_wb_result`
- `western_bid_last_notify`
- `yo_western_bid_order_{invoice}`

## Existing Code Changes Required

Main file:

- Add `require_once` for Western Bid service.
- Add service property/factory.
- Replace WayForPay hooks with Western Bid hooks or keep old hooks temporarily for rollback.
- Replace/rest routes:
  - remove/disable WayForPay routes after Western Bid works.
  - add Western Bid notify route.
- Update `ajax_start_card_payment()`:
  - provider `western_bid` calls Western Bid service.
- Update `ajax_check_payment_status()`:
  - find local order by `yo_western_bid_order_{invoice}`.
  - provider branch for `western_bid`.
  - for Western Bid, rely mostly on webhook/local meta, because docs describe notify POST rather than a status-check API.
  - if `paid=1`, reuse the existing early paid branch and queue the finalizer.
  - if not paid, return `paid:false`, `status` from `western_bid_payment_status` or `waiting`.
- Update `ajax_final_order_status()`:
  - include Western Bid invoice mapping.
  - include `western_bid_invoice` in invoice mismatch checks.
- Update `card_provider_for_order()`:
  - use `western_bid` instead of `wayforpay`.
- Update `card_fee_percent_for_provider()`:
  - use `western_bid_card_fee_percent`.
- Update `keycrm_add_payment()`:
  - use `keycrm_payment_method_western_bid`.
  - label as `Western Bid`.
- Update `process_successful_card_payment()` label:
  - `Western Bid` for provider `western_bid`.
- Update `get_order_data()` meta keys:
  - add `western_bid_invoice`.
  - optionally keep `wayforpay_order_reference` during transition.
- Update `sanitize()` / defaults without deleting unrelated existing settings.
- Update plugin header name/description only after Western Bid replaces WayForPay in visible behavior.

Frontend JS:

- Replace/extend message listener:
  - accept `yo_western_bid_return`.
  - keep `yo_wayforpay_return` during transition if old orders may exist.
- No major UI rewrite expected if Western Bid still opens in the existing iframe.
- If Western Bid blocks iframe embedding, fallback will need top-level redirect or popup.
- Keep the active payment session token and `waitForFinalOrderThenShowSuccess()` flow unchanged.

Settings/admin:

- Rename WayForPay tab to Western Bid.
- Rename fields and labels.
- Keep old WayForPay values out of visible UI unless rollback support is desired.
- Add Western Bid notify URL display.

Docs:

- Update `PROJECT_CONTEXT.md` plugin name if WayForPay is fully removed.
- Update `PLUGIN_MAP.md` integrations and routes.
- Update `CHANGELOG.txt` with new plugin version.
- Update `DEVELOPMENT_LOG.md`.
- Update `KNOWN_ISSUES.md` with any Western Bid testing limitations.

## Verification Plan

Static checks:

- `php -l yoleotard-checkout-invoice.php`
- `php -l includes\class-yo-checkout-western-bid.php`
- `node --check assets\yo-checkout.js`
- `git status -sb`

Local logic checks:

- Confirm settings save without wiping Monobank/bank invoice/KeyCRM settings.
- Confirm provider routing returns `western_bid` for configured countries.
- Confirm `card_fee_data()` uses Western Bid percent.
- Confirm generated form contains:
  - action `https://shop.westernbid.info`
  - `wb_login`
  - `wb_hash`
  - `invoice`
  - `amount`
  - `currency_code`
  - `return`
  - `cancel_return`
  - `notify_url`
  - at least one item row.
- Confirm no secret key appears in HTML output or logs.

Live/test checks:

- Create a test order for a Western Bid routed country.
- Start card payment.
- Confirm iframe/form redirects to Western Bid/PayPal/Stripe.
- Complete sandbox/test payment.
- Confirm notify POST reaches site.
- Confirm hash verifies.
- Confirm paid amount/currency match local order.
- Confirm local order becomes paid.
- Confirm KeyCRM order is created after payment.
- Confirm KeyCRM payment method is Western Bid.
- Confirm paid email is sent.
- Confirm sold item auto-hide runs.
- Confirm Step 4 shows the real KeyCRM order number.

## Recommended Implementation Order

1. Snapshot current state with `git status -sb`; do not touch stable bank invoice/Monobank/email/auto-hide files except required adapter points.
2. Create `includes/class-yo-checkout-western-bid.php` with form/hash/notify helpers.
3. Add Western Bid defaults/settings/admin tab and KeyCRM payment method setting.
4. Add service factory, AJAX actions, and REST notify route in the main file.
5. Add start-payment path for provider `western_bid`, modeled after the current `start_wayforpay_payment()` branch.
6. Add form-render endpoint that outputs an auto-submit HTML form to `https://shop.westernbid.info`.
7. Add return/cancel endpoint that posts `yo_western_bid_return` to the parent checkout but does not finalize directly unless already paid.
8. Add webhook/notify endpoint with hash, `wb_result`, `payment_status`, amount, currency, and duplicate transaction checks.
9. Update payment-status polling and final-status lookup to support `western_bid_invoice` and `yo_western_bid_order_{invoice}`.
10. Update provider routing, fee calculation, and KeyCRM payment method/label for `western_bid`.
11. Update frontend message listener to accept both `yo_western_bid_return` and temporary `yo_wayforpay_return`.
12. Update plugin name/description/changelog/docs for the test version.
13. Run static checks:
    - `php -l yoleotard-checkout-invoice.php`
    - `php -l includes\class-yo-checkout-western-bid.php`
    - `node --check assets\yo-checkout.js`
    - `git diff --check`
14. Create a local implementation report before test packaging.
15. Only when requested, create a host-safe test ZIP under `plugin-archives/`.
16. Commit/push only after the user confirms the live Western Bid test works.

## Risk Notes

- Western Bid docs describe notify POST, not a separate status-check API. The existing polling model must read local paid/meta state after webhook/return.
- `payment_status` can change over time. Only `Completed` should mark paid automatically.
- `wb_result` is verification result, not payment status. It must be `VERIFIED`, but that alone is not enough.
- Paid amount and currency comparison is mandatory.
- Existing product URL may not be stored for `url_x`; implementation may need a fallback or frontend collection of product URL.
- Live testing confirmed that PayPal login and Stripe Checkout are not reliable inside the Step 3 iframe; v4.0.45 switches Western Bid PayPal/Stripe to an external popup/window while the original checkout keeps polling for webhook/final status.
- Live testing after the external-window switch showed PayPal requires the exact Western Bid-issued sandbox PayPal buyer credentials. With those credentials, PayPal payment can pass.
- Western Bid switched the merchant into real working mode for Stripe and PayPal.
- Live Stripe/PayPal testing showed the payment window closes after payment but the original checkout does not reach Step 4. This means the local checkout has not received or accepted a verified Western Bid notify/webhook yet.
- v4.0.48 sends ISO-2 country codes and fuller buyer/address aliases to Western Bid, avoids sending delivery as a duplicate provider shipping charge, and writes safe `checkout-debug wb-...` log lines for form preparation and webhook receive/verification.
- Live webhook log showed Western Bid prefixes the submitted invoice with the merchant login in notify payloads: submitted `YO-WB-...`, received `{wb_login}-YO-WB-...`.
- v4.0.49 maps both full and normalized invoice values back to the same local order while keeping hash verification tied to the full received Western Bid invoice.
- Live Stripe webhook log showed `payment_status=Completed`, `wb_result=VERIFIED`, matching `mc_gross`, but empty `mc_currency`. v4.0.49 treats empty `mc_currency` as the configured Western Bid currency after hash/status/amount checks pass.
- A later live Stripe webhook sent raw `mc_gross="1"` and failed hash verification because the plugin had normalized it to `1.00` before calculating the hash.
- v4.0.50 verifies the notify hash against raw and normalized amount string candidates, while still requiring the normalized paid amount to exactly match the local order total.
- v4.0.50 also allows Western Bid polling/final status to recognize both local and merchant-prefixed invoice values so a completed webhook can continue to Step 4.
- Secret key must never be printed in HTML, logs, changelog, docs, or Git.
