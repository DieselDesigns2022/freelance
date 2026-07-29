# Troubleshooting

## Database credentials not configured

**Symptom:** Public or admin pages fail with a database connection error.

**Root cause:** `DB_HOST`, `DB_NAME`, `DB_USER`, or `DB_PASS` are missing or incorrect, and defaults do not match the server.

**Solution:** Set the correct environment variables in the server/hosting environment.

**Lesson learned:** Confirm database credentials before testing application behavior.

## Database schema not imported

**Symptom:** Errors mention missing tables such as `portfolio_projects` or `admin_users`.

**Root cause:** `database/portfolio_schema.sql` has not been imported into the configured database.

**Solution:** Run:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/portfolio_schema.sql
```

**Lesson learned:** Schema import is required before first use.

## Upload folder not writable

**Symptom:** Image uploads fail with a save error.

**Root cause:** The web server cannot write to `uploads/portfolio/`.

**Solution:** Set ownership/permissions for the web server user.

**Lesson learned:** Upload paths need writable permissions after deployment.

## Admin setup says setup complete

**Symptom:** `/admin/setup.php` says “Setup is already complete.”

**Root cause:** At least one row already exists in `admin_users`.

**Solution:** Use `/admin/login.php`. If credentials are lost, recover through a controlled database/admin reset process after backing up data.

**Lesson learned:** Setup is intentionally one-time only.

## Uploaded images not appearing

**Symptom:** Image upload succeeds but images do not display publicly.

**Root cause:** Possible causes include incorrect file permissions, missing file, wrong relative path, unpublished project, or viewing a page section that does not display that image type.

**Solution:** Check the database image path, confirm the file exists under `uploads/portfolio/`, confirm web server read permissions, and confirm the project is published.

**Lesson learned:** Image display depends on database path, filesystem file, permissions, and project visibility.

## Live website links not working

**Symptom:** Live site button is missing or link fails.

**Root cause:** `live_url` may be empty or not a full valid URL.

**Solution:** Edit the project and enter a full URL including `https://`.

**Lesson learned:** The admin form expects a complete URL.

## Public pages showing empty states

**Symptom:** Website Builds or Shopify Make-Overs page says portfolio is coming soon.

**Root cause:** No published projects exist for that section, or projects are still drafts.

**Solution:** In admin, create projects and set status to Published.

**Lesson learned:** Public listings intentionally hide drafts.

## Lightbox not opening

**Symptom:** Clicking a project image does nothing.

**Root cause:** `assets/js/portfolio.js` may not be loading, or the image element may not have `data-lightbox-src`.

**Solution:** Confirm the JS file loads in browser dev tools and confirm project detail images render with lightbox data attributes.

**Lesson learned:** Lightbox is vanilla JavaScript and depends on the expected data attributes.

## Unpublished project not visible publicly

**Symptom:** `/project.php?slug=...` shows “Project not found” for a known project.

**Root cause:** The project status is `draft` or the slug is incorrect.

**Solution:** Publish the project or verify the slug in the admin edit page.

**Lesson learned:** Draft content is intentionally blocked from public access.

## Request form says submitted but no request appears

**Symptom:** A request submission shows success but no request appears in admin.

**Root cause:** The hidden honeypot field may have been filled by a bot or browser autofill.

**Solution:** Confirm the visible form fields are used and the hidden `website_url_confirm` field remains blank.

**Lesson learned:** Honeypot submissions intentionally do not save data but avoid showing spam-specific errors.

## FAQ page is empty

**Symptom:** `/faq.php` shows an empty state.

**Root cause:** No FAQs are published, or the schema seed statements have not been imported.

**Solution:** Import the updated schema or create/publish FAQs in the admin panel.

**Lesson learned:** Public FAQ output only uses published FAQ rows.


## Request form shows length validation errors

**Symptom:** The website request form asks you to shorten a field.

**Root cause:** A varchar-backed field is longer than the database column allows.

