-- Add every Standard Shopify Revamp field missing from Custom Shopify Theme.
-- Existing custom-only fields are retained after the shared intake and reruns are idempotent.
SET @standard_id=(SELECT id FROM questionnaire_templates WHERE title='Standard Shopify Revamp Questionnaire' LIMIT 1);
SET @custom_id=(SELECT id FROM questionnaire_templates WHERE title='Custom Shopify Theme Questionnaire' LIMIT 1);
UPDATE questionnaire_fields custom_field
LEFT JOIN questionnaire_fields standard_field
  ON standard_field.questionnaire_template_id=@standard_id
 AND standard_field.field_key=custom_field.field_key
SET custom_field.sort_order = custom_field.sort_order + 10000
WHERE custom_field.questionnaire_template_id=@custom_id
  AND custom_field.sort_order < 10000
  AND standard_field.id IS NULL;
INSERT INTO questionnaire_fields
(questionnaire_template_id,field_key,field_type,label,admin_label,help_text,placeholder,options_json,validation_json,is_required,is_active,sort_order,created_at,updated_at)
SELECT @custom_id, standard_field.field_key,standard_field.field_type,standard_field.label,standard_field.admin_label,
       standard_field.help_text,standard_field.placeholder,standard_field.options_json,standard_field.validation_json,
       standard_field.is_required,standard_field.is_active,standard_field.sort_order,NOW(),NOW()
FROM questionnaire_fields standard_field
WHERE standard_field.questionnaire_template_id=@standard_id
  AND NOT EXISTS (
    SELECT 1 FROM questionnaire_fields custom_field
    WHERE custom_field.questionnaire_template_id=@custom_id
      AND custom_field.field_key=standard_field.field_key
  );
UPDATE questionnaire_templates SET status='draft',updated_at=NOW() WHERE id=@custom_id;
