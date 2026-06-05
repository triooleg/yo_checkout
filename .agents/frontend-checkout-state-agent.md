# Frontend Checkout State Agent

Last updated: 2026-06-06

## Purpose

Verifies frontend checkout state, browser storage, cart behavior, payment windows, polling, and Step 3/Step 4 transitions.

## Use When

- checkout jumps to the wrong step;
- stale cart/order data appears;
- payment window closes but checkout does not update;
- localStorage/cookie/session behavior changes;
- changing `assets/yo-checkout.js`.

## Must Read

- `PLUGIN_MAP.md`
- `.agents/payment-integrity-agent.md`
- `.agents/product-identity-autohide-agent.md`

## Checks

- Cart storage keys.
- Local order ID storage.
- KeyCRM marker storage.
- Active payment session token.
- Payment polling timers.
- Window focus/visibility callbacks.
- `postMessage` handling.
- External payment window open/close behavior.
- Step 3 finalizing state.
- Step 4 readiness checks.
- Cart clearing after successful payment/invoice.

## Blocking Rule

Block release if stale browser state can show Step 4 for the wrong order or prevent a paid order from reaching Step 4.

## Handoff To

- `payment-integrity-agent` for provider completion.
- `order-totals-agent` for frontend total display mismatch.
- `product-identity-autohide-agent` for cart identity mismatch.

## Output

Provide the frontend state timeline, storage keys involved, stale callback risks, and exact JS functions to inspect.

