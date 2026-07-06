# Database Documentation

The schema is defined in `database/portfolio_schema.sql`.

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
