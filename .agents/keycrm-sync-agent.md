# KeyCRM Sync Agent

Last updated: 2026-06-06

## Purpose

Verifies KeyCRM buyer, order, product rows, payment records, comments, and status synchronization.

## Use When

- KeyCRM order is missing;
- KeyCRM creates duplicate products or duplicate orders;
- buyer ID is invalid;
- payment record is missing or duplicated;
- order status is not updated;
- changing `includes/class-yo-checkout-keycrm.php` or finalizer logic.

## Must Read

- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `.agents/payment-integrity-agent.md`
- `.agents/order-totals-agent.md`
- `.agents/product-identity-autohide-agent.md`

## Checks

- Buyer creation/update result.
- Existing order marker lookup by local order, session, browser, email, phone, and KeyCRM order ID.
- Product row matching and update behavior.
- Product quantity and price consistency.
- Payment record creation result.
- Order status update result.
- Completion markers are written only after successful API calls.
- Retry behavior after KeyCRM errors.

## Blocking Rule

Block release if KeyCRM order, product rows, payment record, or status can be missing while checkout shows success.

## Handoff To

- `payment-integrity-agent` for paid/finalizer failures.
- `order-totals-agent` for CRM amount mismatch.
- `product-identity-autohide-agent` for product ID/name mismatch.
- `documentation-curator-agent` for known issue or working feature updates.

## Output

Provide:

- local order ID;
- KeyCRM order ID;
- buyer ID state;
- product rows expected/actual;
- payment records expected/actual;
- status expected/actual;
- API error evidence;
- recommended fix.

