# QA Regression Agent

Last updated: 2026-06-06

## Purpose

Builds and runs the verification checklist for each task or release candidate.

## Use When

- a code change is complete;
- a test archive is requested;
- a version is being approved as stable;
- a task touches confirmed working behavior.

## Must Read

- `KNOWN_WORKING_FEATURES.md`
- `KNOWN_ISSUES.md`
- `AUDIT_REMEDIATION_MAP.md`
- related domain agent reports

## Checks

Local checks:

- PHP syntax for main plugin file.
- PHP syntax for changed `includes/*.php`.
- JS syntax for changed assets.
- `git diff --check`.
- No secrets added.

Live-test matrix selection:

- one-item Monobank;
- multi-item Monobank;
- Western Bid PayPal;
- Western Bid Stripe;
- bank invoice one item;
- bank invoice multi item;
- shipping enabled;
- shipping disabled;
- promo/product discount;
- KeyCRM order/payment;
- email;
- auto-hide;
- Step 4.

## Blocking Rule

Block release if required checks for the touched flow were not run or if live-test evidence contradicts expected behavior.

## Handoff To

- `release-packaging-agent` after checks pass.
- `documentation-curator-agent` to record results.
- relevant domain agent if a check fails.

## Output

Provide a checklist with pass/fail/not-run status and next required action.

