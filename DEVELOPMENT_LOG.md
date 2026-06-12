# YOleotard Checkout Development Log

Codex must read this file before making any code change.

This file records completed work, verification, and repository rollback points.

## Log Format

Each task should record:

- date
- user request
- files changed
- behavior changed
- verification performed
- documentation updates
- repository rollback point or blocker

## 2026-06-12 - Whole-Badge UIkit Promo GIF Tooltip v4.0.61

User request:

- Review the official UIkit tooltip documentation at `https://getuikit.com/docs/tooltip`.
- Use that implementation for the GIF tooltip.
- Make the whole green promo badge trigger the tooltip.
- Remove the separate question-mark help icon.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.js`
- `assets/yo-checkout.css`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `PROJECT_GOVERNANCE.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.61`.
- The configured promo GIF remains managed through the Promo tab Media Library URL field.
- The frontend now applies `uk-tooltip` and the GIF HTML title directly to `.yo-promo-badge`.
- The full green promo badge is focusable and acts as the hover/tap target.
- The separate question-mark tooltip button from v4.0.60 was removed.
- Promo-code calculation, sale-product exclusion, cart behavior, payment providers, KeyCRM, email, and sold-item hiding were not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l` passed for every PHP file under `includes/`.
- `node --check assets/yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for `plugin-archives/yoleotard-checkout-invoice.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, and real `assets/` / `includes/` directories after extraction.

Live test status:

- Not live-tested yet. Install the v4.0.61 test archive, choose a GIF in the Promo tab, then hover/tap the whole green badge on a live product card.

Repository rollback point:

- Local git checkpoint commit: `d998312` (`Make promo GIF tooltip use whole badge`).

## 2026-06-12 - Promo Badge GIF Tooltip v4.0.60

User request:

- Use the current template UIkit tooltip utility to show a GIF explaining how to apply the promo discount.
- Add an admin Promo tab field where the manager can choose/paste the GIF from the WordPress Media Library.
- Show the GIF when hovering or tapping the green discount icon/badge on the product card.
- Verify the plugin and create a test archive.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.js`
- `assets/yo-checkout.css`
- `docs/superpowers/plans/2026-06-12-promo-gif-tooltip.md`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `PROJECT_GOVERNANCE.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.60`.
- Promo settings now include `promo_tooltip_gif_url`, saved as a sanitized URL.
- The Promo tab includes a Media Library picker button for selecting the tooltip GIF.
- Frontend config exposes `promoTooltipGifUrl` to `assets/yo-checkout.js`.
- When a promo badge is active and a GIF URL is configured, the badge shows a small focusable help icon that uses UIkit tooltip markup to display the GIF.
- Promo-code calculation, sale-product exclusion, cart behavior, payment providers, KeyCRM, email, and sold-item hiding were not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l` passed for every PHP file under `includes/`.
- `node --check assets/yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for `plugin-archives/yoleotard-checkout-invoice.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, and real `assets/` / `includes/` directories after extraction.

Live test status:

- Not live-tested yet. Install the v4.0.60 test archive, choose a GIF in the Promo tab, then verify the tooltip on a live product card.

Repository rollback point:

- Local git checkpoint commit: `f91a034` (`Add promo badge GIF tooltip`).

## 2026-06-02 - Western Bid Card Payment Integration v4.0.44

User request:

- Start implementing Western Bid online payment based on `WESTERN_BID_MIGRATION_MAP.md`.
- Keep working Monobank, bank invoice, KeyCRM, email, product identity, reservation, and auto-hide logic intact as much as possible.
- Verify and create a test archive.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-western-bid.php`
- `includes/class-yo-checkout-keycrm.php`
- `includes/class-yo-checkout-sold-items.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `WESTERN_BID_MIGRATION_MAP.md`

Behavior changed:

- Plugin header version increased to `4.0.44`.
- Visible card-provider settings now show Western Bid instead of WayForPay.
- New Western Bid service owns payment form generation, local invoice mapping, return-page messaging, notify/webhook verification, safe notify snapshots, and deferred finalizer queueing.
- Western Bid notify marks a local order paid only after hash, `wb_result=VERIFIED`, `payment_status=Completed`, amount, and currency checks pass.
- Card provider routing now uses `western_bid_countries`; Test mode can force `western_bid`.
- KeyCRM paid payment selection supports `keycrm_payment_method_western_bid`.
- Frontend payment return listener accepts `yo_western_bid_return` while keeping legacy `yo_wayforpay_return` temporarily.
- Existing successful-card finalization remains shared: KeyCRM order creation, paid email, auto-hide, and Step 4 final status are not duplicated in the Western Bid service.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed during implementation.
- `php -l includes\class-yo-checkout-western-bid.php` passed during implementation.
- `php -l includes\class-yo-checkout-keycrm.php` passed during implementation.
- `php -l includes\class-yo-checkout-sold-items.php` passed during implementation.
- `node --check assets\yo-checkout.js` passed during implementation.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.44.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, Western Bid service present, and real `assets/` / `includes/` directories after extraction.

Documentation updates:

- `WESTERN_BID_MIGRATION_MAP.md` reviewed against v4.0.43 and updated before implementation.
- `PLUGIN_MAP.md`, `PROJECT_CONTEXT.md`, `KNOWN_ISSUES.md`, `CHANGELOG.txt`, and this log were updated for v4.0.44.

Repository rollback point:

- Not created yet by user rule. Commit/push only after the user confirms the Western Bid test archive works.

## 2026-06-02 - Western Bid External PayPal/Stripe Window v4.0.45

User request:

- PayPal accepts the password in a normal browser login but not on checkout Step 3.
- Stripe console reports that Stripe Checkout cannot run in an iframe.
- Change the plugin so Western Bid PayPal/Stripe checkout opens outside the iframe, returns to checkout, and Step 4 follows the existing success/failure logic without breaking working features.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-western-bid.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version increased to `4.0.45`.
- Frontend now predicts Western Bid routing from `western_bid_countries` / forced test provider and opens a separate payment window synchronously from the card-payment click.
- Western Bid PayPal/Stripe `pageUrl` is loaded into the separate payment window instead of `#yo-payment-frame`.
- Step 3 shows an external-payment waiting panel and continues polling the original checkout until webhook/final status completes.
- Western Bid return page now posts `yo_western_bid_return` to `window.opener` as well as `window.parent`, then attempts to close the popup.
- Canceled/failed Western Bid returns stop polling and return the customer to payment-method selection.
- Monobank iframe flow and bank invoice flow were not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-western-bid.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.45.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, Western Bid service present, and real `assets/` / `includes/` directories after extraction.

Documentation updates:

- `PLUGIN_MAP.md`, `PROJECT_CONTEXT.md`, `KNOWN_ISSUES.md`, `CHANGELOG.txt`, and this log were updated for v4.0.45.

Repository rollback point:

- Not created yet by user rule. Commit/push only after the user confirms the Western Bid external-window test works.

## 2026-06-02 - External Card Provider Window For Monobank v4.0.46

User request:

- Apply the same outside-iframe logic to Monobank card payments because country/provider payment blocking may have the same cause as PayPal/Stripe iframe restrictions.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version increased to `4.0.46`.
- The frontend now opens a separate payment window synchronously for card payments before provider AJAX completes.
- Any returned card provider `pageUrl`, including Monobank and Western Bid, is loaded into that separate payment window instead of the Step 3 iframe.
- The original checkout modal stays on Step 3, displays a secure-payment-window waiting panel, and continues existing payment polling/final order status checks.
- Monobank backend service, webhook handling, KeyCRM finalization, paid email, Step 4 readiness, reservations, bank invoice flow, and sold-item auto-hide were not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-western-bid.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Confirmed Western Bid login/secret values provided in conversation were not written to repository files.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.46.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, Western Bid service present, and real `assets/` / `includes/` directories after extraction.

Documentation updates:

- `PLUGIN_MAP.md`, `PROJECT_CONTEXT.md`, `KNOWN_ISSUES.md`, `CHANGELOG.txt`, and this log were updated for v4.0.46.

Repository rollback point:

- Not created yet by user rule. Commit/push only after the user confirms the external card provider window test works.

## 2026-06-02 - External Card Provider Return Bridge v4.0.47

User request:

- The external payment window must close after payment confirmation/return and the main checkout must continue to Step 4. This applies to Monobank and other payment types.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-monobank.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version increased to `4.0.47`.
- Monobank redirect URL now points to the site homepage with `yo_checkout_return=card` instead of the missing `/confirm` page.
- Frontend detects external same-site card-provider returns (`/confirm` legacy path or `yo_checkout_return=card`), posts `yo_card_provider_return` to the opener checkout window, and attempts to close the external payment window.
- Main checkout receives `yo_card_provider_return`, continues payment polling, and still waits for the existing final-order status checks before Step 4.
- Western Bid return handling remains in its service, and Monobank backend/webhook/finalizer logic remains unchanged.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-monobank.php` passed.
- `php -l includes\class-yo-checkout-western-bid.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Confirmed Western Bid login/secret values provided in conversation were not written to repository files.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.47.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, Monobank and Western Bid services present, and real `assets/` / `includes/` directories after extraction.

Documentation updates:

- `PLUGIN_MAP.md`, `PROJECT_CONTEXT.md`, `KNOWN_ISSUES.md`, `CHANGELOG.txt`, and this log were updated for v4.0.47.

Repository rollback point:

- Not created yet by user rule. Commit/push only after the user confirms the external return bridge works.

## 2026-06-02 - Western Bid Stripe Address And Notify Diagnostics v4.0.48

User request:

