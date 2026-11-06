USE admin_product_db;

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address VARCHAR(500) NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customers_name (name),
    INDEX idx_customers_phone (phone)
);

ALTER TABLE orders
    ADD COLUMN customer_id INT DEFAULT NULL AFTER id,
    ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    product_sku VARCHAR(100) DEFAULT NULL,
    movement_type ENUM('in', 'out') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    stock_before INT UNSIGNED NOT NULL,
    stock_after INT UNSIGNED NOT NULL,
    note VARCHAR(500) DEFAULT NULL,
    reference_type VARCHAR(30) NOT NULL DEFAULT 'manual',
    reference_id INT DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_movements_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_movements_admin FOREIGN KEY (admin_id) REFERENCES admins(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_inventory_movements_created_at (created_at),
    INDEX idx_inventory_movements_product (product_id)
);
