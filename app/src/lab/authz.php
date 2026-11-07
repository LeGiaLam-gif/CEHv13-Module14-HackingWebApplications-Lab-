<?php

function lab_user_permissions(int $roleId): array {
    static $cache = [];
    if (!array_key_exists($roleId, $cache)) {
        $stmt = db()->prepare(
            "SELECT p.code
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id"
        );
        $stmt->execute(["role_id" => $roleId]);
        $cache[$roleId] = array_fill_keys(array_column($stmt->fetchAll(), "code"), true);
    }
    return $cache[$roleId];
}

function lab_has_permission(array $user, string $code): bool {
    return isset(lab_user_permissions((int) $user["role_id"])[$code]);
}

function lab_require_permission(array $user, string $code, string $group = "BAC_VERTICAL", ?string $targetType = null, ?int $targetId = null): void {
    $verticalTarget = in_array($group, ["products", "users"], true)
        || ($group === "delete_product" && ($user["role_name"] ?? "") === "customer");
    $bypass = lab_vuln("BAC_VERTICAL") && $verticalTarget;
    $bypass = $bypass || (lab_vuln("BFLA") && in_array($group, ["role", "delete_user", "delete_product"], true));
    if (!$bypass && !lab_has_permission($user, $code)) {
        lab_audit("deny", "missing_permission:" . $code, $targetType, $targetId);
        throw new ApiHttpException("Bạn không có quyền thực hiện thao tác này.", 403);
    }
}

function lab_owns_customer(array $user, int $customerId): bool {
    $stmt = db()->prepare("SELECT 1 FROM customers WHERE id = :customer_id AND user_id = :user_id");
    $stmt->execute(["customer_id" => $customerId, "user_id" => (int) $user["id"]]);
    return (bool) $stmt->fetchColumn();
}

function lab_can_access_order(array $user, array $order, string $op): bool {
    if (lab_vuln("BOLA")) {
        return true;
    }
    if (in_array($op, ["read", "update_status"], true)) {
        $permission = $op === "read" ? "orders.read_all" : "orders.update_status";
        if (lab_has_permission($user, $permission)) {
            return true;
        }
    }
    return $order["customer_id"] !== null
        && lab_owns_customer($user, (int) $order["customer_id"]);
}

function lab_can_access_address(array $user, array $address, string $op): bool {
    return lab_vuln("BOLA")
        || lab_owns_customer($user, (int) $address["customer_id"]);
}

function lab_can_access_user(array $user, int $targetId, string $op): bool {
    return lab_vuln("BOLA")
        || (int) $user["id"] === $targetId
        || lab_has_permission($user, "users.read_all")
        || lab_has_permission($user, "users.manage");
}

function lab_require_owner(bool $allowed, ?string $targetType = null, ?int $targetId = null): void {
    if (!$allowed) {
        lab_audit("deny", "not_owner", $targetType, $targetId);
        $status = (int) (getenv("LAB_DENY_STATUS_OBJECT") ?: 403);
        if (!in_array($status, [403, 404], true)) {
            $status = 403;
        }
        throw new ApiHttpException("Bạn không có quyền truy cập tài nguyên này.", $status);
    }
}

function lab_actor_customer(array $user): array {
    $stmt = db()->prepare("SELECT * FROM customers WHERE user_id = :user_id LIMIT 1");
    $stmt->execute(["user_id" => (int) $user["id"]]);
    $customer = $stmt->fetch();
    if (!$customer) {
        throw new ApiHttpException("Không tìm thấy hồ sơ khách hàng.", 403);
    }
    return $customer;
}
