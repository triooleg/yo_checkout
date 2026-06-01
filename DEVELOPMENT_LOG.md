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
