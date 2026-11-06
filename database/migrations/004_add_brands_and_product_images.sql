USE admin_product_db;

CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE products
    ADD COLUMN brand_id INT DEFAULT NULL AFTER category_id,
    ADD INDEX idx_product_brand (brand_id),
    ADD CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
        ON DELETE RESTRICT ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_product_images_product_path (product_id, image_path),
    INDEX idx_product_images_order (product_id, sort_order)
);

INSERT INTO product_images (product_id, image_path, sort_order)
SELECT p.id, p.image_path, 0
FROM products p
WHERE p.image_path IS NOT NULL
  AND p.image_path <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM product_images pi
      WHERE pi.product_id = p.id AND pi.image_path = p.image_path
  );
