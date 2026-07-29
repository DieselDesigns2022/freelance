# Database Documentation

The schema is defined in `database/portfolio_schema.sql`.

## Phase 2.2 product intake and live examples

`products.intake_type` is a `VARCHAR(100) NOT NULL DEFAULT 'general_service'` routing field. Supported application values are `shopify_revamp_standard`, `shopify_custom_kit`, `website_custom_build`, and `general_service`; VARCHAR storage permits later additions without an enum migration. Existing rows safely default to `general_service`. The repository does not seed or otherwise confirm the production standard Shopify Revamp product ID or slug, so the migration does not guess or automatically classify one.

After applying the migration, Angela must first run this read-only verification query:

```sql
SELECT id, name, slug, service_type, intake_type
FROM products
WHERE service_type = 'shopify_makeover'
ORDER BY id;
```

After confirming exactly which row is the existing standard Shopify Revamp, use an exact ID predicate (replace the placeholder with the verified numeric ID):

```sql
UPDATE products
SET intake_type = 'shopify_revamp_standard'
WHERE id = <verified_product_id>
  AND service_type = 'shopify_makeover'
  AND intake_type = 'general_service';
```

An exact verified slug may be used instead of the ID. Never update every `shopify_makeover` row because future Custom Kit products may use the same service type.

`product_live_examples` stores multiple public links per product. Its signed `INT product_id` exactly matches the signed `products.id`, is indexed with `sort_order` and `id`, and references `products(id) ON DELETE CASCADE`. Each row has a required title and URL plus display order and creation time. The admin edit page manages these rows; the public product detail renders them in `sort_order, id` order only when rows exist. The legacy `products.demo_url` and `demo_password` remain supported.

Phase 2.2 follows the project's existing MariaDB-compatible additive migration conventions. Confirm database compatibility with `ADD COLUMN IF NOT EXISTS`, take a full backup, obtain approval, and run the production migration once. It has not yet been run in production:

```bash
mysql -u <user> -p diesel_portfolio < database/migrations/20260729_phase_2_2_product_intake_live_examples.sql
```

## Table: `admin_users`

### Purpose

Stores admin accounts for the private portfolio admin panel.

### Relationships

No foreign keys currently reference this table.

### Important Columns

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `name` | Admin display/name field. |
| `email` | Unique login email. |
| `password_hash` | Password hash created by `password_hash()`. |
| `created_at` | Creation timestamp. |
| `updated_at` | Optional update timestamp. |
| `last_login_at` | Updated after successful login. |

### Status Fields

None.

### Business Rules

- `/admin/setup.php` creates the first admin only when this table has zero rows.
- Passwords are never stored in plain text.
- Email must be unique.

### Indexes / Keys

- Primary key: `id`.
- Unique key: `email`.

### Notes

There is no role system implemented. Any admin user has access to the admin panel.

## Table: `portfolio_projects`

### Purpose

Stores portfolio projects for both public sections: Website Builds and Shopify Make-Overs.

### Relationships

- One project can have many `portfolio_project_images` rows.
- One project can have many `portfolio_before_after_pairs` rows.
- Related rows cascade on project delete by foreign key.

### Important Columns

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `section_type` | `website` or `shopify_makeover`. |
| `title` | Project title. |
| `slug` | Unique public slug used by `project.php?slug=...`. |
| `short_description` | Required short public summary. |
| `full_description` | Optional longer project notes. |
| `client_name` | Optional client/business name. |
| `project_type` | Website type or make-over type. |
| `live_url` | Optional live website link. |
| `tools_used` | Optional tools/technology notes. |
| `completion_date` | Optional launch/completion date. |
| `thumbnail_path` | Optional portfolio card/hero image path. |
| `is_featured` | Homepage featured flag. |
| `status` | Draft or published. |
| `sort_order` | Manual display order. |
| `seo_title` | Optional project detail SEO title. |
| `seo_description` | Optional project detail SEO description. |
| `created_at` | Creation timestamp. |
| `updated_at` | Optional update timestamp. |

### Status Fields

- `status = 'draft'`: not shown publicly.
- `status = 'published'`: eligible for public listing/detail pages.

