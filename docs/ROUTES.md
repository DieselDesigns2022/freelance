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
