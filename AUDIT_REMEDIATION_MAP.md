# YOleotard Checkout Audit Remediation Map

Last updated: 2026-06-03

## Purpose

This file is the staged implementation map for strengthening the YOleotard checkout plugin before adding new functionality.

The map is based on the holistic architecture, security, payment, state-management, data-storage, and maintainability audit completed on 2026-06-03.

The goal is not to redesign the customer experience or replace working business logic. The goal is to preserve the confirmed working checkout behavior while removing the technical risks identified during the audit.

Codex must read this file before starting any new code change until all critical and high-priority phases are completed.

## Non-Negotiable Functional Invariants

The following confirmed working behavior must remain unchanged unless a phase explicitly requires an internal implementation change:

- Product cards, cart, reservations, promo display, shipping selection, and checkout steps remain visually and functionally familiar to the customer.
- Monobank payment continues to open in a separate payment window, close after provider return, and continue to Step 4 after verified payment.
- Western Bid PayPal and Stripe continue to open in a separate payment window and continue to Step 4 after verified webhook confirmation.
- Bank invoice checkout continues to create or update a KeyCRM order, generate invoice files, send the customer email, and show the invoice confirmation screen.
- Step 4 continues to appear only after payment is confirmed, a real KeyCRM order number exists, KeyCRM finalization is complete, and the paid email is sent.
- KeyCRM order creation, customer emails, shipping calculator, multi-item checkout, product reservations, and sold-item auto-hide remain available.
- Existing AJAX action names, REST route URLs, frontend element IDs, and documented integration settings should remain compatible unless a migration phase explicitly documents a safe transition.
- Working modules must not be rewritten as part of unrelated cleanup.

## General Implementation Rules

- Implement one phase at a time.
- Keep each phase small enough to live-test independently.
- Do not combine security hardening, architecture extraction, and visible feature changes in the same release.
- Add new backend responsibilities as separate classes under `includes/`.
- Preserve existing public method wrappers where needed so current flows keep calling the same names.
- Before changing a confirmed working area, read `KNOWN_WORKING_FEATURES.md`.
- Before each phase, create or confirm a repository rollback point.
- After each phase, update `DEVELOPMENT_LOG.md`, `PLUGIN_MAP.md`, `KNOWN_ISSUES.md`, and `CHANGELOG.txt` when runtime behavior changes.
- Do not mark a phase complete until local verification and the required live test both pass.
- Do not commit a version as stable until the user confirms the live test works.

## Current Audit Baseline

Audit baseline:

- Stable GitHub version: `4.0.51`
- Current local worktree version: `4.0.56`
- Disabled shipping total persistence was live-tested successfully on 2026-06-03
- PHP syntax checks passed
- JavaScript syntax check passed
- `git diff --check` passed with line-ending warnings only
- No Western Bid login, secret key, Monobank token, or KeyCRM token was found in the repository

## Priority Summary

### Critical

1. Stop trusting browser-provided product prices, discounts, weights, and titles as authoritative order values.
2. Verify Monobank webhook signatures before marking orders paid.
3. Protect generated invoice files containing customer personal data.

### High

4. Add per-order guest access tokens for public AJAX order actions.
5. Make KeyCRM completion markers depend on confirmed API success.
6. Recover safely from stale payment-finalizer locks.
7. Prevent sold-item auto-hide from matching a different product with a similar title.

### Medium

8. Create one authoritative totals service and immutable payment totals snapshot.
9. Move reservations from one shared WordPress option to atomic storage.
10. Add cleanup for temporary mapping options and old local checkout data.
11. Validate `postMessage` origins and simplify frontend payment state.
12. Remove legacy duplicate code and continue splitting the main PHP file.
13. Replace or verify the Dompdf download/install process.
14. Add automated regression tests for the checkout's highest-risk behavior.

## Phase 0 - Baseline and Regression Harness

Status: completed as documentation baseline on 2026-06-06 in `REGRESSION_BASELINE.md`.

### Objective

Create a repeatable safety baseline before changing payment or order authority logic.

### Changes

- Document the exact current request and response fields for:
  - `yo_checkout_create_order`
  - `yo_checkout_start_card_payment`
  - `yo_checkout_create_bank_invoice`
  - `yo_checkout_check_payment_status`
  - `yo_checkout_final_order_status`