- Monobank external payment window now closes and reaches Step 4 successfully.
- PayPal still rejects the entered password.
- Stripe asks for the full delivery address again even though checkout Step 1 already has it.
- After Stripe payment, the payment window closes but the original checkout does not reach Step 4.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-western-bid.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `WESTERN_BID_MIGRATION_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version increased to `4.0.48`.
- Western Bid form generation now sends an ISO-2 country code and additional address aliases (`country_code`, `zip_code`) alongside the existing buyer fields.
- Western Bid form generation keeps the full local card total in `amount` and no longer sends delivery as a second provider shipping charge; delivery remains included in the checkout total and is recorded in a non-charging note field.
- Western Bid form preparation and webhook receive/verification now write safe `checkout-debug wb-...` lines to the existing admin Hiding log without logging the Western Bid secret key.
- Monobank external payment-window return is documented as live-tested working after v4.0.47.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-western-bid.php` passed.
- `php -l includes\class-yo-checkout-monobank.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Confirmed Western Bid login/secret values provided in conversation were not written to repository files.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.48.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, Monobank and Western Bid services present, and real `assets/` / `includes/` directories after extraction.

Documentation updates:

- `PLUGIN_MAP.md`, `PROJECT_CONTEXT.md`, `KNOWN_ISSUES.md`, `KNOWN_WORKING_FEATURES.md`, `WESTERN_BID_MIGRATION_MAP.md`, `CHANGELOG.txt`, and this log were updated for v4.0.48.

Repository rollback point:

- Not created yet by user rule. Commit/push only after the user confirms Western Bid Stripe/PayPal test behavior is acceptable.

## 2026-06-02 - Western Bid Integrated With Pending Step 4 Confirmation

User request:

- PayPal password issue was resolved by using the specific Western Bid-issued test PayPal login/password.
- Western Bid switched the merchant to real working Stripe/PayPal mode.
- Stripe/PayPal provider payment can complete and the payment window closes, but the main checkout remains on Step 3.
- Commit the current version as a checkpoint: Western Bid payment is implemented and works at the provider handoff/payment level, with the remaining issue in confirmation to Step 4.

Files changed:

- `KNOWN_ISSUES.md`
- `WESTERN_BID_MIGRATION_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- No runtime plugin behavior changed in this entry.
- Documentation now records that PayPal login was not a popup/code issue; it required the Western Bid-issued sandbox PayPal credentials.
- Documentation now records the current remaining Western Bid issue: provider payment succeeds/closes, but checkout has not yet received or accepted a verified notify/local paid marker for Step 4.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-western-bid.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Confirmed Western Bid login/secret values provided in conversation were not written to repository files.

Documentation updates:

- `KNOWN_ISSUES.md`, `WESTERN_BID_MIGRATION_MAP.md`, and this log were updated.

Repository rollback point:

- Local git checkpoint commit: `418254a` (`Add Western Bid payment integration checkpoint`).

## 2026-06-02 - Western Bid Notify Invoice Prefix Mapping v4.0.49

User request:

- User provided Western Bid logs after a successful provider payment where checkout still stayed on Step 3.
- Diagnose why the completed payment was not transferred into checkout Step 4.

Root cause:

- Checkout submitted Western Bid form invoice `YO-WB-22037-1780403106`.
- Western Bid notify reached the site with `invoice = {wb_login}-YO-WB-22037-1780403106`, adding the merchant login before the local invoice.
- The previous webhook handler only looked up `yo_western_bid_order_{invoice}` by the exact received invoice, so it could not find local order `22037` and returned before marking the order paid.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-western-bid.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `WESTERN_BID_MIGRATION_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version increased to `4.0.49`.
- Western Bid webhook now maps both the full received invoice and normalized local invoice candidates back to the same local order.
- Webhook hash verification still uses the full received Western Bid invoice, preserving the documented security check.
- When a prefixed notify invoice maps successfully, the full notify invoice is saved as an alias for future polling/lookup.
- If no mapping is found, the log now writes `western_bid webhook order not found` with the candidate invoice values.
- Country ISO conversion now maps `San Marino` to `SM`; previous fallback produced `SA`, which is Saudi Arabia and could confuse Western Bid/Stripe address handling.
- Full user log showed Stripe/Western Bid notify does reach the plugin with `payment_status=Completed`, `wb_result=VERIFIED`, and matching `mc_gross`, but `mc_currency` can be empty.
- Western Bid verification now treats empty `mc_currency` as the configured Western Bid currency after hash/status/amount checks pass.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-western-bid.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Confirmed Western Bid login/secret values provided in conversation were not written to repository files.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.49.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, Monobank and Western Bid services present, and real `assets/` / `includes/` directories after extraction.

Documentation updates:

- `PLUGIN_MAP.md`, `PROJECT_CONTEXT.md`, `KNOWN_ISSUES.md`, `WESTERN_BID_MIGRATION_MAP.md`, `CHANGELOG.txt`, and this log were updated for v4.0.49.

Repository rollback point:

- Not created yet. Commit/push after live test confirms Step 4 completion.

## 2026-06-03 - Western Bid Stripe Confirmation And Step 4 v4.0.50

User request:

- PayPal payment completed, created the KeyCRM order, and sent the customer email, but checkout remained on Step 3.
- Stripe payment completed at the provider, but Western Bid webhook verification failed and checkout remained on Step 3.
- Fix Stripe confirmation, keep the working payment flows intact, and create a test archive.

Root cause:

- Western Bid Stripe notify sent the paid amount as raw string `1`, while the previous verifier normalized it to `1.00` before calculating the webhook hash.
- The numeric amount is equivalent, but the MD5 input string is different, so the valid Stripe notify was rejected as `Western Bid notify hash is invalid`.
- Western Bid can also use either the original local invoice `YO-WB-...` or the merchant-prefixed notify invoice during the return/polling path, so Step 4 matching must accept both values for the same local order.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-western-bid.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `WESTERN_BID_MIGRATION_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version increased to `4.0.50`.
- Western Bid hash verification now tries the raw provider `mc_gross` string and the normalized two-decimal amount.
- After hash verification, the paid amount is still normalized and compared strictly with the local order total.
- Western Bid payment polling and final-order status now recognize both the local invoice and merchant-prefixed notify invoice for the same local order.
- Monobank, bank invoice, KeyCRM, email, reservation, and sold-item auto-hide behavior were not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-western-bid.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Confirmed Western Bid login/secret values provided in conversation were not written to repository files.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.50.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, Western Bid service present, and real `assets/` / `includes/` directories after extraction.

Documentation updates:

- `PLUGIN_MAP.md`, `PROJECT_CONTEXT.md`, `KNOWN_ISSUES.md`, `WESTERN_BID_MIGRATION_MAP.md`, `CHANGELOG.txt`, and this log were updated for v4.0.50.

Repository rollback point:

- Working v4.0.50 commit pushed to GitHub: `fe2b778` (`Fix Western Bid Stripe confirmation`).

Live test confirmation:

- User confirmed the v4.0.50 payment confirmation fix works.
- Western Bid PayPal created the KeyCRM order and sent the customer email.
- Western Bid Stripe payment confirmation continued to Step 4.
- A separate totals issue was reported: disabled delivery is excluded from card payment but still appears in customer email and KeyCRM totals.
- Stripe log confirmed `western_bid webhook received` followed by `western_bid webhook completed` for a raw `mc_gross="1"` and empty `mc_currency`.
- The same test exposed a separate auto-hide title fallback issue: a purchased `test "Dynamic Pulse"` item disabled a `DUO "Dynamic Pulse"` Builder item.

## 2026-06-03 - Persist Disabled Shipping Total v4.0.51

User request:

- After confirming Western Bid works, user reported that disabled delivery is excluded from card payment but still appears in the customer email and KeyCRM total.

Root cause:

- `shipping_data()` returned `0.00` when shipping was disabled, so card payment calculation was correct.
- The disabled branch did not persist that zero into `shipping_cost_eur`.
- KeyCRM and email later read the older stored `shipping_cost_eur` value and included stale delivery in their totals.

Files changed:

- `yoleotard-checkout-invoice.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version increased to `4.0.51`.
- Disabled shipping now persists `shipping_cost_eur = 0.00` and `shipping_source = disabled`.
- Old selected-delivery metadata is cleared when shipping is disabled.
- Card payment, KeyCRM, and customer email now read the same stored shipping total.
- Confirmed Western Bid payment confirmation logic was not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `php -l includes\class-yo-checkout-email.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- All PHP files under `includes/` passed `php -l` before the v4.0.51 live-test archive was prepared.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and the local reference copy `plugin-archives/yoleotard-checkout-invoice-v4.0.51.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, main plugin file present, and real `assets/` / `includes/` directories after extraction.
- Live test is still required.

Documentation updates:

- `PLUGIN_MAP.md`, `PROJECT_CONTEXT.md`, `KNOWN_ISSUES.md`, `CHANGELOG.txt`, and this log were updated for v4.0.51.

Repository rollback point:

- User confirmed v4.0.51 works after live testing on 2026-06-03.
- The test log showed Monobank order creation for local order `#22064`, KeyCRM order `#592`, and successful sold-item auto-hide for `new_leotard_velvet_flowers`.
- Stable v4.0.51 commit pushed to GitHub: `6ee6734` (`Confirm v4.0.51 shipping totals and add audit map`).

## 2026-06-03 - Holistic Audit Remediation Map

User request:

- Create a staged map file from the completed holistic plugin audit.
- Preserve the current functional checkout behavior and include only the improvements identified during the audit.
- Use the map before starting new feature development.

Files changed:

- `AUDIT_REMEDIATION_MAP.md`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Documentation changed:

- Added a dedicated audit remediation map with functional invariants, implementation rules, priority levels, staged phases, verification gates, live-test matrix, stop conditions, and completion definition.
- Added `AUDIT_REMEDIATION_MAP.md` to the required reading list before future code changes and new functionality.
- No runtime code, plugin version, payment logic, checkout behavior, or integration setting was changed.

Verification performed:

- Reviewed the map against the current plugin structure and the 2026-06-03 audit findings.
- `git diff --check` will be run after the documentation update.

