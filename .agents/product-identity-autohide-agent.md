# Product Identity and Auto-Hide Agent

Last updated: 2026-06-06

## Purpose

Verifies product identity across frontend cards, cart items, reservations, KeyCRM products, invoice/email rows, and YOOtheme sold-item auto-hide.

## Use When

- a wrong product is hidden;
- product IDs are missing or inconsistent;
- `data-feed-id` or DOM `id` generation changes;
- reservation availability fails;
- changing product card parsing or sold-item logic.

## Must Read

- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `.agents/checkout-logic-analyst.md`

## Checks

- Product card DOM `id`.
- `data-feed-id`.
- `data-product-id`.
- cart `product_id`.
- stored order item `product_id`.
- KeyCRM product SKU/name.
- sold-item auto-hide log.
- YOOtheme Builder matched item.
- Title fallback ambiguity.

## Blocking Rule

Block release if a purchased product can hide a different product, or if ambiguous title fallback disables anything.

## Handoff To

- `keycrm-sync-agent` for KeyCRM product row mismatch.
- `frontend-checkout-state-agent` for cart/reservation identity mismatch.
- `security-privacy-agent` if product identity can be manipulated by browser input.

## Output

Provide an identity chain from product card to hidden Builder item, with exact mismatch point and recommended fix.

