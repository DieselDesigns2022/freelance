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

## Password Hashing

Implemented: Passwords are created with `password_hash()` and verified with `password_verify()`.

## Prepared Statements

Implemented: Application database reads/writes use PDO prepared statements for user-controlled parameters.

## Output Escaping

Implemented: The shared `e()` helper uses `htmlspecialchars()` with `ENT_QUOTES` and UTF-8. Public/admin views use this helper for user-controlled output.


## Live URL Validation

Implemented: Optional live website links must be blank or valid URLs with an `http` or `https` scheme. Non-web schemes such as `javascript:` and `ftp://` are rejected by admin create/edit validation.

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

- No email sending is performed.
- No CAPTCHA or paid spam-protection service is used.
