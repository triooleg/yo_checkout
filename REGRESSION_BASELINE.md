# YOleotard Checkout Regression Baseline

Last updated: 2026-06-06

## Purpose

This file is the Phase 0 baseline from `AUDIT_REMEDIATION_MAP.md`.

It documents the currently confirmed working checkout behavior before security, authority, totals, and architecture hardening phases begin.

The goal is to make future changes testable. After each later phase, compare the plugin against this baseline to confirm that working behavior was preserved or that intentional differences were documented.

This file is documentation only. It does not change runtime logic and does not require a plugin version bump.

## Baseline Version

- Stable runtime version: `4.0.51`
- Stable branch: `main`
- Baseline date: 2026-06-06
- Current next implementation phase after this baseline: `Phase 1 - Trusted Server-Side Product Catalog`

## Protected Working Behavior

The following behavior is confirmed working and must be protected:

- Shipping Calculator
- Disabled-shipping card checkout totals remain aligned with KeyCRM and customer email after v4.0.51
- Bank invoice checkout for two-item orders
- KeyCRM order creation for bank invoice checkout
- Customer email sending for bank invoice checkout
- Bank invoice confirmation screen with KeyCRM order number
- Monobank card payment flow
- Monobank external payment window closes after provider return and continues to Step 4
- Western Bid PayPal card payment flow, KeyCRM order creation, customer email, and Step 4
- Western Bid Stripe card payment flow and Step 4
- KeyCRM order creation for card checkout
- Customer email sending for card checkout
- Step 4 card payment success screen
- Sold-item auto-hide for multi-item card checkout
- Host-safe test ZIP packaging
- Local PHP and JS syntax check workflows

## Current Known Monitoring Notes

- Promo-code state and expiration handling is not listed as fully stable because it has open monitoring notes.
- Browser-provided product price, discount, weight, and title are still accepted in the current code and will be addressed by Phase 1.
- Monobank webhook signature verification is not yet implemented and will be addressed by Phase 2.
- Per-order guest ownership tokens are not yet implemented and will be addressed by Phase 3.
- Invoice URLs are still generated as public files and will be addressed by Phase 4.

## Checkout Endpoint Baseline

All listed AJAX actions are currently called through WordPress `admin-ajax.php` with:

- `action`
- `nonce`

The frontend helper is `post(action, formData)` in `assets/yo-checkout.js`.

### `yo_checkout_create_order`

Backend owner:

- `YO_Checkout_Invoice_Plugin::ajax_create_order()`

Primary frontend call:

- Step 1 customer/order submit in `assets/yo-checkout.js`
- Bank invoice flow first calls this action to save the current cart before creating the invoice

Important request fields currently sent:

- `local_id` when updating an existing local draft
- `checkout_session_id`
- `buyer_id`
- `title`
- `product_id`
- `price_eur`
- `original_price_eur`
- `discount_eur`
- `image_url`
- `shipping_weight_kg`
- `promo_code_applied`
- `cart_items_count`
- `cart_items_json`
- customer form fields from `#yo-pay-form`, including name, phone, email, address, city, zip, country

Important current response fields:

- `localId`
- `orderId`
- `buyerId`
- `cartMarker.keycrm_order_id`
- `cartMarker.local_id`
- `cardProvider`
- `cardFee`
- `shipping`
- `shippingOptions`
- `bankTotal`
- `debugId`

Expected baseline behavior:

- A local checkout post exists or is updated.
- Current cart/order data is stored.
- Shipping preview is available.
- Card fee preview is available.
- Bank total preview is available.
- KeyCRM order can be created or updated according to the current flow.
- No Step 4 is shown at this stage.

### `yo_checkout_start_card_payment`

Backend owner:

- `YO_Checkout_Invoice_Plugin::ajax_start_card_payment()`

Primary frontend call:

- Click on the card payment button from Step 2 / Step 3 flow.

Important request fields currently sent:

- `local_id`
- `checkout_session_id`
- `buyer_id`

Important current response fields:

- For Monobank:
  - `provider`
  - `invoiceId`
  - `pageUrl`
  - `orderId`
  - `cartMarker.local_id`
