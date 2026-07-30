-- Phase 2.3 manual-invoice questionnaire add-ons (additive and rerunnable).
SET @schema_name = DATABASE();
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='order_questionnaire_snapshots' AND COLUMN_NAME='total_addon_cents')=0,
  'ALTER TABLE order_questionnaire_snapshots ADD COLUMN total_addon_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER questionnaire_snapshot_json', 'SELECT 1');
PREPARE statement FROM @sql; EXECUTE statement; DEALLOCATE PREPARE statement;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='order_questionnaire_answers' AND COLUMN_NAME='addon_snapshot_json')=0,
  'ALTER TABLE order_questionnaire_answers ADD COLUMN addon_snapshot_json JSON NULL AFTER answer_json', 'SELECT 1');
PREPARE statement FROM @sql; EXECUTE statement; DEALLOCATE PREPARE statement;
