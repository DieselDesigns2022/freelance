# Codex Workflow

This repository has a strict workflow for AI-assisted development.

## Branch Workflow

- Codex must verify the current branch before work.
- Codex must run `git status --short` before work when making repository changes.
- Codex must keep changes scoped to the requested task.
- Codex must not rewrite unrelated application code during documentation-only passes.
- Codex must not modify uploads or environment files unless explicitly requested.

## Backup Rules

Before any deployment, destructive action, or database/schema change:

- Back up the database.
- Back up `uploads/portfolio/`.
- Record the current commit hash.
- Confirm rollback steps.

## Pull Request Workflow

- Codex must not create PRs unless specifically approved by the user.
- Codex must not update, close, merge, or modify PRs unless specifically instructed.
- Codex must not merge.
- Codex must not deploy.
- A PR record was previously created too early during the initial implementation. It must not be acted on without approval.

## Testing Workflow

Codex must run checks appropriate to the change:

- PHP lint checks for PHP changes.
- Documentation sanity checks for documentation changes.
- `git diff --check` when possible.
- Manual testing instructions when runtime services such as MySQL are not available.

Codex must report the exact checks run and whether they passed, failed, or were unavailable.

## Documentation Workflow

- Codex must update docs when behavior changes.
- Codex must update docs when routes change.
- Codex must update docs when database schema changes.
- Codex must update docs when deployment, security, or testing workflow changes.
- Codex must not claim planned features are implemented.
- Codex must label future work as future roadmap.
- Codex must label known limitations clearly.

## Deployment Workflow

- Codex must not deploy.
- Deployment requires explicit user approval.
- Deployment must include backups, pull/checkout of approved code, schema import if needed, upload permission checks, first-admin setup if needed, and smoke testing.

## Reporting Requirements

Codex must report:

- Changed files.
- Confirmation whether application behavior changed.
- Confirmation whether database schema changed.
- Tests/checks run.
- Current git status.
- Latest commit hash if a commit was made.
- PR activity status.

## Known Pitfalls

- Do not approve work based only on a summary.
- Actual changed file review happens after summary review.
- Do not document imagined features.
- Do not document buyer/seller/cart/download workflows; this is a portfolio site.
- Do not create sample uploaded portfolio images during documentation passes.
- Do not touch `uploads/portfolio/` during docs-only cleanup.

## Lessons Learned From Previous Development

- The initial implementation created a PR record too early. Future PR creation requires explicit user approval.
- Bootstrap documentation was incomplete after the initial build and needed a dedicated cleanup pass.
- Documentation must be accurate to the actual code and schema before file-by-file review continues.
