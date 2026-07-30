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

Template statuses are application-allowlisted to `draft`, `active`, and `archived`. Field types are application-allowlisted to `short_text`, `long_text`, `email`, `phone`, `url`, `number`, `date`, `yes_no`, `dropdown`, `radio`, `checkboxes`, `file`, `multiple_files`, `information`, and `section_heading`. Validation JSON supports allowed extensions, maximum file count/size, minimum/maximum text length, and minimum/maximum number. Options are JSON arrays.

Field keys must be unique per template and use stable lowercase identifiers. Labels never regenerate keys. A field type with historical answers cannot be changed; duplicate it and deactivate the original. Deactivation preserves history. Templates used by active products cannot be archived or made inactive. Historical displays use the submitted snapshot, never the mutable template. Destructive template deletion is intentionally not offered.

Active `shopify_revamp_standard` and `shopify_custom_kit` products require active questionnaire and contract templates. Draft products may omit either. Website builds may be assigned a questionnaire for Phase 2.4 but do not require one yet. General services retain the generic intake.

## Deployment

1. Back up site files and database.
2. Pull `phase-2.3-questionnaire-builder-custom-shopify-intake` at the approved deployment step.
3. Apply `database/migrations/20260730_phase_2_3_questionnaire_builder.sql` after all Phase 2.2 migrations.
4. Verify all five tables/column changes, indexes, signed `INT` foreign keys, and both seeds.
5. Identify the Standard Shopify Revamp product by exact verified ID or slug. Do not guess it.
6. In Product Admin, assign **Standard Shopify Revamp Questionnaire** and verify its active contract. Or, after verifying the slug, run this guarded statement and confirm exactly one affected row:

```sql
UPDATE products
SET questionnaire_template_id = (
  SELECT id FROM questionnaire_templates
  WHERE title = 'Standard Shopify Revamp Questionnaire' AND status = 'active'
)
WHERE slug = 'REPLACE_WITH_VERIFIED_EXACT_SLUG'
  AND intake_type = 'shopify_revamp_standard';
```

7. Test the dynamic Revamp form and a complete contract-signing flow.
8. Review the draft **Custom Shopify Theme Questionnaire**, create/activate the correct contract, activate the questionnaire, and assign both to the exact verified Custom Shopify Theme draft product through Admin. Activate the product only after both assignments validate.
9. Test option validation, single/multiple uploads, snapshot/answer display, signing, and manual payment updates.
10. Verify legacy orders and perform public/admin smoke tests.

The migration does not assign production product IDs. It is additive, but the `ALTER TABLE` statements are intended to run once in migration order. Rollback should restore the database backup; removing tables/columns would discard Phase 2.3 submissions. Uploaded files written during a failed transaction are cleaned up. Existing uploads are unchanged.

## Testing status

Static syntax/diff and source security reviews are recorded in `docs/TESTING.md`. Database-backed, browser, real multipart-upload, email, contract-signing, contract-copy, and live manual-payment workflows remain deployment/staging tests unless separately recorded.
