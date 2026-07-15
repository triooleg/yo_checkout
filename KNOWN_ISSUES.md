# YOleotard Checkout Known Issues

Codex must read this file before making any code change.

This file tracks known risks, limitations, and unresolved technical debt.

## Active Issues

### Western Bid Step 2 disclaimer live validation

Status: v4.0.75 test candidate requires visual validation.

Details:

- Step 2 checkout receipt now shows the Western Bid Merchant of Record disclaimer inside the existing information block.
- The disclaimer explains that Western Bid, Inc. is the Merchant of Record and that `WESTERN BID` will appear as payee on PayPal and card statements.
- Payment routing, totals, KeyCRM, email, and invoice behavior were not changed.

Handling:

- Install the v4.0.75 test archive.
- Add a product to cart, fill Step 1, continue to Step 2, and confirm the disclaimer is visible and compact inside the information block.
- Confirm card and SEPA/SWIFT invoice buttons still require Terms confirmation and continue to the existing flows.

### KeyCRM order ID in Monobank live validation

Status: v4.0.74 test candidate requires live validation.

Details:

- Monobank invoice destination must show the KeyCRM order number instead of local `WEB-*` ID.
- v4.0.74 creates/reuses the KeyCRM card order before Monobank invoice creation and blocks provider start if KeyCRM creation fails.
- The paid finalizer must update the same KeyCRM order without creating a duplicate.

Handling:

- Create a Monobank card checkout and confirm KeyCRM order exists before the Monobank page opens.
- Confirm Monobank purpose shows that KeyCRM number and no `WEB-*` local ID.
- Complete payment and confirm the same KeyCRM order receives payment, paid status, email completion, and Step 4.
- Retry payment start once and confirm no duplicate KeyCRM order is created.

### Tracking notifications live validation

Status: v4.0.73 test candidate requires live validation.

Details:

- v4.0.72 adds the `YOleotard Checkout > Tracking Notifications` admin page.
- The list includes successful card orders (`paid = 1`) and completed bank invoice orders (`bank_invoice_created = 1`).
- v4.0.73 delegates tracking delivery to the shared email service, uses the invoice/paid logo and card style, includes the order number in the subject/heading, sends from `no-reply@yoleotard.com`, and adds Facebook/Instagram/TikTok footer links.

Handling:

- Confirm paid card and invoice customers are listed, while drafts are excluded.
- Send a valid HTTP(S) tracking URL to a controlled test email.
- Confirm the English HTML email arrives, both links work, and the row changes from Pending to Sent.
- Confirm a mail failure or invalid URL does not mark the order Sent.

### Bank-invoice YOOtheme Panel Style reservation live validation

Status: v4.0.71 test candidate requires live validation.

Details:

- v4.0.67 attempted to mark YOOtheme Builder items purchased through a successful bank invoice as `Card Default` style after the bank invoice email was successfully sent.
- Product cards rendered with YOOtheme `Card Default` are treated as invoice-reserved on the frontend: buy buttons become grey/non-clickable and a centered reservation badge is shown without a countdown timer.
- Server-side YOOtheme availability checks also treat `Card Default` style as unavailable, so an older open cart should not be able to pay for an invoice-reserved product.
- If a manager manually changes the Builder item style back to `None`, the frontend invoice-reserved state should disappear after the page updates/reloads.
- Live v4.0.67 testing confirmed that the Builder style was applied, but after some time the storefront card visually returned to an active Buy button while the server still rejected add-to-cart as unavailable.
- v4.0.68 adds a frontend server-availability sync for visible product cards, so cards that the backend already reports as unavailable are greyed and non-clickable before the customer clicks Buy, even if the current rendered DOM no longer exposes `uk-card-default`.
- Later inspection showed that v4.0.67/v4.0.68 wrote the hidden Builder value `props.style = default` instead of the visible YOOtheme `Panel > Style` field. This could leave the admin field at `None` while the backend still treated the card as unavailable.
- v4.0.69 moved the write to `props.panel_style`, but used `default`; live testing showed YOOtheme left the select blank/red and did not add the frontend `uk-card-default` class.
- v4.0.70 writes the valid YOOtheme value `props.panel_style = card-default`, clears the old erroneous `props.style = default` value on matched invoice items, and no longer treats legacy hidden/invalid values as unavailable.