- Add a small test fixture set for one-item and multi-item carts.
- Record expected totals for:
  - shipping enabled
  - shipping disabled
  - product discount
  - promo discount
  - Monobank fee
  - Western Bid fee
  - bank invoice without card fee
- Record the required live-test matrix before implementation begins.

### Must Not Change

- No runtime behavior.
- No plugin version bump is required for documentation-only baseline work.

### Completion Gate

- The current working flows can be compared against a written expected result after every later phase.

## Phase 1 - Trusted Server-Side Product Catalog

Status: implemented locally as v4.0.54 test candidate on 2026-06-06; live checkout validation is still required before marking it stable.

### Objective

Make the server the authority for product identity, title, price, product discount, and shipping weight.

### New Module

- `includes/class-yo-checkout-product-catalog.php`

Suggested responsibility:

- Resolve a product by stable `product_id`.
- Read trusted product data from the published YOOtheme product source or a structured checkout-owned catalog.
- Return canonical title, current price, original price, product discount, weight, image, and availability.
- Reject unknown, ambiguous, sold, or unavailable product IDs.

### Changes

- Browser cart continues to send `product_id` for user interface purposes.
- Server ignores browser-provided price, discount, weight, and title as authoritative values.
- `sanitize_order_input()` and `sanitize_cart_items_json()` use the catalog result to build stored order items.
- Promo code discounts continue to be calculated by the existing promo service after trusted product prices are loaded.
- KeyCRM, payment providers, email, invoices, and Step 4 continue to read the stored server-built order snapshot.

### Compatibility Strategy

- Keep the current browser payload fields temporarily so old cached JavaScript does not fail immediately.
- Log mismatches between browser values and server values during the transition.
- Do not reject a valid cart only because a display title differs in punctuation or quote encoding when `product_id` resolves correctly.

### v4.0.52 Implementation Note

- Added `includes/class-yo-checkout-product-catalog.php`.
- `sanitize_cart_items_json()` now attempts to resolve each cart item by `product_id` from the configured YOOtheme product source page.
- When resolved, the stored checkout item uses server-side title, current price, original price, product discount, weight, and image.
- When not resolved, the existing sanitized browser payload remains as a compatibility fallback and the order stores `product_catalog_status` / `product_catalog_summary`.
- Promo discounts still run after catalog resolution through the existing promo service.

### v4.0.53 Hotfix Note

- v4.0.52 exhausted PHP memory on the live YOOtheme page during Step 2 order creation.
- Root cause: the first catalog implementation unserialized/JSON-encoded full page meta values while searching for product data.
- v4.0.53 no longer expands the full YOOtheme meta tree and only reads small source fragments around the requested `product_id` / title.

### v4.0.54 Follow-Up Note

- Live tests showed Monobank and bank invoice completed successfully, but catalog diagnostics still reported `partial_fallback`.
- v4.0.54 allows trusted catalog matching when the stored Builder card has the same title and that title generates the requested canonical product ID, even if the literal DOM `product_id` is only added later by frontend enhancers.
- The frontend now keeps a reference to the opened card-payment window and attempts to close it when the main checkout reaches Step 4.

### Must Not Change

- Product card appearance.
- Cart appearance.
- Existing product ID generation rules.
- Promo code business rules.
- Shipping business rules.

### Verification

- Modify price, discount, weight, and title in DevTools: server total must remain correct.
- One-item and multi-item Monobank totals match the catalog.
- One-item and multi-item Western Bid totals match the catalog.
- Bank invoice, KeyCRM, email, and invoice files show the same trusted products and totals.

## Phase 2 - Monobank Webhook Signature Verification

Status: implemented locally as v4.0.56 test candidate on 2026-06-06; live Monobank checkout validation is still required before marking it stable.

### Objective

Mark a Monobank payment paid only after cryptographic webhook verification.

### Module

- Extend `includes/class-yo-checkout-monobank.php`.

### Changes

- Read the raw webhook request body.
- Read the `X-Sign` header.
- Obtain and cache the Monobank merchant public key.
- Verify the ECDSA signature before processing webhook status.
- Reject invalid or missing signatures without changing order state.
- Keep the existing authenticated invoice status request as a recovery path for polling or webhook desynchronization.
- Compare the provider invoice amount and currency with the stored payment snapshot before finalization where available.

### v4.0.56 Implementation Note

