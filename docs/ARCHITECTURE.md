# Architecture

## Architectural Goals

- Keep the portfolio easy to deploy on a basic PHP + MySQL/MariaDB host.
- Avoid heavy frameworks and build tools.
- Keep routes obvious by mapping pages directly to PHP files.
- Separate shared helpers from page-specific rendering.
- Protect admin actions while keeping the admin experience simple for a non-developer.

## Design Philosophy

The site is designed as a service + portfolio website. Service pages explain website builds, website revamps, Shopify make-overs, and visual refreshes; portfolio pages show completed work; FAQ pages answer common scope/process questions; and request intake stores leads for private admin review. The admin panel remains practical: projects, images, requests, FAQs, statuses, and private notes.

## Simple PHP Structure

| Area | Files |
| --- | --- |
| Public pages | `index.php`, `services.php`, `portfolio.php`, `websites.php`, `shopify-makeovers.php`, `project.php`, `faq.php`, `request-website.php` |
| Admin pages | `admin/*.php` |
| Admin layout | `admin/includes/*.php` |
| Shared helpers | `includes/*.php` |
| Styling/JS | `assets/css/style.css`, `assets/js/portfolio.js` |
| Database setup | `database/portfolio_schema.sql` |
| Uploads | `uploads/portfolio/` |

## Routing / Page Structure

Implemented routing is file-based. There is no front controller and no pretty URL router.

Examples:

- `/index.php` loads the service-focused homepage.
- `/services.php` explains services and scope.
- `/portfolio.php` loads all published portfolio work.
- `/websites.php` loads published Website Builds.
- `/faq.php` loads published FAQs.
- `/request-website.php` stores validated website requests.
- `/project.php?slug=project-slug` loads one published project by slug.
- `/admin/projects-edit.php?id=123` loads the edit screen for one project.
- `/admin/requests.php` and `/admin/faqs.php` manage requests and FAQs.

## Public Page Flow

1. Public page includes database and helper files.
2. Page queries only published content where appropriate.
3. Page sets `$pageTitle` and `$metaDescription`.
4. Page includes `includes/header.php`.
5. Page renders content and empty states.
6. Page includes `includes/footer.php` and JavaScript.

## Admin Page Flow

1. Admin page includes auth and CSRF helpers.
2. Protected pages call `require_admin()`.
3. POST handlers call `verify_csrf()`.
4. Input is validated locally in the page.
5. Database writes use prepared statements.
6. Success/error feedback uses flash messages.
7. The user is redirected after writes where implemented.

## Includes / Helpers

- `includes/db.php`: PDO connection.
- `includes/functions.php`: shared utility functions.
- `includes/auth.php`: admin authentication/session helpers.
- `includes/csrf.php`: CSRF token helpers.
- `includes/header.php`: public document head and navigation.
- `includes/footer.php`: public contact CTA and footer.

## Database Access

Database access is centralized through `db()`, which returns a singleton PDO connection. Credentials come from environment variables with local defaults.

## Storage / Uploads

Uploads are stored in `uploads/portfolio/`. The database stores relative paths. Files are saved with randomized names to avoid collisions and avoid trusting user-provided filenames.

## Security Boundaries

- Public pages should only display projects with `status = 'published'`.
- Admin pages are protected by session authentication except setup/login/logout.
- Admin forms use CSRF protection.
- Uploaded images are validated by extension, MIME type, and size.
- File deletion is restricted to paths inside `uploads/portfolio/`.

## Why the System Is Designed This Way

This is a small portfolio application meant to run on common shared/VPS PHP hosting. Direct PHP pages, PDO, vanilla CSS, and vanilla JavaScript keep the project understandable and easy to maintain without framework-specific deployment requirements.

## Service Expansion Architecture

The application now includes lead-generation routes in addition to portfolio routes. `request-website.php` writes validated request records into `website_requests`; admin request pages read and update those records behind `require_admin()`. `faq.php` reads published rows from `faqs`, while admin FAQ pages manage FAQ records. This keeps public request intake separate from private admin review and keeps FAQ publishing controlled by status.

## Storefront, Orders, and Contracts

Phase 2 adds a storefront layer beside the existing portfolio/request system. `products` represent purchasable website kits, builds, Shopify make-overs, revamps, and custom services. Public store and product pages read only active products.

The purchase flow creates an `orders` record with customer details and product snapshots, then creates one `contract_instances` record from the product's assigned `contract_templates` row. The rendered contract snapshot is stored on the instance so template changes never alter old orders or signed contracts.

Contract signing is token-based. The public token route resolves only one contract instance by token hash and never lists orders or contracts. Admin order management controls order/payment statuses separately from contract statuses; signed status is produced by the public signing flow, not by an admin dropdown.

Phase 2.1 extends the storefront for the Shopify Revamp flow. Products can include demo URL/password fields, and `product_images` stores screenshots displayed on product detail pages. Shopify intake answers are stored in `orders.intake_answers_json`, with a readable summary in `project_notes`, and orders capture customer IP/user-agent data. Contract signing stores consent timestamps and a signed-contract hash. Payment remains manual with invoice/payment-instruction follow-up; no live gateway is implemented.