Handling:

- Install the v4.0.71 test build.
- Create a bank invoice for one product and confirm the customer invoice email is sent.
- In YOOtheme Builder, confirm the purchased item has the `Card Default` option selected in `Panel > Style`, not an empty/red select.
- On the storefront, confirm the rendered product card receives the YOOtheme `uk-card-default` class in addition to the plugin's reserved state.
- On the storefront, confirm the card is grey, shows the centered reservation label, and its buy button cannot be clicked immediately and after waiting/refreshing.
- Change the same Builder item style back to `None` and confirm the card becomes purchasable again.

### Desktop Ready-to-Ship height filter spacing live validation

Status: v4.0.66 test candidate requires live validation.

Details:

- v4.0.66 adds a plugin CSS override for the YOOtheme `body.home .yo-height-filter` block on desktop widths.
- The filter should align closer to the main site content width and use smaller outer margin, inner padding, gaps, and button padding.
- The plugin now versions `assets/yo-checkout.css` with `filemtime()` so the uploaded test build should not keep stale filter spacing from browser/cache layers.
- Mobile filter rules are intentionally unchanged.

Handling:

- Install the v4.0.66 test archive and open the home page on desktop.
- Confirm the Ready-to-Ship filter is wider, more compact, and visually aligned with the product grid/main site width.
- Confirm the sticky filter state still remains usable on desktop.
- Confirm mobile filter layout is unchanged.

### Promo badge GIF tooltip live validation

Status: v4.0.65 test candidate requires live validation.

Details:

- v4.0.60 adds a Media Library URL setting in the Promo tab for a GIF explaining how to apply the promo code.
- v4.0.61 applied the UIkit tooltip directly to the whole green promo badge instead of adding a separate help icon.
- Live inspection showed UIkit tooltip rendered the GIF `<img>` markup as visible text in the current theme.
- v4.0.62 used a UIkit notification popup as a workaround, but the desired UI is specifically UIkit Tooltip.
- v4.0.63 initializes the tooltip through the UIkit JavaScript API and passes a JS-built HTML wrapper containing the GIF.
- v4.0.64 initializes the UIkit tooltip only after a new promo badge is inserted into the DOM and retries briefly if UIkit is not ready yet.
- v4.0.65 creates and shows the UIkit tooltip directly from badge `mouseenter`, `focus`, `touchstart`, and `click` events, so initialization happens in response to the user action.
- When the setting is filled and the promo badge is active, hovering, focusing, or tapping the full product-card promo badge should open a UIkit tooltip with the configured GIF.

Handling:

- In WordPress admin, open `YOleotard Checkout > Settings > Promo code`, choose a GIF from the Media Library, save settings, and verify the URL remains saved.
- On a product without an existing sale discount, hover and tap the green promo badge and confirm the GIF appears as an animated image inside a UIkit tooltip, not as raw `<img>` text and not as a notification popup.
- Confirm regular promo badge placement, Buy button behavior, cart opening, and promo-code application are unchanged.

### Purchases report and checkout snapshot live validation

Status: v4.0.59 test candidate requires live validation.

Details:

- v4.0.58 moves YOleotard Checkout out of WordPress Settings into a top-level admin menu.
- It adds a Purchases Report table based on local `yo_invoice_order` records.
- It saves a checkout snapshot when order details are saved and again when the customer selects card or bank invoice payment before Step 3.
- v4.0.59 adds 10/20/50 records-per-page controls and previous/next pagination to the Purchases Report.

Handling:

