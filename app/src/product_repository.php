<?php
require_once __DIR__ . "/db.php";

function product_filter_clause(string $keyword, int $categoryId, int $brandId = 0): array {
    $where = " WHERE 1=1";
    $params = [];

    if ($keyword !== "") {
        $where .= " AND (p.name LIKE :name_kw OR p.sku LIKE :sku_kw)";
        $params["name_kw"] = "%" . $keyword . "%";
        $params["sku_kw"] = "%" . $keyword . "%";
    }

    if ($categoryId > 0) {
        $where .= " AND p.category_id = :cid";
        $params["cid"] = $categoryId;
    }

    if ($brandId > 0) {
        $where .= " AND p.brand_id = :brand_id";
        $params["brand_id"] = $brandId;
    }

    return [$where, $params];
}

function product_paginate(
    string $keyword = "",
    int $categoryId = 0,
    int $limit = 10,
    int $offset = 0,
    int $brandId = 0
): array {
    [$where, $params] = product_filter_clause($keyword, $categoryId, $brandId);
    $sql = "SELECT p.*, c.name AS category_name, b.name AS brand_name
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id"
            . $where;

    $sql .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

    $stmt = db()->prepare($sql);

    // Bind các tham số phân trang dưới dạng số nguyên
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }

    $stmt->bindValue(":limit", max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(":offset", max(0, $offset), PDO::PARAM_INT);

    $stmt->execute();

    return $stmt->fetchAll();
}

function product_count_filtered(string $keyword = "", int $categoryId = 0, int $brandId = 0): int {
    [$where, $params] = product_filter_clause($keyword, $categoryId, $brandId);
    $sql = "SELECT COUNT(*)
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id"
            . $where;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function product_export_filtered(string $keyword = "", int $categoryId = 0, int $brandId = 0): array {
    [$where, $params] = product_filter_clause($keyword, $categoryId, $brandId);
    $sql = "SELECT p.id, p.name, p.sku, c.name AS category_name, b.name AS brand_name,
                   p.description, p.quantity, p.unit_price, p.status, p.created_at, p.updated_at
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id"
            . $where . " ORDER BY p.id DESC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function product_find(int $id): ?array {
    $stmt=db()->prepare("SELECT p.*, c.name AS category_name, b.name AS brand_name FROM products p INNER JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id WHERE p.id=:id LIMIT 1");
    $stmt->execute(["id"=>$id]); $r=$stmt->fetch(); return $r ?: null;
}
function product_create(array $d): int {
    $stmt=db()->prepare("INSERT INTO products(category_id,brand_id,name,sku,description,quantity,unit_price,image_path,status) VALUES(:category_id,:brand_id,:name,:sku,:description,:quantity,:unit_price,:image_path,:status)");
    $stmt->execute($d);
    return (int) db()->lastInsertId();
}
function product_update(int $id, array $d): void {
    $d["id"]=$id;
    $stmt=db()->prepare("UPDATE products SET category_id=:category_id,brand_id=:brand_id,name=:name,sku=:sku,description=:description,unit_price=:unit_price,image_path=:image_path,status=:status WHERE id=:id");
    $stmt->execute($d);
}
function product_delete(int $id): void { $stmt=db()->prepare("DELETE FROM products WHERE id=:id"); $stmt->execute(["id"=>$id]); }
function product_image_all(int $productId): array {
    $stmt = db()->prepare("SELECT id, product_id, image_path, sort_order FROM product_images WHERE product_id=:product_id ORDER BY sort_order ASC, id ASC");
    $stmt->execute(["product_id" => $productId]);
    return $stmt->fetchAll();
}
function product_image_insert_many(int $productId, array $imagePaths): void {
    if (!$imagePaths) return;
    $stmt = db()->prepare("INSERT INTO product_images(product_id, image_path, sort_order) VALUES(:product_id, :image_path, :sort_order)");
    $sortOrderStmt = db()->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = :product_id");
    $sortOrderStmt->execute(["product_id" => $productId]);
    $sortOrder = (int) $sortOrderStmt->fetchColumn();
    foreach ($imagePaths as $imagePath) {
        $stmt->execute([
            "product_id" => $productId,
            "image_path" => $imagePath,
            "sort_order" => $sortOrder++,
        ]);
    }
}
function product_image_delete_many(int $productId, array $imageIds): array {
    if (!$imageIds) return [];
    $ids = array_values(array_unique(array_map("intval", $imageIds)));
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $stmt = db()->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? AND id IN (" . $placeholders . ") FOR UPDATE");
    $stmt->execute(array_merge([$productId], $ids));
    $images = $stmt->fetchAll();
    if (count($images) !== count($ids)) {
        throw new InvalidArgumentException("Danh sách hình ảnh cần xóa không hợp lệ.");
    }

    $delete = db()->prepare("DELETE FROM product_images WHERE product_id = ? AND id IN (" . $placeholders . ")");
    $delete->execute(array_merge([$productId], $ids));
    return $images;
}
function product_image_path_in_use(string $imagePath): bool {
    $stmt = db()->prepare(
        "SELECT EXISTS(SELECT 1 FROM product_images WHERE image_path = :gallery_path)
         OR EXISTS(SELECT 1 FROM products WHERE image_path = :primary_path)"
    );
    $stmt->execute(["gallery_path" => $imagePath, "primary_path" => $imagePath]);
    return (bool) $stmt->fetchColumn();
}
function product_count(): int { return (int) db()->query("SELECT COUNT(*) FROM products")->fetchColumn(); }
function product_total_quantity(): int { return (int) db()->query("SELECT COALESCE(SUM(quantity),0) FROM products")->fetchColumn(); }
function product_total_inventory_value(): float { return (float) db()->query("SELECT COALESCE(SUM(quantity*unit_price),0) FROM products")->fetchColumn(); }
