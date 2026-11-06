<?php
require_once __DIR__ . "/db.php";

function inventory_products(): array {
    return db()->query("SELECT id, name, sku, quantity FROM products ORDER BY name")->fetchAll();
}

function inventory_movements(): array {
    return db()->query(
        "SELECT im.*, a.full_name AS admin_name
         FROM inventory_movements im
         LEFT JOIN admins a ON a.id = im.admin_id
         ORDER BY im.id DESC
         LIMIT 200"
    )->fetchAll();
}

function inventory_create_movement(int $productId, string $type, int $quantity, string $note, int $adminId): void {
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $productStmt = $pdo->prepare(
            "SELECT id, name, sku, quantity FROM products WHERE id = :id FOR UPDATE"
        );
        $productStmt->execute(["id" => $productId]);
        $product = $productStmt->fetch();
        if (!$product) {
            throw new DomainException("Không tìm thấy sản phẩm.");
        }

        $before = (int) $product["quantity"];
        if ($type === "out" && $quantity > $before) {
            throw new DomainException(
                "Sản phẩm \"" . $product["name"] . "\" chỉ còn " . $before . " sản phẩm trong kho."
            );
        }
        $after = $type === "in" ? $before + $quantity : $before - $quantity;
        if ($after > 2147483647) {
            throw new DomainException("Tồn kho sau khi nhập vượt quá giới hạn.");
        }

        $stockStmt = $pdo->prepare("UPDATE products SET quantity = :quantity WHERE id = :id");
        $stockStmt->execute(["quantity" => $after, "id" => $productId]);

        $movementStmt = $pdo->prepare(
            "INSERT INTO inventory_movements
                (product_id, product_name, product_sku, movement_type, quantity, stock_before, stock_after, note, admin_id)
             VALUES
                (:product_id, :product_name, :product_sku, :movement_type, :quantity, :stock_before, :stock_after, :note, :admin_id)"
        );
        $movementStmt->execute([
            "product_id" => $productId,
            "product_name" => $product["name"],
            "product_sku" => $product["sku"],
            "movement_type" => $type,
            "quantity" => $quantity,
            "stock_before" => $before,
            "stock_after" => $after,
            "note" => $note !== "" ? $note : null,
            "admin_id" => $adminId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
