# YOleotard Checkout Known Working Features

Codex must read this file before making any code change.

This file records only features/tools that are confirmed working and currently have no open known bug in `KNOWN_ISSUES.md`.

Items listed here are protected: do not change them unless the current task directly requires it.

## Confirmed Working Without Open Bugs

- Shipping Calculator
- Bank invoice checkout for two-item orders
- KeyCRM order creation for bank invoice checkout
- Customer email sending for bank invoice checkout
- Bank invoice confirmation screen with KeyCRM order number
- Host-safe test ZIP packaging for this hosting flow
- Local PHP syntax check workflow
- Local JS syntax check workflow

## Not Listed As Stable Yet

These areas have worked in some tests, but they are not listed as bug-free because they currently have open monitoring notes, local uncommitted fixes, or recent reported regressions:

- Monobank card payment flow
- KeyCRM order creation/update flow
- Customer email sending tied to card/bank checkout
- Step 4 Success screen
- Sold-item auto-hide
- Promo-code state and expiration handling

## Handling Rule

- Do not touch these areas without necessity.
- If a new task requires changing one of these areas, keep the change as narrow as possible.
- Move an item into `Confirmed Working Without Open Bugs` only after live testing confirms it works and there is no matching active issue in `KNOWN_ISSUES.md`.
