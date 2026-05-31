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

- Pending commit and push.
