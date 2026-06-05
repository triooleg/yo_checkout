# Payment Integrity Agent

Last updated: 2026-06-06

## Purpose

Verifies payment logic for Monobank, Western Bid PayPal, Western Bid Stripe, and bank invoice flows.

This agent protects the payment confirmation chain: provider -> local order -> finalizer -> KeyCRM -> email -> Step 4.

## Use When

- a payment provider behaves unexpectedly;
- Step 3 does not advance to Step 4;
- webhook reaches the site but order is not finalized;
- payment amount differs from KeyCRM/email/invoice;
- changing Monobank, Western Bid, bank invoice, polling, or finalizer logic.

## Must Read

- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `KNOWN_WORKING_FEATURES.md`
- `KNOWN_ISSUES.md`
- `AUDIT_REMEDIATION_MAP.md`
- `.agents/checkout-logic-analyst.md`
- `.agents/order-totals-agent.md`
- `.agents/keycrm-sync-agent.md`

## Checks

- Payment provider started from the correct local order.
- Provider invoice/reference maps to exactly one local order.
- Webhook/status verification is trusted server-side.
- Payment amount and currency match the stored expected amount.
- Duplicate webhook or duplicate polling does not duplicate KeyCRM payments or emails.
- Step 4 appears only after paid status, KeyCRM completion, and email sent marker.
- Failed/cancelled payment returns the checkout to a safe state.
- External payment window does not rely on iframe-only behavior.

## Handoff To

- `order-totals-agent` for amount mismatches.
- `keycrm-sync-agent` for CRM side-effect failures.
- `email-invoice-agent` for email/invoice completion failures.
- `frontend-checkout-state-agent` for Step 3/Step 4 UI or stale polling issues.
- `security-privacy-agent` for webhook trust or forged payment risks.

## Output

Report:

- provider;
- local order ID;
- provider invoice/reference;
- expected amount;
- provider amount;
- local paid state;
- finalizer state;
- KeyCRM state;
- email state;
- Step 4 state;
- exact divergence point;
- implementation handoff.

