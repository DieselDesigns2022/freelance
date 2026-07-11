ALTER TABLE products
  ADD COLUMN IF NOT EXISTS demo_url VARCHAR(500) NULL AFTER contract_template_id,
  ADD COLUMN IF NOT EXISTS demo_password VARCHAR(255) NULL AFTER demo_url;

CREATE TABLE IF NOT EXISTS product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  alt_text VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_product_images_product (product_id),
  INDEX idx_product_images_sort_order (sort_order),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS intake_answers_json JSON NULL AFTER product_snapshot_json,
  ADD COLUMN IF NOT EXISTS customer_ip VARCHAR(100) NULL AFTER intake_answers_json,
  ADD COLUMN IF NOT EXISTS customer_user_agent VARCHAR(500) NULL AFTER customer_ip;

ALTER TABLE contract_instances
  ADD COLUMN IF NOT EXISTS terms_agreed_at DATETIME NULL AFTER signer_user_agent,
  ADD COLUMN IF NOT EXISTS esign_agreed_at DATETIME NULL AFTER terms_agreed_at,
  ADD COLUMN IF NOT EXISTS signed_contract_hash CHAR(64) NULL AFTER esign_agreed_at;
