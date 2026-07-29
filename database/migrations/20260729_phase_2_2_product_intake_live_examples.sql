-- Phase 2.2: product intake routing metadata and multiple public live examples.
-- products.id is a signed INT in the Phase 2 schema, so product_id is also signed INT.
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS intake_type VARCHAR(100) NOT NULL DEFAULT 'general_service' AFTER service_type;

-- No definitive standard Shopify Revamp product ID or slug is seeded in this
-- repository. Existing products therefore remain general_service until Angela
-- verifies the production record. After this migration, list candidates with:
-- SELECT id, name, slug, service_type, intake_type
-- FROM products
-- WHERE service_type = 'shopify_makeover'
-- ORDER BY id;
--
-- After verifying exactly one record, classify it with ONE exact predicate:
-- UPDATE products SET intake_type = 'shopify_revamp_standard'
-- WHERE id = <verified_product_id> AND service_type = 'shopify_makeover'
--   AND intake_type = 'general_service';
-- Alternatively use: WHERE slug = '<verified_exact_slug>' ...
-- Do not update every shopify_makeover row because future Custom Kit products may
-- share that service type.

CREATE TABLE IF NOT EXISTS product_live_examples (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_product_live_examples_product_order (product_id, sort_order, id),
  CONSTRAINT fk_product_live_examples_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
