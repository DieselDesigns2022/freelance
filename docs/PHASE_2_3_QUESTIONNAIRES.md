# Phase 2.3 questionnaire system

Phase 2.3 adds reusable, server-rendered questionnaire templates and fields, product assignment, immutable order snapshots and answers, and field-associated protected uploads. Phase 2.2 remains responsible for intake classification, Live Examples, and Custom Shopify Theme catalog routing. Phase 2.4 will supply the complete Custom Website Build questionnaire and website-specific public workflow.

## Standard Shopify Revamp migration inventory

The previous `purchase.php` form contained these fields. The seed preserves their wording, required state, meaning, and help text:

| Legacy POST/intake key | Seeded field type | Required | Validation |
|---|---|---:|---|
| `shopify_store_url` | `url` | yes | HTTP/HTTPS URL, max 500 |
| `shopify_collaborator_code` | `short_text` | yes | exactly four digits |
| `logo_files` | `multiple_files` | yes | PNG/JPG/JPEG/WEBP/PDF, 15 MB each, up to 10 |
| `top_bar_text` | `short_text` | no | max 190 |
| `scrolling_banner_text` | `short_text` | yes | max 190, at most two dash-separated phrases |
| `featured_collections` | `long_text` | no | — |
| `featured_products` | `long_text` | no | — |
| `new_products_collection` | `short_text` | yes | max 190 |
| `trending_products_collection` | `short_text` | yes | max 190 |
| `collection_cover_names` | `long_text` | yes | — |
| `reviews_app` | `long_text` | no | — |

The seed also uses structural `section_heading` fields and an `information` field containing the existing warning never to submit a Shopify admin password. Structural fields collect no answer.

## Supported definitions and safety

Template statuses are application-allowlisted to `draft`, `active`, and `archived`. Field types are application-allowlisted to `short_text`, `long_text`, `email`, `phone`, `url`, `number`, `date`, `yes_no`, `dropdown`, `radio`, `checkboxes`, `file`, `multiple_files`, `addon`, `information`, and `section_heading`. Validation JSON supports allowed extensions, maximum file count/size, minimum/maximum text length, minimum/maximum number, and add-on pricing/quantity configuration. Options are JSON arrays.

Field keys must be unique per template and use stable lowercase identifiers. Labels never regenerate keys. A field type with historical answers cannot be changed; duplicate it and deactivate the original. Deactivation preserves history. Templates used by active products cannot be archived or made inactive. Historical displays use the submitted snapshot, never the mutable template. Destructive template deletion is intentionally not offered.

Active `shopify_revamp_standard` and `shopify_custom_kit` products require active questionnaire and contract templates. Draft products may omit either. Website builds may be assigned a questionnaire for Phase 2.4 but do not require one yet. General services retain the generic intake.

## Builder and field reuse UX

The authenticated builder uses a compact, single-column list of field cards. An administrator edits one selected field inline, can add a question at the end or at a chosen position between cards, and can reorder cards by drag and drop. **Move Up** and **Move Down** remain keyboard-friendly fallback controls. **Import Questions** copies selected fields from another questionnaire, while **Copy Existing Questionnaire** creates a new draft containing the complete source questionnaire. The source is never modified. The authenticated preview renders the active questionnaire without submitting an order.

Imports, full-questionnaire copies, and field duplication retain all field configuration. Generated stable keys are collision-safe in the destination. If a requested key already exists, a unique suffix is generated. Historical field keys and field types remain immutable; an administrator must duplicate and deactivate a historical field rather than changing its identity or type.

## File uploads and display labels

The stored upload field types remain `file` and `multiple_files`. Their administrator-facing labels are **File Upload** and **Multiple File Uploads**, respectively. This is display-only: public upload validation and processing, database values, protected download/storage behavior, snapshots, and historical-answer protections are unchanged.

## Manual-invoice add-ons

The **Add-On / Upgrade** field has the stored type `addon` and supports these stored pricing methods:

- `flat_fee`: the customer chooses whether to add one fixed-price upgrade.
- `per_additional_item`: the configured included quantity is displayed and the customer enters the number of additional items requested.
- `quantity_priced`: the customer enters the quantity requested at the configured per-item price.

