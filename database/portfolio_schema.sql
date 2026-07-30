CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  short_description TEXT NOT NULL,
  full_description MEDIUMTEXT NULL,
  client_name VARCHAR(255) NULL,
  project_type VARCHAR(255) NULL,
  live_url VARCHAR(500) NULL,
  tools_used TEXT NULL,
  completion_date DATE NULL,
  thumbnail_path VARCHAR(500) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  sort_order INT NOT NULL DEFAULT 0,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_projects_public (status, section_type, sort_order),
  CONSTRAINT chk_section_type CHECK (section_type IN ('website','shopify_makeover')),
  CONSTRAINT chk_project_status CHECK (status IN ('draft','published'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_project_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  image_type VARCHAR(50) NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  caption VARCHAR(255) NULL,
  alt_text VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_images_project (project_id, image_type, sort_order),
  CONSTRAINT chk_image_type CHECK (image_type IN ('gallery','before','after')),
  CONSTRAINT fk_project_images_project FOREIGN KEY (project_id) REFERENCES portfolio_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_before_after_pairs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  before_image_path VARCHAR(500) NOT NULL,
  after_image_path VARCHAR(500) NOT NULL,
  caption VARCHAR(255) NULL,
  before_alt_text VARCHAR(255) NULL,
  after_alt_text VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_pairs_project (project_id, sort_order),
  CONSTRAINT fk_pairs_project FOREIGN KEY (project_id) REFERENCES portfolio_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(100) NULL,
  business_name VARCHAR(190) NULL,
  preferred_contact_method VARCHAR(50) NULL,
  project_type VARCHAR(100) NOT NULL,
  platform VARCHAR(100) NULL,
  current_website_url VARCHAR(500) NULL,
  services_needed TEXT NULL,
  project_description MEDIUMTEXT NOT NULL,
  inspiration_links TEXT NULL,
  budget_range VARCHAR(100) NOT NULL,
  timeline VARCHAR(100) NOT NULL,
  notes MEDIUMTEXT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'new',
  admin_notes MEDIUMTEXT NULL,
  contacted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_website_requests_status (status),
  INDEX idx_website_requests_created_at (created_at),
  INDEX idx_website_requests_email (email),
  CONSTRAINT chk_website_request_status CHECK (status IN ('new','reviewing','contacted','quoted','accepted','declined','archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(255) NOT NULL,
  answer MEDIUMTEXT NOT NULL,
  category VARCHAR(100) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'published',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_faqs_status (status),
  INDEX idx_faqs_category (category),
  INDEX idx_faqs_sort_order (sort_order),
  CONSTRAINT chk_faq_status CHECK (status IN ('draft','published'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'What types of website work do you offer?', 'Diesel Designs offers website builds, website revamps, Shopify make-overs, and visual updates such as graphics, colors, branding polish, and layout refreshes. The exact scope depends on the project and will be confirmed before work begins.', 'Services', 'published', 10, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'What types of website work do you offer?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'Do website revamps include coding changes?', 'Not always. Some revamp services focus on graphics, colors, theme setup, and visual presentation. Coding changes, custom functionality, app/plugin work, or platform limitations may fall outside the standard scope unless specifically agreed to.', 'Scope', 'published', 20, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'Do website revamps include coding changes?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'Can you install a new theme?', 'Depending on the platform and project scope, a new theme may be installed as part of a revamp or make-over. Theme installation does not automatically include custom coding, app/plugin setup, or product/listing updates.', 'Scope', 'published', 30, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'Can you install a new theme?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'What is not included in a basic graphics/color customization service?', 'A basic graphics/color customization service does not include website code edits, app/plugin installation or updates, or adding/removing/editing products, listings, or collections unless those services are specifically included in the project agreement.', 'Scope', 'published', 40, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'What is not included in a basic graphics/color customization service?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'What do I need to provide before work starts?', 'Clients should provide any needed graphics, branding assets, color preferences, login/access details if required, platform information, project goals, and examples or inspiration when available.', 'Process', 'published', 50, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'What do I need to provide before work starts?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'Do I need to approve the final design?', 'Yes. Final design changes should be reviewed and approved before the website goes live or before the project is considered complete.', 'Process', 'published', 60, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'Do I need to approve the final design?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'How many revisions are included?', 'Standard projects may include a set number of minor revision rounds, such as adjustments to colors, graphics, or placement. Extra revisions or new requests outside the original scope may require an additional fee.', 'Process', 'published', 70, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'How many revisions are included?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'Are payments refundable?', 'Because website design and digital services require custom work and time, payments are generally non-refundable once work begins or as stated in the project agreement.', 'Payments', 'published', 80, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'Are payments refundable?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'What happens if I stop responding during the project?', 'Timely communication helps keep the project moving. Delays caused by missing information, late feedback, or lack of response may extend the project timeline and do not count against Diesel Designs’ turnaround time.', 'Process', 'published', 90, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'What happens if I stop responding during the project?');
INSERT INTO faqs (question, answer, category, status, sort_order, created_at)
SELECT 'Will my website be SEO-friendly?', 'SEO is considered during website builds and revamps. Diesel Designs focuses on clear page structure, helpful content, readable titles and descriptions, mobile-friendly layouts, internal links, and image alt text where appropriate. SEO results are not guaranteed, but the goal is to build a clean foundation that search engines can understand.', 'SEO', 'published', 100, NOW()
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'Will my website be SEO-friendly?');
CREATE TABLE IF NOT EXISTS contract_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  service_type VARCHAR(50) NOT NULL,
  version VARCHAR(50) NOT NULL DEFAULT '1.0',
  body MEDIUMTEXT NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_contract_templates_status (status),
  CONSTRAINT chk_contract_templates_service_type CHECK (service_type IN ('website_kit','website_build','shopify_makeover','website_revamp','custom_service')),
  CONSTRAINT chk_contract_templates_status CHECK (status IN ('draft','active','archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  short_description TEXT NOT NULL,
  full_description MEDIUMTEXT NULL,
  service_type VARCHAR(50) NOT NULL,
  intake_type VARCHAR(100) NOT NULL DEFAULT 'general_service',
  fulfillment_type VARCHAR(50) NOT NULL DEFAULT 'service',
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  deposit_amount DECIMAL(10,2) NULL,
  turnaround_text TEXT NULL,
  includes_text MEDIUMTEXT NULL,
  requirements_text MEDIUMTEXT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  contract_template_id INT NULL,
  demo_url VARCHAR(500) NULL,
  demo_password VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_products_public (status, sort_order),
  INDEX idx_products_contract_template (contract_template_id),
  CONSTRAINT chk_products_service_type CHECK (service_type IN ('website_kit','website_build','shopify_makeover','website_revamp','custom_service')),
  CONSTRAINT chk_products_fulfillment_type CHECK (fulfillment_type IN ('service','digital_kit','hybrid')),
  CONSTRAINT chk_products_status CHECK (status IN ('draft','active','archived')),
  CONSTRAINT fk_products_contract_template FOREIGN KEY (contract_template_id) REFERENCES contract_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(50) NULL UNIQUE,
  product_id INT NULL,
  customer_name VARCHAR(190) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  business_name VARCHAR(190) NULL,
  phone VARCHAR(100) NULL,
  preferred_contact_method VARCHAR(50) NULL,
  website_url VARCHAR(500) NULL,
  project_notes MEDIUMTEXT NULL,
  product_name_snapshot VARCHAR(255) NOT NULL,
  product_price_snapshot DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  service_type_snapshot VARCHAR(50) NOT NULL,
  product_snapshot_json JSON NULL,
  intake_answers_json JSON NULL,
  customer_ip VARCHAR(100) NULL,
  customer_user_agent VARCHAR(500) NULL,
  order_status VARCHAR(50) NOT NULL DEFAULT 'pending_contract',
  payment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
  contract_status VARCHAR(50) NOT NULL DEFAULT 'pending',
  admin_notes MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_orders_email (customer_email),
  INDEX idx_orders_order_status (order_status),
  INDEX idx_orders_contract_status (contract_status),
  INDEX idx_orders_payment_status (payment_status),
  INDEX idx_orders_product (product_id),
  CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT chk_orders_order_status CHECK (order_status IN ('pending_contract','contract_sent','contract_signed','payment_pending','paid','in_progress','completed','cancelled')),
  CONSTRAINT chk_orders_payment_status CHECK (payment_status IN ('not_required','pending','paid','refunded','failed')),
  CONSTRAINT chk_orders_contract_status CHECK (contract_status IN ('pending','sent','viewed','signed','void'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_uploads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(100) NULL,
  file_size INT NULL,
  upload_type VARCHAR(50) DEFAULT 'intake_asset',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order_uploads_order (order_id),
  CONSTRAINT fk_order_uploads_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_instances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  contract_template_id INT NULL,
  contract_title_snapshot VARCHAR(255) NOT NULL,
  contract_version_snapshot VARCHAR(50) NOT NULL,
  contract_body_snapshot MEDIUMTEXT NOT NULL,
  rendered_contract_snapshot MEDIUMTEXT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  sent_at DATETIME NULL,
  viewed_at DATETIME NULL,
  signed_at DATETIME NULL,
  signer_legal_name VARCHAR(190) NULL,
  typed_signature VARCHAR(190) NULL,
  signer_ip VARCHAR(100) NULL,
  signer_user_agent VARCHAR(500) NULL,
  terms_agreed_at DATETIME NULL,
  esign_agreed_at DATETIME NULL,
  signed_contract_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_contract_instances_order (order_id),
  INDEX idx_contract_instances_status (status),
  CONSTRAINT fk_contract_instances_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_instances_template FOREIGN KEY (contract_template_id) REFERENCES contract_templates(id) ON DELETE SET NULL,
  CONSTRAINT chk_contract_instances_status CHECK (status IN ('pending','sent','viewed','signed','void'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