- Live-test opening the new `YOleotard Checkout` admin menu and `Purchases Report` submenu.
- Switch the report between 10, 20, and 50 records per page and verify previous/next pagination keeps the selected status and page-size filters.
- Create one card checkout and one bank invoice checkout, then confirm the report shows customer, contact, products, totals, payment method, provider IDs, and saved snapshot data.
- Confirm the old settings tabs, shipping import, and Dompdf install redirects still return to the settings page under the new top-level menu.

### Per-order guest access token live validation

Status: v4.0.57 test candidate requires live validation.

Details:

- v4.0.57 adds per-order guest access tokens for public AJAX actions that read or mutate a local checkout order.
- Only the token hash is stored in WordPress order meta.
- Provider webhooks remain outside the guest-token check and continue using provider authentication.

Handling:

- Live-test Monobank card payment, Western Bid Stripe/PayPal card payment, and bank invoice flow.
- Confirm Step 2 shipping changes, Step 3 polling, Step 4 finalization, KeyCRM creation, email sending, and sold-item auto-hide still work.
- If a customer sees `Order access token is invalid or expired`, check whether browser storage/cookies were cleared between Step 2 and Step 3.

### Monobank webhook signature verification live validation

Status: fixed and live-tested after v4.0.56.

Details:

- v4.0.56 implements Monobank webhook `X-Sign` verification using the raw request body and the cached Monobank merchant public key.
- Invalid or missing webhook signatures no longer mark local orders paid.
- Authenticated Monobank invoice status polling remains as the recovery path if webhook delivery or verification is delayed.

Handling:

- Keep monitoring the admin Hiding log for `monobank webhook verified` during future Monobank tests.
- If webhook verification fails in a later regression, check `mono_webhook_signature_error` on the local order and the checkout-debug log before changing payment logic.

### Product catalog fallback during Phase 1

Status: v4.0.54 test candidate requires live validation.

Details:

- Phase 1 adds a server-side product catalog service that resolves product data by `product_id` from the configured YOOtheme product source page.
- During the transition, if a product cannot be resolved, checkout keeps the existing sanitized browser payload instead of blocking payment.
- This preserves confirmed checkout behavior while the source parser is validated against real YOOtheme storage.
- v4.0.52 caused a live Step 2 memory exhaustion because it expanded full YOOtheme meta values while searching for product data.
- v4.0.53 fixed the memory exhaustion and live tests confirmed Monobank and bank invoice still complete, but catalog diagnostics still showed `partial_fallback`.

Handling:

- v4.0.53 limits catalog reads to small product-specific fragments and avoids full meta unserialization/JSON encoding during checkout creation.
- v4.0.54 also allows trusted title-derived matching when the stored Builder data does not contain the literal frontend `product_id`.
- Watch checkout-debug for `product catalog fallback used`.
- Check order meta `product_catalog_status` and `product_catalog_summary` after test orders.
- After live validation confirms all active product cards resolve as `trusted`, a later phase can make unknown/ambiguous products fail closed.

### Card payment popup can remain on provider page

Status: v4.0.54 test candidate requires live validation.

Details:

- In the v4.0.53 Monobank test, the main checkout reached Step 4 successfully but the provider popup stayed on `pay.monobank.ua` until `Return to site` was clicked.
- This happens because checkout JavaScript cannot run on the external provider domain.

Handling:

- v4.0.54 keeps a reference to the opened card-payment window and attempts to close it from the main checkout when Step 4 is shown.
- If the browser refuses to close a cross-origin provider window in some cases, payment completion remains correct; the remaining issue is UX only.

### Western Bid card payment needs live provider testing

Status: fixed and live-tested after v4.0.50.

Details:

- v4.0.44 adds Western Bid as the visible replacement card provider for the old WayForPay branch.
- The integration verifies notify hash, `wb_result`, `payment_status`, paid amount, and currency before marking a local order paid.
- Western Bid documentation describes notify POST but not a status-check API, so checkout polling waits on local webhook/meta state.
- Live testing showed PayPal login fails inside Step 3 iframe even with a correct password, and Stripe explicitly reports that Stripe Checkout cannot run in an iframe.
- v4.0.45 opens Western Bid PayPal/Stripe in a separate payment window and keeps the original checkout polling for webhook/final status.
- PayPal login issue was traced to the specific Western Bid PayPal test account credentials required by the sandbox instructions; with the issued login/password, PayPal payment passed.
- Western Bid merchant was switched to real working Stripe/PayPal mode.
- Live Stripe/PayPal payment windows can close after payment, but the main checkout remains on Step 3. The captured log showed that Western Bid notify reaches the site, but it prefixes the original checkout invoice with the merchant login: form invoice `YO-WB-22037-1780403106`, notify invoice `{wb_login}-YO-WB-22037-1780403106`.
- v4.0.49 keeps hash verification against the full received Western Bid invoice, but maps the local order by both full and normalized invoice candidates.
- The full log showed Stripe notify reaches the plugin with matching `mc_gross`, `payment_status=Completed`, and `wb_result=VERIFIED`, but `mc_currency` can be empty. v4.0.49 treats empty `mc_currency` as the configured Western Bid currency when the other verification checks pass.
- A later Stripe log showed `mc_gross="1"` and `western_bid webhook verification failed: Western Bid notify hash is invalid`. The previous verifier normalized the amount to `1.00` before building the hash, but Western Bid signed the raw string `1`.
- v4.0.50 verifies the Western Bid hash against both the raw provider amount and normalized two-decimal amount, then keeps the actual paid amount comparison strict.
- v4.0.50 also lets payment polling and final Step 4 status accept both the original `YO-WB-...` invoice and the merchant-prefixed notify invoice.
- Stripe also asked for the full delivery address again even though checkout Step 1 already had it. v4.0.48 sends ISO-2 country code and extra address aliases, and avoids sending delivery as a second provider shipping charge.

Handling:

- User confirmed Western Bid payment confirmation and Step 4 work after v4.0.50.
- PayPal also created the KeyCRM order and sent the customer email.
- Keep the webhook hash, invoice mapping, finalizer, and polling behavior unchanged unless a direct Western Bid issue requires it.

### Disabled delivery can remain in email and KeyCRM totals

Status: fixed and live-tested in v4.0.51 on 2026-06-03.

Details:

- When delivery is disabled, the card payment amount does not include delivery.
- The customer email and KeyCRM total still include a delivery amount.
- This creates a mismatch between the amount actually paid and the order records.
- Root cause: the disabled branch of `shipping_data()` returned `0.00` to card payment calculation but did not persist that zero, leaving an older `shipping_cost_eur` value for KeyCRM and email.

Handling:

- v4.0.51 persists `shipping_cost_eur = 0.00`, sets the source to `disabled`, and clears stale selected-delivery metadata.
- User confirmed the disabled-shipping checkout works after the v4.0.51 test.
- Keep the shared zero-shipping persistence behavior unchanged unless a direct shipping totals issue requires it.

### Monobank external payment window return

Status: fixed and live-tested on 2026-06-02.

Details:

- v4.0.46 opened Monobank in an external payment window to avoid provider/country iframe restrictions.
- The first return bridge pointed to the missing `/confirm?order_id=` page and showed a WordPress 404 in the payment window.
- v4.0.47 changed the return URL to the site homepage with `yo_checkout_return=card`.

Handling:

- User live-tested Monobank after v4.0.47: the payment window closed and the original checkout continued to Step 4.
- Keep Monobank backend/webhook/finalizer logic unchanged unless a direct Monobank task requires it.

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

### Host upload is sensitive to ZIP names and path separators

Status: packaging rule fixed in documentation, use the corrected archive builder for every future test ZIP.

Details:

