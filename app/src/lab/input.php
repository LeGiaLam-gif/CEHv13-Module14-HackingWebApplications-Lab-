<?php

function lab_field_policy(string $resource, string $role, string $op): array {
    $basePolicies = [
        "users.register" => ["username", "email", "password", "full_name", "phone"],
        "users.update" => ["full_name", "phone"],
        "users.admin_update" => ["full_name", "phone", "account_status"],
        "users.role" => ["role_id"],
        "addresses.create" => ["label", "line1", "city", "phone", "is_default"],
        "addresses.update" => ["label", "line1", "city", "phone", "is_default"],
        "orders.create" => ["items", "address_id"],
        "orders.update" => ["shipping_address", "status"],
        "orders.payment" => ["payment_status"],
        "products.create" => ["name", "description", "category_id", "brand_id", "sku", "unit_price"],
        "products.update" => ["name", "description", "category_id", "brand_id", "sku", "unit_price", "status"],
    ];

    $key = $resource . "." . $op;
    if (!isset($basePolicies[$key])) {
        return [];
    }

    if ($key === "users.update") {
        return $role === "admin" ? ["full_name", "phone", "account_status"] : ["full_name", "phone"];
    }

    if ($key === "orders.update") {
        return $role === "customer" ? ["shipping_address"] : ["status"];
    }

    if ($key === "products.update") {
        $catalogFields = ["name", "description", "category_id", "brand_id", "sku"];
        return $role === "admin" ? [...$catalogFields, "unit_price", "status"] : $catalogFields;
    }

    if ($key === "products.create") {
        $createFields = ["name", "description", "category_id", "brand_id", "sku"];
        return $role === "admin" ? [...$createFields, "unit_price"] : $createFields;
    }

    if ($key === "users.admin_update" && $role === "admin") {
        return $basePolicies[$key];
    }

    return $basePolicies[$key];
}

function lab_filter_input(string $resource, array $user, string $op, array $input): array {
    $role = (string) ($user["role_name"] ?? "");
    $allowed = lab_field_policy($resource, $role, $op);
    if (lab_vuln("MASS_ASSIGN")) {
        return $input;
    }
    $restricted = array_values(array_diff(array_keys($input), $allowed));
    if ($restricted) {
        $fields = array_values(array_map("strval", $restricted));
        foreach ($fields as $field) {
            lab_audit("deny", "restricted_field:" . $field, $resource);
        }
        throw new ApiHttpException("Yêu cầu chứa trường không được phép.", 422, ["fields" => $fields]);
    }
    return $input;
}

function lab_table_columns(string $table): array {
    static $cache = [];
    $allowedTables = ["users", "orders", "order_items", "products"];
    if (!in_array($table, $allowedTables, true)) {
        throw new LogicException("Unsupported mass-assignment table.");
    }
    if (!isset($cache[$table])) {
        $stmt = db()->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name"
        );
        $stmt->execute(["table_name" => $table]);
        $cache[$table] = array_column($stmt->fetchAll(), "COLUMN_NAME");
    }
    return $cache[$table];
}

function lab_assign_all(string $table, int $id, array $input): void {
    $columns = array_intersect(array_keys($input), lab_table_columns($table));
    if (!$columns) {
        return;
    }
    $assignments = [];
    $values = ["row_id" => $id];
    foreach (array_values($columns) as $index => $column) {
        $placeholder = "field_" . $index;
        $assignments[] = "`" . $column . "` = :" . $placeholder;
        $values[$placeholder] = $input[$column];
    }
    $sql = "UPDATE `" . $table . "` SET " . implode(", ", $assignments) . " WHERE id = :row_id";
    $stmt = db()->prepare($sql);
    $stmt->execute($values);
}

function lab_project_output(string $resource, array $user, array $row, bool $isOwner = false): array {
    if (lab_vuln("PROPERTY_EXPOSURE")) {
        return $row;
    }
    unset($row["password_hash"]);
    $isAdmin = ($user["role_name"] ?? "") === "admin";
    if (!$isOwner && !$isAdmin) {
        unset($row["role_id"], $row["account_status"], $row["store_credit"]);
    }
    return $row;
}
