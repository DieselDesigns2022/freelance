# Phase History

This file is permanent engineering history. It is more detailed than `CHANGELOG.md` and should preserve workflow lessons, implementation context, and review status.

## Initial Portfolio Build - 2026-07-05

### Planning

The requested application was a complete portfolio website for a digital designer. The app needed public portfolio pages and a private admin panel for managing completed website builds and Shopify theme make-overs.

### Objectives

- Build public pages for homepage, Website Builds, Shopify Make-Overs, and project details.
- Build private admin setup/login/dashboard/project/image management pages.
- Add MySQL/MariaDB schema.
- Add image upload handling.
- Add gallery and Shopify before/after support.
- Add responsive styling and vanilla JavaScript lightbox behavior.
- Add basic security protections appropriate for a simple PHP app.

### Features Implemented

- Homepage with hero, featured projects, services, and contact CTA.
- Website Builds public listing.
- Shopify Make-Overs public listing.
- Project detail page with live links, metadata, thumbnail, gallery, and Shopify before/after sections.
- Admin first-user setup.
- Admin login/logout.
- Admin dashboard counts and recent projects.
- Admin project list with filters and publish/unpublish action.
- Admin project create/edit/delete.
- Admin image manager for gallery, before, after, and paired before/after uploads.
- Vanilla JavaScript lightbox.
- Dark purple/teal responsive styling.

### Database Changes

Created `database/portfolio_schema.sql` with:

- `admin_users`
- `portfolio_projects`
- `portfolio_project_images`
- `portfolio_before_after_pairs`

### Infrastructure Changes

- Added `uploads/portfolio/` as the upload destination.
- Added Apache `.htaccess` hardening for the upload folder.
- Added environment-variable-driven PDO configuration.

### Security Changes

- Added password hashing and verification.
- Added session-based admin authentication.
- Added CSRF helpers and CSRF checks on admin forms.
- Added prepared statements.
- Added output escaping helper.
- Added image type/size validation.
- Added guarded image deletion.

### Bug Fixes

None recorded during the initial build. This was the first implementation pass.

### Testing

Recorded checks from the initial implementation:

- `php -l index.php`
- `php -l project.php`
- `php -l admin/projects-images.php`
- `php -l admin/projects-edit.php`
- Full PHP lint pass with `find . -name '*.php' -not -path './.git/*'` and `php -l`.

### Regression Testing

Manual regression testing still needs to happen after database import and admin setup, including public pages, admin CRUD, uploads, draft visibility, and lightbox behavior.

### Deployment Status

Not deployed.

### Commit

Initial implementation was committed as:

```text
a0e42be - Build PHP portfolio website and admin panel
```

### Workflow Issue

A PR record was created too early during the initial implementation pass. That PR record must not be merged, updated, closed, or acted on unless the user explicitly approves PR creation/review later in the workflow.

Known previously created PR information:

- Previous PR title: `Add Diesel Designs portfolio site and secure admin panel (PHP + MySQL)`
- Status in this documentation pass: previously created too early; do not act on it without approval.

### Lessons Learned

- PR creation must wait for explicit user approval.
- Bootstrap documentation should be complete before deeper file-by-file review.
- Summary review does not replace actual changed-file review.
- Documentation must clearly separate implemented, not implemented, future roadmap, and known limitation items.

### Current Project Status

Status at the time of this entry: the initial portfolio implementation and bootstrap documentation were committed, but individual changed-file review, runtime setup, database import, and live testing were still required before approval or deployment.

## Services, Requests, and FAQs Expansion - 2026-07-05

### Objectives

Expand the portfolio into a lead-generating service website for website builds, revamps, Shopify make-overs, and visual refreshes without removing portfolio or admin project management functionality.

### Features Implemented

- Added Services, Portfolio, FAQ, and Request a Website public pages.
- Updated homepage and navigation for service-focused messaging.
- Added database-backed website request intake with honeypot handling.
- Added admin request list/detail management with statuses and private notes.
- Added admin FAQ management and public FAQ output with FAQ schema.

### Database Changes

- Added `website_requests`.
- Added `faqs`.
- Added starter FAQ seed statements.

### Deployment Status

Not deployed. Updated schema must be imported before request and FAQ features are used.

## Phase 2 — Storefront Purchases & Contract Signing System

Objective: expand the site from a portfolio/request site into a basic storefront for service packages with per-order contract signing.

Implemented: public store/product/purchase/signing/copy routes; admin product CRUD; admin contract template CRUD and preview; admin order management; manual order/payment status controls; token regeneration before signing; contract status tracking; signed HTML contract downloads.

Database changes: added `products`, `contract_templates`, `orders`, and `contract_instances` through `database/migrations/20260710_phase_2_store_contract_system.sql` and updated the canonical schema.

Limitations: no live payment gateway, no PDF generation library, no public order listing, and raw signing tokens cannot be recovered after creation because only token hashes are stored. Deployment requires running the migration and creating/assigning active contract templates before products can be purchased publicly.

## Phase 2.1 - Shopify Revamp Product Flow
Phase 2.1 builds the first focused product/customer flow around Shopify Website Revamp services: admin product setup, screenshots, demo URL/password, Shopify-specific intake, contract placeholders, token signing audit fields, manual payment follow-up, and admin order review. It does not add Shopify admin password collection, account creation, payment gateways, PDFs, or initials support.
