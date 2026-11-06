<?php
require_once __DIR__ . "/db.php";

function customer_all(): array {
    return db()->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();
}

function customer_find(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM customers WHERE id = :id LIMIT 1");
    $stmt->execute(["id" => $id]);
    $customer = $stmt->fetch();
    return $customer ?: null;
}

function customer_create(array $customer): int {
    $stmt = db()->prepare(
        "INSERT INTO customers (name, phone, email, address, notes)
         VALUES (:name, :phone, :email, :address, :notes)"
    );
    $stmt->execute($customer);
    return (int) db()->lastInsertId();
}

function customer_update(int $id, array $customer): void {
    $customer["id"] = $id;
    $stmt = db()->prepare(
        "UPDATE customers
         SET name = :name, phone = :phone, email = :email, address = :address, notes = :notes
         WHERE id = :id"
    );
    $stmt->execute($customer);
}
