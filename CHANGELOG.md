# Changelog

## Step 4 Bootstrap Documentation Cleanup - 2026-07-05

### Major Features Added

- Added complete root and `docs/` documentation foundation.
- Documented architecture, routes, database tables, deployment, testing, security, SEO, troubleshooting, phase history, and Codex workflow.

### Major Fixes

- Documented the workflow issue that a PR record was created too early during the initial implementation pass.
- Added explicit development standards that PRs, merges, and deployments require user approval.

## Initial Portfolio Implementation - 2026-07-05

### Major Features Added

- Built public Diesel Designs portfolio pages for the homepage, Website Builds, Shopify Make-Overs, and project detail views.
- Added private admin setup, login/logout, dashboard, project CRUD, publish/unpublish, delete, and image management.
- Added gallery, before, after, and paired before/after image support.
- Added MySQL/MariaDB schema for admin users, projects, images, and before/after pairs.
- Added vanilla CSS styling and vanilla JavaScript lightbox behavior.

### Major Fixes

- None. This was the first implementation entry.

## Services, Requests, and FAQs Expansion - 2026-07-05

### Major Features Added

- Expanded the public site into Website Builds & Revamps by Diesel Designs.
- Added Services, Portfolio, FAQ, and Request a Website public pages.
- Added database-backed website request intake with honeypot spam handling.
- Added admin request management and FAQ management.
- Added FAQ schema JSON-LD for visible published FAQs.

### Major Fixes

- Documented that request submissions are stored for admin review and no email sending is implemented.

## Phase 2 — Storefront Purchases & Contract Signing System

- Added public store, product detail, purchase/order-start, contract signing, and signed contract copy routes.
- Added admin CRUD for products and contract templates plus order management, manual payment status controls, contract sent/viewed/signed tracking, token regeneration before signing, and signed-contract HTML downloads.
- Added additive database tables for products, contract templates, orders, and contract instances.
- Kept payment handling manual; no payment gateway or PDF dependency was added.

## Phase 2.1 - Shopify Revamp Product Flow
- Added additive database migration `database/migrations/20260710_phase_2_1_shopify_revamp_flow.sql` for product demo fields, product images, order intake metadata, customer IP/user agent, and contract signing audit hash/consent timestamps.
- Simplified admin product setup for current service-product flow and added Shopify Revamp demo/image support.
- Added Shopify Revamp-specific public intake questions with a clear warning not to enter Shopify admin passwords.
- Contract signing remains token based and manual-payment only; no live payment gateway, PDF generation, or initials support was added.
- Optional order notification uses PHP `mail()` only when `ADMIN_ORDER_EMAIL` or `ORDER_NOTIFY_EMAIL` is configured.