Repository rollback point:

- Include this audit map with the approved stable v4.0.51 rollback point.

## 2026-06-03 - Project Manager Start Guide

User request:

- Create an initial instruction for a new manager describing how the development plan and work sequence should look based on the current project.
- The instruction will be used as a template for creating a new project.

Files changed:

- `PROJECT_MANAGER_START_GUIDE.md`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Documentation changed:

- Added a manager-facing process guide that covers required project documents, planning structure, standard task sequence, verification, versioning, live testing, archive creation, Git rollback points, working-feature protection, known-issue handling, architecture rules, payment rules, data authority rules, stop conditions, and a reusable phase template.
- Added the guide to project context and plugin map references.
- No runtime code, plugin version, payment logic, checkout behavior, or integration setting was changed.

Verification performed:

- Documentation-only change; `git diff --check` will be run before commit.

Repository rollback point:

- Documentation commit pushed to GitHub: `448b1d4` (`Add project manager start guide`).

## 2026-06-06 - Checkout Logic Analyst Agent

User request:

- Create an analytics agent that checks the current plugin code, performs logical verification of checkout behavior, identifies error stages and mismatches, and hands findings off for implementation.

Files changed:

- `.agents/checkout-logic-analyst.md`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Documentation changed:

- Added a reusable analysis-first agent instruction for checkout logic investigations.
- The agent must read the project documentation, map expected vs actual flow, inspect logs/code/state, classify findings, and produce an implementation handoff before code changes.
- Added references to the agent in project context and plugin map.
- No runtime code, plugin version, payment logic, checkout behavior, or integration setting was changed.

Verification performed:

- Documentation-only change; `git diff --check` will be run before commit.

Repository rollback point:

- Documentation commit pushed to GitHub: `2c48b46` (`Add checkout logic analyst agent`).

## 2026-06-06 - Project Agent System

User request:

- Create the necessary agents for the project and define their rules and relationships.

Files changed:

- `.agents/README.md`
- `.agents/payment-integrity-agent.md`
- `.agents/order-totals-agent.md`
- `.agents/keycrm-sync-agent.md`
- `.agents/email-invoice-agent.md`
- `.agents/product-identity-autohide-agent.md`
- `.agents/security-privacy-agent.md`
- `.agents/frontend-checkout-state-agent.md`
- `.agents/qa-regression-agent.md`
- `.agents/release-packaging-agent.md`
- `.agents/documentation-curator-agent.md`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Documentation changed:

- Added a coordinated agent system with global rules, default workflows, blocking rules, and handoff format.
- Added domain agents for payment integrity, totals, KeyCRM, emails/invoices, product identity and auto-hide, security/privacy, frontend checkout state, QA regression, release/packaging, and documentation curation.
- Updated project context and plugin map to point to `.agents/README.md` as the agent index.
- No runtime code, plugin version, payment logic, checkout behavior, or integration setting was changed.

Verification performed:

- Documentation-only change; `git diff --check` will be run before commit.

Repository rollback point:

- Documentation commit pushed to GitHub: `0aa7f26` (`Add coordinated project agent system`).

## 2026-06-06 - Project Orchestrator Agent

User request:

- Create an orchestrator so development follows the project rules.

Files changed:

- `.agents/project-orchestrator-agent.md`
- `.agents/README.md`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Documentation changed:

- Added the Project Orchestrator Agent as the main coordinator for task intake, agent selection, phase control, blocking rules, implementation handoff, verification, documentation, release, and archive flow.
- Updated the agent index so the orchestrator runs before domain agents for bug investigations and audit remediation phases.
- Updated project context and plugin map to point coordinated work through the orchestrator first.
- No runtime code, plugin version, payment logic, checkout behavior, or integration setting was changed.

Verification performed:

- Documentation-only change; `git diff --check` will be run before commit.

Repository rollback point:

- Documentation commit pushed to GitHub: `7c4cf2c` (`Add project orchestrator and governance model`).

## 2026-06-06 - Project Governance Model

User request:

- Rebuild project management so development follows the correct rules.

Files changed:

- `PROJECT_GOVERNANCE.md`
- `.agents/project-orchestrator-agent.md`
- `.agents/README.md`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `PROJECT_MANAGER_START_GUIDE.md`
- `DEVELOPMENT_LOG.md`

Documentation changed:

- Added a top-level project governance model with task gates from intake through release and rollback.
- Defined management roles, agent responsibilities, task classification, implementation permission, blocking model, phase governance, archive governance, Git governance, and version governance.
- Updated the orchestrator so it uses `PROJECT_GOVERNANCE.md` as the controlling project-management model.
- Added governance references to project context, plugin map, manager guide, and agent index.
- No runtime code, plugin version, payment logic, checkout behavior, or integration setting was changed.

Verification performed:

- Documentation-only change; `git diff --check` will be run before commit.

Repository rollback point:

- Documentation commit pushed to GitHub: `7c4cf2c` (`Add project orchestrator and governance model`).

## 2026-06-06 - Phase 0 Regression Baseline

User request:

- Start the next implementation point from the development plan.
- The current queued point was `Phase 0 - Baseline and Regression Harness`.

Files changed:

- `REGRESSION_BASELINE.md`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `PROJECT_GOVERNANCE.md`
- `DEVELOPMENT_LOG.md`

Documentation changed:

- Added the Phase 0 regression baseline for stable v4.0.51 checkout behavior.
- Documented the current AJAX endpoint request/response fields for order creation, card payment start, bank invoice creation, payment polling, and final order status polling.
- Documented expected one-item card, multi-item card, bank invoice, shipping enabled/disabled, product discount, and promo monitoring behavior.
- Added the regression test matrix and local/release verification baseline for future audit remediation phases.
- Marked Phase 0 as completed in `AUDIT_REMEDIATION_MAP.md`.
- Updated governance to show `Phase 1 - Trusted Server-Side Product Catalog` as the next planned runtime implementation phase.
- No runtime code, plugin version, payment logic, checkout behavior, or integration setting was changed.

Verification performed:

- Documentation-only change; `git diff --check` will be run before commit.

Repository rollback point:

- Documentation commit pushed to GitHub: `ba7603e` (`Add phase 0 regression baseline`).

## 2026-06-06 - Phase 1 Trusted Server-Side Product Catalog v4.0.52

User request:

- Start the next implementation point from the development plan after Phase 0.
- Implement Phase 1 without breaking confirmed Monobank, Western Bid, bank invoice, KeyCRM, email, shipping, reservation, and sold-item auto-hide behavior.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-product-catalog.php`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `KNOWN_ISSUES.md`
- `CHANGELOG.txt`
- `DEVELOPMENT_LOG.md`

Implementation:

- Added `YO_Checkout_Product_Catalog_Service` in a separate include file.
- Connected the service through a thin wrapper in the main plugin class.
- `sanitize_cart_items_json()` now attempts to resolve each cart item by stable `product_id` from the configured YOOtheme product source page.
- When a product resolves, the stored order item uses server-side title, current price, original price, product discount, weight, and image.
- `sanitize_order_input()` now recalculates the order snapshot totals from the sanitized/trusted cart items before promo logic runs.
- Promo-code logic still runs after catalog resolution in the existing promo service.
- If a product cannot be resolved during this transition phase, checkout keeps the sanitized browser values for compatibility and records `product_catalog_status` / `product_catalog_summary` diagnostics.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l` passed for every PHP file under `includes/`, including the new product catalog service.
- Payment provider classes, KeyCRM service, email service, promo service, and sold-item service were not edited.

Live test status:

- Not live-tested yet. Treat v4.0.52 as a Phase 1 test candidate until a site checkout confirms catalog resolution and all protected payment flows still work.

Repository rollback point:

- Pending until final verification and commit/push.

## 2026-06-06 - Product Catalog Memory Hotfix v4.0.53

User report:

- After installing/testing v4.0.52, checkout failed on Step 2 with `Connection error`.
- Checkout debug log showed `yo_checkout_create_order fatal shutdown` with `Allowed memory size of 536870912 bytes exhausted`.

Root cause:

- The first Phase 1 product catalog implementation scanned the configured YOOtheme page by loading all post meta values.
- For array/serialized values it called `maybe_unserialize()` and `wp_json_encode()` to flatten the full meta tree before searching.
- On the live YOOtheme Builder page this can expand a very large structure and exhaust PHP memory during `yo_checkout_create_order`.

Files changed:

- `includes/class-yo-checkout-product-catalog.php`
- `yoleotard-checkout-invoice.php`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `KNOWN_ISSUES.md`
- `CHANGELOG.txt`
- `DEVELOPMENT_LOG.md`

Implementation:

- Removed full meta flattening from the product catalog source reader.
- The catalog now searches raw post content/meta strings for the requested `product_id` / title and parses only small nearby fragments.
- Fragment reads are capped to avoid loading or copying the full YOOtheme Builder tree during checkout creation.
- Payment, KeyCRM, email, promo, shipping, reservation, and sold-item auto-hide services were not edited.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l` passed for every PHP file under `includes/`.

Live test status:

- Not live-tested yet. v4.0.53 replaces v4.0.52 as the product catalog test candidate.

Repository rollback point:

- Pending until final verification and commit/push.

## 2026-06-06 - Product Catalog Matching And Payment Window UX v4.0.54

User report:

- v4.0.53 allowed both Monobank card payment and bank invoice checkout to complete successfully.
- Monobank card payment created KeyCRM order `#595`, email/finalization completed, and sold-item auto-hide disabled `new_leotard_velvet_flowers`.
- Bank invoice created KeyCRM order `#596`, generated invoice files, and sent the customer email.
- Checkout-debug still reported `product catalog fallback used` with `partial_fallback`.
- The Monobank payment popup stayed on `pay.monobank.ua` until the customer clicked `Return to site`, although the main checkout had already reached Step 4.

Root cause:

