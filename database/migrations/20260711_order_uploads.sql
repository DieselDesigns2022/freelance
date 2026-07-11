CREATE TABLE IF NOT EXISTS order_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    upload_type VARCHAR(50) DEFAULT 'intake_asset',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_uploads_order (order_id),
    CONSTRAINT fk_order_uploads_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
