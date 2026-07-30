# Security Documentation

## Authentication

Implemented: Admin authentication is session-based. `admin/login.php` checks an email/password against `admin_users` and uses `password_verify()`.

## Authorization

Implemented: Protected admin pages call `require_admin()`, which redirects unauthenticated users to `/admin/login.php`.

Known limitation: There is no role-based authorization. Any authenticated admin has full admin access.

## Sessions

Implemented:

- Sessions are started as needed.
- Successful login calls `session_regenerate_id(true)`.
- Logout clears the session array and destroys the session.

Known limitation: The code does not currently set custom secure cookie options such as `Secure`, `HttpOnly`, or `SameSite` in application code. These should be configured at the PHP/server level or added in a future hardening pass.

## CSRF

Implemented: Admin forms render a CSRF hidden field and POST handlers call `verify_csrf()` before write actions.

Covered areas include:

- Admin setup.
- Admin login.
- Project create/edit/delete.
- Project list quick publish/unpublish.
- Image upload/delete.
- Public purchase and signing forms.
- Admin product create/edit/archive actions.
- Admin contract template create/edit actions.
- Admin order status updates.
- Admin mark sent, void, and replacement-link actions.

## Password Hashing

Implemented: Passwords are created with `password_hash()` and verified with `password_verify()`.

## Prepared Statements

Implemented: Application database reads/writes use PDO prepared statements for user-controlled parameters.

## Output Escaping

Implemented: The shared `e()` helper uses `htmlspecialchars()` with `ENT_QUOTES` and UTF-8. Public/admin views use this helper for user-controlled output.


## Live URL Validation

Implemented: Optional live website links must be blank or valid URLs with an `http` or `https` scheme. Non-web schemes such as `javascript:` and `ftp://` are rejected by admin create/edit validation.

## Phase 2.2 Product Intake and Live Examples

Implemented in code (database-backed browser security testing remains pending):

- Product create/edit and live-example actions remain behind admin authentication and existing CSRF verification.
- Submitted intake types use the strict four-value application allowlist.
- Titles are required and limited to 255 characters; URLs are limited to 500 characters and must be valid `http` or `https` URLs.
- Prepared statements are used. Updates and deletes are scoped by example ID and product ID to prevent cross-product manipulation.
- Save/delete POST actions check that `product_live_examples` exists and show a migration-required error without querying the missing table.
- Admin and public titles/URLs are escaped. Public links use `target="_blank"` with `rel="noopener noreferrer"`.
- The public Live Examples section is suppressed when no examples exist.

## Upload Security

Implemented:

- Maximum upload size is 10MB.
- Allowed extensions are JPG, JPEG, PNG, and WEBP.
- MIME type is checked with `finfo`.
- Random filenames are generated with `random_bytes()`.
- Files are stored under `uploads/portfolio/`.

Known limitation: Image dimensions are not currently checked or resized. Virus scanning is not implemented.

## Safe Image Deletion

Implemented: `delete_portfolio_file()` only deletes files whose relative paths start with `uploads/portfolio/` and whose real paths resolve inside that directory.

## Environment Variables / Database Credentials

Implemented: `includes/db.php` reads `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`, with local defaults.

Known limitation: There is no `.env` loader in this project. Production credentials should be set by the server/hosting environment.

## Admin Route Protection

Implemented: dashboard, project, request, and FAQ admin pages call `require_admin()`.

Known limitation: `admin/logout.php` does not call `require_admin()`; it is safe to call because it only clears any existing session and redirects to login.

## Public Content Boundary

Implemented: Public project listing/detail queries require `status = 'published'`.

## Known Security Limitations

- No role-based admin permissions.
- No login rate limiting or lockout.
- No audit log.
- No custom session cookie security settings in code.
- No Content Security Policy headers in code.
- No server-level nginx upload hardening is included; only Apache `.htaccess` is present.
- No automated security scanner or dependency scanner is configured.

## Website Request Security

Implemented:

- Public request submissions use CSRF protection through the existing CSRF helper.
- Request submissions validate required fields, email format, allowed select values, and optional current website URL as `http` or `https` only.
- A hidden honeypot field (`website_url_confirm`) treats likely bot submissions as generic success without saving the request.
- Requests are stored in `website_requests` and are not exposed publicly.
- Admin notes are stored separately and shown only on authenticated admin pages.
- Request management pages call `require_admin()`.

Not implemented:

- Website request submissions do not send email notifications.
- Phase 2.1 order notifications are optional and use PHP `mail()` only when `ADMIN_ORDER_EMAIL` or `ORDER_NOTIFY_EMAIL` is configured; order creation must not depend on mail delivery.
- No CAPTCHA or paid spam-protection service is used.

## Phase 2 Storefront and Contract Security

- Public purchase and signing forms use CSRF tokens; the purchase form also uses the existing honeypot pattern.
- Signing links use random tokens generated with `random_bytes()`. The database stores only a SHA-256 hash, so raw tokens cannot be recovered or displayed later.
- Admin replacement-link generation updates `contract_instances.token_hash` and displays the new raw URL only in the immediate response. It is not stored in the database or session.
- Token routes resolve only the single matching contract instance and do not expose public order or contract listings.
- Contract snapshots are stored on `contract_instances` so future template edits do not change past order contracts.
- Signed contract copies are printable/downloadable HTML only after signing. Unsigned contracts cannot be downloaded as signed copies.
- Admin product, template, order, and contract-copy routes require existing admin authentication.
- Contract `signed` status is set by the public signing flow with legal name, typed signature, timestamp, IP address, and user-agent audit fields; admin status controls do not fake signature data.

### Contract lifecycle hardening

- Voided contracts cannot be signed, cannot generate replacement signing links, and cannot be downloaded as signed contract copies.
- Signed contracts cannot generate replacement signing links and cannot be voided from the simple order view controls.
- Regenerating a signing link updates the stored token hash, so old raw links become invalid immediately.
- `signed` contract status is produced only by the public signing flow after the signer completes required signature fields and confirmations.
- Signed contract HTML downloads are available only when the contract instance status is `signed`.

## Phase 2.1 Shopify Revamp security notes
Product screenshots reuse the hardened `uploads/portfolio/` path and are validated by extension, MIME type, maximum 10MB size, randomized filenames, and constrained deletion. The Shopify Revamp intake form explicitly tells customers not to enter Shopify admin passwords. Signing remains token-hash based and noindexed; signed copies display legal name, typed signature, signed timestamp, IP address, user agent, terms/e-sign consent timestamps, and a SHA-256 hash of stable signing evidence. Payment remains manual only.

## Phase 2.1 corrective hardening
Order creation now wraps order and contract-instance writes in an explicit transaction with rollback on failure and a generic public error message. Admin signed-contract copies distinguish pending, voided, signed, and unexpected contract states, and downloads are still emitted only for signed contracts.

### Phase 2.3 questionnaires
See [`docs/PHASE_2_3_QUESTIONNAIRES.md`](PHASE_2_3_QUESTIONNAIRES.md) for the reusable builder, schema, field inventory, snapshot/upload security, assignment rules, deployment, rollback, and pending live tests.
