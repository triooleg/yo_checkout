# YOleotard Checkout Work History

This file records what was changed after each development task. Before starting a new task, read this file and `PLUGIN_MAP.md`.

## Working Rules

- Start every task by reviewing `PLUGIN_MAP.md` and the latest entries in this file.
- Keep existing behavior unchanged unless the requested task requires a change.
- Put new functionality into separate files when practical, then include/enqueue it from the main plugin file.
- When modifying an existing large area, consider extracting that area into a separate file as part of the task.
- After completing a task, update this history with:
  - date
  - user request
  - files changed
  - behavior changed
  - verification performed
  - map update status
  - repository/rollback point
- If `PLUGIN_MAP.md` becomes outdated, update it in the same task.
- After every completed code change, create a git commit when `.git` is available, so the project can be returned to the previous state. If git is unavailable, record that no repository rollback point could be created.

## 2026-05-31 - Initial Plugin Analysis And Documentation

User request:

- Analyze the working YOleotard checkout plugin and create a map for future development.
- Establish a process where every future task updates history and updates the map when structure changes.
- Preserve current functions and avoid growing the main file with new functionality.
- Verify syntax/logic/workability after each task.

Files changed:

- Added `PLUGIN_MAP.md`.
- Added `WORK_HISTORY.md`.
- Updated both documents to require repository rollback points after each future change.

Behavior changed:

- No runtime behavior changed. Documentation only.

Findings:

- Plugin currently consists of one large main PHP file, one JS file, one CSS file, and historical changelog files.
- Main PHP file contains the full backend: settings, checkout AJAX, payment providers, KeyCRM, shipping, invoice generation, emails, sold-item hiding.
- Frontend JS contains the full checkout/cart/payment flow.
- There is no `.git` repository in the current plugin directory.

Verification performed:

- `node --check assets\yo-checkout.js` passed with no syntax errors.
- `php -l yoleotard-checkout-invoice.php` could not run because `php` is not installed or not available in PATH in the current local environment.
- Documentation files were opened after creation to confirm they were written correctly.

Map update status:

- `PLUGIN_MAP.md` created and aligned with current structure.
- Rollback/commit rule added to `PLUGIN_MAP.md`.

Repository/rollback point:

- No git commit was created because the current directory is not a git repository.
- Earlier check returned: `fatal: not a git repository (or any of the parent directories): .git`.
