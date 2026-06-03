# YOleotard Checkout Project Context

Last updated: 2026-06-03

## Required Reading Before Changes

Codex must read these files before making any code change:

- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `AUDIT_REMEDIATION_MAP.md`

If a task changes plugin structure, data flow, hooks, AJAX actions, REST routes, settings, assets, integrations, or known risks, update the relevant documentation in the same task.

Before adding new functionality, review `AUDIT_REMEDIATION_MAP.md` and complete or consciously account for the relevant audit-hardening phase first.

For onboarding a new manager or starting a similar new project, use `PROJECT_MANAGER_START_GUIDE.md` as the process template.

## Project

This repository contains the working checkout plugin for `yoleotard.com`.

Plugin name:

- `YOleotard Checkout + Monobank + Western Bid + IBAN Invoice`

Current plugin version in the main PHP header:

- `4.0.51`

Main business goal:

- Let customers buy YOleotard products directly on the site.
- Support cart flow, product reservation, promo code discounts, delivery calculation, card payments, bank invoices, KeyCRM order synchronization, paid-order emails, invoices, and hiding sold products.

## Current Architecture

The plugin is currently concentrated in:

- `yoleotard-checkout-invoice.php`
- `includes/*.php`
- `assets/yo-checkout.js`
- `assets/yo-checkout.css`

The main PHP file is large and should not keep growing. Future development should gradually move new functionality into separate files and classes.

Preferred direction:

- Add new backend functionality as separate classes under `includes/`.
- Keep the main plugin file as the bootstrap/composition layer where possible.
- Add new frontend functionality as separate files under `assets/` when practical.
- Extract existing functionality only when a task touches that area or when extraction is needed to keep a change safe.

## Development Rules

- Preserve existing working behavior unless the task explicitly asks for a behavior change.
- Check `KNOWN_WORKING_FEATURES.md` before touching confirmed working areas.
- Before changing code, read the required documentation files listed above.
- Before editing an existing function, understand the related flow in `PLUGIN_MAP.md`.
- Do not rewrite large working areas just for style.
- Prefer small, reversible changes.
- When a completed task changes runtime behavior, bump the plugin header version at the maintainer's discretion and append a short entry to the single `CHANGELOG.txt` file.
- Test ZIP archives must be created only when the user explicitly asks for a test archive.
- Test ZIP archives must be stored under local folder `plugin-archives/`, which is excluded from git. For this hosting/WordPress upload flow, the installable ZIP file must be named exactly `yoleotard-checkout-invoice.zip` and the ZIP contents must have one top-level folder named exactly `yoleotard-checkout-invoice/`. All internal ZIP paths must use forward slashes (`/`), for example `yoleotard-checkout-invoice/assets/yo-checkout.js`. Do not use PowerShell `Compress-Archive` directly for the installable plugin ZIP because this host may unpack Windows backslashes (`\`) as literal filename characters.
- After creating the ZIP, verify with Python `zipfile` or another ZIP listing that there are zero entries containing `\`, and verify a local extraction creates real `assets/` and `includes/` directories.
- Record the test version in the plugin header and `CHANGELOG.txt`; do not rely on the ZIP filename for the version.
- Keep a repository rollback point after each completed task.
- After each task, update `DEVELOPMENT_LOG.md`.
- Keep `PLUGIN_MAP.md` current when structure changes.
- Keep `KNOWN_ISSUES.md` current when a limitation, risk, or unresolved problem is discovered.

## Repository Rules

- Use git for rollback points.
- Before changes: check `git status -sb`.
- After changes: inspect diff, run available checks, commit the completed task, and push to GitHub when credentials allow it.
- Record the commit hash in `DEVELOPMENT_LOG.md`.

## Verification Rules

Use the checks that match the change:

- PHP syntax: `php -l yoleotard-checkout-invoice.php`
- JS syntax: `node --check assets/yo-checkout.js`
- Git state: `git status -sb`

If a required tool is missing, record it in `DEVELOPMENT_LOG.md` and `KNOWN_ISSUES.md`.
