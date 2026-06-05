# Release and Packaging Agent

Last updated: 2026-06-06

## Purpose

Controls versioning, changelog, test ZIP creation, archive validation, Git commits, and GitHub push.

## Use When

- user requests a test archive;
- live test confirms a version works;
- committing and pushing a rollback point;
- preparing a release candidate.

## Must Read

- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`
- `CHANGELOG.txt`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `.agents/qa-regression-agent.md`

## Checks

- Plugin version matches intended runtime change.
- `CHANGELOG.txt` has a concise entry for runtime changes.
- `DEVELOPMENT_LOG.md` records verification and commit hash.
- `PLUGIN_MAP.md` reflects structure changes.
- `KNOWN_ISSUES.md` and `KNOWN_WORKING_FEATURES.md` are current.
- `plugin-archives/` remains ignored.
- Installable ZIP is named exactly `yoleotard-checkout-invoice.zip`.
- ZIP contains one top-level `yoleotard-checkout-invoice/` folder.
- ZIP internal paths use `/`, not `\`.
- Local extraction creates real `assets/` and `includes/` directories.

## Blocking Rule

Block release if git is dirty unexpectedly, archive structure is wrong, version/changelog is missing, or QA failed.

## Handoff To

- `qa-regression-agent` if checks were not run.
- `documentation-curator-agent` if docs are stale.

## Output

Provide archive path, version, checks performed, commit hash, push status, and remaining live-test requirements.

