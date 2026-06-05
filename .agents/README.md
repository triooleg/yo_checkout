# YOleotard Checkout Agent System

Last updated: 2026-06-06

## Purpose

This folder contains reusable project agents for the YOleotard checkout plugin.

Agents are role instructions. They help Codex or a human maintainer analyze, plan, verify, and release changes without losing the current project rules.

Agents do not replace `PROJECT_CONTEXT.md`, `PLUGIN_MAP.md`, `KNOWN_ISSUES.md`, `KNOWN_WORKING_FEATURES.md`, or `AUDIT_REMEDIATION_MAP.md`. They operate on top of those documents.

## Global Agent Rules

Every agent must:

- read the required project documents before analysis or implementation;
- protect confirmed working behavior from `KNOWN_WORKING_FEATURES.md`;
- keep runtime code unchanged unless the user explicitly asks for implementation;
- produce evidence-based findings with file/function/log references;
- mark incomplete conclusions as hypotheses;
- avoid unrelated refactors;
- hand off actionable work with exact files, risks, tests, and rollback notes;
- update documentation when its work changes project structure, flow, risks, or confirmed behavior.

## Required Reading For All Agents

1. `PROJECT_CONTEXT.md`
2. `PLUGIN_MAP.md`
3. `DEVELOPMENT_LOG.md`
4. `KNOWN_ISSUES.md`
5. `KNOWN_WORKING_FEATURES.md`
6. `AUDIT_REMEDIATION_MAP.md`
7. Relevant `.agents/*.md` files for cooperating roles

## Agent List

- `checkout-logic-analyst.md`
  First-line investigation agent for expected vs actual checkout flow.

- `payment-integrity-agent.md`
  Payment provider, webhook, polling, amount, and Step 4 verification agent.

- `order-totals-agent.md`
  Price, discount, shipping, fee, bank/card total, invoice/email/KeyCRM consistency agent.

- `keycrm-sync-agent.md`
  KeyCRM buyer/order/product/payment/status synchronization agent.

- `email-invoice-agent.md`
  Customer email, bank invoice, paid email, invoice file, and attachment agent.

- `product-identity-autohide-agent.md`
  Product ID, feed ID, YOOtheme card identity, reservation identity, and sold-item auto-hide agent.

- `security-privacy-agent.md`
  Webhook authenticity, public AJAX ownership, invoice privacy, secret, and data exposure agent.

- `frontend-checkout-state-agent.md`
  Browser cart, localStorage, payment window, polling, Step 3/Step 4, and stale callback agent.

- `qa-regression-agent.md`
  Local and live regression checklist agent.

- `release-packaging-agent.md`
  Version, changelog, archive, git, push, and release-readiness agent.

- `documentation-curator-agent.md`
  Project map, development log, known issues, working features, and changelog consistency agent.

## Default Agent Workflow

For bug investigation:

1. `checkout-logic-analyst`
2. Domain agent:
   - payment issue -> `payment-integrity-agent`
   - totals issue -> `order-totals-agent`
   - KeyCRM issue -> `keycrm-sync-agent`
   - email/invoice issue -> `email-invoice-agent`
   - product hiding issue -> `product-identity-autohide-agent`
   - security/privacy issue -> `security-privacy-agent`
   - frontend state issue -> `frontend-checkout-state-agent`
3. `qa-regression-agent`
4. `documentation-curator-agent`
5. `release-packaging-agent` only when user confirms the change works or requests an archive/release.

For audit remediation phases:

1. Start with the phase in `AUDIT_REMEDIATION_MAP.md`.
2. Run `checkout-logic-analyst` to map the current flow.
3. Run the matching domain agent.
4. Prepare implementation handoff.
5. Implement only after user confirmation or explicit implementation request.
6. Run `qa-regression-agent`.
7. Run `documentation-curator-agent`.
8. Run `release-packaging-agent` if a test archive, commit, or push is required.

## Blocking Rules

The following agents can block implementation or release:

- `payment-integrity-agent` can block release if payment amount/status/Step 4 is inconsistent.
- `order-totals-agent` can block release if totals differ between provider, KeyCRM, email, invoice, and frontend.
- `keycrm-sync-agent` can block release if KeyCRM order/payment/status is missing or duplicated.
- `security-privacy-agent` can block release if payment can be forged or customer data is exposed.
- `product-identity-autohide-agent` can block release if auto-hide can disable the wrong product.
- `qa-regression-agent` can block release if required checks are missing.
- `release-packaging-agent` can block release if archive structure or git state is unsafe.

## Handoff Format Between Agents

Each agent should pass:

- summary;
- evidence;
- affected files/functions/actions;
- current expected flow;
- actual observed flow;
- findings by severity;
- recommended implementation;
- behavior to preserve;
- required local checks;
- required live tests;
- documentation updates;
- rollback point.

## Minimal Use For Each Task

For small tasks:

- use `checkout-logic-analyst`;
- use one domain agent;
- use `qa-regression-agent`;
- use `documentation-curator-agent`;
- use `release-packaging-agent` only if release/ZIP/git is requested or required.

For payment or checkout core tasks, use all relevant domain agents before implementation.

