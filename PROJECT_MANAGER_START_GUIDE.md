# Project Manager Start Guide

Last updated: 2026-06-03

## Purpose

This guide explains how a new project manager should organize development for a plugin like the YOleotard checkout project.

Use it as a starting instruction when creating a new project, onboarding a new manager, or asking Codex to continue work in a structured way.

The main principle is simple:

Preserve working business behavior, document the system, make changes in small reversible phases, verify each phase, and keep a repository rollback point.

## Manager Role

The manager is responsible for keeping the development process controlled, testable, and understandable.

The manager does not need to write every line of code personally, but must make sure that:

- the current working version is known;
- the project map is current;
- every task has a clear goal and scope;
- working features are protected;
- risky changes are split into phases;
- each completed task is tested and documented;
- Git contains rollback points;
- test archives are built correctly only when needed.

## Required Project Documents

Every serious plugin project should have these files from the beginning:

- `PROJECT_CONTEXT.md`
  General project purpose, rules, current version, repository rules, and verification rules.

- `PLUGIN_MAP.md`
  Technical navigation map: files, modules, hooks, AJAX actions, REST routes, data flow, storage, integrations, and known entry points.

- `DEVELOPMENT_LOG.md`
  Append-only task history: what was requested, what changed, how it was verified, and which commit was created.

- `KNOWN_ISSUES.md`
  Active bugs, risks, limitations, environment notes, and monitoring items.

- `KNOWN_WORKING_FEATURES.md`
  Confirmed working features that should not be changed without necessity.

- `CHANGELOG.txt`
  Customer/maintainer-readable version history. Keep one changelog file and append to it.

- Optional but recommended: `AUDIT_REMEDIATION_MAP.md`
  A staged hardening plan based on audit findings before adding new features.

## Required Reading Before Any Change

Before starting any new code change, the manager should require Codex or any developer to read:

1. `PROJECT_CONTEXT.md`
2. `PLUGIN_MAP.md`
3. `DEVELOPMENT_LOG.md`
4. `KNOWN_ISSUES.md`
5. `KNOWN_WORKING_FEATURES.md`
6. The active phase map, for example `AUDIT_REMEDIATION_MAP.md`

If the project does not yet have these files, the first task is to create them before feature development continues.

## How a Development Plan Should Look

A good development plan should be staged, narrow, and testable.

Each phase should include:

- Objective
- Current behavior to preserve
- Files or modules expected to change
- Data flow affected
- User-visible behavior affected
- Risks
- Rollback strategy
- Local verification
- Live-test checklist
- Documentation updates
- Git commit/push requirement

Avoid plans like:

- "rewrite checkout"
- "improve payment"
- "clean code"
- "fix everything"

Prefer plans like:

- "Phase 1: move trusted product price calculation to server while keeping current checkout UI unchanged"
- "Phase 2: add Monobank webhook signature verification while keeping existing return and Step 4 flow unchanged"
- "Phase 3: add per-order access token to public AJAX actions without renaming existing AJAX actions"

## Standard Task Sequence

Every task should follow this sequence.

### 1. Confirm the Goal

Write the task goal in one or two sentences.

Example:

The goal is to make disabled shipping persist as `0.00` so card payment, KeyCRM, and customer email totals match.

### 2. Read Current Context

Read the required project documents.

Check:

- current stable version;
- open known issues;
- confirmed working features;
- previous task notes;
- active phase map;
- current Git status.

### 3. Identify Protected Behavior

Before changing anything, list what must keep working.

Example:

- Monobank payment still reaches Step 4.
- Western Bid PayPal/Stripe still reach Step 4.
- Bank invoice checkout remains unchanged.
- Customer email still sends.
- KeyCRM order still creates.

### 4. Map the Code Area

Find the exact code path before editing.

For a WordPress plugin, identify:

- PHP class or function;
- AJAX action;
- REST route;
- frontend JS entry point;
- post meta or option keys;
- external API call;
- related email, CRM, invoice, or payment side effect.

Do not edit by guessing.

### 5. Choose the Smallest Safe Change

Prefer the smallest change that fixes the task.

If new logic is needed:

- create a new class under `includes/`;
- keep the main plugin file as a wrapper/composition layer;
- preserve existing method names when needed for compatibility.

Do not perform unrelated refactoring in the same task.

### 6. Implement

Make scoped changes only.

Rules:

- do not rewrite working modules without a reason;
- do not change public AJAX action names unless planned;
- do not change REST route URLs unless planned;
- do not change provider settings unexpectedly;
- do not change UI copy unless requested;
- do not mix feature work with security hardening unless the phase requires it.

### 7. Verify Locally

Use checks that match the change.

Common checks:

- `php -l yoleotard-checkout-invoice.php`
- `php -l includes/*.php`
- `node --check assets/yo-checkout.js`
- `git diff --check`
- ZIP structure check if an archive is requested

For payment or checkout changes, also verify the expected data path:

- amount;
- shipping;
- discount;
- fee;
- provider invoice;
- KeyCRM order;
- email;
- Step 4;
- sold-item auto-hide.

### 8. Update Documentation

After the task, update:

- `DEVELOPMENT_LOG.md`
- `PLUGIN_MAP.md` if structure or flow changed
- `KNOWN_ISSUES.md` if a bug/risk was found or resolved
- `KNOWN_WORKING_FEATURES.md` only after live testing confirms a feature works
- `CHANGELOG.txt` if runtime behavior or plugin version changed

### 9. Create Test Archive Only When Requested

Do not create a test archive automatically unless the user asks.

When creating a WordPress plugin archive:

