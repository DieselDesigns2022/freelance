# Testing Guide

## Public Testing

Verify:

- Homepage loads at `/index.php` and, if configured by the server, `/`.
- Website Builds page loads at `/websites.php`.
- Shopify Make-Overs page loads at `/shopify-makeovers.php`.
- Published project detail page loads at `/project.php?slug=...`.
- Draft/unpublished project is blocked publicly and shows the friendly 404 message.
- Live website links open correctly in a new tab/window.
- Live URL validation allows blank values, `http://` URLs, and `https://` URLs.
- Live URL validation rejects `javascript:`, `ftp://`, and malformed URLs.

## Admin Testing

Verify:

- `/admin/setup.php` creates a first admin only when zero admins exist.
- `/admin/setup.php` shows setup complete after an admin exists.
- Login works at `/admin/login.php`.
- Logout works at `/admin/logout.php`.
- Dashboard loads for logged-in admins.
- Project list filters work.
- Create project works for Website Build.
- Create project works for Shopify Make-Over.
- Edit project works.
- Publish/draft status works.
- Delete project works after confirmation.
- Non-logged-in access to protected admin pages redirects to login.

## Database Testing

Verify:

- `database/portfolio_schema.sql` imports successfully.
- All six current tables exist: `admin_users`, `portfolio_projects`, `portfolio_project_images`, `portfolio_before_after_pairs`, `website_requests`, and `faqs`.
- Project slugs are unique.
- Project images connect to the correct project through `project_id`.
- Deleting a project cascades related image and before/after pair rows.

## Upload Testing

Verify:

- Thumbnail upload works.
- Gallery upload works.
- Before image upload works.
- After image upload works.
- Paired before/after upload works.
- JPG/JPEG, PNG, and WEBP are accepted.
- Unsafe file types are rejected.
- Files larger than 10MB are rejected.
- Image delete removes the database row and physical file when safe.

## Image Lightbox Testing

Verify:

- Clicking a public project image opens the lightbox.
- Close button closes the lightbox.
- Clicking outside the image closes the lightbox.
- Escape key closes the lightbox.
- Previous/next buttons work when multiple images are present.
- Left/right arrow keys work when the lightbox is open.

## Shopify Before/After Testing

Verify:

- Shopify Make-Over detail pages show paired before/after sets when uploaded.
- Separate Before Photos and After Photos groups appear when uploaded.
- Before/after layouts are side by side on desktop and stacked on mobile.
- Empty before/after states are friendly when no before/after images exist.

## Security Testing

Verify:

- Admin pages require login.
- Admin POST forms reject missing/invalid CSRF tokens.
- Login rejects invalid credentials.
- Passwords are stored as hashes, not plain text.
- Public pages do not show draft projects.
- Uploaded PHP/scripts are rejected by upload validation.
- Deletion does not allow path traversal outside `uploads/portfolio/`.

## Mobile Testing

Verify on narrow viewport/mobile device:

- Navigation wraps without hiding links.
- Portfolio grids become one column.
- Before/after layouts stack.
- Admin forms remain usable.
- Lightbox fits within the viewport.

## Empty State Testing

Verify:

- Homepage shows a friendly message when no featured projects exist.
- Website Builds page shows “Website build portfolio coming soon.” when no published website projects exist.
- Shopify Make-Overs page shows “Shopify make-over portfolio coming soon.” when no published Shopify projects exist.
- Image manager shows “No images uploaded for this project yet.” when appropriate.
- Project gallery shows a friendly empty state when no gallery images exist.

## Regression Testing

After changes, re-test:

- Public routes.
- Admin auth.
- Project create/edit/delete.
- Image upload/delete.
- Published/draft visibility.
- Documentation accuracy for changed behavior.

## Smoke Testing

Minimum smoke test after setup/deployment:

1. Import schema.
2. Create first admin.
3. Log in.
4. Create one Website Build as draft.
5. Confirm draft is not public.
6. Publish it.
7. Confirm it appears on `/websites.php`.
8. Upload a gallery image.
9. Confirm image appears on project detail page and opens in lightbox.
10. Log out.

## Services, Requests, and FAQ Testing

Verify:

- Homepage loads with services, featured work, why-work section, FAQ preview, and request CTA.
- Services page loads.
- Portfolio page loads.
- FAQ page loads and shows only published FAQs.
- FAQ schema output includes only visible published FAQs.
- Request page loads.
- Request form validates required fields.
- Request form rejects bad email.
- Request form rejects non-http/https current website URL.
- Request form saves a valid request with status `new`.
- Honeypot submission shows generic success behavior without creating visible spam/error behavior.
- Admin requests page requires login.
- Admin can view request details.
- Admin can update request status.
- Admin can add private admin notes.
- Admin can mark a request contacted.
- Admin can archive or delete a request.
- Admin FAQ pages require login.
- Admin can create FAQ.
- Admin can edit FAQ.
- Admin can publish/draft FAQ.
