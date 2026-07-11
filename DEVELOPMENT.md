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

### Phase 2 Public Store and Contract Routes

- `/store.php`: lists active purchasable services/products.
- `/product-service.php?slug=...`: shows an active product/service detail page.
- `/purchase.php?product=...`: public order-start form that validates customer details, creates an order, snapshots product/template data, and creates a contract instance. Public purchase is blocked when the selected product lacks an active assigned contract template.
- `/sign-contract.php?token=...`: secure token-based contract signing page.
- `/contract-copy.php?token=...`: secure token-based contract copy view.
- `/contract-copy.php?token=...&download=1`: signed-only HTML contract download.

Token routes are private by token and marked noindex. Payment handling is manual only in Phase 2; no live payment gateway is implemented.

## Admin Pages

- `admin/setup.php`: creates the first admin only when zero admins exist.
- `admin/login.php`: authenticates admins.
- `admin/logout.php`: destroys the admin session.
- `admin/dashboard.php`: shows project/request counts, Phase 2 pending order count, contracts awaiting signature, signed contracts, manual payment pending count, recent orders, recent requests, and recent projects.
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

### Phase 2 Admin Store and Contract Pages

- `admin/products.php`: lists products and archives products.
- `admin/products-create.php`: creates products.
- `admin/products-edit.php`: edits products.
- `admin/product-form.php`: shared product form partial.
- `admin/contract-templates.php`: lists contract templates.
- `admin/contract-templates-create.php`: creates contract templates.
- `admin/contract-templates-edit.php`: edits contract templates.
- `admin/contract-template-form.php`: shared contract template form partial.
- `admin/contract-template-preview.php`: previews a rendered template with sample placeholder data.
- `admin/orders.php`: lists customer orders and contract/payment statuses.
- `admin/order-view.php`: views order details, updates manual order/payment status, marks pending contracts sent, voids unsigned/unvoided contracts, and generates replacement signing links for pending/sent/viewed contracts.
- `admin/contract-copy.php`: authenticated admin view/download route for signed HTML contract copies.

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

Admin forms include `csrf_field()`. POST handlers call `verify_csrf()` before writes. CSRF protection is implemented for admin setup, login, project creation/editing/list actions, image management forms, public purchase forms, public signing forms, admin product create/edit/archive actions, admin contract template create/edit actions, admin order status updates, admin mark-contract-sent actions, admin void-contract actions, and admin replacement signing link generation.

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

## Phase 2 Development Notes

The storefront is intentionally framework-free and follows the existing include pattern: public routes load `includes/db.php`, `includes/functions.php`, and `includes/csrf.php` when forms are state-changing. Admin routes continue to use `includes/auth.php` and `require_admin()`.

Products are public only when `products.status = active`. Active products should have an active `contract_templates` assignment because `purchase.php` blocks checkout when a contract is missing or inactive. Orders snapshot product and contract metadata at creation time. Contract instances store the original template body snapshot and the rendered contract snapshot.

Public signing links use a random token generated with `random_bytes()`. Only `hash('sha256', $token)` is stored. Admins cannot recover old raw tokens; they can generate a replacement signing link before signing, which updates the hash and displays the raw URL once for copying.

## Phase 2.1 Shopify Revamp Development Notes

Phase 2.1 makes Shopify Revamp the first focused product flow. Admin product edit supports screenshots/product images, and products support demo URL/password fields. `purchase.php` shows Shopify-specific intake fields for `shopify_makeover` products and warns customers not to enter Shopify admin passwords. Orders store intake JSON, a readable intake summary, and customer IP/user-agent data. Signing remains token-based, records consent timestamps and a signed-contract hash, and moves orders to `payment_pending` after signing. Diesel Designs sends payment instructions or an invoice manually; optional order notification is environment-configured through `ADMIN_ORDER_EMAIL` or `ORDER_NOTIFY_EMAIL`.
