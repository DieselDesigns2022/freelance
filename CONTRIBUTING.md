# Contributing Guide

## Coding Standards

- Use PHP 8+ compatible syntax.
- Keep the project framework-free unless a future approved phase changes the stack.
- Use PDO prepared statements for database reads and writes.
- Escape output with the shared `e()` helper before rendering user-controlled content.
- Protect admin write actions with CSRF checks.
- Do not wrap imports/includes in try/catch blocks.
- Keep public behavior and admin behavior aligned with documentation.

## Branch Workflow

1. Verify the current branch before starting work.
2. Run `git status --short` before making changes.
3. Keep each pass scoped to the requested work.
4. Do not mix documentation-only changes with application behavior changes unless explicitly requested.
5. Commit only related files.

## Pull Request Workflow

- PRs must not be created until the user explicitly approves PR creation.
- Do not update, close, merge, or modify a PR unless the user specifically instructs it.
- A previously created PR record exists from the initial implementation pass and was created too early.
- That PR must not be merged or acted on without approval.

## Testing Requirements

At minimum, run checks relevant to the change:

- PHP syntax checks for PHP changes.
- `git diff --check` for whitespace/conflict-marker issues.
- Documentation existence/link/content checks for documentation changes.
- Manual browser/admin/database/upload checks after environment setup for behavior changes.

## Backup Requirements

Before deployments or risky operations:

- Back up the database.
- Back up `uploads/portfolio/`.
- Record the current commit hash.
- Confirm rollback steps are understood.

## Documentation Requirements

Update documentation when changing:

- Routes/pages.
- Database schema.
- Authentication or authorization behavior.
- Upload behavior.
- Deployment process.
- Security posture.
- SEO behavior.
- Developer workflow.

Do not document future roadmap items as implemented.

## Developer Workflow

1. Read the request and confirm scope.
2. Inspect actual files before documenting or changing behavior.
3. Make minimal, focused changes.
4. Run required checks.
5. Review `git diff`.
6. Commit if the environment expects commits.
7. Do not create a PR unless explicitly approved.
8. Report changed files, checks, git status, and latest commit hash.

## Phase Completion Checklist

- [ ] Scope stayed within the requested phase.
- [ ] Application behavior was not changed unless requested.
- [ ] Database schema was not changed unless requested.
- [ ] Documentation was updated for any behavior/schema/workflow changes.
- [ ] Required checks were run.
- [ ] `git status --short` was reported.
- [ ] No PR was created unless explicitly approved.
- [ ] No merge or deployment happened.

## Lessons Learned

- A summary is not a substitute for actual file review.
- PR creation must wait for explicit user approval.
- Bootstrap documentation should exist before deeper review phases continue.
- Documentation must separate implemented features from future roadmap and known limitations.