### Business Rules

- Public listing pages only show matching section type and `published` status.
- Public project detail lookup requires matching slug and `published` status.
- Slugs must be unique.
- `section_type` must be `website` or `shopify_makeover`.

### Indexes / Keys

- Primary key: `id`.
- Unique key: `slug`.
- Index: `idx_projects_public (status, section_type, sort_order)`.
- Check constraints for `section_type` and `status`.

### Notes

`project_type` stores either website type or Shopify make-over type, depending on `section_type`.

## Table: `portfolio_project_images`

### Purpose

Stores individual gallery, before, and after images for projects.

### Relationships

- Belongs to `portfolio_projects` through `project_id`.
- Deleted automatically by database cascade when the parent project row is deleted.

### Important Columns

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `project_id` | Parent project ID. |
| `image_type` | `gallery`, `before`, or `after`. |
| `image_path` | Relative uploaded image path. |
| `caption` | Optional public/admin caption. |
| `alt_text` | Optional image alt text. |
| `sort_order` | Manual ordering. |
| `created_at` | Creation timestamp. |
| `updated_at` | Optional update timestamp. |

### Status Fields

None.

### Business Rules

- `image_type` must be `gallery`, `before`, or `after`.
- Public Shopify project pages group `before` and `after` images separately.
- Public gallery sections use `gallery` images.

### Indexes / Keys

- Primary key: `id`.
- Index: `idx_images_project (project_id, image_type, sort_order)`.
- Foreign key: `project_id` references `portfolio_projects(id)` on delete cascade.
- Check constraint for `image_type`.

### Notes

Physical files are stored in `uploads/portfolio/`; the database stores only relative paths.

## Table: `portfolio_before_after_pairs`

### Purpose

Stores paired before/after image comparisons for Shopify Make-Over projects.

### Relationships

- Belongs to `portfolio_projects` through `project_id`.
- Deleted automatically by database cascade when the parent project row is deleted.

### Important Columns

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `project_id` | Parent project ID. |
| `before_image_path` | Relative path to before image. |
| `after_image_path` | Relative path to after image. |
| `caption` | Optional pair caption/title. |
| `before_alt_text` | Optional before image alt text. |
| `after_alt_text` | Optional after image alt text. |
| `sort_order` | Manual ordering. |
| `created_at` | Creation timestamp. |
| `updated_at` | Optional update timestamp. |

### Status Fields

None.

### Business Rules

- Intended for Shopify Make-Over projects.
- Public project page displays pairs before separate before/after image groups.
- Both before and after images are required by the admin upload form.

### Indexes / Keys

- Primary key: `id`.
- Index: `idx_pairs_project (project_id, sort_order)`.
- Foreign key: `project_id` references `portfolio_projects(id)` on delete cascade.

### Notes

The database does not enforce that paired records only belong to `shopify_makeover` projects; the admin UI only exposes the paired upload section for Shopify Make-Over projects.

## Table: `website_requests`

### Purpose

Stores public website build/revamp request submissions for private admin review.

### Relationships

No foreign keys currently reference this table.

### Important Columns

- `name`, `email`, `phone`, `business_name`: requester contact details.
- `preferred_contact_method`: preferred follow-up method.
- `project_type`, `platform`, `services_needed`: project classification and requested services.
- `current_website_url`: optional existing website URL.
- `project_description`, `inspiration_links`, `budget_range`, `timeline`, `notes`: project intake details.
- `status`: request workflow state.
- `admin_notes`: private admin-only notes.
- `contacted_at`, `created_at`, `updated_at`: request timestamps.

### Status Fields

Allowed statuses are `new`, `reviewing`, `contacted`, `quoted`, `accepted`, `declined`, and `archived`.

### Business Rules

- Requests are never listed publicly.
- Admin notes are private and must not be exposed on public pages.
- Public request submissions start as `new`.

### Indexes / Keys

- Primary key: `id`.
- Indexes: `status`, `created_at`, and `email`.

## Table: `faqs`

### Purpose

Stores public FAQ content managed by admins.

### Relationships

No foreign keys currently reference this table.

### Important Columns

