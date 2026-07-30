# Routes

The app uses direct PHP files instead of a front controller.

## Public Routes

| Method | URL / Path | File | Auth Required | Description |
| --- | --- | --- | --- | --- |
| GET | `/` | Web server default to `index.php` if configured | No | Homepage when the server maps directory index to `index.php`. |
| GET | `/index.php` | `index.php` | No | Homepage with hero, featured projects, services, and contact CTA. |
| GET | `/websites.php` | `websites.php` | No | Lists published Website Build projects. |
| GET | `/shopify-makeovers.php` | `shopify-makeovers.php` | No | Lists published Shopify Make-Over projects. |
| GET | `/project.php?slug=...` | `project.php` | No | Displays one published project by slug; unpublished or missing projects show a friendly 404 message. |

## Admin Routes

| Method | URL / Path | File | Auth Required | Description |
| --- | --- | --- | --- | --- |
| GET | `/admin/setup.php` | `admin/setup.php` | No | Shows first-admin setup or setup-complete message. |
| POST | `/admin/setup.php` | `admin/setup.php` | No | Creates first admin only if zero admins exist; CSRF protected. |
| GET | `/admin/login.php` | `admin/login.php` | No | Shows admin login form. |
| POST | `/admin/login.php` | `admin/login.php` | No | Authenticates admin credentials; CSRF protected. |
| GET | `/admin/logout.php` | `admin/logout.php` | Admin session expected | Destroys session and redirects to login. |
| GET | `/admin/dashboard.php` | `admin/dashboard.php` | Yes | Shows project counts, recent projects, and admin shortcuts. |
| GET | `/admin/projects.php` | `admin/projects.php` | Yes | Lists projects with filters and actions. |
| POST | `/admin/projects.php` | `admin/projects.php` | Yes | Quick publish/unpublish action; CSRF protected. |
| GET | `/admin/projects-create.php` | `admin/projects-create.php` | Yes | Shows add-project form. |
| POST | `/admin/projects-create.php` | `admin/projects-create.php` | Yes | Creates a project and optional thumbnail; CSRF protected. |
| GET | `/admin/projects-edit.php?id=...` | `admin/projects-edit.php` | Yes | Shows edit form for one project. |
| POST | `/admin/projects-edit.php?id=...` | `admin/projects-edit.php` | Yes | Updates or deletes a project; CSRF protected. |
| GET | `/admin/projects-images.php?id=...` | `admin/projects-images.php` | Yes | Shows image manager for one project. |
| POST | `/admin/projects-images.php?id=...` | `admin/projects-images.php` | Yes | Uploads or deletes images/pairs; CSRF protected. |

## Asset and Upload Paths

| Method | URL / Path | File/Directory | Auth Required | Description |
| --- | --- | --- | --- | --- |
| GET | `/assets/css/style.css` | `assets/css/style.css` | No | Site/admin stylesheet. |
| GET | `/assets/js/portfolio.js` | `assets/js/portfolio.js` | No | Public lightbox JavaScript. |
| GET | `/uploads/portfolio/...` | `uploads/portfolio/` | No | Uploaded public portfolio images. |

## Service Expansion Routes

| Method | URL / Path | File | Auth Required | Description |
| --- | --- | --- | --- | --- |
| GET | `/services.php` | `services.php` | No | Website build, revamp, Shopify make-over, and scope information. |
| GET | `/portfolio.php` | `portfolio.php` | No | Combined published portfolio work. |
| GET | `/faq.php` | `faq.php` | No | Published FAQs and FAQ schema. |
| GET | `/request-website.php` | `request-website.php` | No | Website request form. |
| POST | `/request-website.php` | `request-website.php` | No | Validates and stores website requests; CSRF and honeypot protected. |
| GET | `/admin/requests.php` | `admin/requests.php` | Yes | Lists website requests with status filters. |
| GET/POST | `/admin/request-view.php?id=...` | `admin/request-view.php` | Yes | Views, updates, archives, marks contacted, or deletes a request. |
| GET/POST | `/admin/faqs.php` | `admin/faqs.php` | Yes | Lists FAQs, toggles status, and deletes FAQs. |
| GET/POST | `/admin/faqs-create.php` | `admin/faqs-create.php` | Yes | Creates FAQs. |
| GET/POST | `/admin/faqs-edit.php?id=...` | `admin/faqs-edit.php` | Yes | Edits or deletes FAQs. |

