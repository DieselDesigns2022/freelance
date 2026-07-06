# Developer Guide

## Architecture

The app is a simple page-controller PHP application. Each public or admin URL maps directly to a PHP file. Shared helpers live in `includes/`, admin layout includes live in `admin/includes/`, assets live in `assets/`, and SQL setup lives in `database/`.

## Request Lifecycle

1. A browser requests a PHP page directly.
2. The page includes required helpers such as `includes/db.php`, `includes/functions.php`, `includes/auth.php`, or `includes/csrf.php`.
3. Public pages query published portfolio data and render through shared header/footer includes.
4. Admin pages call `require_admin()` except setup/login/logout pages.
5. Admin POST requests verify CSRF tokens, validate input, then use prepared statements for database writes.
6. Upload requests validate non-file fields first, then validate file size, extension, and MIME type before saving files under `uploads/portfolio/`.

## Public Pages

- `index.php`: service-focused homepage with hero, services overview, featured work, why-work copy, FAQ preview, and request CTA.
- `services.php`: website build, website revamp, Shopify make-over, graphics/color customization, and scope information.
- `portfolio.php`: combined published portfolio projects.
- `websites.php`: published Website Build projects.
- `shopify-makeovers.php`: published Shopify Make-Over projects.
- `project.php`: published project details by slug; supports website galleries and Shopify before/after sections.
- `faq.php`: published FAQ page with FAQPage JSON-LD for visible FAQs.
- `request-website.php`: public website build/revamp request form with CSRF, validation, honeypot, and database storage.

## Admin Pages

- `admin/setup.php`: creates the first admin only when zero admins exist.
- `admin/login.php`: authenticates admins.
- `admin/logout.php`: destroys the admin session.
- `admin/dashboard.php`: shows project counts, request counts, recent requests, and recent projects.
- `admin/projects.php`: lists and filters projects; includes publish/unpublish quick action.
- `admin/projects-create.php`: creates portfolio projects.
- `admin/projects-edit.php`: edits and deletes projects.
- `admin/projects-images.php`: manages gallery, before, after, and paired before/after images.
- `admin/requests.php`: lists website requests with status filters.
- `admin/request-view.php`: views requests and manages status, contacted date, archive/delete actions, and private admin notes.
- `admin/faqs.php`: lists FAQs, toggles status, and deletes FAQs.
- `admin/faqs-create.php`: creates FAQs.
- `admin/faqs-edit.php`: edits and deletes FAQs.
- `admin/faq-form.php`: shared FAQ form partial.

## Includes and Helpers

- `includes/db.php`: creates a PDO connection using environment variables or defaults.
- `includes/functions.php`: escaping, redirects, flash messages, labels, slug helpers, URL validation, image upload, safe image deletion, and project card rendering.
- `includes/csrf.php`: CSRF token generation, form field rendering, and verification.
- `includes/auth.php`: admin session helpers, login, logout, and route protection.
- `includes/header.php` and `includes/footer.php`: public layout and contact CTA.

## Database Layer

The app uses PDO configured in `includes/db.php`. SQL statements use prepared statements for application queries and writes. The schema is defined in `database/portfolio_schema.sql`.

## Authentication

Authentication is session-based. `admin_users.password_hash` stores hashes created by `password_hash()`. Login verifies credentials with `password_verify()`, regenerates the session ID, and stores a small `admin_user` array in the session.

## CSRF

Admin forms include `csrf_field()`. POST handlers call `verify_csrf()` before writes. CSRF protection is implemented for admin setup, login, project creation/editing/list actions, and image management forms.

## Security

Implemented security basics include:

- Admin route protection with `require_admin()`.
- Prepared statements.
- Escaped output.
- Password hashing.
- Session ID regeneration on login.
- CSRF tokens on admin forms.
- Upload extension, MIME, and size checks.
- Random upload filenames.
- Deletion guard that only deletes files under `uploads/portfolio/`.

Known limitations are documented in `docs/SECURITY.md`.

## Upload Handling

Image uploads accept JPG, JPEG, PNG, and WEBP files up to 10MB. Project create/edit validation checks non-file fields before saving thumbnail uploads. Paired before/after uploads delete any successfully saved counterpart if the other upload fails, preventing orphaned files. Files are saved to `uploads/portfolio/` with random filenames. The relative path is stored in the database. Apache installs can use `uploads/portfolio/.htaccess` for additional hardening, but nginx deployments need equivalent server configuration.

## Deployment Workflow

Deployment is manual for now:

1. User approves deployment.
2. Back up database and uploads.
3. Pull the approved branch/commit on the server.
4. Import schema if this is first deployment.
5. Configure database environment variables.
6. Ensure upload permissions.
7. Visit `/admin/setup.php` if no admin exists.
8. Run smoke tests.

No automated deployment is implemented.

## Recovery Workflow

If a deployment fails:

1. Stop making changes.
2. Record the failing commit and error.
3. Restore the previous code commit.
4. Restore the database backup if schema/data changed.
5. Restore upload backup if files changed.
6. Re-run smoke tests.

## Documentation Maintenance

Documentation must be updated in the same change when behavior changes. If a feature is planned but not implemented, list it as future roadmap, not implemented functionality.

## Design Philosophy

The design uses a dark base with purple and teal accents, rounded cards, soft shadows, responsive grids, and image-first layouts. The goal is polished and fun, not overly corporate.

## Website Request Workflow

Visitors submit website build/revamp requests through `request-website.php`. The form validates required fields, email format, and optional current website URL with the shared http/https-only URL validator. A honeypot field named `website_url_confirm` silently treats likely spam as success without saving a row. Valid requests are stored in `website_requests` with status `new`.

Admins manage requests in `admin/requests.php` and `admin/request-view.php`. Admins can update status, add private admin notes, mark contacted, archive, or delete requests. Request data and admin notes are never shown publicly.

## FAQ Workflow

Published FAQs display on `faq.php` and the homepage FAQ preview. Admins manage FAQ rows through `admin/faqs.php`, `admin/faqs-create.php`, and `admin/faqs-edit.php`. Draft FAQs remain hidden from public pages and FAQ schema output.