**Solution:** Shorten the named field and put longer details in the project description, inspiration links, or notes fields.

**Lesson learned:** Short contact/classification fields have database-sized limits, while project detail fields allow longer text.

## FAQ schema is missing

**Symptom:** The FAQ page loads but no FAQPage JSON-LD appears.

**Root cause:** There are no published FAQ rows visible on the FAQ page.

**Solution:** Publish at least one FAQ in the admin FAQ manager.

**Lesson learned:** FAQ schema is generated only for visible published FAQs.


## Missing table errors for FAQs or website requests

**Symptom:** The homepage, FAQ page, request page, or admin dashboard fails with a missing table error for `faqs` or `website_requests`.

**Root cause:** Updated service/request/FAQ code is running before the expanded schema was imported or applied.

**Solution:** Import or apply the updated schema, confirm all six current tables exist, then reload the page.

**Lesson learned:** Schema updates must be applied before loading or testing code that queries new tables.

## Phase 2 Troubleshooting

### Store or admin product pages fail with missing table errors
Run `database/migrations/20260710_phase_2_store_contract_system.sql` against the active MariaDB database.

### Shopify Revamp product flow fails with missing column/table errors
If product pages, product edit image management, purchase intake, signing, or signed contract copies fail with missing columns or tables such as `demo_url`, `demo_password`, `product_images`, `intake_answers_json`, `customer_ip`, `customer_user_agent`, `terms_agreed_at`, `esign_agreed_at`, or `signed_contract_hash`, the Phase 2.1 migration likely has not been run. Apply `database/migrations/20260710_phase_2_1_shopify_revamp_flow.sql` against the active MariaDB database.

### Store is empty
Confirm products exist with `status = active`. Draft and archived products are intentionally hidden.

### Product detail loads but purchase is blocked
The product must have an assigned contract template whose status is `active`. Create/activate the template, assign it to the product, and save the product as active.

### Signing link became invalid after regeneration
Generating a replacement signing link updates the stored token hash. Older raw links stop working by design. Copy and send the replacement URL shown immediately after generation.

### Signed contract download is unavailable
HTML download is allowed only after `contract_instances.status = signed`. Unsigned or void contracts show a pending/unavailable state.

### Contract is voided

Voided contracts intentionally cannot be signed, downloaded as signed copies, or used to generate replacement signing links. If the customer still needs to sign after a contract has been voided, create a new order/contract instance. If the contract has not been voided yet and the link was lost, generate a replacement link before voiding.

### Replacement signing link controls are hidden

Replacement links are available only for pending, sent, or viewed contracts. Signed and void contracts block regeneration by design.

### Signed copy download is blocked

HTML signed-contract downloads are only available after the signing flow sets the contract instance status to `signed`.

## Phase 2.2 Troubleshooting

### Intake column or live-example table is unavailable

An `Unknown column 'intake_type'` error, a missing `product_live_examples` error, or the admin message “Run the Phase 2.2 database migration before managing live examples.” usually means `database/migrations/20260729_phase_2_2_product_intake_live_examples.sql` has not been applied. Confirm migration status before assuming an application-code failure. The migration has not yet been applied in this Phase 2.2 workflow.

Before approved execution, confirm `products.id` is signed `INT`; `product_live_examples.product_id` must also be signed `INT` for the cascading foreign key. After backup and migration, verify with `SHOW CREATE TABLE product_live_examples;`.

### Standard Shopify Revamp remains `general_service`

This is expected immediately after migration because the repository has no confirmed production ID or slug and the migration does not guess. List candidates read-only:

```sql
SELECT id, name, slug, service_type, intake_type
FROM products
WHERE service_type = 'shopify_makeover'
ORDER BY id;
```

After verifying the correct record, update only its exact ID or exact verified slug, retaining guards for `service_type = 'shopify_makeover'` and `intake_type = 'general_service'`. Never update all Shopify Make-Over rows; future Custom Kit products may share the service type. See `docs/DEPLOYMENT.md` for the guarded update.