- The product catalog hotfix looked for small source fragments by `product_id` / title, but `parse_card_text()` still required the literal `product_id` to exist inside the fragment.
- Some product IDs are added to the live DOM by frontend enhancers and may not exist literally in the stored YOOtheme Builder data.
- Browser JavaScript cannot run on `pay.monobank.ua`, so the popup cannot close itself until Monobank redirects it back to the site; however, the opener checkout can attempt to close a window it opened once Step 4 is reached.

Files changed:

- `assets/yo-checkout.js`
- `includes/class-yo-checkout-product-catalog.php`
- `yoleotard-checkout-invoice.php`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `CHANGELOG.txt`
- `DEVELOPMENT_LOG.md`

Implementation:

- Product catalog now accepts a title-derived match when the stored card title generates the requested canonical product ID.
- Frontend stores the active card-payment popup reference and attempts to close it when Step 4 success is shown.
- Back/cancel payment cleanup also closes the active payment window if one exists.

Verification performed:

- Pending final local syntax checks after this documentation update.

Live test status:

- Not live-tested yet. v4.0.54 should be tested against the same one-item Monobank and bank invoice scenarios that passed on v4.0.53, checking whether `product_catalog_status` becomes `trusted`.

Repository rollback point:

- Pending until final verification and commit/push.

## 2026-05-31 - Repository Documentation Baseline

User request:

- Fix the current working version in GitHub.
- Create repository files:
  - `PROJECT_CONTEXT.md`
  - `PLUGIN_MAP.md`
  - `DEVELOPMENT_LOG.md`
  - `KNOWN_ISSUES.md`
- Ask Codex to always read these files before making changes.
- Gradually move new functions into separate classes.

Files changed:

- Added `PROJECT_CONTEXT.md`.
- Added `DEVELOPMENT_LOG.md`.
- Added `KNOWN_ISSUES.md`.
- Updated `PLUGIN_MAP.md`.
- Kept `WORK_HISTORY.md` as a legacy note from the initial mapping step.

Behavior changed:

- No runtime plugin behavior changed. Documentation and process only.

Verification performed:

- `node --check assets\yo-checkout.js` passed with no syntax errors.
- `php -l yoleotard-checkout-invoice.php` could not run because `php` is not installed or not available in PATH in the current local environment.
- `git status -sb` showed only documentation changes for this task.

Documentation updates:

- Added required-reading rule for future Codex work.
- Added gradual class extraction direction for new functionality.
- Added repository rollback and GitHub publishing process.

Repository rollback point:

- Baseline documentation commit pushed to GitHub: `ee843e6` (`Add project documentation baseline`).

## 2026-05-31 - PHP Syntax Verification

User request:

- Check the plugin code through PHP because the previous PHP check could not run.

Files changed:

- Updated `DEVELOPMENT_LOG.md`.
- Updated `KNOWN_ISSUES.md`.

Behavior changed:

- No runtime plugin behavior changed. Documentation and verification only.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- PHP executable found at `D:\Projects\php-8.5.6-nts-Win32-vs17-x64\php.exe`.
- Output: `No syntax errors detected in yoleotard-checkout-invoice.php`.

Documentation updates:

- Recorded successful PHP syntax verification.
- Updated PHP CLI known issue from active environment limitation to resolved environment note.

Repository rollback point:

- PHP verification log commit pushed to GitHub: `aa3938f` (`Record PHP syntax verification`).

## 2026-05-31 - Extract Sold Item Auto-Hide Service

User request:

- Analyze why products are not hidden after successful Monobank checkout.
- Propose and apply the correct fix.
- Move the sold-item hiding function out of the main plugin file into a separate file/class.
- Make logs use the real KeyCRM order number instead of arbitrary-looking local WordPress IDs.

Files changed:

- Added `includes/class-yo-checkout-sold-items.php`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.
- Updated `KNOWN_ISSUES.md`.

Behavior changed:

- Sold-item auto-hide backend logic is now handled by `YO_Checkout_Sold_Items_Service`.
- Existing main-file methods now delegate to the sold-items service.
- Auto-hide logs now prefer the KeyCRM `order_id` and include the local WordPress ID only as technical context.
- Page backup for auto-hide now skips previous `_yo_checkout_autohide_backup_*` meta entries to avoid recursively storing old backups and making the page meta payload too large.
- Optional frontend fallback renderer is delegated to the sold-items service.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git status -sb` showed only this task's code and documentation changes.

Documentation updates:

- `PLUGIN_MAP.md` updated with the new `includes/class-yo-checkout-sold-items.php` service and delegated sold-item flow.
- `KNOWN_ISSUES.md` updated with the resolved auto-hide backup/logging risk.

Repository rollback point:

- Sold-item service extraction commit pushed to GitHub: `28613e4` (`Extract sold item auto-hide service`).

## 2026-05-31 - Bank Invoice Invalid Buyer Retry And Clean Sold Logs

User request:

- Analyze why bank invoice checkout shows `KeyCRM order was not created` with `buyer.id is invalid`.
- Clean auto-hide logs so product names do not show raw escape fragments such as `u201c`.
- After comprehensive verification, propose what should be done to fix the invoice and log issues.

Files changed:

- Updated `yoleotard-checkout-invoice.php`.
- Updated `includes/class-yo-checkout-sold-items.php`.
- Updated `DEVELOPMENT_LOG.md`.
- Updated `KNOWN_ISSUES.md`.

Behavior changed:

- Bank invoice KeyCRM update/create flow now detects `buyer.id` invalid errors, deletes the stale local `buyer_id`, creates a fresh KeyCRM buyer from the checkout data, and retries once.
- Auto-hide log product titles are decoded/cleaned before saving, so JSON-style unicode fragments like `u201cBlack Radianceu201d` render as readable quoted text.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.

Documentation updates:

- `KNOWN_ISSUES.md` updated with invoice invalid-buyer diagnosis and monitoring note.

Repository rollback point:

- Invoice invalid-buyer retry commit: `dbbe888` (`Fix bank invoice invalid buyer retry`).

## 2026-05-31 - Version Accounting Rule

User request:

- Keep version accounting inside the plugin at Codex discretion.
- Make it clear what changed compared with the previous plugin version.
- Create test versions for site testing only when explicitly requested.

Files changed:

- Updated `yoleotard-checkout-invoice.php`.
- Added `CHANGELOG-v4.0.26.txt`.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- Plugin header version increased to `4.0.26`.
- Plugin description now summarizes the bank-invoice invalid-buyer retry and sold-log cleanup.
- Versioning workflow documented: runtime changes should bump the header version and add a concise matching changelog.
- Test ZIP archive workflow documented: create ZIPs only on explicit request.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `node --check assets\yo-checkout.js` passed.

Documentation updates:

- Added version-accounting and test-archive rules to project context/map.

Repository rollback point:

- Version accounting commit: `4dceda3` (`Add plugin version accounting`).

## 2026-05-31 - Email Product Thumbnails And Single Changelog

User request:

- Keep only one plugin update text file and append future version notes to it.
- Add mini product images to customer emails for card payments and bank invoice payments.

Files changed:

- Updated `yoleotard-checkout-invoice.php`.
- Added `CHANGELOG.txt`.
- Removed separate `CHANGELOG-v*.txt` files from the plugin package.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- Plugin header version increased to `4.0.27`.
- Customer order emails now show a compact product list with 64px thumbnails when order items have image URLs.
- The same shared email order block is used by both paid-card emails and bank-invoice emails.
- Version notes now live in one append-only `CHANGELOG.txt` file.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `node --check assets\yo-checkout.js` passed.
- Confirmed only one changelog text file remains: `CHANGELOG.txt`.

Documentation updates:

- Updated version/changelog rules to require one append-only `CHANGELOG.txt`.

Repository rollback point:

- Email thumbnails and single changelog commit: `85cd5e9` (`Add email product thumbnails`).

## 2026-05-31 - Extract Promo Code Service

User request:

- Move the promo code function out of the main plugin file and connect it back to the main file so it can be changed separately later.

Files changed:

- Added `includes/class-yo-checkout-promo.php`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- No intended customer-facing behavior change.
- Promo code checks, expiration handling, discount calculation, AJAX application, and order-data promo application now live in `YO_Checkout_Promo_Service`.
- The main plugin file now keeps small wrapper methods and delegates promo behavior to the service.
- Plugin version and `CHANGELOG.txt` were not changed because this is an internal extraction without functional behavior changes.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-promo.php` passed.
- `node --check assets\yo-checkout.js` passed.

Documentation updates:

- `PLUGIN_MAP.md` updated with the new promo service and delegated promo flow.

Repository rollback point:

- Promo service extraction commit: `2fa9cb3` (`Extract promo code service`).

## 2026-05-31 - Extract Google Reviews Service

User request:

- In future test ZIP archive names, include the current working plugin version.
- Move Google Reviews into a separate file/function and connect it back to the main file.
- Check whether the Google Reviews function still works.

Files changed:

- Added `includes/class-yo-checkout-google-reviews.php`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- No intended customer-facing behavior change.
- Google Customer Reviews footer script rendering now lives in `YO_Checkout_Google_Reviews_Service`.
- The main plugin file delegates `render_google_customer_reviews_scripts()` to the service.
- The frontend callback name `window.YOCheckoutGoogleReviews` and queue/render logic are preserved.
- Test archive naming rule updated: include the current plugin version in archive filenames.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-google-reviews.php` passed.
- `node --check assets\yo-checkout.js` passed.
- Confirmed the service still renders `window.YOCheckoutGoogleReviews`, `yoRenderGoogleCustomerReviewsOptIn`, Google `platform.js`, merchant widget script, `surveyoptin.render`, and `merchantWidget.start`.
- Confirmed `assets\yo-checkout.js` still calls `window.YOCheckoutGoogleReviews()` after successful card payment when opt-in is enabled.

Documentation updates:

- `PROJECT_CONTEXT.md` and `PLUGIN_MAP.md` updated with versioned test archive naming rule.
- `PLUGIN_MAP.md` updated with the new Google Reviews service and delegated render flow.

Repository rollback point:

- Google Reviews service extraction commit: `a3c81e3` (`Extract Google Reviews service`).

## 2026-05-31 - Western Bid Migration Map

User request:

- Analyze the provided Western Bid documentation and credentials.
- Create a complete action map for replacing WayForPay with Western Bid later.
- Do not implement the replacement now.

Files changed:

- Added `WESTERN_BID_MIGRATION_MAP.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- No runtime plugin behavior changed.
- Western Bid implementation plan is now stored as a repository document.
- Secret key was not written to any repository file.