- A ZIP named with a test/version suffix caused the host to create a second plugin folder instead of updating `yoleotard-checkout-invoice`.
- A ZIP built with Windows-style path separators caused the host file manager to unpack files named like `yoleotard-checkout-invoice\assets\yo-checkout.js` instead of creating real nested folders.
- WordPress then showed `Plugin file not found` because the expected `yoleotard-checkout-invoice/yoleotard-checkout-invoice.php` path did not exist as real directories.

Handling:

- Installable ZIP must be named exactly `yoleotard-checkout-invoice.zip`.
- Before generating a newer exact-name ZIP, preserve the existing exact-name ZIP as the version it contains, for example `yoleotard-checkout-invoice-v4.0.62.zip`, so previous test packages remain available locally.
- ZIP entries must use forward slashes and start with `yoleotard-checkout-invoice/`.
- Verify no entry contains `\` before giving the archive to the user.
- Do not use PowerShell `Compress-Archive` directly for this plugin package.

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

Status: fixed and live-tested on 2026-06-01.

Details:

- The bank invoice path can reuse an existing unpaid KeyCRM order from local/session/cart markers.
- If the reused local draft contains an old/deleted KeyCRM `buyer_id`, KeyCRM rejects the order update with `buyer.id is invalid`.
- Card payment did not show this after the recent change because the successful-card path creates a fresh KeyCRM buyer/order after payment.

Handling:

- KeyCRM update/create now detects invalid `buyer.id`, clears the stale local `buyer_id`, creates a new KeyCRM buyer from the current checkout data, and retries once.
- If the retry still fails, the KeyCRM error remains visible so the next issue is not hidden.
- Live bank invoice testing with two products confirmed invoice generation, KeyCRM order creation/update, customer email sending, and no browser alert containing `buyer.id is invalid`.

### Repeated bank invoice requests could duplicate emails and KeyCRM product rows

Status: fixed and live-tested for the normal two-item bank invoice path on 2026-06-01; keep monitoring aggressive repeated-click/reload cases.

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
- If Step 3 still shows a connection error, the frontend alert should now include an AJAX action, HTTP status/response excerpt, and a `Debug ID`. The admin Hiding log should include matching `checkout-debug` lines for the same ID.
- Bank invoice requests now ignore stale browser/cart KeyCRM markers so an old KeyCRM order number cannot be reused for a new invoice cart.
- Retryable `preparing` responses are allowed to continue longer than the backend lock window; if preparation still does not finish, the user sees a friendly retry message instead of raw JSON.
- If an invoice already exists for the same local order and cart hash, it is returned before stale KeyCRM markers are cleared. This avoids creating a fresh KeyCRM order for a duplicate invoice retry.

### Monobank multi-item destination can exceed provider length limit

Status: fixed and live-tested on 2026-06-01.

Details:

- Monobank rejects card invoice creation when `merchantPaymInfo.destination` is too long.
- Multi-item carts previously used the concatenated product title string, which could exceed Monobank limits.

Handling:

- Monobank destination/comment and basket item name now use a compact label like `custom leotard x5`.
- Step 4 success product text also uses the compact label for multi-item card purchases.
- Live card payment testing confirmed successful payment, KeyCRM order creation, and paid email delivery.

### Card payment Step 4 could open before KeyCRM/email finished

Status: fixed and live-tested on 2026-06-01.

Details:

- A successful card payment with three products could remain on Step 3 while KeyCRM/email were not completed.
- After returning to the cart and starting a different cart, stale polling callbacks from the previous paid card session could open Step 4 for the wrong checkout state.
- The interval polling path could call `showSuccess()` directly on `paid:true`, bypassing the final backend check for KeyCRM order creation and paid email delivery.

Handling:

- Card payment now uses an active session token. Old timeout, interval, focus, visibility, and iframe-load callbacks are ignored after the customer leaves that payment session.
- `paid:true` now opens a `Payment received / Preparing your order confirmation...` finalizing state instead of Step 4.
- Step 4 is shown only after `yo_checkout_final_order_status` returns a real KeyCRM order ID with `keycrmDone=true` and `emailSent=true`.
- Step 1 order save no longer sends stored KeyCRM order markers from browser storage/cookies.
- Card payment start clears stale KeyCRM markers from the local draft before creating a Monobank/WayForPay payment.
- Bank invoice flow is intentionally unchanged.
- Live card payment testing confirmed payment completion, KeyCRM order creation, customer email delivery, and correct Step 4 transition.

### Checkout JS cache could keep old Step 3 behavior

Status: fixed in v4.0.32, verify live page source after upload.

Details:

- The frontend checkout script was still enqueued with static version `4.0.19`.
- Browser, WordPress, or host cache could keep serving an old `yo-checkout.js`, so newer Step 3 diagnostics did not appear and the alert still showed only `Connection error`.

Handling:

- `yo-checkout.js` is now enqueued with `filemtime()` so the URL version changes when the file changes.
- After uploading a test ZIP, inspect page source or browser devtools and confirm `yo-checkout.js?ver=` changed from the old static value.

### Sold-item auto-hide can overmatch similar Builder items

Status: tightened and live-tested for multi-item card checkout by v4.0.41; keep as watch area for future product-title/Builder markup changes.

Details:

- A three-item card payment appeared to disable six YOOtheme items in Builder.
- Some disabled items may have been from previous tests, but matching was still too permissive because broad text/alias matching could match more than the exact purchased card.
- A v4.0.50 Western Bid Stripe test purchased `New leotards test "Dynamic Pulse" for height 130-165` with product ID `new_leotards_test_dynamic_pulse`, but backend auto-hide disabled `New leotards DUO "Dynamic Pulse" for height 130-165`. This confirms that title fallback can still overmatch products that share the same quoted model name and height but differ in another important title word.

Handling:

- Frontend immediate hide after card payment now uses exact product identity matching only.
- Backend YOOtheme auto-hide now requires stricter model/height identity checks and logs each disabled matched item as `Disabled matched item: ...`.
- Backend YOOtheme auto-hide now ignores long content/text fields for disable decisions and only disables one Builder item per purchased product identity in a single order.
- Product card `id` / `data-feed-id` is now stored as `product_id`; availability checks and auto-hide prefer exact product ID matching before title matching.
- Checkout v4.0.36 now generates a stable fallback product ID from the card title when the card has no DOM/feed ID, so cart/order data no longer depends on the Google Feed or Product Card Enhancer plugins assigning IDs before checkout reads the card.
- Checkout v4.0.37 keeps Step 3 payable checks compatible with existing YOOtheme Builder data that does not store product IDs: the payment gate tries product ID first, then falls back to the title-based availability check instead of blocking valid carts.
- Checkout v4.0.38 aligns frontend DOM identity with the feed system: if a card has `data-feed-id` and no `id`, checkout sets `id` to the same feed ID and stores that value as `data-product-id`.
- Checkout v4.0.39 makes reservations product-ID-aware and logs separate Step 3 payable diagnostics for availability and reservation failures.
- Checkout v4.0.40 keeps Step 3 aligned to the active `product_id` reservation: if the same buyer owns the reservation by ID, the payment gate no longer depends on title quote/encoding matching to proceed. Cart-item removal also releases by `product_id`.
- Checkout v4.0.41 processes backend auto-hide one purchased product identity at a time, so a multi-item card order should disable every purchased Builder item instead of only the first/last matched item.
- Live testing after v4.0.41 confirmed that all products in the cart were hidden after card payment.
- Title fallback no longer treats one title word plus the same height range as enough to disable an item; this reduces the risk of hiding a different model with the same height.
- Existing already-disabled wrong Builder items are not automatically restored; use the YOOtheme backup/custom fields or manually re-enable them if needed.
- Future auto-hide work should treat differing meaningful title words such as `test` versus `DUO` as a mismatch, even when quoted model name and height are equal.

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
