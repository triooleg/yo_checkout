# YOleotard Checkout Development Log

Codex must read this file before making any code change.

This file records completed work, verification, and repository rollback points.

## Log Format

Each task should record:

- date
- user request
- files changed
- behavior changed
- verification performed
- documentation updates
- repository rollback point or blocker

## 2026-05-31 - Repository Documentation Baseline

User request:

- Fix the current working version in GitHub.
- Create repository files:
  - `PROJECT_CONTEXT.md`
  - `PLUGIN_MAP.md`
  - `DEVELOPMENT_LOG.md`
  - `KNOWN_ISSUES.md`
- Ask Codex to always read these files before making changes.
- Gradually move new functions into separate classes.

Files changed:

- Added `PROJECT_CONTEXT.md`.
- Added `DEVELOPMENT_LOG.md`.
- Added `KNOWN_ISSUES.md`.
- Updated `PLUGIN_MAP.md`.
- Kept `WORK_HISTORY.md` as a legacy note from the initial mapping step.

Behavior changed:

- No runtime plugin behavior changed. Documentation and process only.

Verification performed:

- `node --check assets\yo-checkout.js` passed with no syntax errors.
- `php -l yoleotard-checkout-invoice.php` could not run because `php` is not installed or not available in PATH in the current local environment.
- `git status -sb` showed only documentation changes for this task.

Documentation updates:

- Added required-reading rule for future Codex work.
- Added gradual class extraction direction for new functionality.
- Added repository rollback and GitHub publishing process.

Repository rollback point:

- Baseline documentation commit pushed to GitHub: `ee843e6` (`Add project documentation baseline`).

## 2026-05-31 - PHP Syntax Verification

User request:

- Check the plugin code through PHP because the previous PHP check could not run.

Files changed:

- Updated `DEVELOPMENT_LOG.md`.
- Updated `KNOWN_ISSUES.md`.

Behavior changed:

- No runtime plugin behavior changed. Documentation and verification only.

Verification performed:

- `php -l yoleotard-checkout-invoice.php` passed.
- PHP executable found at `D:\Projects\php-8.5.6-nts-Win32-vs17-x64\php.exe`.
- Output: `No syntax errors detected in yoleotard-checkout-invoice.php`.

Documentation updates:

- Recorded successful PHP syntax verification.
- Updated PHP CLI known issue from active environment limitation to resolved environment note.

Repository rollback point:

- PHP verification log commit pushed to GitHub: `aa3938f` (`Record PHP syntax verification`).
