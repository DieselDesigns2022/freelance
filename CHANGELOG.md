# Changelog

## Phase 2.2 — Product Intake Types + Live Example Links

- Added allowlisted product intake classifications to product create/edit workflows.
- Added ordered product live examples with authenticated, CSRF-protected admin management and conditional public display.
- Preserved product screenshots, the legacy demo URL/password, standard Shopify Revamp intake, contract signing, and manual payment behavior.
- Added the additive `20260729_phase_2_2_product_intake_live_examples.sql` migration; it does not classify the production Shopify Revamp automatically, so verification and an exact ID or verified-slug update remain operational steps.
- The reusable Questionnaire Builder and complete Custom Shopify Theme intake are planned for Phase 2.3; Custom Website Build intake is planned for Phase 2.4.
- Live testing refined product display rules: premade Shopify Revamps use one demo URL/password, while custom Shopify kits and custom website builds use multiple live examples.
- Reorganized the admin product form into Product Setup, Product Information, Live Demo, Live Examples, and Product Images sections for easier navigation.
- Updated customer-facing service and intake labels to use the accurate Custom Shopify Theme terminology.
- Routed Custom Shopify Theme products to Shopify Make-Overs instead of Website Builds.
- Applied and verified the Phase 2.2 production migration on MariaDB 10.11, then classified the verified premade Shopify Revamp product as `shopify_revamp_standard`.

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

## Phase 2.3 — Questionnaire Builder
- Added reusable questionnaire administration, dynamic product intake, immutable order snapshots/answers, field-associated protected uploads, and seeded Standard Shopify Revamp and draft Custom Shopify Theme questionnaires.
- Added the compact single-column field-card builder with inline editing, add-at-position controls, drag-and-drop ordering, Move Up/Move Down fallbacks, selected-question imports, full-questionnaire draft copies, preview, and collision-safe generated keys.
- Clarified the administrator labels for stored `file` and `multiple_files` types as **File Upload** and **Multiple File Uploads** without changing upload behavior or storage.
- Added the stored `addon` field type for flat-fee, per-additional-item, and quantity-priced manual-invoice upgrades. Admin dollar input is converted to integer cents, and server calculations—not informational browser totals—are authoritative.
- Added immutable per-answer add-on pricing snapshots, aggregate questionnaire add-on totals, and the Admin Order **Manual Invoice Add-Ons** display. Add-ons do not collect payment or change product, Stripe, contract, or manual-payment behavior.
- Added the additive, rerunnable `database/migrations/20260730_phase_2_3_questionnaire_addons.sql`; live migration and browser/order regression testing remain pending.