- `includes/class-yo-checkout-monobank.php` now verifies webhook `X-Sign` against the raw request body using the cached merchant public key from `/api/merchant/pubkey`.
- Public keys are cached per Monobank token mode (`live` / `test`) and refreshed once if signature verification fails.
- Invalid, missing, or unverifiable signatures return an error response and do not mark the order paid.
- Signed paid webhooks compare provider `ccy` and `amount` / `finalAmount` with the stored Monobank card total when those fields are present.
- Authenticated status polling remains available as the recovery path when webhook delivery is delayed or rejected.

### Must Not Change

- Monobank payment window behavior.
- Monobank return URL behavior.
- Shared successful payment finalizer behavior.
- Step 4 requirements.

### Verification

- Valid Monobank payment reaches Step 4.
- Invalid signature does not mark the order paid.
- Missing signature does not mark the order paid.
- Duplicate valid webhook remains idempotent.
- Polling can recover when webhook delivery is delayed.

## Phase 3 - Per-Order Guest Access Tokens

### Objective

Prevent one visitor from reading or mutating another visitor's local checkout order by guessing `local_id` or invoice mappings.

### New Module

- `includes/class-yo-checkout-order-access.php`

### Changes

- Generate a cryptographically random access token when a local checkout order is created.
- Store only a secure hash of the token in order meta.
- Return the raw token only to the browser that created the order.
- Require the token for every public AJAX action that reads or changes an existing local order.
- Bind payment status and final order status checks to both order identity and access token.
- Keep WordPress nonce verification as CSRF protection; the order token is an additional ownership check.

### Affected Actions

- Shipping option update
- Card payment start
- Bank invoice creation
- Payment status polling
- Final order status polling
- Any future action accepting `local_id`

### Compatibility Strategy

- Add token support to PHP and JavaScript in the same release.
- Do not remove existing nonce checks.
- Provide a clear recoverable error when an old cached checkout lacks a valid order token.

### Verification

- Correct browser token can continue checkout.
- Missing or incorrect token cannot read order status or start payment.
- A token from one order cannot access another order.
- Monobank, Western Bid, and bank invoice flows still complete normally.

## Phase 4 - Protected Invoice Delivery

### Objective

Prevent public guessing of invoice HTML/PDF URLs containing customer personal data.

### New Module

- `includes/class-yo-checkout-invoice-files.php`

### Changes

- Generate a random invoice file token or protected download token.
- Stop using only predictable `invoice-{order_id}` public filenames.
- Prefer a protected WordPress download endpoint that validates the order access token or a dedicated invoice token.
- Keep email attachments working from local filesystem paths.
- Keep the Step 4 bank invoice buttons working.
- Add a retention and cleanup rule for old invoice files.

### Must Not Change

- Invoice content.
- PDF attachment behavior.
- Customer bank invoice email.
- Bank invoice confirmation screen.

### Verification

- A valid invoice link opens for the customer.
- Guessing a nearby order number does not expose another invoice.
- Email attachment remains valid.
- Existing invoices are not accidentally deleted during migration.

## Phase 5 - Reliable Payment Finalizer and KeyCRM Retry

### Objective

Make successful payment side effects idempotent, retryable, and recoverable after partial failure.

### New Module

- `includes/class-yo-checkout-payment-finalizer.php`

### Changes

- Move the shared payment finalization workflow out of the main PHP file.
- Keep the current public wrapper method name during migration.
- Treat KeyCRM payment creation, KeyCRM status update, paid email, and sold-item auto-hide as separate idempotent steps.
- Set each completion marker only after confirmed success.
- Make KeyCRM request methods return structured success or `WP_Error`.
- Store the last error and schedule retry when a step fails.
- Add stale-lock takeover using a timestamp and a conservative timeout.
- Log lock recovery and each failed/retried side effect.

### Must Not Change

- Step 4 continues to require real KeyCRM order ID, KeyCRM completion, and paid email.
- Payment providers continue to call the same shared finalization entry point.
- Duplicate valid webhooks must not create duplicate KeyCRM payments or duplicate emails.

### Verification

- Simulated KeyCRM failure does not set a false success marker.
- Retry completes the missing KeyCRM action without duplicating successful actions.
- A stale finalizer lock can be recovered.
- Duplicate webhook and duplicate polling remain safe.

## Phase 6 - Exact Sold-Item Auto-Hide Identity

### Objective

Hide only the exact purchased YOOtheme item.

### Module