- For Western Bid:
  - `provider`
  - `invoiceId`
  - `pageUrl` or form/opening data depending on provider flow
  - `orderId`
  - `cartMarker`

Expected baseline behavior:

- Product availability/payability is checked before provider start.
- Card amount includes product total, shipping when enabled, and card service fee.
- Monobank opens in a separate payment window.
- Western Bid PayPal/Stripe open in a separate payment window.
- Original checkout remains on Step 3/finalizing until server confirmation is complete.

### `yo_checkout_create_bank_invoice`

Backend owner:

- `YO_Checkout_Invoice_Plugin::ajax_create_bank_invoice()`

Primary frontend call:

- Bank invoice button after `yo_checkout_create_order` saves the current cart.

Important request fields currently sent:

- `local_id`
- `yo_checkout_debug_id`
- `checkout_session_id`
- `buyer_id`
- `ignore_stale_keycrm_marker = 1`

Important current response fields:

- `invoiceUrl`
- `htmlUrl`
- `pdfMessage`
- `bankType`
- `orderId`
- `debugId`
- `cartMarker.keycrm_order_id`
- `cartMarker.local_id`
- Optional reused/locked/preparing fields for retry-safe responses

Expected baseline behavior:

- Current cart is saved first.
- Payability check runs before invoice creation.
- Expired/invalid promo data is removed from stale local orders.
- KeyCRM order is ensured before invoice output.
- Bank total excludes card payment service fee.
- Invoice HTML/PDF is generated or reused by cart hash.
- Customer email with invoice is sent once per cart hash.
- Frontend shows the bank invoice confirmation screen with KeyCRM order number and waiting-for-payment status.

### `yo_checkout_check_payment_status`

Backend owner:

- `YO_Checkout_Invoice_Plugin::ajax_check_payment_status()`

Primary frontend call:

- Payment polling after provider payment window opens.

Important request fields currently sent:

- `invoice_id`
- `local_id`
- `poll_reason`

Important current response fields:

- `paid`
- `status`
- `provider`
- `localId`
- `orderId` when available
- `finalizer` when queued
- provider-specific waiting/error fields

Expected baseline behavior:

- Polling maps invoice/reference to the correct local order.
- When provider confirms payment, local order becomes paid and finalizer is queued or run.
- Polling alone does not show Step 4 until final readiness is confirmed by `yo_checkout_final_order_status`.

### `yo_checkout_final_order_status`

Backend owner:

- `YO_Checkout_Invoice_Plugin::ajax_final_order_status()`

Primary frontend call:

- Final polling after payment is received while frontend shows "Preparing your order confirmation".

Important request fields currently sent:

- `local_id`
- `invoice_id`

Important current response fields:

- `localId`
- `paid`
- `orderId`
- `keycrmDone`
- `emailSent`
- `autoHideDone`
- `ready`
- `amount`
- `keycrmError`
- `finalizerError`
- `emailError`

Expected baseline behavior:

- Step 4 appears only when:
  - `paid = true`
  - `orderId` is a real KeyCRM order number
  - `keycrmDone = true`
  - `emailSent = true`
- Auto-hide completion is reported but is not required for Step 4 readiness.
- Errors are exposed for diagnostics instead of silently hiding finalizer failures.

## Expected Flow Baselines

### One-Item Card Payment

Expected sequence:

1. Customer adds one product to cart.
2. Product is reserved.
3. Step 1 saves local order with current cart.
4. Step 2 shows shipping, card fee, and totals.
5. Customer chooses card payment.
6. Card provider opens in separate window.
7. Provider confirms payment by webhook/status.
8. Local order is marked paid.
9. Finalizer creates/updates KeyCRM payment/status.
10. Paid customer email is sent.
11. Sold product is hidden.
12. Frontend final status becomes ready.
13. Step 4 success screen appears with KeyCRM order number.

Pass criteria:

- Provider amount equals expected card total.
- KeyCRM order exists.
- Paid email arrives.
- Step 4 appears only after final readiness.
- Purchased product is hidden.

### Multi-Item Card Payment

Expected differences from one-item flow:

- Cart contains multiple product IDs.
- Compact provider description may use a generic label such as `custom leotard xN`.
- KeyCRM contains every purchased item once.
- Email shows purchased items.
- Auto-hide disables every purchased product and no unpurchased product.

Pass criteria:

- No duplicate KeyCRM product rows.
- No extra hidden products.
- Step 4 uses correct KeyCRM order number.

### Bank Invoice Checkout

Expected sequence:

1. Customer adds one or more products.
2. Product reservations are active.
3. Step 1 saves local order and current cart.
4. Bank invoice action validates product availability.
5. KeyCRM order is created or updated.
6. Bank invoice total excludes card service fee.
7. HTML/PDF invoice is generated or reused.
8. Customer invoice email is sent.
9. Frontend shows invoice confirmation screen with KeyCRM order number.

Pass criteria:

- KeyCRM order number matches confirmation screen.
- Email arrives once for the cart hash.
- Invoice total equals KeyCRM/email bank invoice total.
- No card fee is added to bank invoice total.

### Shipping Enabled

Expected behavior:

- Shipping calculator returns an amount and source.
- Shipping appears in frontend total.
- Shipping is included in card total.
- Shipping is included in bank invoice total.
- Shipping is included in KeyCRM and customer email totals.

### Shipping Disabled

Expected behavior:

- `shipping_cost_eur` persists as `0.00`.
- `shipping_source` is `disabled`.
- Old selected shipping metadata is cleared.
- Card provider amount, KeyCRM total, and customer email total exclude shipping.

### Product Discount

Expected behavior:

- Product-level discount is displayed in cart/order details.
- Product-level discount is reflected in local order item data.
- KeyCRM, email, invoice, and provider total use the same discounted value.

### Promo Discount

Expected behavior:

- Promo applies only when enabled and valid.
- Expired promo data should not survive into new bank invoice creation.
- Promo is still under monitoring and requires extra checks after future changes.

## Regression Test Matrix

Use this matrix after later phases.

| Scenario | Provider/Flow | Items | Shipping | Discounts | Expected Result |
|---|---|---:|---|---|---|
| Card one item | Monobank | 1 | enabled | none/product | Step 4, KeyCRM, email, auto-hide |
| Card multi item | Monobank | 3+ | enabled | product | Step 4, all items in KeyCRM, all exact products hidden |
| Card one item | Western Bid PayPal | 1 | enabled | none/product | Step 4, KeyCRM, email |
| Card one item | Western Bid Stripe | 1 | enabled | none/product | Step 4, KeyCRM, email |
| Card shipping disabled | Monobank or Western Bid | 1 | disabled | none/product | Provider, KeyCRM, email totals exclude shipping |
| Bank invoice one item | IBAN invoice | 1 | enabled | none/product | Invoice screen, KeyCRM, email, no card fee |
| Bank invoice multi item | IBAN invoice | 2+ | enabled | product/promo when valid | No duplicate email/order rows |
| Promo monitoring | Any eligible flow | 1+ | any | promo | Valid promo applies; expired promo does not |
| Reservation conflict | Cart/reservation | 1 | any | any | Second customer cannot buy reserved/sold product |
| Auto-hide exactness | Card paid | 2+ | any | any | Only exact purchased product IDs are disabled |

## Local Verification Baseline

Run the relevant checks after each future phase:

- `php -l yoleotard-checkout-invoice.php`
- `php -l` for changed PHP files under `includes/`
- `node --check assets/yo-checkout.js` when JS changes
- `git diff --check`
- Confirm no secrets were added to the repository

## Release Baseline

A future runtime change is not stable until:

- local checks pass;
- relevant live-test matrix rows pass;
- documentation is updated;
- user confirms live test success;
- Git rollback point is committed and pushed;
- commit hash is recorded in `DEVELOPMENT_LOG.md`.

## Phase 0 Completion Criteria

Phase 0 is complete when:

- this baseline exists;
- `PROJECT_CONTEXT.md`, `PLUGIN_MAP.md`, `AUDIT_REMEDIATION_MAP.md`, and `DEVELOPMENT_LOG.md` reference it;
- no runtime code was changed;
- repository has a documentation rollback point.

