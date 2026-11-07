CREATE TABLE security_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    method VARCHAR(10) NOT NULL,
    route VARCHAR(255) NOT NULL,
    target_type VARCHAR(40) NULL,
    target_id INT NULL,
    decision ENUM('allow','deny') NOT NULL,
    reason VARCHAR(255) NULL,
    lab_mode VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_actor (actor_user_id),
    INDEX idx_audit_target (target_type, target_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;
