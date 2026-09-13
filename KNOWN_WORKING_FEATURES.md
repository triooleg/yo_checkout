# YOleotard Checkout Known Working Features

Codex must read this file before making any code change.

This file records only features/tools that are confirmed working and currently have no open known bug in `KNOWN_ISSUES.md`.

Items listed here are protected: do not change them unless the current task directly requires it.

## Confirmed Working Without Open Bugs

- Shipping Calculator
- Disabled-shipping card checkout totals remain aligned with KeyCRM and customer email after v4.0.51
- Bank invoice checkout for two-item orders
- KeyCRM order creation for bank invoice checkout
- Customer email sending for bank invoice checkout after the v4.0.35 email-service extraction
- Bank invoice confirmation screen with KeyCRM order number
- Monobank card payment flow
- Monobank live/test token switch in the admin settings after v4.0.55
- Monobank webhook signature verification after v4.0.56
- Monobank external payment window closes after provider return and continues to Step 4 after v4.0.47
- Western Bid PayPal card payment flow, KeyCRM order creation, customer email, and Step 4 after v4.0.50
- Western Bid Stripe card payment flow and Step 4 after v4.0.50
- Western Bid card payments with both enabled and disabled delivery after the v4.0.78 amount/shipping payload fix, live-confirmed by the project owner
- KeyCRM order creation for card checkout
- Customer email sending for card checkout after the v4.0.35 email-service extraction
- Step 4 card payment success screen after the v4.0.35 email-service extraction
- Sold-item auto-hide for multi-item card checkout after v4.0.41
- Host-safe test ZIP packaging for this hosting flow
- Local PHP syntax check workflow
- Local JS syntax check workflow

## Not Listed As Stable Yet

These areas have worked in some tests, but they are not listed as bug-free because they currently have open monitoring notes, local uncommitted fixes, or recent reported regressions:

- Promo-code state and expiration handling
- v4.0.87 manager-assisted purchase layout, clipboard fallbacks, and Meta Messenger webhook until live WordPress/Meta testing confirms public delivery
- v4.0.88 Western Bid physical-goods flag and predicted KeyCRM invoice reference until a live provider request and paid-order reconciliation are confirmed
- v4.0.89 Card Default reservation scoping and Monobank predicted KeyCRM reference until live storefront and paid-order reconciliation are confirmed

## Handling Rule

- Do not touch these areas without necessity.
- If a new task requires changing one of these areas, keep the change as narrow as possible.
- Move an item into `Confirmed Working Without Open Bugs` only after live testing confirms it works and there is no matching active issue in `KNOWN_ISSUES.md`.
