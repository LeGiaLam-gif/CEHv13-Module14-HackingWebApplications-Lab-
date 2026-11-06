<?php
require_once __DIR__ . "/db.php";

function brand_all(): array {
    return db()->query(
        "SELECT b.*,
                (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) AS product_count
         FROM brands b
         ORDER BY b.id DESC"
    )->fetchAll();
}

function brand_find(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM brands WHERE id = :id LIMIT 1");
    $stmt->execute(["id" => $id]);
    $brand = $stmt->fetch();
    return $brand ?: null;
}

function brand_create(string $name, ?string $description, int $status): int {
    $stmt = db()->prepare("INSERT INTO brands(name, description, status) VALUES(:name, :description, :status)");
    $stmt->execute(["name" => $name, "description" => $description, "status" => $status]);
    return (int) db()->lastInsertId();
}

function brand_update(int $id, string $name, ?string $description, int $status): void {
    $stmt = db()->prepare("UPDATE brands SET name = :name, description = :description, status = :status WHERE id = :id");
    $stmt->execute(["id" => $id, "name" => $name, "description" => $description, "status" => $status]);
}

function brand_delete(int $id): void {
    $stmt = db()->prepare("DELETE FROM brands WHERE id = :id");
    $stmt->execute(["id" => $id]);
}

function brand_product_count(int $id): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM products WHERE brand_id = :id");
    $stmt->execute(["id" => $id]);
    return (int) $stmt->fetchColumn();
}

function brand_name_exists(string $name, int $exceptId = 0): bool {
    $stmt = db()->prepare("SELECT 1 FROM brands WHERE name = :name AND id <> :except_id LIMIT 1");
    $stmt->execute(["name" => $name, "except_id" => $exceptId]);
    return (bool) $stmt->fetchColumn();
}
