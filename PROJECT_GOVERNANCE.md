# YOleotard Checkout Project Governance

Last updated: 2026-06-06

## Purpose

This document defines how the YOleotard checkout project is managed.

It is the top-level operating model for planning, analysis, implementation, verification, release, documentation, and rollback.

The goal is to keep development predictable: no rushed changes to payment logic, no undocumented releases, no archive mistakes, and no accidental changes to confirmed working behavior.

## Governance Principle

Every task must move through controlled gates:

1. Intake
2. Classification
3. Agent selection
4. Analysis
5. Implementation decision
6. Implementation
7. Local verification
8. Documentation
9. Live testing
10. Release / commit / archive
11. Stable rollback point

The `Project Orchestrator Agent` owns this sequence.

## Governance Roles

### Project Owner

Usually the user.

Responsibilities:

- defines business priority;
- confirms live-test success;
- approves stable commits when required;
- decides whether to proceed after an analysis report;
- requests test archives.

### Project Orchestrator Agent

File:

- `.agents/project-orchestrator-agent.md`

Responsibilities:

- receives tasks;
- identifies mode: analysis, implementation, release, or mixed;
- selects domain agents;
- controls phase order;
- protects working features;
- stops work when blocking agents report blockers;
- prepares final handoff;
- ensures verification and documentation happen.

### Domain Agents

Files:

- `.agents/payment-integrity-agent.md`
- `.agents/order-totals-agent.md`
- `.agents/keycrm-sync-agent.md`
- `.agents/email-invoice-agent.md`
- `.agents/product-identity-autohide-agent.md`
- `.agents/security-privacy-agent.md`
- `.agents/frontend-checkout-state-agent.md`

Responsibilities:

- analyze their domain;
- find risks and mismatches;
- block unsafe implementation or release;
- provide exact handoff with files, tests, and rollback notes.

### QA and Release Agents

Files:

- `.agents/qa-regression-agent.md`
- `.agents/release-packaging-agent.md`
- `.agents/documentation-curator-agent.md`

Responsibilities:

- verify local and live-test checklist;
- keep docs aligned with code;
- manage version, archive, commit, and push rules.

## Task Classification

The orchestrator must classify every task before work starts.

### Analysis Task

Examples:

- "check why this failed"
- "analyze the code"
- "make a report"
- "what should be changed"

Rules:

- no runtime code changes;
- produce findings and handoff;
- user confirmation is required before implementation.

### Implementation Task

Examples:

- "fix it"
- "move this function"
- "add this module"
- "make the change"

Rules:

- run analysis first if the task touches payment, totals, KeyCRM, email, identity, security, or frontend checkout state;
- make the smallest safe change;
- verify locally;
- update docs.

### Release Task

Examples:

- "make archive"
- "commit this as working"
- "push to GitHub"
- "final test version"

Rules:

- run QA;
- verify documentation;
- verify archive if requested;
- commit/push only allowed files;
- record commit hash.

### Mixed Task

Examples:

- "analyze, fix, test, and make archive"

Rules:

- split internally into analysis -> implementation -> verification -> archive;
- do not skip gates;
- if analysis discovers a Critical or High risk outside the requested scope, pause and report.

## Standard Governance Flow

### Gate 1 - Intake

The orchestrator restates the task.

Required output:

- goal;
- mode;
- risk level;
- expected affected areas.

### Gate 2 - Context Check

Read:

- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `AUDIT_REMEDIATION_MAP.md`
- `.agents/README.md`
- `.agents/project-orchestrator-agent.md`

Required check:

- `git status --short --branch`

### Gate 3 - Protected Behavior

List confirmed working behavior that may be touched.

If a task touches a protected feature, define exact tests for it.

### Gate 4 - Agent Selection

Select agents based on affected area.

Minimum:

- `project-orchestrator-agent`

For most checkout work:

- `checkout-logic-analyst`
- one or more domain agents
- `qa-regression-agent`
- `documentation-curator-agent`

For release or archive:

- `release-packaging-agent`