Administrators enter a normal non-negative dollar amount (for example `2`, `2.00`, `12.50`, or `50.00`). The server parses the decimal string without floating-point arithmetic and stores `unit_price_cents` as integer cents in field validation JSON. Negative or malformed prices, prices with more than two decimal places, invalid/negative quantities, inconsistent minimum/maximum ranges, maximum or minimum violations, and step mismatches are rejected server-side.

The public JavaScript total is informational only. The server authoritatively recalculates each line from the saved field configuration and submitted selection or quantity. Add-ons are exclusively for later manual invoicing: they do not change online product pricing, collect payment, or modify Stripe, contracts, signing, payment links, or the existing manual-payment workflow.

At submission, the answer snapshot preserves the upgrade name, pricing method, integer-cent unit price, included quantity, selected quantity, billable quantity, and integer-cent line total. The questionnaire snapshot also preserves the aggregate add-on total, so later edits to a questionnaire do not change an existing order. The Admin Order view presents selected items under **Manual Invoice Add-Ons** and labels the aggregate as **Total Additional Amount to Invoice**. Unselected questionnaires show **No paid add-ons selected**.

## Deployment

1. Back up site files and database.
2. Pull `phase-2.3-questionnaire-file-uploads-addons` at the approved deployment step.
3. Apply `database/migrations/20260730_phase_2_3_questionnaire_builder.sql` after all Phase 2.2 migrations.
4. Apply the additive, rerunnable `database/migrations/20260730_phase_2_3_questionnaire_addons.sql` before using the add-on order workflow. It adds `order_questionnaire_snapshots.total_addon_cents` and `order_questionnaire_answers.addon_snapshot_json` without rewriting existing answers.
5. Verify all questionnaire tables/column changes, indexes, signed `INT` foreign keys, and both seeds.
6. Identify the Standard Shopify Revamp product by exact verified ID or slug. Do not guess it.
7. In Product Admin, assign **Standard Shopify Revamp Questionnaire** and verify its active contract. Or, after verifying the slug, run this guarded statement and confirm exactly one affected row:

```sql
UPDATE products
SET questionnaire_template_id = (
  SELECT id FROM questionnaire_templates
  WHERE title = 'Standard Shopify Revamp Questionnaire' AND status = 'active'
)
WHERE slug = 'REPLACE_WITH_VERIFIED_EXACT_SLUG'
  AND intake_type = 'shopify_revamp_standard';
```

8. Test the dynamic Revamp form and a complete contract-signing flow.
9. Review the draft **Custom Shopify Theme Questionnaire**, create/activate the correct contract, activate the questionnaire, and assign both to the exact verified Custom Shopify Theme draft product through Admin. Activate the product only after both assignments validate.
10. Test builder interactions, add-on calculations, option validation, single/multiple uploads, snapshot/answer display, signing, and manual payment updates.
11. Verify legacy orders and perform public/admin smoke tests.

The base builder migration does not assign production product IDs. Its schema changes are additive but intended to run once in migration order; the add-on migration is explicitly rerunnable. Rollback should restore the database backup because removing tables/columns would discard Phase 2.3 submissions. Uploaded files written during a failed transaction are cleaned up. Existing uploads are unchanged.

## Testing status

Static PHP syntax checks, `git diff --check`, focused questionnaire tests, deterministic dollar-to-cent cases, forged-total regression coverage, and static Admin Order rendering review are complete and recorded in `docs/TESTING.md`.

Live MySQL migration verification, authenticated browser testing, builder visual testing, add/edit/import/copy/reorder interactions, public add-on rendering, JavaScript total updates, full order submission, immutable database snapshot verification, Admin **Manual Invoice Add-Ons** display, existing single/multiple file-upload regression, contract-signing regression, and mobile/accessibility review remain pending until deployment or an equivalent live environment. No live add-on testing has been recorded as passed.

## Conditional logic and conditional pricing

Phase 2.3 now stores field rules and their actions in normalized tables. Rules are included in immutable order snapshots and evaluated authoritatively on submission. Conditional visibility and required state use deterministic Hide and Optional precedence, while matched flat fees are deduplicated by stable rule/action keys and stored in integer cents. The builder and preview expose rule configuration and live feedback; historical snapshots without a `rules` member remain compatible.
