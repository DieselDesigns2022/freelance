# Website Builds & Revamps by Diesel Designs

## Purpose

Diesel Designs Portfolio is a PHP 8 + MySQL/MariaDB service and portfolio website for Diesel Designs at dieseldesigns.co, focused on website builds, website revamps, Shopify make-overs, and visual website refreshes.

The application has:

- Public portfolio pages for website builds and Shopify make-overs.
- Public project detail pages with galleries, live links, and Shopify before/after sections.
- A private session-based admin panel for creating, editing, publishing, unpublishing, deleting, and managing project images.

## Current Development Status

**Implemented:** Initial portfolio build committed as `a0e42be - Build PHP portfolio website and admin panel`.

**Workflow note:** A PR record was created too early during the initial implementation pass. It must not be merged, updated, closed, or otherwise acted on unless the user explicitly approves PR activity later in the workflow.

**Review status:** Initial implementation and bootstrap documentation have been committed. The project still requires individual changed-file review, runtime setup, database import, and live testing before approval or deployment.

## Technology Stack

- PHP 8+
- MySQL/MariaDB
- PDO prepared statements
- Session-based admin authentication
- `password_hash` / `password_verify`
- Vanilla CSS
- Vanilla JavaScript
- Apache-compatible upload hardening via `uploads/portfolio/.htaccess`

## Quick Start

1. Create a MySQL/MariaDB database.
2. Import the schema:

   ```bash
   mysql -u YOUR_USER -p YOUR_DATABASE < database/portfolio_schema.sql
   ```

3. Configure database credentials with environment variables if defaults are not appropriate:
   - `DB_HOST` defaults to `127.0.0.1`
   - `DB_NAME` defaults to `diesel_portfolio`
   - `DB_USER` defaults to `root`
   - `DB_PASS` defaults to an empty string
4. Ensure `uploads/portfolio/` is writable by the web server.
5. Visit `/admin/setup.php` once to create the first admin user.
6. Log in at `/admin/login.php`.
7. Add projects, upload images, and publish projects from the admin panel.

## Repository Structure

| Path | Purpose |
| --- | --- |
| `index.php` | Public homepage with hero, featured projects, service cards, and contact CTA. |
| `websites.php` | Public website-build listing page. |
| `shopify-makeovers.php` | Public Shopify make-over listing page. |
| `project.php` | Public project detail page for both project sections. |
| `admin/` | Private admin setup, login, dashboard, CRUD, and image management pages. |
| `admin/includes/` | Admin header/footer layout includes. |
| `includes/` | Shared DB, auth, CSRF, helpers, and public layout includes. |
| `assets/css/` | Public/admin CSS and placeholder SVG. |
| `assets/js/` | Vanilla JavaScript lightbox behavior. |
| `database/` | SQL schema. |
| `uploads/portfolio/` | Public upload destination for portfolio images. |
| `docs/` | Detailed project documentation. |

## Current Implementation Status

### Implemented

- Public homepage.
- Website Builds listing page.
- Shopify Make-Overs listing page.
- Project detail page with gallery and Shopify before/after support.
- Public image lightbox with Escape key close and previous/next navigation.
- Contact CTA using `mailto:`.
- Admin setup for first admin user.
- Admin login/logout.
- Admin dashboard.
- Admin project list, filters, create, edit, publish/unpublish, and delete.
- Admin image manager for gallery, before, after, and paired before/after images.
- SQL schema for all current tables.
- Basic upload hardening and safe deletion guard.

### Not Implemented

- General contact/website request email sending is not implemented. Website requests are stored in admin without email notification. Phase 2.1 order notifications are optional and use PHP `mail()` only when `ADMIN_ORDER_EMAIL` or `ORDER_NOTIFY_EMAIL` is configured.
- Pretty URL routing.
- Canonical tags.
- Open Graph metadata.
- Sitemap generation.
- Robots.txt.
- General portfolio/project structured data. FAQPage JSON-LD is implemented for visible published FAQs.
- Automated browser/end-to-end test suite.

