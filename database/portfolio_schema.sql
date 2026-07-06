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
