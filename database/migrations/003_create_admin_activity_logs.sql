USE admin_product_db;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    actor_name VARCHAR(150) NOT NULL,
    action VARCHAR(30) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT DEFAULT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_activity_logs_admin FOREIGN KEY (admin_id) REFERENCES admins(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_admin_activity_logs_created_at (created_at),
    INDEX idx_admin_activity_logs_admin (admin_id),
    INDEX idx_admin_activity_logs_entity (entity_type, entity_id)
);