## Project Principles

- Keep the application simple and framework-free unless a future phase explicitly changes that direction.
- Match documentation to actual code, not planned ideas.
- Protect admin pages and write actions.
- Prefer readable PHP, prepared statements, escaped output, and small helper functions.
- Do not create PRs, merge, or deploy without explicit user approval.
- Update documentation whenever behavior, routes, database schema, deployment, or workflow changes.

## Documentation Index

- [Changelog](CHANGELOG.md)
- [Contributing Guide](CONTRIBUTING.md)
- [Developer Guide](DEVELOPMENT.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Routes](docs/ROUTES.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Testing](docs/TESTING.md)
- [Security](docs/SECURITY.md)
- [SEO](docs/SEO.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Phase History](docs/PHASE_HISTORY.md)
- [Codex Workflow](docs/CODEX_WORKFLOW.md)

## Service Website Expansion

Diesel Designs now functions as both a service website and a portfolio. Visitors can learn about website builds, website revamps, Shopify make-overs, graphics/color customization, view portfolio work, read FAQs, and submit a website request.

New public pages:

- `/services.php` — website build, revamp, Shopify make-over, and scope information.
- `/portfolio.php` — combined published portfolio work.
- `/faq.php` — published FAQs with FAQ schema when FAQs exist.
- `/request-website.php` — database-backed website build/revamp request form with honeypot spam protection.

New admin pages:

- `/admin/requests.php` and `/admin/request-view.php?id=...` — request review, status updates, admin notes, contact marking, archive, and delete.
- `/admin/faqs.php`, `/admin/faqs-create.php`, and `/admin/faqs-edit.php?id=...` — FAQ management.

General contact/website request email sending, payment processing, and paid third-party services are not implemented. Requests are stored in the admin panel only. Phase 2.1 order notifications are optional and use PHP `mail()` only when configured through `ADMIN_ORDER_EMAIL` or `ORDER_NOTIFY_EMAIL`.

## Phase 2 Storefront and Contract System

Phase 2 adds a store foundation for selling Diesel Designs website kits, website builds, Shopify make-overs, revamps, and custom service packages. Public visitors can browse `/store.php`, view active product/service detail pages, and start an order with customer/project details.

Each purchasable product must be assigned an active contract template before public ordering is allowed. The order flow snapshots product details and renders a contract instance so later edits to templates do not change previously generated or signed contracts. Customers sign through secure token links and can view or download a printable HTML contract copy after signing.

Admins can manage products, contract templates, and orders from the admin area. Payment handling is manual only in this phase through payment status fields; no live payment gateway, card capture, Stripe, PayPal, Square, WooCommerce, or Shopify checkout integration is included.

### Phase 2.2 Product Intake Types and Live Examples

Products store an allowlisted intake type: `shopify_revamp_standard`, `shopify_custom_kit`, `website_custom_build`, or `general_service`. Products default to `general_service`; the field is a `VARCHAR`, not a database enum. Product details can show multiple live examples ordered by display order and ID while retaining screenshots, the single demo URL, and optional demo password.

Phase 2.2 does not change visible purchase forms: Standard Shopify Revamp still uses the existing `shopify_makeover` service-type flow. The reusable Questionnaire Builder and complete Custom Shopify Theme intake are Phase 2.3 work, and Custom Website Build intake is Phase 2.4 work. Existing deployments require the Phase 2.2 migration. Because the repository has no confirmed production identifier for the standard Shopify Revamp, verify the production product and classify it using one exact ID or verified slug after migration; see `docs/DEPLOYMENT.md`.

### Phase 2.3 questionnaires
See [`docs/PHASE_2_3_QUESTIONNAIRES.md`](docs/PHASE_2_3_QUESTIONNAIRES.md) for the reusable builder, schema, field inventory, snapshot/upload security, assignment rules, deployment, rollback, and pending live tests.