- `question`: FAQ question.
- `answer`: FAQ answer.
- `category`: optional grouping label.
- `status`: draft/published visibility.
- `sort_order`: public/admin ordering.
- `created_at`, `updated_at`: timestamps.

### Status Fields

Allowed statuses are `draft` and `published`.

### Business Rules

- Public FAQ pages and FAQ schema include only `published` FAQs.
- Draft FAQs are admin-only.

### Indexes / Keys

- Primary key: `id`.
- Indexes: `status`, `category`, and `sort_order`.

## Phase 2 Tables

### products

Stores purchasable services and kits shown on the public store when `status = active`. Key columns include `name`, unique `slug`, descriptions, `service_type`, `intake_type`, `fulfillment_type`, `price`, optional `deposit_amount`, `turnaround_text`, `includes_text`, `requirements_text`, `is_featured`, `sort_order`, nullable `contract_template_id`, optional `demo_url`, and optional `demo_password`. Active products should point at an active contract template. Indexes support public listing and contract-template lookups.

Allowed service types: `website_kit`, `website_build`, `shopify_makeover`, `website_revamp`, `custom_service`. Allowed fulfillment types: `service`, `digital_kit`, `hybrid`. Allowed statuses: `draft`, `active`, `archived`.

`intake_type` is `VARCHAR(100) NOT NULL DEFAULT 'general_service'`, not an enum. Application values are `shopify_revamp_standard`, `shopify_custom_kit`, `website_custom_build`, and `general_service`.

### product_images

Stores screenshots/product images for storefront products. Key columns include `product_id`, `image_path`, optional `alt_text`, `sort_order`, and `created_at`. Image files use the existing hardened `uploads/portfolio/` path. The `product_id` foreign key references `products(id)` with `ON DELETE CASCADE`, so deleting a product cascades related `product_images` rows.

### product_live_examples

Stores public example links belonging to products. Required fields are `title VARCHAR(255)` and `url VARCHAR(500)`; `sort_order` defaults to zero. The signed `INT product_id` exactly matches signed `products.id` and references it with `ON DELETE CASCADE`. The composite index `idx_product_live_examples_product_order (product_id, sort_order, id)` supports display in `sort_order`, then `id` order.

### contract_templates

Stores reusable contract language by service type. Key columns include `title`, unique `slug`, `service_type`, `version`, `body`, and `status`. Templates can be drafted, activated, or archived. Products reference templates, but contract instances snapshot template title, version, body, and rendered output for historical accuracy.

### orders

Stores a customer's purchase/order request. Key columns include unique `order_number`, nullable `product_id`, customer contact fields, `website_url`, `project_notes`, product snapshot columns, `product_snapshot_json`, `intake_answers_json`, `customer_ip`, `customer_user_agent`, `order_status`, `payment_status`, `contract_status`, and `admin_notes`. Shopify Revamp orders store a readable intake summary in `project_notes`. Indexes support customer email and status filtering.

Order statuses: `pending_contract`, `contract_sent`, `contract_signed`, `payment_pending`, `paid`, `in_progress`, `completed`, `cancelled`. Payment statuses: `not_required`, `pending`, `paid`, `refunded`, `failed`. Contract statuses: `pending`, `sent`, `viewed`, `signed`, `void`.

### contract_instances

Stores one generated contract for an order. Key columns include `order_id`, nullable `contract_template_id`, title/version/body snapshots, `rendered_contract_snapshot`, unique `token_hash`, `status`, `sent_at`, `viewed_at`, `signed_at`, `signer_legal_name`, `typed_signature`, `signer_ip`, `signer_user_agent`, `terms_agreed_at`, `esign_agreed_at`, and `signed_contract_hash`. Only the token hash is stored; raw tokens are not recoverable. The signed contract hash is generated at signing from stable signing evidence.

## Phase 2.1 Shopify Revamp migration
Existing deployments must run `database/migrations/20260710_phase_2_1_shopify_revamp_flow.sql`. The migration is additive for MariaDB 10.11: `products.demo_url`, `products.demo_password`, `product_images`, Shopify intake/customer audit columns on `orders`, and consent/hash fields on `contract_instances`.
