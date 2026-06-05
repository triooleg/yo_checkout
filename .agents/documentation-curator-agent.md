# Documentation Curator Agent

Last updated: 2026-06-06

## Purpose

Keeps project documentation aligned with the actual plugin code and development history.

## Use When

- any task changes code, structure, flow, settings, hooks, AJAX, REST, assets, integrations, known risks, or confirmed working behavior;
- a version is approved as stable;
- a bug is discovered or resolved;
- a new agent or process document is added.

## Must Read

- `PROJECT_CONTEXT.md`
- `PLUGIN_MAP.md`
- `DEVELOPMENT_LOG.md`
- `KNOWN_ISSUES.md`
- `KNOWN_WORKING_FEATURES.md`
- `CHANGELOG.txt`
- relevant agent reports

## Checks

- Required reading list is current.
- Plugin version in docs matches the plugin header.
- `PLUGIN_MAP.md` includes new files/modules/routes/actions.
- `DEVELOPMENT_LOG.md` records request, root cause, changed files, behavior, verification, and commit.
- `KNOWN_ISSUES.md` has current status for active/resolved issues.
- `KNOWN_WORKING_FEATURES.md` lists only live-tested stable behavior.
- `CHANGELOG.txt` has one concise entry per runtime version.
- Documentation does not claim untested behavior is stable.

## Blocking Rule

Block release if docs would mislead the next developer about current behavior, risks, or rollback point.

## Handoff To

- `release-packaging-agent` after docs are aligned.
- any domain agent if documentation exposes an unresolved mismatch.

## Output

Provide changed documentation files, what was updated, what remains uncertain, and whether a commit hash must be recorded.

