-- Phase 2.3: normalized questionnaire conditional rules and authoritative fee snapshots.
CREATE TABLE IF NOT EXISTS questionnaire_field_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  questionnaire_template_id BIGINT UNSIGNED NOT NULL,
  source_field_id BIGINT UNSIGNED NOT NULL,
  operator VARCHAR(40) NOT NULL,
  comparison_value_json JSON NULL,
  stable_key VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_questionnaire_rule_stable (questionnaire_template_id, stable_key),
  KEY idx_questionnaire_rules_template (questionnaire_template_id, source_field_id),
  CONSTRAINT fk_questionnaire_rules_template FOREIGN KEY (questionnaire_template_id) REFERENCES questionnaire_templates(id) ON DELETE CASCADE,
  CONSTRAINT fk_questionnaire_rules_source FOREIGN KEY (source_field_id) REFERENCES questionnaire_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questionnaire_rule_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_id BIGINT UNSIGNED NOT NULL,
  action_type ENUM('show','hide','required','optional','fee') NOT NULL,
  target_field_id BIGINT UNSIGNED NULL,
  fee_name VARCHAR(180) NULL,
  fee_cents BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 10,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  KEY idx_questionnaire_actions_rule (rule_id, sort_order),
  KEY idx_questionnaire_actions_target (target_field_id),
  CONSTRAINT fk_questionnaire_actions_rule FOREIGN KEY (rule_id) REFERENCES questionnaire_field_rules(id) ON DELETE CASCADE,
  CONSTRAINT fk_questionnaire_actions_target FOREIGN KEY (target_field_id) REFERENCES questionnaire_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_conditional_total := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_questionnaire_snapshots' AND COLUMN_NAME='total_conditional_fee_cents');
SET @sql := IF(@has_conditional_total=0, 'ALTER TABLE order_questionnaire_snapshots ADD COLUMN total_conditional_fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER total_addon_cents', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