- Extend `includes/class-yo-checkout-sold-items.php`.

### Changes

- Prefer exact stored `product_id` matching in YOOtheme Builder data.
- Add or preserve checkout-owned product ID metadata in the Builder source when possible.
- Treat differing meaningful title words as a mismatch even when quoted model name and height are equal.
- Keep title matching only as a compatibility fallback.
- Detect ambiguous fallback matches and log them without disabling any item.
- Do not mark auto-hide complete when the match is ambiguous or no exact item was disabled.

### Must Not Change

- Existing product ID rules.
- Confirmed multi-item auto-hide behavior for exact products.
- Admin hiding log availability.
- YOOtheme backup behavior.

### Verification

- `test "Dynamic Pulse"` cannot hide `DUO "Dynamic Pulse"`.
- Multi-item purchase hides every exact purchased item and no others.
- Missing Builder product ID produces a clear diagnostic log.
- Ambiguous title fallback does not disable a product.

## Phase 7 - Single Totals Service and Immutable Payment Snapshot

### Objective

Ensure payment provider, KeyCRM, email, invoice, and frontend confirmation always use the same totals.

### New Module

- `includes/class-yo-checkout-order-totals.php`

### Changes

- Centralize product subtotal, product discount, promo discount, shipping, card fee, bank total, and card total calculation.
- Save an immutable totals snapshot when payment or bank invoice preparation starts.
- Make Monobank, Western Bid, KeyCRM, email, invoice generation, and Step 4 read the same snapshot.
- Keep separate display labels for card payment and bank invoice without duplicating arithmetic.

### Must Not Change

- Existing fee percentages.
- Existing promo rules.
- Existing shipping rates.
- Bank invoice exclusion of card payment service fee.

### Verification

- Shipping enabled and disabled totals match everywhere.
- Product and promo discounts match everywhere.
- Monobank and Western Bid charged amount matches KeyCRM and email.
- Bank invoice total matches KeyCRM, email, HTML, and PDF.

## Phase 8 - Atomic Reservation Storage

### Objective

Prevent concurrent customers from overwriting reservation state.

### New Module

- `includes/class-yo-checkout-reservation.php`

### Storage

- Add a dedicated WordPress database table, for example `{$wpdb->prefix}yo_checkout_reservations`.
- Use a unique key on stable `product_id`.
- Store owner identifier, title snapshot, created time, and expiry time.

### Changes

- Replace whole-array option read/modify/write operations with atomic insert, update, release, and cleanup queries.
- Keep the current AJAX action names and frontend reservation UI.
- Add migration logic that reads existing option reservations during a short transition period.

### Must Not Change

- Reservation timer duration.
- Reservation badge appearance.
- Cart add/remove behavior.

### Verification

- Two simultaneous customers cannot reserve the same product.
- Removing an item releases only that customer's reservation.
- Expired reservations become available.
- Multi-item carts reserve and release every exact product ID.

## Phase 9 - Frontend Payment State and Message Hardening

### Objective

Reduce stale checkout state and prevent unrelated windows from influencing payment UI.

### Changes

- Validate `postMessage` origin before reacting to payment-return messages.
- Continue validating invoice and active payment session identifiers.
- Define one explicit frontend checkout state object as the runtime source of truth.
- Reduce duplicated order identifiers across localStorage, sessionStorage, cookies, and globals where compatibility allows.
- Add expiry and cleanup for stored checkout markers.
- Split payment coordination from general cart UI when the touched logic is stable enough to extract.

### Suggested Assets

- `assets/yo-checkout-payment.js`
- Later, if needed: `assets/yo-checkout-cart.js`

### Must Not Change

- External payment window UX.
- Payment polling behavior.
- Step 3 and Step 4 visible content.
- Cart persistence for normal customers.

### Verification

- Old payment callbacks cannot open Step 4 for a new cart.
- Messages from an unexpected origin are ignored.
- Refreshing checkout during an active valid payment can recover safely.
- Completed checkout clears only the relevant stored state.

## Phase 10 - Data Retention and Operational Cleanup

### Objective

Prevent long-term growth of temporary options, invoice files, logs, and abandoned local checkout records.

### Changes

- Define retention periods for:
  - Monobank invoice mapping options
  - Western Bid invoice mapping options
  - KeyCRM browser/session mapping options
  - abandoned local checkout orders
  - invoice files
  - debug logs