Verification performed:

- Extracted Western Bid documentation text from the provided PDFs using local `pdftotext`.
- Reviewed current WayForPay code surface and identified settings, AJAX, REST, provider routing, fee, KeyCRM, polling, webhook, and frontend touchpoints.

Documentation updates:

- `PLUGIN_MAP.md` updated with `WESTERN_BID_MIGRATION_MAP.md`.

Repository rollback point:

- Western Bid migration map commit: `df0d529` (`Add Western Bid migration map`).

## 2026-05-31 - Extract Monobank Service

User request:

- Move the Monobank function/integration into a separate file and connect it back to the main file.

Files changed:

- Added `includes/class-yo-checkout-monobank.php`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- No intended customer-facing behavior change.
- Monobank invoice creation, invoice status checking, invoice/local-order mapping helper, and webhook handling now live in `YO_Checkout_Monobank_Service`.
- The main plugin file now delegates Monobank start/status/webhook work to the service while keeping the shared successful-payment finalizer in the main class.
- Plugin version and `CHANGELOG.txt` were not changed because this is an internal extraction without functional behavior changes.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-monobank.php` passed.
- `node --check assets\yo-checkout.js` passed.

Documentation updates:

- `PLUGIN_MAP.md` updated with the new Monobank service and delegated Monobank flow.

Repository rollback point:

- Monobank service extraction commit: `c40e4fa` (`Extract Monobank service`).

## 2026-05-31 - Extract KeyCRM Service

User request:

- Move all KeyCRM-related functionality into a separate file and connect it back to the main plugin file.

Files changed:

- Added `includes/class-yo-checkout-keycrm.php`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- No intended customer-facing behavior change.
- KeyCRM marker lookup/reuse, buyer/order creation and update, product synchronization helpers, payment records, order comments, paid-card order creation, and raw KeyCRM API requests now live in `YO_Checkout_KeyCRM_Service`.
- The main plugin file keeps thin wrapper methods for the existing KeyCRM method names so checkout, invoice, payment finalization, and unpaid-order flows continue to call the same internal API.
- Plugin version and `CHANGELOG.txt` were not changed because this is an internal extraction without functional behavior changes.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `node --check assets\yo-checkout.js` passed.

Documentation updates:

- `PLUGIN_MAP.md` updated with the new KeyCRM service and delegated KeyCRM flow.

Repository rollback point:

- KeyCRM service extraction commit: `4b03c8b` (`Extract KeyCRM service`).

## 2026-06-01 - Prevent Duplicate Bank Invoice Emails And KeyCRM Product Rows

User request:

- Fix the issue found during testing where a two-item bank invoice order sent two emails and duplicated one product row in KeyCRM.

Files changed:

- Updated `assets/yo-checkout.js`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `includes/class-yo-checkout-keycrm.php`.
- Updated `CHANGELOG.txt`.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `KNOWN_ISSUES.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- Plugin version increased to `4.0.28`.
- Bank invoice button now has a frontend in-progress guard to prevent repeat submissions.
- Bank invoice creation now has a server-side lock and cart hash reuse so the same request returns the existing invoice without resending email or adding another KeyCRM bank payment.
- Bank invoice email sending is idempotent per cart hash.
- KeyCRM existing product rows are matched before order update so repeated invoice requests update rows instead of appending duplicate products.
- Unsafe KeyCRM zero-quantity row deletion remains disabled.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed.

Documentation updates:

- `CHANGELOG.txt`, `PROJECT_CONTEXT.md`, `PLUGIN_MAP.md`, and `KNOWN_ISSUES.md` updated for v4.0.28.

Repository rollback point:

- Duplicate bank invoice handling fix commit: `855ca0d` (`Fix duplicate bank invoice handling`).

## 2026-06-01 - Make Bank Invoice Lock Retry-Safe

User request:

- Fix the Step 3 bank invoice message `Bank invoice is already being prepared` / `Connection error` after testing one-item and two-item invoice orders.

Files changed:

- Updated `assets/yo-checkout.js`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `CHANGELOG.txt`.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `KNOWN_ISSUES.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- Plugin version increased to `4.0.29`.
- Active bank invoice locks now return `success:true` with `preparing:true` instead of a customer-facing error.
- Frontend bank invoice flow now waits and retries while invoice preparation is active.
- Frontend still resets the bank invoice button on real failure.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed.

Documentation updates:

- `CHANGELOG.txt`, `PROJECT_CONTEXT.md`, `PLUGIN_MAP.md`, and `KNOWN_ISSUES.md` updated for v4.0.29.

Repository rollback point:

- Bank invoice retry-safe lock commit: `7e40e33` (`Make bank invoice lock retry safe`).

## 2026-06-01 - Refresh Bank Invoice Cart And Expired Promo State

User request:

- Fix the Step 3 bank invoice `Connection error` after adding two products, and fix one-product invoice generation that reused two old products and an expired May 31 promo discount on June 1.

Files changed:

- Updated `assets/yo-checkout.js`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `CHANGELOG.txt`.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `KNOWN_ISSUES.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- Plugin version increased to `4.0.30`.
- Bank invoice click now saves the current checkout form and current cart through `yo_checkout_create_order` before calling `yo_checkout_create_bank_invoice`.
- Bank invoice reuse metadata is cleared when the local order is refreshed, so a stale invoice/cart hash cannot be reused after the customer changes products.
- Frontend promo cart persistence now depends on the current active promo configuration.
- Bank invoice creation strips invalid or expired promo code data from reused local order meta before recalculating bank totals and generating invoice files.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `php -l includes\class-yo-checkout-promo.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Documentation updates:

- `CHANGELOG.txt`, `PROJECT_CONTEXT.md`, `PLUGIN_MAP.md`, and `KNOWN_ISSUES.md` updated for v4.0.30.

Repository rollback point:

- Bank invoice cart refresh and expired promo cleanup commit: `6a80172` (`Refresh bank invoice cart state`).

## 2026-06-01 - Document Test Archive Packaging Rule

User request:

- Remember the correct archive structure so WordPress updates the existing plugin instead of installing a new plugin.

Files changed:

- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- No plugin runtime behavior changed.
- Documentation now requires test ZIP files to contain one top-level folder named exactly `yoleotard-checkout-invoice/`, even when the ZIP filename includes the test version and description.

Verification performed:

- Documentation-only change; no PHP/JS syntax check required.
- `git diff --check` passed with only Git line-ending warnings.

Documentation updates:

- Added the archive packaging rule to the project context and plugin map.

Repository rollback point:

- Archive packaging documentation commit: `909e622` (`Document plugin archive packaging`).

## 2026-06-01 - Correct Host-Safe Plugin ZIP Name Rule

User request:

- The uploaded test archive still created a duplicate plugin in WordPress and a duplicate plugin folder on hosting. Document and use the archive format that updates the existing plugin folder.

Files changed:

- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.
- Created local test archive `yoleotard-checkout-invoice.zip`.

Behavior changed:

- No plugin runtime behavior changed.
- Documentation now requires the installable ZIP filename to be exactly `yoleotard-checkout-invoice.zip` for this host, with one internal top-level folder `yoleotard-checkout-invoice/`.
- Version information remains in the plugin header and `CHANGELOG.txt`, not in the upload ZIP filename.

Verification performed:

- Verified `yoleotard-checkout-invoice.zip` contains `yoleotard-checkout-invoice/` as its top-level folder.
- Documentation-only code change; no PHP/JS syntax check required.

Documentation updates:

- Corrected the previous archive naming rule in project context and plugin map.

Repository rollback point:

- Host-safe plugin archive naming commit: `1d6fa63` (`Correct plugin archive naming rule`).

## 2026-06-01 - Fix ZIP Path Separator Packaging

User request:

- After uploading the archive, WordPress asked to activate the plugin and then showed `Plugin file not found`; on hosting the plugin folder contained flat filenames with backslashes instead of real directories.

Files changed:

- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `KNOWN_ISSUES.md`.
- Updated `DEVELOPMENT_LOG.md`.
- Rebuilt local test archive `yoleotard-checkout-invoice.zip` using forward-slash ZIP paths.

Behavior changed:

- No plugin runtime behavior changed.
- Test archive creation now avoids PowerShell `Compress-Archive` for the installable package and requires explicit forward-slash ZIP entries.

Verification performed:

- Verified `yoleotard-checkout-invoice.zip` contains zero entries with `\`.
- Verified the first entries are under `yoleotard-checkout-invoice/`.
- Locally extracted the ZIP and confirmed `assets/` and `includes/` are real directories.

Documentation updates:

- Added host-specific ZIP path separator warning and verification steps to project context, plugin map, and known issues.

Repository rollback point:

- ZIP path separator packaging documentation commit: `85ca1f5` (`Document host-safe zip paths`).

## 2026-06-01 - Add Bank Invoice Step 3 Diagnostics

User request:

- Step 3 still shows `Connection error`; KeyCRM orders are not created and customer invoice emails are not sent. Add extended logging so the cause can be identified.

Files changed:

- Updated `assets/yo-checkout.js`.
- Updated `yoleotard-checkout-invoice.php`.
- Updated `CHANGELOG.txt`.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `KNOWN_ISSUES.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- Plugin version increased to `4.0.31`.
- Frontend bank invoice connection errors now display the AJAX action, HTTP status, debug ID, local order ID, KeyCRM marker, cart count, server message/details, and response excerpt when available.
- The bank invoice flow now sends one debug ID through `yo_checkout_create_order` and `yo_checkout_create_bank_invoice`.
- Server-side order save and bank invoice creation write `checkout-debug` trace lines to the existing admin Hiding log and to PHP `error_log`.
- Fatal PHP shutdown diagnostics are recorded for these AJAX actions when PHP stops before returning JSON.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Documentation updates:

