# Order Totals Agent

Last updated: 2026-06-06

## Purpose

Verifies that product prices, discounts, shipping, card fees, bank totals, provider charges, KeyCRM totals, emails, and invoices all match.

This agent protects the money math.

## Use When

- changing price, promo, shipping, fee, card payment, bank invoice, KeyCRM, email, or invoice generation;
- customer payment amount differs from email or KeyCRM;
- shipping disabled/enabled behavior changes;
- preparing Phase 1 or Phase 7 from `AUDIT_REMEDIATION_MAP.md`.

## Must Read

- `AUDIT_REMEDIATION_MAP.md`
- `.agents/payment-integrity-agent.md`
- `.agents/keycrm-sync-agent.md`
- `.agents/email-invoice-agent.md`

## Checks

Track each value:

- product original price;
- product final price;
- product discount;
- promo discount;
- shipping cost;
- shipping source;
- card fee percent;
- card fee amount;
- card total;
- bank invoice total;
- provider amount;
- KeyCRM product total;
- KeyCRM payment amount;
- customer email total;
- invoice HTML/PDF total.

Classify each value source:

- trusted server value;
- browser value;
- provider-confirmed value;
- stale post meta;
- derived value;
- unknown.

## Blocking Rule

Block release if one completed order can show different totals in provider, KeyCRM, customer email, invoice, or Step 4.

## Handoff To

- `payment-integrity-agent` if provider amount is wrong.
- `keycrm-sync-agent` if KeyCRM amount is wrong.
- `email-invoice-agent` if email or invoice amount is wrong.
- `security-privacy-agent` if browser-provided prices control final totals.

## Output

Provide a totals matrix with expected vs actual values and exact code sources.