## Phase 2 Public Routes

| Method | Route | Auth | Purpose |
| --- | --- | --- | --- |
| GET | `/store.php` | No | Lists active purchasable products/services. |
| GET | `/product-service.php?slug=...` | No | Shows an active product/service detail page with service schema. |
| GET/POST | `/purchase.php?product=...` | No | Validates customer details, creates an order, snapshots product/template details, and generates a contract instance. |
| GET/POST | `/sign-contract.php?token=...` | Token | Shows and signs one contract instance by secure token. |
| GET | `/contract-copy.php?token=...` | Token | Shows a pending state or printable signed contract copy. |
| GET | `/contract-copy.php?token=...&download=1` | Token | Downloads signed contract HTML only after signature. |

## Phase 2 Admin Routes

| Method | Route | Auth | Purpose |
| --- | --- | --- | --- |
| GET/POST | `/admin/products.php` | Admin | Lists products and archives products. |
| GET/POST | `/admin/products-create.php` | Admin | Creates products with server-side validation. |
| GET/POST | `/admin/products-edit.php?id=...` | Admin | Edits products with server-side validation. |
| GET | `/admin/contract-templates.php` | Admin | Lists contract templates. |
| GET/POST | `/admin/contract-templates-create.php` | Admin | Creates contract templates with server-side validation. |
| GET/POST | `/admin/contract-templates-edit.php?id=...` | Admin | Edits contract templates with server-side validation. |
| GET | `/admin/contract-template-preview.php?id=...` | Admin | Renders a template preview with sample placeholder data. |
| GET | `/admin/orders.php` | Admin | Lists customer orders and contract/payment statuses. |
| GET/POST | `/admin/order-view.php?id=...` | Admin | Views order details, updates manual statuses, marks sent, voids unsigned contracts, and generates replacement signing links. |
| GET | `/admin/contract-copy.php?id=...` | Admin | Views printable signed contract copy. |
| GET | `/admin/contract-copy.php?id=...&download=1` | Admin | Downloads signed contract HTML only after signature. |

## Phase 2.1 Shopify Revamp flow routes
- `admin/products-create.php` and `admin/products-edit.php`: simplified product setup with demo URL/password and product image management on edit.
- `product-service.php`: public product detail page with screenshots, demo link, optional demo password, and Order Now CTA.
- `purchase.php`: Shopify Revamp intake form for `shopify_makeover` products plus the existing generic fallback for other service types.
- `sign-contract.php`, `contract-copy.php`, and `admin/contract-copy.php`: token contract signing and signed-copy audit display. Payment remains manual; no payment gateway route exists.

## Phase 2.2 product intake metadata and live examples

- `admin/products-create.php`: saves an allowlisted product intake type; live examples become available after the base product has an ID.
- `admin/products-edit.php?id=...`: updates the allowlisted intake type and adds, edits, changes display order, or deletes product-owned live example links with admin authentication and CSRF protection. No separate live-example handler route was added.
- `shopify-makeovers.php`: displays active `shopify_makeover` and `website_kit` products so premade Shopify Revamps and Custom Shopify Themes appear in the same Shopify service category.
- `websites.php`: displays active `website_build` and `website_revamp` products; Custom Shopify Themes are excluded from this page.
- `product-service.php?slug=...`: preserves screenshots and the purchase CTA. `shopify_revamp_standard` products show the single demo URL/password, while `shopify_custom_kit` and `website_custom_build` products conditionally show ordered Live Examples.
- `purchase.php?product=...`: unchanged in Phase 2.2. `intake_type` is stored for future routing but does not alter visible forms; standard Shopify Revamp continues to use `service_type = shopify_makeover`.

The reusable Questionnaire Builder and complete Custom Shopify Theme intake are Phase 2.3 work. Custom Website Build intake is Phase 2.4 work.