- Updated project context, plugin map, known issues, and changelog for v4.0.31 diagnostics.

Repository rollback point:

- Bank invoice diagnostics commit: `2b576eb` (`Add bank invoice diagnostics`).

## 2026-06-01 - Bust Checkout JS Cache And Tighten Auto-Hide Matching

User request:

- Step 3 still showed the old `Connection error` with no expanded diagnostics, and a three-product card payment appeared to hide six products in YOOtheme Builder.

Files changed:

- Updated `yoleotard-checkout-invoice.php`.
- Updated `assets/yo-checkout.js`.
- Updated `includes/class-yo-checkout-sold-items.php`.
- Updated `CHANGELOG.txt`.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `KNOWN_ISSUES.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- Plugin version increased to `4.0.32`.
- `yo-checkout.js` is now enqueued with `filemtime()` instead of static version `4.0.19`, so live pages should load the updated diagnostic/payment JS after upload.
- Frontend immediate card-payment hiding now uses exact product identity matching only, not broad text matching.
- Backend YOOtheme auto-hide matching now uses stricter model/height identity checks and logs each disabled matched Builder item.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Live page check before this fix showed `yo-checkout.js?ver=4.0.19`, confirming the site could keep serving stale checkout JavaScript.

Documentation updates:

- Updated project context, plugin map, known issues, and changelog for v4.0.32.

Repository rollback point:

- Checkout JS cache and auto-hide matching fix commit: `1693d79` (`Bust checkout asset cache and tighten auto-hide`).

## 2026-06-01 - Local Test Fix For Invoice Retry, Monobank Title, And Auto-Hide Scope

User request:

- Do not commit or push to GitHub until live testing confirms the fix works.
- Analyze Step 3 bank invoice `preparing` JSON, stale KeyCRM order marker, Monobank `destination is too long`, long success product titles, and card auto-hide disabling too many products.
- Create a test archive after the local fix.

Files changed locally:

- `assets/yo-checkout.js`
- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-keycrm.php`
- `includes/class-yo-checkout-monobank.php`
- `includes/class-yo-checkout-sold-items.php`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Bank invoice frontend retries `preparing` responses for longer and no longer shows raw success JSON as an error when preparation is still in progress.
- Bank invoice order save and KeyCRM creation ignore stale browser/cart KeyCRM markers, clear old local `order_id`/`buyer_id`, and create the invoice order from the current cart.
- Monobank card payment destination and basket item name now use a short `custom leotard xN` label for multi-item carts.
- Success confirmation product text now uses `custom leotard xN` for multi-item card purchases.
- YOOtheme auto-hide matching ignores long content/text blobs for disable decisions and limits disabling to one Builder item per purchased product identity.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `php -l includes\class-yo-checkout-monobank.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Documentation updates:

- This development log entry records the uncommitted local test fix.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-06 - Phase 3 Per-Order Guest Access Tokens v4.0.57

User request:

- Execute Phase 3 from the audit remediation map after confirming v4.0.56 works.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-order-access.php`
- `includes/class-yo-checkout-promo.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `PROJECT_GOVERNANCE.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.57`.
- Added a dedicated order-access service that issues a cryptographically random token for local checkout orders and stores only a hash in order meta.
- Frontend checkout state stores the token beside the local draft and sends it with protected order AJAX requests.
- Protected AJAX actions now include create/update order, shipping update, existing-order promo update, card-payment start, bank-invoice creation, payment-status polling, and final-order-status polling.
- Provider webhooks are intentionally unchanged and continue using provider-level authentication.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed during implementation.
- `php -l includes/class-yo-checkout-order-access.php` passed during implementation.
- `php -l includes/class-yo-checkout-promo.php` passed during implementation.
- `node --check assets/yo-checkout.js` passed during implementation.
- Full verification will be run before commit.

Live test status:

- Not live-tested yet. v4.0.57 should be tested with Monobank card, Western Bid Stripe/PayPal card, and bank invoice checkout before marking it stable.

Repository rollback point:

- Pending.

## 2026-06-11 - Paginated Purchases Report v4.0.59

User request:

- Add paginated purchases output with a 10, 20, and 50 records-per-page switch.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-purchase-report.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `PROJECT_GOVERNANCE.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.59`.
- Purchases Report now uses `WP_Query` pagination instead of loading a fixed 100 records.
- Admin can switch report page size between 10, 20, and 50 rows.
- Previous/next pagination keeps the selected status and per-page filters.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l` passed for every PHP file under `includes/`.
- `node --check assets/yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Live test status:

- Not live-tested yet. Check the report controls in WordPress admin after installing the test build.

Repository rollback point:

- Pending.

## 2026-06-11 - Purchases Report And Checkout Snapshots v4.0.58

User request:

- Add a purchases report table.
- Save all customer/cart data filled in the checkout when moving toward Step 3 and choosing a payment method.
- Use the Quick Pay plugin payment logs as the basis for the admin report style.
- Move the plugin out of WordPress Settings into the general admin menu like Yo Quick Pay.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-purchase-report.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `PROJECT_GOVERNANCE.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.58`.
- The plugin now registers a top-level `YOleotard Checkout` admin menu instead of living only under Settings.
- A `Purchases Report` submenu displays recent local checkout orders with customer, contacts, products, totals, payment status, provider IDs, invoice link, and expandable saved snapshot data.
- A new purchase report service saves structured snapshots when order details are saved and when the customer starts card payment or bank invoice creation.
- Frontend payment requests now send `payment_method_choice` and `terms_confirmed` before Step 3 starts.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed during implementation.
- `php -l includes/class-yo-checkout-purchase-report.php` passed during implementation.
- `php -l` passed for every PHP file under `includes/` during implementation.
- `node --check assets/yo-checkout.js` passed during implementation.
- Full verification will be run before commit.

Live test status:

- Not live-tested yet. v4.0.58 should be tested in the admin menu and with one card checkout plus one bank invoice checkout.

Repository rollback point:

- Pending.

## 2026-06-06 - Confirm Monobank Webhook Signature Verification v4.0.56

User request:

- User confirmed the Monobank payment flow works after the v4.0.56 webhook-signature change.
- Mark the current version as working before starting Phase 3.

Files changed:

- `PROJECT_GOVERNANCE.md`
- `KNOWN_WORKING_FEATURES.md`
- `KNOWN_ISSUES.md`
- `AUDIT_REMEDIATION_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Documentation only. Runtime code was not changed.
- `v4.0.56` is now recorded as the stable runtime version for Monobank webhook signature verification.

Verification performed:

- Documentation-only update.

Repository rollback point:

- Pending commit/push in this task.

## 2026-06-06 - Monobank Live/Test Token Switch v4.0.55

User request:

- Add one more field in the Monobank admin section for a test token.
- Add the ability to choose which Monobank token is active.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-monobank.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.55`.
- Monobank admin settings now include `Active Monobank token`, `Monobank live X-Token`, and `Monobank test X-Token`.
- Monobank invoice creation uses the selected token mode from admin settings.
- The selected mode is saved to local order meta `mono_token_mode`.
- Monobank status polling for an existing invoice uses the saved per-order mode, so switching the admin selector later does not break already-open payment checks.
- Empty selected tokens return a clear error: `Monobank live token is empty` or `Monobank test token is empty`.
- Monobank webhook/finalizer, KeyCRM, paid email, bank invoice, Western Bid, product identity, reservation, shipping, and auto-hide behavior were not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l` passed for every PHP file under `includes/`.
- `node --check assets/yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Repository rollback point:

- Commit `Add Monobank live test token switch` created locally and pushed to GitHub in this task. Final hash is reported in the task response.

## 2026-06-06 - Confirm Monobank Live/Test Token Switch v4.0.55

User confirmation:

- User confirmed the Monobank live/test token switch works.

Files changed:

- `KNOWN_WORKING_FEATURES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- No runtime code changed.
- `KNOWN_WORKING_FEATURES.md` now records the Monobank live/test token switch as confirmed working after v4.0.55.

Verification performed:

- Documentation-only update.

Repository rollback point:

- Pending commit/push in this task.

## 2026-06-06 - Phase 2 Monobank Webhook Signature Verification v4.0.56

User request:

- Proceed with the next audit-remediation step after confirming v4.0.55 works.

References checked:

- `AUDIT_REMEDIATION_MAP.md` Phase 2.
- Monobank acquiring docs for webhook `X-Sign` verification and `/api/merchant/pubkey`.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-monobank.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `AUDIT_REMEDIATION_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.56`.
- Monobank webhook handling now verifies the raw request body against the `X-Sign` header before accepting paid webhook status.
- The Monobank merchant public key is fetched from `/api/merchant/pubkey`, cached per live/test token mode, and refreshed once if signature verification fails.
- Invalid, missing, or unverifiable webhook signatures return an error response and do not mark the local order paid.
- Signed paid webhooks compare provider `ccy` and `amount` / `finalAmount` with the stored Monobank card total when those fields are present.
- Existing authenticated Monobank status polling remains the recovery path if webhook delivery is delayed or rejected.
- Monobank payment window behavior, return URL, shared payment finalizer, Step 4 readiness, KeyCRM, paid email, bank invoice, Western Bid, product identity, reservation, shipping, and auto-hide behavior were not intentionally changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l` passed for every PHP file under `includes/`.
- `node --check assets/yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Confirmed no Monobank token values or new secrets were added to repository files.
- Optional inline OpenSSL sample sanity-check could not start because the Windows sandbox returned `setup refresh failed`; PHP syntax checks still passed in the same environment.