### Gate 5 - Analysis Handoff

Before implementation, the orchestrator must have:

- exact problem statement;
- expected flow;
- actual flow;
- affected files/functions/actions;
- risk classification;
- implementation recommendation;
- tests.

### Gate 6 - Implementation Permission

Implementation is allowed when:

- user explicitly requested implementation;
- no blocking agent blocks the change;
- protected behavior is identified;
- rollback strategy is clear.

Implementation is not allowed when:

- user asked for analysis only;
- payment/security/privacy blocker is unresolved;
- change would mix unrelated phases;
- expected behavior is still unclear.

### Gate 7 - Local Verification

Required checks depend on changed files.

Default:

- `php -l yoleotard-checkout-invoice.php`
- `php -l` for changed files under `includes/`
- `node --check assets/yo-checkout.js` if JS changed
- `git diff --check`
- no secrets added

### Gate 8 - Documentation

Update documentation based on change.

Possible files:

- `DEVELOPMENT_LOG.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `CHANGELOG.txt`
- `PROJECT_CONTEXT.md`
- `AUDIT_REMEDIATION_MAP.md`
- `.agents/*.md`

### Gate 9 - Live Testing

Live testing is required before marking runtime behavior stable.

The project owner confirms live test success.

Do not move a feature into `KNOWN_WORKING_FEATURES.md` before live confirmation.

### Gate 10 - Release and Rollback Point

After confirmed success:

- commit task files;
- push to GitHub when allowed;
- record commit hash in `DEVELOPMENT_LOG.md`;
- keep repository clean.

## Blocking Model

The orchestrator must pause the task when any blocking agent reports a blocker.

Blocking areas:

- payment can be forged;
- provider amount differs from local/KeyCRM/email/invoice;
- Step 4 can appear before finalization;
- KeyCRM order/payment/status is missing while checkout shows success;
- wrong product can be hidden;
- customer invoice can be accessed by guessing URL;
- archive can install as duplicate plugin;
- documentation would misrepresent reality.

## Phase Governance

Current phase plan lives in:

- `AUDIT_REMEDIATION_MAP.md`

The orchestrator must not skip phases casually.

Current next phase:

- `Phase 3 - Per-Order Guest Access Tokens`

Phase 0 is complete. Phase 1 and Phase 2 are implemented as test candidates and require the documented live validation before they are marked stable.

## Archive Governance

Archives are created only when user requests.

Installable archive rule for this project:

- file name: `yoleotard-checkout-invoice.zip`
- top-level folder inside ZIP: `yoleotard-checkout-invoice/`
- internal paths use `/`
- archive stored in `plugin-archives/`
- `plugin-archives/` is not committed
- before generating a new exact-name ZIP, preserve the existing exact-name ZIP as a versioned local archive, for example `yoleotard-checkout-invoice-v4.0.62.zip`, so the previous test package is not overwritten
- verify extraction before handoff

## Git Governance

Before work:

- check status.

During work:

- do not revert unrelated user changes.

After confirmed task:

- stage only related files;
- commit with clear message;
- push when allowed;
- record hash.

For analysis-only work:

- commit only if new/updated documentation was intentionally created.

## Version Governance

Bump plugin version only for runtime behavior changes.

Do not bump plugin version for:

- documentation-only work;
- agent instructions;
- planning documents;
- governance documents.

Runtime changes require:

- plugin header update;
- `CHANGELOG.txt` entry;
- `DEVELOPMENT_LOG.md` entry;
- verification;
- live test when applicable.

## Project Board

Current status:

- Stable runtime version: `4.0.56`
- Current branch: `main`
- Current next planned work: `Phase 4 - Invoice Access Hardening`
- Runtime work in progress: `v4.0.62` whole-badge promo GIF notification test candidate
- Phase 0 baseline: completed in `REGRESSION_BASELINE.md`

## Final Rule

No task is "done" because code was changed.

A task is done when the orchestrator can show:

- what changed;
- why it changed;
- what stayed protected;
- how it was verified;
- where it is documented;
- which commit can restore it.