- Add a scheduled cleanup action with conservative defaults.
- Never delete paid orders or required accounting records without an explicit policy.
- Record cleanup counts and errors in an admin-visible log.

### Verification

- Active and recently completed checkouts remain accessible.
- Expired temporary mappings are removed.
- Paid order records are preserved.
- Cleanup can run repeatedly without error.

## Phase 11 - Legacy Code Removal and Main File Reduction

### Objective

Reduce the chance of editing the wrong copy of logic while preserving behavior.

### Changes

- Remove unreachable legacy sold-item helper copies after the extracted service is confirmed stable.
- Remove WayForPay settings, AJAX actions, REST routes, helpers, and metadata only after Western Bid has remained stable and rollback requirements are satisfied.
- Continue extracting touched responsibilities from `yoleotard-checkout-invoice.php`.

### Recommended Extraction Order

1. Payment finalizer
2. Invoice files and bank invoice flow
3. Shipping service
4. Order access service
5. Reservation service

### Target

- Keep the main plugin file primarily as bootstrap, hook registration, dependency composition, settings entry points, and compatibility wrappers.
- Reduce the main PHP file gradually; do not perform a one-release rewrite.

### Verification

- No removed hook, AJAX action, REST route, or wrapper is still referenced.
- PHP syntax and frontend syntax checks pass.
- Monobank, Western Bid, bank invoice, KeyCRM, email, shipping, reservation, and auto-hide live tests pass.

## Phase 12 - Dompdf Supply-Chain Hardening

### Objective

Avoid downloading and executing an unverified PHP archive.

### Changes

- Prefer bundling Dompdf through a controlled Composer/vendor build.
- If remote installation remains necessary, pin an exact release and verify a documented SHA-256 checksum before extraction.
- Restrict extraction paths and reject unexpected files.

### Must Not Change

- PDF invoice appearance.
- HTML invoice fallback.
- Existing invoice email attachment behavior.

## Phase 13 - Automated Regression Tests

### Objective

Catch checkout regressions before they require real customer payment tests.

### Minimum Test Coverage

- Server rejects browser price tampering.
- Promo expiration and discount calculation.
- Shipping enabled and disabled totals.
- Monobank signature verification.
- Western Bid webhook verification for PayPal and Stripe amount formats.
- Payment finalizer idempotency and stale-lock recovery.
- KeyCRM error retry markers.
- Multi-item reservation concurrency.
- Exact sold-item auto-hide identity.
- Invoice access protection.
- Step 4 readiness requirements.

## Release and Live-Test Matrix

Every runtime phase must run the checks relevant to its scope:

- `php -l yoleotard-checkout-invoice.php`
- `php -l` for every changed PHP file under `includes/`
- `node --check assets/yo-checkout.js` and any new changed JS file
- `git diff --check`
- Confirm no secrets were added to the repository
- Confirm documentation matches the new structure

Live tests should be selected from:

- One-item Monobank payment
- Multi-item Monobank payment
- One-item Western Bid PayPal payment
- One-item Western Bid Stripe payment
- Multi-item Western Bid payment
- One-item bank invoice
- Multi-item bank invoice
- Shipping enabled
- Shipping disabled
- Product discount
- Promo discount
- Reservation conflict from two browsers
- Exact sold-item auto-hide
- Customer email
- KeyCRM order and payment totals
- Step 4 order number and readiness

## Stop Conditions

Stop the current phase and restore the previous stable version if any of the following occurs:

- A payment provider charges a different amount than KeyCRM or email records.
- Step 4 appears before KeyCRM and email completion.
- A valid payment no longer reaches Step 4.
- Bank invoice creates duplicate emails or duplicate KeyCRM product rows.
- A purchased product hides a different YOOtheme item.
- A customer's order or invoice can be accessed using another customer's checkout state.
- A phase requires changing unrelated confirmed working behavior.

## Completion Definition

The audit remediation program is complete when:

- Product prices and totals are authoritative on the server.
- Monobank and Western Bid webhooks are cryptographically verified.
- Public order actions require order ownership tokens.
- Invoice files are not publicly guessable.
- Finalization and KeyCRM side effects are retryable and idempotent.
- Auto-hide uses exact product identity.
- Totals come from one shared service.
- Reservations are atomic.
- Temporary data has a retention policy.
- Legacy duplicate code is removed.
- High-risk flows have automated regression coverage.