Live test status:

- Not live-tested yet. v4.0.56 should be tested with one Monobank card payment and the admin Hiding log should show `monobank webhook verified`.

Repository rollback point:

- Pending.
## 2026-06-01 - Finalize Working Bank Invoice Version 4.0.33

User request:

- Style the bank invoice confirmation next-step block using UIkit/YOOtheme-friendly classes and icons.
- Mark the tested invoice flow as a working GitHub rollback point.
- Create a final test archive.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.css`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.33`.
- Bank invoice confirmation uses a UIkit `uk-card` with `uk-grid`, `uk-flex`, `uk-list`, `uk-box-shadow-small`, and `uk-icon` icons for the order/status/timing/next-steps block.
- `KNOWN_WORKING_FEATURES.md` now records the live-tested two-item bank invoice flow, KeyCRM order creation for bank invoice, bank invoice email sending, and bank invoice confirmation order number.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `php -l includes\class-yo-checkout-monobank.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Documentation updates:

- Version, plugin map, changelog, known issues, known working features, and this log were updated.

Repository rollback point:

- Working bank invoice v4.0.33 rollback commit: `ad971b8` (`Finalize working bank invoice flow v4.0.33`).

## 2026-06-01 - Harden Card Payment Finalization v4.0.34

User request:

- Implement the approved card-flow correction map without touching the working bank invoice flow.
- Prevent Step 4 from appearing before KeyCRM order creation and paid email completion.
- Prevent stale card polling from a previous cart/session from affecting a later checkout.

Files changed:

- `assets/yo-checkout.js`
- `yoleotard-checkout-invoice.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.34`.
- Card payment polling now uses an active payment session token.
- `stopPaymentPolling()` fully resets interval, active invoice/local IDs, session token, and final-order polling state.
- All card polling paths now route paid payments through `yo_checkout_final_order_status`; the interval path no longer calls `showSuccess()` directly.
- A card-only `Payment received / Preparing your order confirmation...` state is shown while KeyCRM/email side effects complete.
- Step 4 now requires backend `ready=true`, a numeric KeyCRM order ID, `keycrmDone=true`, and `emailSent=true`.
- Card payment start clears stale KeyCRM order/buyer markers from the local draft before creating a card provider payment.
- Step 1 order save no longer sends stored KeyCRM order markers from browser storage/cookies.
- Final order status and payment status check for invoice/local mismatches to prevent stale paid local orders from driving a new payment UI.
- Paid email failures are stored in `paid_email_error` and prevent `emailSent=true` until the email send succeeds.
- Bank invoice flow was not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Documentation updates:

- Changelog, plugin map, known issues, project context, and this log were updated.

Repository rollback point:

- Not created yet. This card-flow fix should be live-tested before marking it as working.

## 2026-06-01 - Confirm Card Payment v4.0.34 As Working

User request:

- Mark the tested card payment version as working.
- Push the working version to GitHub.

Files changed:

- `KNOWN_WORKING_FEATURES.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- No runtime behavior changed in this entry.
- Documentation now records Monobank card payment, card KeyCRM order creation, card paid email sending, and Step 4 card payment success as live-tested working features.

Verification performed:

- User live-tested card payment and confirmed payment succeeds, KeyCRM order is created, and email notification arrives.

Documentation updates:

- `KNOWN_WORKING_FEATURES.md`, `KNOWN_ISSUES.md`, and this log were updated.

Repository rollback point:

- Working card payment v4.0.34 rollback commit: `81789d7` (`Confirm working card payment flow v4.0.34`).

## 2026-06-01 - Improve Bank Invoice Confirmation Screen

User request:

- After a successful bank invoice checkout, make the confirmation window more complete.
- Show the KeyCRM order number, waiting-for-payment status, expected bank-transfer timing, and what happens after payment is received.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.js`
- `assets/yo-checkout.css`
- `CHANGELOG.txt`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- The bank invoice confirmation screen now shows `Order #KEYCRM_ID`.
- The same screen now explains that the status is waiting for payment, bank transfers usually arrive within 1-3 business days, and the customer will receive confirmation/processing/shipment updates after payment.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `node --check assets\yo-checkout.js` passed.

Documentation updates:

- `CHANGELOG.txt`, `PLUGIN_MAP.md`, and this log were updated.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Add Known Working Features File

User request:

- Create `KNOWN_WORKING_FEATURES.md`.
- Record confirmed working features:
  - Monobank payment
  - KeyCRM order creation
  - Customer email sending
  - Step 4 Success
  - Shipping Calculator
- Do not touch these areas without necessity.

Files changed:

- Added `KNOWN_WORKING_FEATURES.md`.
- Updated `PROJECT_CONTEXT.md`.
- Updated `PLUGIN_MAP.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- No runtime plugin behavior changed. Documentation/process only.

Verification performed:

- Documentation-only change; no PHP/JS syntax check required.

Documentation updates:

- Added confirmed working feature guard and included it in the required reading set.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Correct Known Working Features Conservatively

User request:

- Re-check the work history and do not blindly use the example list as confirmed working.
- Keep only tools/features that are working without known open bugs.

Files changed:

- Updated `KNOWN_WORKING_FEATURES.md`.
- Updated `DEVELOPMENT_LOG.md`.

Behavior changed:

- No runtime plugin behavior changed. Documentation/process only.

Verification performed:

- Reviewed `DEVELOPMENT_LOG.md` and `KNOWN_ISSUES.md`.
- Documentation-only change; no PHP/JS syntax check required.

Documentation updates:

- `KNOWN_WORKING_FEATURES.md` now lists only bug-free confirmed items and moves payment/KeyCRM/email/Step 4/invoice/auto-hide/promo areas into a not-yet-stable section until live retesting clears them.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Add Product ID Based KeyCRM And Auto-Hide Logic

User request:

- Improve KeyCRM order creation and sold-item hiding logic.
- Use the unique product card `id` / `data-feed-id` where available to avoid text-matching bugs.

Files changed:

- `assets/yo-checkout.js`
- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-keycrm.php`
- `includes/class-yo-checkout-sold-items.php`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Frontend product extraction now stores the YOOtheme card `id` / `data-feed-id` as `product_id` and carries it through cart items.
- Server sanitizes and stores `product_id` in `cart_items_json`.
- Cart availability checks and payable checks pass `product_id` to the sold-items service.
- Auto-hide prefers exact product ID matching before title matching and logs product IDs beside titles.
- KeyCRM product payloads use `product_id` as SKU when available, so future product-row sync can match by SKU.
- Card payment KeyCRM finalization now attempts to recover/update an existing `order_id` before creating a new KeyCRM order.
- Bank invoice creation now returns an existing invoice for the same cart hash before clearing stale KeyCRM meta.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-keycrm.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Documentation updates:

- `PLUGIN_MAP.md` and `KNOWN_ISSUES.md` updated with the `product_id`-based matching behavior.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Extract Customer Email Service v4.0.35

User request:

- First move customer email sending into a separate file.
- Second, apply the previously recommended improvement so bank invoice email sent markers are written only after a successful customer email send.
- Create a plugin archive for site testing.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-email.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.35`.
- Customer bank-invoice and paid-card email rendering/sending now live in `YO_Checkout_Email_Service`.
- The main plugin keeps `send_bank_invoice_email()` and `send_paid_email()` wrappers, so existing checkout/payment call sites continue to use the same method names.
- Bank invoice email sent markers are now updated only when the customer email send succeeds.
- Failed bank invoice customer email sends now write `bank_invoice_email_error` and remain retryable because the cart-hash sent marker is not advanced.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-email.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed after cleanup.
- Host-safe archive verification passed for `yoleotard-checkout-invoice.zip`: forward-slash paths only and real `assets/` / `includes/` directories after extraction.

Documentation updates:

- Project context, plugin map, changelog, known issues, known working features, and this log were updated for v4.0.35.

Repository rollback point:

- Not created yet. This email extraction should be live-tested before marking it as a working rollback point.

## 2026-06-01 - Move Plugin Archives Out Of Git

User request:

- Store plugin archives in a separate folder.
- Add the corresponding git rule so the archive folder is not added to the repository.

Files changed:

- `.gitignore`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- No plugin runtime behavior changed.
- Local ZIP archives are now stored under `plugin-archives/`.
- `.gitignore` excludes `plugin-archives/` so generated test archives do not appear as repository files.
- Future test ZIP path is `plugin-archives/yoleotard-checkout-invoice.zip`; the upload filename and internal folder rules stay the same.

Verification performed:

- Existing root ZIP files were moved into `plugin-archives/`.
- `git status -sb` no longer lists ZIP archives from the root after the move.

Documentation updates:

- Project context and plugin map now document the archive folder and git-ignore rule.

Repository rollback point:

- Not created yet. This is a process/documentation change and should be included with the current uncommitted v4.0.35 test work if/when it is confirmed.

## 2026-06-01 - Confirm Email Service Extraction v4.0.35

User request:

- User live-tested both payment variants after the email extraction.
- Mark the email extraction as successful and commit this stable email version.
- Analyze the sold-item hiding mismatch report without changing hiding code yet.

Files changed:

- `KNOWN_WORKING_FEATURES.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- No runtime plugin behavior changed in this entry.
- Documentation now records customer email sending for bank invoice and card checkout as live-tested working after the v4.0.35 email-service extraction.
- Documentation also records Step 4 card success after the v4.0.35 email-service extraction as live-tested working.

Verification performed:

