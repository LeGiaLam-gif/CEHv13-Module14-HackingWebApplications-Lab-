CREATE TABLE IF NOT EXISTS admin_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_password_resets_expiration (expires_at),
    CONSTRAINT fk_admin_password_resets_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);
