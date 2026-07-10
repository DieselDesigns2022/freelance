# Deployment Guide

## GitHub Workflow

- Keep work on a feature/review branch until approved.
- Do not create a PR unless the user explicitly approves PR creation.
- Do not merge without explicit user approval.
- Do not deploy without explicit user approval.
- Record the commit hash being deployed.

## VPS Workflow

1. Confirm the approved branch/commit.
2. Back up the current database and `uploads/portfolio/`.
3. Pull the approved code onto the server.
4. Configure database credentials.
5. Import schema if this is a first deployment.
6. Confirm upload permissions.
7. Run smoke tests.

## Branch Workflow

- Verify branch with `git branch --show-current`.
- Verify working tree with `git status --short`.
- Pull only when it is safe for the environment and workflow.
- Do not force-push or rewrite shared history without approval.

## Pull Process

Example server pull process after approval:

```bash
git fetch origin
git checkout APPROVED_BRANCH
git pull --ff-only origin APPROVED_BRANCH
```

## Database Schema Import Process

For first deployment, import:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/portfolio_schema.sql
```

The schema creates these current tables for fresh installs:

- `admin_users`
- `portfolio_projects`
- `portfolio_project_images`
- `portfolio_before_after_pairs`
- `website_requests`
- `faqs`
- `contract_templates`
- `products`
- `orders`
- `contract_instances`


## Schema-Before-Code Requirement

For first deployment, import the full `database/portfolio_schema.sql` before testing the site. For existing deployments, apply the new `website_requests` and `faqs` table definitions and FAQ seed statements before deploying or loading the expanded service/request/FAQ code.

If the updated code is loaded before the schema is updated, pages that query `faqs` or `website_requests` may fail with missing-table database errors. This affects the homepage FAQ preview, FAQ page, request page, admin dashboard, admin request pages, and admin FAQ pages. Phase 2 storefront and admin order pages also require the Phase 2 migration before use; missing `products`, `contract_templates`, `orders`, or `contract_instances` tables can break store and admin order pages. Existing deployments should run `database/migrations/20260710_phase_2_store_contract_system.sql` before testing the Phase 2 storefront/contract system.

## Environment / Database Credential Setup

`includes/db.php` reads these environment variables:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

If not set, local defaults are used. Production should set explicit credentials through the web server, PHP-FPM pool, hosting control panel, or server environment configuration.

## Upload Folder Permission Setup

Ensure the web server can write to:

```bash
uploads/portfolio/
```

Typical VPS example:

```bash
chown -R www-data:www-data uploads/portfolio
chmod 755 uploads uploads/portfolio
```

Adjust user/group for the actual server.

## First Admin Setup

After schema import and deployment, visit:

```text
/admin/setup.php
```

Create the first admin account. After one admin exists, setup is disabled and shows “Setup is already complete.”

## Backup Process

Before deployment:

```bash
mysqldump -u YOUR_USER -p YOUR_DATABASE > backup-before-deploy.sql
tar -czf uploads-portfolio-backup.tar.gz uploads/portfolio
```

Store backups somewhere safe and outside the public web root when possible.

## Smoke Testing

After deployment, verify:

- `/index.php` loads.
- `/services.php` loads.
- `/portfolio.php` loads.
- `/websites.php` loads.
- `/shopify-makeovers.php` loads.
- `/faq.php` loads.
- `/request-website.php` loads.
- `/admin/login.php` loads.
- `/admin/setup.php` behaves correctly depending on whether an admin exists.
- After login, `/admin/requests.php` and `/admin/faqs.php` load.
- Request and FAQ features work after the updated schema creates `website_requests` and `faqs`.
- A test image upload works in the admin panel.
- Uploaded image appears publicly after publishing a project.

## Rollback Procedure

1. Stop making changes.
2. Record the failing commit and symptoms.
3. Restore the previous approved commit:

   ```bash
   git checkout PREVIOUS_GOOD_COMMIT
   ```

4. Restore database backup if database changes were deployed.
5. Restore `uploads/portfolio/` backup if uploads were changed.
6. Re-run smoke tests.

## Deployment Status

No deployment has been performed in this workflow.

## Schema Updates for Services Expansion

The canonical schema file now also creates `website_requests` and `faqs`, and seeds general starter FAQs. Existing deployments need the new table definitions and FAQ seed statements applied before using request or FAQ features.

## Phase 2 Deployment Notes

Run the additive migration after deploying the code:

```bash
mysql -u <user> -p <database> < database/migrations/20260710_phase_2_store_contract_system.sql
```

After migration, create at least one active contract template in admin, then assign an active contract template to each product that should be purchasable. Public purchase is blocked for active products without an active contract template.

There is no payment gateway deployment step in Phase 2. Payment status is managed manually in admin. Signed contract downloads are HTML, so no PDF service or Composer dependency is required.