- store it in `plugin-archives/`;
- keep `plugin-archives/` excluded from Git;
- the installable ZIP should use the exact plugin folder name expected by the host;
- internal ZIP paths must use forward slashes `/`;
- verify the archive before giving it to the tester;
- verify extraction creates real `assets/` and `includes/` folders.

For this YOleotard hosting flow, the installable file must be:

- `yoleotard-checkout-invoice.zip`

A versioned copy may exist locally for orientation, but the installable archive should keep the exact plugin name.

### 10. Live Test

A task is not stable until the relevant live test passes.

The manager should collect:

- visible checkout result;
- provider result;
- KeyCRM order number;
- customer email result;
- admin/plugin log;
- sold-item hiding result when relevant.

### 11. Commit and Push

After live test confirms the task works:

- check `git status`;
- stage only task-related files;
- create a clear commit;
- push to GitHub;
- record the commit hash in `DEVELOPMENT_LOG.md`.

If the user asks not to commit until testing, wait.

## Versioning Rules

Increase plugin version when runtime behavior changes.

Examples of runtime changes:

- payment behavior;
- checkout totals;
- email content;
- KeyCRM payloads;
- reservation logic;
- product hiding;
- public AJAX behavior;
- frontend checkout logic.

Do not increase plugin version for documentation-only changes unless the project explicitly uses documentation versions.

Every runtime version should have:

- plugin header version update;
- `CHANGELOG.txt` entry;
- `DEVELOPMENT_LOG.md` entry;
- local checks;
- live test when applicable;
- Git rollback point after confirmation.

## Protecting Working Features

The manager must protect confirmed working features.

Use `KNOWN_WORKING_FEATURES.md` as a "do not touch without reason" list.

When a task touches a working feature:

- state why it must be touched;
- keep the change narrow;
- test that feature directly after the change;
- move it back to working only after live confirmation if a regression occurs.

## Handling Known Issues

Use `KNOWN_ISSUES.md` as the risk board.

Each issue should include:

- status;
- details;
- root cause if known;
- handling plan;
- test needed to close it.

Do not delete old issues silently.

When an issue is fixed:

- mark it fixed;
- add version/date;
- describe the live test;
- keep useful history.

## Architecture Rules

The main plugin file should not keep growing forever.

Preferred architecture:

- main file: bootstrap, hooks, settings entry points, wrappers;
- `includes/`: backend services/classes;
- `assets/`: frontend JS/CSS;
- docs: maps, logs, known issues, working features.

New backend functionality should usually become a class:

- `class-yo-checkout-product-catalog.php`
- `class-yo-checkout-order-access.php`
- `class-yo-checkout-order-totals.php`
- `class-yo-checkout-payment-finalizer.php`
- `class-yo-checkout-reservation.php`

Do not extract everything at once.

Extract when:

- a task touches that area;
- a module is becoming risky;
- duplicate logic causes bugs;
- a new feature needs a clear owner.

## Payment Project Rules

Payment logic must be treated as high-risk.

For each provider, know:

- how payment is created;
- where the customer is redirected;
- how the provider confirms payment;
- how the webhook is verified;
- how local order status is updated;
- how KeyCRM is finalized;
- when email is sent;
- when Step 4 appears;
- what happens on duplicate webhook;
- what happens on provider delay.

Never mark an order paid only because the browser says payment succeeded.

Payment completion should come from verified provider data or a trusted server-side status check.

## Data Authority Rules

The browser is not an authority for:

- final product price;
- discount;
- shipping cost;
- card fee;
- product availability;
- payment status;
- KeyCRM status;
- sold-item hiding completion.

The browser may send:

- selected product ID;
- customer input;
- selected shipping option key;
- selected payment method;
- current UI/cart state for convenience.

The server should calculate and store:

- trusted product snapshot;
- trusted totals;
- payment snapshot;
- KeyCRM payload;
- invoice totals;
- email totals;
- paid status.

## Stop Conditions

Stop the task and do not continue stacking changes if:

- payment amount differs from KeyCRM or email;
- Step 4 opens before KeyCRM/email completion;
- a working payment provider stops reaching Step 4;
- bank invoice duplicates emails or products;
- auto-hide disables the wrong product;
- code starts requiring broad rewrites outside the task scope;
- live test contradicts local assumptions;
- a security issue is found that changes the phase priority.

## Recommended New Project Startup Checklist

For a new plugin project, begin with:

1. Create the repository.
2. Create `PROJECT_CONTEXT.md`.
3. Create `PLUGIN_MAP.md`.
4. Create `DEVELOPMENT_LOG.md`.
5. Create `KNOWN_ISSUES.md`.
6. Create `KNOWN_WORKING_FEATURES.md`.
7. Create `CHANGELOG.txt`.
8. Define packaging rules.
9. Define versioning rules.
10. Define local verification commands.
11. Define live-test checklist.
12. Make the first Git commit before feature work.

## Example Phase Template

Use this template for each phase:

```md
## Phase N - Short Name

### Objective

What this phase must achieve.

### Current Working Behavior To Preserve

- Item 1
- Item 2
- Item 3

### Files Expected To Change

- `file-a.php`
- `includes/class-example.php`
- `assets/example.js`

### Implementation Steps

1. Step one.
2. Step two.
3. Step three.

### Verification

- Local check 1
- Local check 2
- Live test 1
- Live test 2

### Documentation Updates

- `DEVELOPMENT_LOG.md`
- `PLUGIN_MAP.md`
- `KNOWN_ISSUES.md`
- `CHANGELOG.txt`

### Rollback Plan

Return to commit `...` if this phase breaks the protected behavior.
```

## Final Rule

The best development plan is not the biggest plan.

The best plan is the one that keeps the current working business alive while making the next risk smaller.