- User live-tested bank invoice and card payment flows; both worked and customer email notifications arrived.
- Code review identified a likely sold-item hiding mismatch cause: backend title fallback can treat a shared height range as the one matching "important" token, allowing a same-height but different model to be disabled when product ID matching is unavailable or not found in YOOtheme Builder JSON.

Documentation updates:

- `KNOWN_WORKING_FEATURES.md`, `KNOWN_ISSUES.md`, and this log were updated.

Repository rollback point:

- Pending in this task: commit and push stable v4.0.35 email extraction.

## 2026-06-01 - Checkout-Owned Product Identity v4.0.36

User request:

- Design and implement a correct product-card ID system as an architect/developer.
- Add it as a separate file and connect it to the checkout plugin.
- Create a test archive after completion.

Files changed:

- `yoleotard-checkout-invoice.php`
- `includes/class-yo-checkout-product-identity.php`
- `includes/class-yo-checkout-sold-items.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.36`.
- Checkout now owns product identity through `YO_Checkout_Product_Identity_Service`.
- Frontend checkout now generates and stores a deterministic `data-product-id` from the product card title when a YOOtheme card has no `id`, `data-feed-id`, or `data-product-id`.
- Backend order/cart sanitization now generates a fallback `product_id` from the title if the browser did not provide one.
- Reservation and cart availability checks now pass product IDs into YOOtheme availability checks where available.
- Sold-item title fallback no longer allows one matched word plus the same height range to disable a different model.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-product-identity.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.36.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, and real `assets/` / `includes/` directories after extraction.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Step 3 Product Identity Fallback v4.0.37

User request:

- Fix a Step 3 error that appeared for both card and bank invoice payments after v4.0.36.
- The browser alert reported a valid cart item as unavailable with a short `product_id` such as `new_leotard_velvet_flowers`.
- Create a new archive for testing.

Root cause:

- Product Card Enhancer / Google Feed matching can assign a short frontend `data-feed-id` without the height suffix.
- Existing YOOtheme Builder storage does not persist those frontend-only IDs on every product card.
- v4.0.36 made Step 3 availability checks pass that product ID into the YOOtheme availability service, which could block valid products when the ID was not found in Builder data.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.37`.
- Frontend checkout now prefers its generated product ID with height when another DOM/feed ID is shorter and does not include the height suffix.
- Step 3 payable checks now try product ID first but fall back to the existing title-based availability check when Builder data has no saved product ID.
- Product IDs remain stored in cart/order data for KeyCRM/email/auto-hide; this change only prevents the payment gate from rejecting valid products because Builder storage lacks the frontend-only ID.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-product-identity.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.37.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, and real `assets/` / `includes/` directories after extraction.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Mirror Feed ID Into Card ID v4.0.38

User request:

- Adjust the product identity implementation so product card `id` is set exactly from the existing feed ID.
- If a card has no `id`, take the value from `data-feed-id` and add it as the card `id`.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.38`.
- Frontend checkout now mirrors `data-feed-id` into the card `id` when the card has no `id`.
- Checkout uses the same value for DOM `id`, feed ID, and `data-product-id` when a feed ID exists.
- Cards without any DOM/feed ID still receive a generated fallback `data-product-id` from the title.
- The Step 3 payable fallback from v4.0.37 remains in place so existing Builder data without saved product IDs does not block valid carts.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-product-identity.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Product-ID-Aware Step 3 Reservations v4.0.39

User request:

- Fix the bank invoice Step 3 failure where `create_order` succeeded but `bank_invoice payable check failed`.
- Add enough diagnostics to understand whether the failure is availability or reservation related.
- Create an archive for testing.

Root cause:

- The Step 3 payable gate mixed availability and reservation checks in a single condition, so logs only showed title/product ID.
- Reservations were keyed only by normalized title. Title encoding differences such as `u201c` text versus real curly quotes could make the payable gate re-check a different reservation key than the one created when adding to cart.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.js`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.39`.
- Reservations now use `product_id` as the primary reservation key when it is available, while still checking the legacy title key for compatibility with existing reservations.
- Step 3 payable checks now return detailed diagnostics: reason, page ID, availability by ID, availability by title fallback, reservation allowed/ensured, current buyer ID, and reservation owner.
- Cart/invoice cleanup now sends `product_id` when releasing reservations.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-product-identity.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.39.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, and real `assets/` / `includes/` directories after extraction.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Product-ID Primary Reservation Gate v4.0.40

User request:

- Fix the Step 3 card/bank invoice failure after product ID work.
- Make checkout remember and validate cart products by product ID instead of product title so quotes/encoding do not confuse the payment flow.
- Create an archive for testing.

Root cause:

- Cart items already stored `product_id`, and reservations were product-ID-aware, but some later checks still fell back to title-first behavior.
- `ajax_create_order()` still confirmed reservations by title only in one pre-payment check.
- Step 3 payable checks retried availability by YOOtheme title matching even when the same buyer already owned an active reservation for the exact `product_id`.
- Removing an item from the cart released reservation state by title only, so an ID-based reservation/countdown could remain active.

Files changed:

- `yoleotard-checkout-invoice.php`
- `assets/yo-checkout.js`
- `includes/class-yo-checkout-sold-items.php`
- `includes/class-yo-checkout-product-identity.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.40`.
- Cart validation, order save, card Step 3, and bank invoice Step 3 now treat the customer's own active `product_id` reservation as a valid payable product identity.
- Reservation cleanup on cart remove/unavailable cleanup now sends `product_id`, not only title.
- Reservation badges on product cards prefer `product_id` matching.
- Unicode quote/dash normalization was strengthened as a compatibility fallback, but the main flow now relies on product ID.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `php -l includes\class-yo-checkout-product-identity.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.40.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, and real `assets/` / `includes/` directories after extraction.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Multi-Item Auto-Hide Per Product v4.0.41

User request:

- Card payment for four products created the KeyCRM order correctly, but backend auto-hide disabled only one purchased model.
- Fix the sold-item hiding logic and create an archive for testing.
- Clarification: YOOtheme/Builder storage may not contain checkout product IDs, so the backend search for the Builder item must work by product title.

Root cause:

- Auto-hide passed the full purchased item list into one recursive YOOtheme matcher.
- That shared pass could let one matched Builder item consume the match/logging path while the remaining purchased product identities in the same order were not disabled.
- The auto-hide log also showed loose unicode fragments like `u201d` because title cleanup decoded only some broken unicode forms.

Files changed:

- `includes/class-yo-checkout-sold-items.php`
- `yoleotard-checkout-invoice.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.41`.
- Backend YOOtheme auto-hide now processes post content and meta one purchased product identity at a time.
- The Builder/database match still works by product title when Builder storage does not have product IDs.
- `product_id` remains useful for cart/reservation/order identity and deduplication, but it is not required for finding the Builder item to hide.
- Multi-item card orders should now disable every purchased product card that can be matched by title/product identity, instead of only one item.
- Sold-item auto-hide log cleanup now decodes common loose unicode quote/dash fragments such as `u201d`.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `php -l includes\class-yo-checkout-product-identity.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.41.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, and real `assets/` / `includes/` directories after extraction.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-01 - Feed-Style Fallback Product IDs v4.0.42

User request:

- After confirming that checkout and auto-hide work, adjust fallback IDs for product cards without `feed_id`.
- New checkout-generated IDs should follow the same style as feed IDs and should not include the trailing height part.
- Create an archive for testing.

Root cause:

- `includes/class-yo-checkout-product-identity.php` and the matching frontend JS fallback generated IDs from the full card title.
- For titles like `New author's leotard "Magic Flower" for height 145-150`, the fallback became `new_author_s_leotard_magic_flower_for_height_145_150`.
- Existing feed IDs use the model/title slug without the height suffix, for example `new_author_s_leotard_magic_flower`.

Files changed:

- `includes/class-yo-checkout-product-identity.php`
- `assets/yo-checkout.js`
- `yoleotard-checkout-invoice.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.42`.
- Fallback product IDs now remove trailing `for height NNN-NNN` / `height NNN-NNN` before slug generation.
- Existing `data-feed-id`, DOM `id`, and `data-product-id` values are still preserved and not regenerated.
- Confirmed working multi-item card auto-hide was moved into `KNOWN_WORKING_FEATURES.md` based on user testing.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-product-identity.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.42.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, and real `assets/` / `includes/` directories after extraction.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.

## 2026-06-02 - Frontend Currency Symbol Encoding v4.0.43

User request:

- Checkout works, but visible UI symbols are broken in the buy button, cart totals, order summary, delivery, service fee, and total payment rows.
- Create an archive for checking the fix.

Root cause:

- Some frontend strings in `assets/yo-checkout.js` contained mojibake forms of symbols such as euro, bullet, middle dot, check mark, and dash.
- These strings are injected with `innerHTML` / `textContent`, so the broken bytes were shown directly to the customer.

Files changed:

- `assets/yo-checkout.js`
- `yoleotard-checkout-invoice.php`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`

Behavior changed:

- Plugin header version is now `4.0.43`.
- Checkout UI now uses safe HTML entities such as `&euro;`, `&bull;`, `&middot;`, `&mdash;`, `&check;` and `\u20ac` for textContent output.
- Payment, KeyCRM, email, reservation, and sold-item auto-hide logic were not changed.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- `php -l includes\class-yo-checkout-product-identity.php` passed.
- `php -l includes\class-yo-checkout-sold-items.php` passed.
- `node --check assets\yo-checkout.js` passed.
- `git diff --check` passed with only Git line-ending warnings.
- Host-safe archive verification passed for both `plugin-archives/yoleotard-checkout-invoice.zip` and `plugin-archives/yoleotard-checkout-invoice-v4.0.43.zip`: forward-slash paths only, one top-level `yoleotard-checkout-invoice/` folder, and real `assets/` / `includes/` directories after extraction.

Repository rollback point:

- Not created yet by user request. Commit/push only after the user confirms the test archive works.
