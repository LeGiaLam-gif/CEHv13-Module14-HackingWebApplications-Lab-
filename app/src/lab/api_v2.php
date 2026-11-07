<?php

require_once __DIR__ . "/../api.php";
require_once __DIR__ . "/../product_repository.php";
require_once __DIR__ . "/../order_repository.php";
require_once __DIR__ . "/../helpers.php";
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/audit.php";
require_once __DIR__ . "/input.php";
require_once __DIR__ . "/user_repository.php";
require_once __DIR__ . "/authz.php";
require_once __DIR__ . "/auth.php";

function lab_api_dispatch(string $path): void {
    lab_bootstrap();
    $method = (string) ($_SERVER["REQUEST_METHOD"] ?? "GET");
    try {
        if (lab_auth_dispatch($path, $method)) {
            return;
        }
        if (in_array($method, ["POST", "PUT", "PATCH", "DELETE"], true)) {
            api_verify_csrf();
        }
        $user = lab_require_authenticated_user();
        if ($path === "/api/v2/me" && $method === "GET") {
            lab_audit("allow", "profile_read", "user", (int) $user["id"]);
            api_success(lab_project_output("users", $user, $user, true));
        }
        if ($path === "/api/v2/me" && $method === "PATCH") {
            $input = lab_filter_input("users", $user, "update", api_request_data());
            lab_user_update_profile((int) $user["id"], $input);
            $customer = db()->prepare("UPDATE customers SET name = :name, phone = :phone WHERE user_id = :user_id");
            $customer->execute([
                "name" => $input["full_name"] ?? ($user["full_name"] ?: $user["username"]),
                "phone" => $input["phone"] ?? ($user["phone"] ?? ""),
                "user_id" => (int) $user["id"],
            ]);
            lab_audit("allow", "profile_update", "user", (int) $user["id"]);
            api_success(lab_project_output("users", lab_user_find((int) $user["id"]), $user, true));
        }
        if (preg_match('#^/api/v2/users(?:/([0-9]+)(?:/(role))?)?$#D', $path, $matches)) {
            lab_api_users($user, $method, $matches);
        }
        if (preg_match('#^/api/v2/addresses(?:/([0-9]+))?$#D', $path, $matches)) {
            lab_api_addresses($user, $method, $matches);
        }
        if (preg_match('#^/api/v2/orders(?:/([0-9]+)(?:/(cancel|payment))?)?$#D', $path, $matches)) {
            lab_api_orders($user, $method, $matches);
        }
        if (preg_match('#^/api/v2/products(?:/([0-9]+))?$#D', $path, $matches)) {
            lab_api_products($user, $method, $matches);
        }
        if ($path === "/api/v2/audit" && $method === "GET") {
            lab_require_permission($user, "audit.read", "BAC_VERTICAL", "audit");
            $rows = db()->query(
                "SELECT id, actor_user_id, method, route, target_type, target_id,
                        decision, reason, lab_mode, ip_address, created_at
                 FROM security_audit_log ORDER BY id DESC LIMIT 200"
            )->fetchAll();
            lab_audit("allow", "audit_read", "audit");
            api_success($rows);
        }
        throw new ApiHttpException("Không tìm thấy endpoint API v2.", 404);
    } catch (ApiHttpException $e) {
        if (empty($GLOBALS["lab_audit_recorded"])) {
            lab_audit("deny", "http_" . $e->status());
        }
        if ($e->status() === 405) {
            header("Allow: GET, POST, PATCH, PUT, DELETE");
        }
        api_error($e->getMessage(), $e->status(), $e->details());
    } catch (DomainException $e) {
        if (empty($GLOBALS["lab_audit_recorded"])) {
            lab_audit("deny", "validation_error");
        }
        api_error($e->getMessage(), 422);
    } catch (PDOException $e) {
        if (empty($GLOBALS["lab_audit_recorded"])) {
            lab_audit("deny", "database_error");
        }
        if (($e->errorInfo[0] ?? $e->getCode()) === "23000") {
            api_error("Giá trị đã tồn tại hoặc vi phạm ràng buộc dữ liệu.", 409);
        }
        error_log("Lab API database error: " . $e->getMessage());
        api_error("Đã xảy ra lỗi cơ sở dữ liệu.", 500);
    } catch (Throwable $e) {
        if (empty($GLOBALS["lab_audit_recorded"])) {
            lab_audit("deny", "internal_error");
        }
        error_log("Lab API error: " . $e->getMessage());
        api_error("Đã xảy ra lỗi khi xử lý yêu cầu.", 500);
    }
}

function lab_api_users(array $user, string $method, array $matches): void {
    $id = isset($matches[1]) && $matches[1] !== "" ? api_id($matches[1]) : null;
    $isRole = isset($matches[2]) && $matches[2] === "role";
    if ($id === null && !$isRole && $method === "GET") {
        lab_require_permission($user, "users.read_all", "users", "user");
        $rows = array_map(fn($row) => lab_project_output("users", $user, $row), lab_users_all());
        lab_audit("allow", "users_list");
        api_success($rows);
    }
    if ($id === null) {
        throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
    }
    $target = lab_user_find($id);
    if (!$target) {
        throw new ApiHttpException("Không tìm thấy người dùng.", 404);
    }
    if ($isRole && $method === "PUT") {
        lab_require_permission($user, "users.manage", "role", "user", $id);
        if ($id === (int) $user["id"]) {
            throw new ApiHttpException("Không thể tự hạ quyền hoặc đổi vai trò của chính mình.", 422);
        }
        $input = lab_filter_input("users", $user, "role", api_request_data());
        $roleId = api_integer($input["role_id"] ?? null);
        $stmt = db()->prepare("SELECT 1 FROM roles WHERE id = :id");
        $stmt->execute(["id" => $roleId]);
        if ($roleId === null || !$stmt->fetchColumn()) {
            throw new ApiHttpException("Vai trò không hợp lệ.", 422, ["fields" => ["role_id"]]);
        }
        db()->prepare("UPDATE users SET role_id = :role_id WHERE id = :id")->execute(["role_id" => $roleId, "id" => $id]);
        lab_audit("allow", "role_updated", "user", $id);
        api_success(lab_project_output("users", $user, lab_user_find($id)));
    }
    if ($method === "GET") {
        lab_require_owner(lab_can_access_user($user, $id, "read"), "user", $id);
        lab_audit("allow", "user_read", "user", $id);
        api_success(lab_project_output("users", $user, $target, $id === (int) $user["id"]));
    }
    if ($method === "PATCH") {
        lab_require_owner(lab_can_access_user($user, $id, "update"), "user", $id);
        if ($id !== (int) $user["id"] && !lab_vuln("BOLA")) {
            lab_require_permission($user, "users.manage", "users", "user", $id);
        }
        $rawInput = api_request_data();
        $canManage = lab_has_permission($user, "users.manage") && (int) $user["id"] !== $id;
        $input = lab_filter_input("users", $user, $canManage ? "admin_update" : "update", $rawInput);
        if ($canManage && array_key_exists("account_status", $input)) {
            lab_require_permission($user, "users.manage", "users", "user", $id);
            if (!in_array($input["account_status"], ["pending", "active", "suspended"], true)) {
                throw new ApiHttpException("account_status không hợp lệ.", 422, ["fields" => ["account_status"]]);
            }
        }
        lab_user_update_profile($id, $input);
        lab_audit("allow", "user_updated", "user", $id);
        api_success(lab_project_output("users", $user, lab_user_find($id), $id === (int) $user["id"]));
    }
    if ($method === "DELETE") {
        lab_require_permission($user, "users.delete", "delete_user", "user", $id);
        if ($id === (int) $user["id"]) {
            throw new ApiHttpException("Không thể tự xóa tài khoản đang đăng nhập.", 422);
        }
        db()->prepare("DELETE FROM users WHERE id = :id")->execute(["id" => $id]);
        lab_audit("allow", "user_deleted", "user", $id);
        api_success(["message" => "Đã xóa người dùng."]);
    }
    throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
}

function lab_api_addresses(array $user, string $method, array $matches): void {
    $id = isset($matches[1]) && $matches[1] !== "" ? api_id($matches[1]) : null;
    if ($id === null && $method === "GET") {
        $customer = lab_actor_customer($user);
        $stmt = db()->prepare("SELECT * FROM addresses WHERE customer_id = :customer_id ORDER BY id");
        $stmt->execute(["customer_id" => (int) $customer["id"]]);
        lab_audit("allow", "addresses_list", "customer", (int) $customer["id"]);
        api_success($stmt->fetchAll());
    }
    if ($id === null && $method === "POST") {
        if (($user["role_name"] ?? "") !== "customer") {
            throw new ApiHttpException("Thao tác này chỉ dành cho customer.", 403);
        }
        $input = lab_filter_input("addresses", $user, "create", api_request_data());
        $customer = lab_actor_customer($user);
        $values = lab_address_values($input);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($values["is_default"]) {
                $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE customer_id = :customer_id")
                    ->execute(["customer_id" => (int) $customer["id"]]);
            }
            $stmt = $pdo->prepare(
                "INSERT INTO addresses (customer_id, label, line1, city, phone, is_default)
                 VALUES (:customer_id, :label, :line1, :city, :phone, :is_default)"
            );
            $stmt->execute($values + ["customer_id" => (int) $customer["id"]]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        lab_audit("allow", "address_created", "address", $newId);
        api_success(lab_address_find($newId), 201);
    }
    if ($id === null) {
        throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
    }
    $address = lab_address_find($id);
    if (!$address) {
        throw new ApiHttpException("Không tìm thấy địa chỉ.", 404);
    }
    lab_require_owner(lab_can_access_address($user, $address, $method), "address", $id);
    if ($method === "GET") {
        lab_audit("allow", "address_read", "address", $id);
        api_success($address);
    }
    if ($method === "PATCH") {
        $input = lab_filter_input("addresses", $user, "update", api_request_data());
        $values = lab_address_values(array_merge($address, $input));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($values["is_default"]) {
                $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE customer_id = :customer_id")
                    ->execute(["customer_id" => (int) $address["customer_id"]]);
            }
            $stmt = $pdo->prepare(
                "UPDATE addresses SET label = :label, line1 = :line1, city = :city,
                    phone = :phone, is_default = :is_default WHERE id = :id"
            );
            $stmt->execute($values + ["id" => $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        lab_audit("allow", "address_updated", "address", $id);
        api_success(lab_address_find($id));
    }
    if ($method === "DELETE") {
        db()->prepare("DELETE FROM addresses WHERE id = :id")->execute(["id" => $id]);
        lab_audit("allow", "address_deleted", "address", $id);
        api_success(["message" => "Đã xóa địa chỉ."]);
    }
    throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
}

function lab_address_find(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM addresses WHERE id = :id LIMIT 1");
    $stmt->execute(["id" => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function lab_address_values(array $input): array {
    $default = $input["is_default"] ?? 0;
    if (!in_array($default, [true, false, 0, 1, "0", "1"], true)) {
        throw new ApiHttpException("is_default không hợp lệ.", 422);
    }
    return [
        "label" => api_required_string($input + ["label" => "home"], "label", "Nhãn", 50),
        "line1" => api_required_string($input, "line1", "Địa chỉ", 255),
        "city" => api_required_string($input, "city", "Thành phố", 100),
        "phone" => api_optional_string($input, "phone", "Số điện thoại", 30),
        "is_default" => in_array($default, [true, 1, "1"], true) ? 1 : 0,
    ];
}

function lab_api_orders(array $user, string $method, array $matches): void {
    $id = isset($matches[1]) && $matches[1] !== "" ? api_id($matches[1]) : null;
    $action = $matches[2] ?? "";
    if ($id === null && $method === "POST") {
        if (($user["role_name"] ?? "") !== "customer") {
            throw new ApiHttpException("Chỉ customer được tạo đơn hàng.", 403);
        }
        $input = lab_filter_input("orders", $user, "create", api_request_data());
        $itemsInput = $input["items"] ?? null;
        if (!is_array($itemsInput) || !$itemsInput) {
            throw new ApiHttpException("Đơn hàng phải có danh sách items.", 422, ["fields" => ["items"]]);
        }
        $items = [];
        foreach ($itemsInput as $item) {
            if ($item instanceof stdClass) $item = (array) $item;
            if (!is_array($item)) throw new ApiHttpException("Mỗi item phải là object.", 422);
            $productId = api_integer($item["product_id"] ?? null);
            $quantity = api_integer($item["quantity"] ?? null);
            if ($productId === null || $productId <= 0 || $quantity === null || $quantity <= 0) {
                throw new ApiHttpException("Sản phẩm hoặc số lượng không hợp lệ.", 422);
            }
            $items[$productId] = ($items[$productId] ?? 0) + $quantity;
        }
        $customer = lab_actor_customer($user);
        $addressId = api_integer($input["address_id"] ?? null);
        $address = $addressId === null ? null : lab_address_find($addressId);
        if (!$address) {
            throw new ApiHttpException("Địa chỉ không hợp lệ.", 422, ["fields" => ["address_id"]]);
        }
        lab_require_owner(
            (int) $address["customer_id"] === (int) $customer["id"],
            "address",
            $addressId
        );
        $id = order_create([
            "customer_id" => (int) $customer["id"],
            "address_snapshot" => (string) $address["line1"] . ", " . (string) $address["city"],
            "phone_snapshot" => (string) ($address["phone"] ?: $customer["phone"]),
        ], $items);
        if (lab_vuln("MASS_ASSIGN")) {
            lab_assign_all("orders", $id, $input);
            $created = order_find($id);
            foreach ($itemsInput as $index => $item) {
                $item = $item instanceof stdClass ? (array) $item : $item;
                if (!empty($created["items"][$index]["id"]) && is_array($item)) {
                    lab_assign_all("order_items", (int) $created["items"][$index]["id"], $item);
                }
            }
        }
        lab_audit("allow", "order_created", "order", $id);
        api_success(order_find($id), 201);
    }
    if ($id === null && $method === "GET") {
        if (lab_has_permission($user, "orders.read_all")) {
            $rows = order_all();
        } else {
            $customer = lab_actor_customer($user);
            $stmt = db()->prepare("SELECT * FROM orders WHERE customer_id = :customer_id ORDER BY id DESC");
            $stmt->execute(["customer_id" => (int) $customer["id"]]);
            $rows = $stmt->fetchAll();
        }
        lab_audit("allow", "orders_list");
        api_success($rows);
    }
    if ($id === null) {
        throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
    }
    $order = order_find($id);
    if (!$order) {
        throw new ApiHttpException("Không tìm thấy đơn hàng.", 404);
    }
    if ($action === "cancel" && $method === "POST") {
        lab_require_owner(lab_can_access_order($user, $order, "cancel"), "order", $id);
        if (!order_can_cancel((string) $order["status"])) {
            throw new ApiHttpException("Không thể hủy đơn hàng ở trạng thái hiện tại.", 422);
        }
        order_update_status($id, "cancelled");
        lab_audit("allow", "order_cancelled", "order", $id);
        api_success(order_find($id));
    }
    if ($action === "payment" && $method === "PUT") {
        lab_require_permission($user, "orders.set_payment", "BAC_VERTICAL", "order", $id);
        $input = lab_filter_input("orders", $user, "payment", api_request_data());
        $status = $input["payment_status"] ?? null;
        if (!in_array($status, ["unpaid", "paid", "refunded"], true)) {
            throw new ApiHttpException("payment_status không hợp lệ.", 422, ["fields" => ["payment_status"]]);
        }
        db()->prepare("UPDATE orders SET payment_status = :status WHERE id = :id")->execute(["status" => $status, "id" => $id]);
        lab_audit("allow", "payment_status_updated", "order", $id);
        api_success(order_find($id));
    }
    if ($method === "GET") {
        lab_require_owner(lab_can_access_order($user, $order, "read"), "order", $id);
        lab_audit("allow", "order_read", "order", $id);
        api_success($order);
    }
    if ($method === "PATCH") {
        $input = lab_filter_input("orders", $user, "update", api_request_data());
        if (array_key_exists("status", $input)) {
            lab_require_permission($user, "orders.update_status", "BAC_VERTICAL", "order", $id);
            lab_require_owner(lab_can_access_order($user, $order, "update_status"), "order", $id);
            if (!is_string($input["status"])) {
                throw new ApiHttpException("status không hợp lệ.", 422);
            }
            order_update_status($id, $input["status"]);
        } elseif (array_key_exists("shipping_address", $input)) {
            lab_require_owner(lab_can_access_order($user, $order, "update"), "order", $id);
            if (($user["role_name"] ?? "") !== "customer" || $order["status"] !== "new") {
                throw new ApiHttpException("Không thể cập nhật địa chỉ giao hàng.", 403);
            }
            $shippingAddress = api_required_string($input, "shipping_address", "Địa chỉ giao hàng", 500);
            db()->prepare("UPDATE orders SET shipping_address = :address WHERE id = :id")
                ->execute(["address" => $shippingAddress, "id" => $id]);
        } else {
            throw new ApiHttpException("Không có trường cập nhật hợp lệ.", 422);
        }
        if (lab_vuln("MASS_ASSIGN")) lab_assign_all("orders", $id, $input);
        lab_audit("allow", "order_updated", "order", $id);
        api_success(order_find($id));
    }
    throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
}

function lab_api_products(array $user, string $method, array $matches): void {
    $id = isset($matches[1]) && $matches[1] !== "" ? api_id($matches[1]) : null;
    if ($id === null && $method === "GET") {
        $keyword = $_GET["keyword"] ?? "";
        $categoryId = filter_var($_GET["category_id"] ?? 0, FILTER_VALIDATE_INT);
        $brandId = filter_var($_GET["brand_id"] ?? 0, FILTER_VALIDATE_INT);
        $page = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($_GET["per_page"] ?? 20, FILTER_VALIDATE_INT);
        if (!is_string($keyword) || $categoryId === false || $categoryId < 0 || $brandId === false || $brandId < 0 || $page === false || $page < 1 || $perPage === false || $perPage < 1) {
            throw new ApiHttpException("Tham số lọc hoặc phân trang không hợp lệ.", 400);
        }
        $perPage = min(100, $perPage);
        $total = product_count_filtered($keyword, $categoryId, $brandId);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) throw new ApiHttpException("Trang yêu cầu không tồn tại.", 400);
        $rows = product_paginate($keyword, $categoryId, $perPage, ($page - 1) * $perPage, $brandId);
        foreach ($rows as &$row) $row["images"] = product_image_all((int) $row["id"]);
        unset($row);
        lab_audit("allow", "products_list");
        api_success($rows, 200, ["page" => $page, "per_page" => $perPage, "total" => $total, "total_pages" => $totalPages]);
    }
    if ($id === null && $method === "POST") {
        lab_require_permission($user, "products.update", "products", "product");
        $input = lab_filter_input("products", $user, "create", api_request_data());
        if (array_key_exists("quantity", $input)) {
            throw new ApiHttpException("Không thể cập nhật tồn kho trực tiếp.", 422, ["fields" => ["quantity"]]);
        }
        if (isset($input["unit_price"]) && !lab_vuln("MASS_ASSIGN")) {
            lab_require_permission($user, "products.set_price", "BAC_VERTICAL", "product");
        }
        $name = api_required_string($input, "name", "Tên sản phẩm", 200);
        $categoryId = api_integer($input["category_id"] ?? null);
        if ($categoryId === null || $categoryId <= 0 || !category_find($categoryId)) throw new ApiHttpException("Danh mục không hợp lệ.", 422);
        $brandId = $input["brand_id"] ?? null;
        if ($brandId !== null && $brandId !== "" && (!is_numeric($brandId) || !brand_find((int) $brandId))) throw new ApiHttpException("Thương hiệu không hợp lệ.", 422);
        $price = $input["unit_price"] ?? 0;
        if (!is_numeric($price) || (float) $price < 0) throw new ApiHttpException("Đơn giá không hợp lệ.", 422);
        $product = [
            "category_id" => $categoryId,
            "brand_id" => $brandId === null || $brandId === "" ? null : (int) $brandId,
            "name" => $name,
            "sku" => api_optional_string($input, "sku", "SKU", 100),
            "description" => api_optional_string($input, "description", "Mô tả", 2000),
            "quantity" => 0,
            "unit_price" => (float) $price,
            "image_path" => null,
            "status" => 1,
        ];
        $newId = product_create($product);
        lab_audit("allow", "product_created", "product", $newId);
        api_success(product_find($newId), 201);
    }
    if ($id === null) throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
    $product = product_find($id);
    if (!$product) throw new ApiHttpException("Không tìm thấy sản phẩm.", 404);
    if ($method === "GET") {
        $product["images"] = product_image_all($id);
        lab_audit("allow", "product_read", "product", $id);
        api_success($product);
    }
    if ($method === "PATCH") {
        lab_require_permission($user, "products.update", "products", "product", $id);
        $input = lab_filter_input("products", $user, "update", api_request_data());
        if (array_key_exists("quantity", $input)
            && api_integer($input["quantity"]) !== (int) $product["quantity"]) {
            throw new ApiHttpException("Không thể cập nhật tồn kho trực tiếp.", 422, ["fields" => ["quantity"]]);
        }
        if ((array_key_exists("unit_price", $input) || array_key_exists("status", $input)) && !lab_vuln("MASS_ASSIGN")) {
            lab_require_permission($user, "products.set_price", "BAC_VERTICAL", "product", $id);
        }
        $current = array_merge($product, $input);
        $categoryId = api_integer($current["category_id"] ?? null);
        $brandValue = $current["brand_id"] ?? null;
        $brandId = $brandValue === "" || $brandValue === null ? null : api_integer($brandValue);
        $price = $current["unit_price"] ?? null;
        if ($categoryId === null || $categoryId <= 0 || !category_find($categoryId)
            || ($brandValue !== null && $brandValue !== "" && ($brandId === null || !brand_find($brandId)))
            || !is_numeric($price) || (float) $price < 0) {
            throw new ApiHttpException("Dữ liệu sản phẩm không hợp lệ.", 422);
        }
        $payload = [
            "category_id" => $categoryId,
            "brand_id" => $brandId,
            "name" => api_required_string($current, "name", "Tên sản phẩm", 200),
            "sku" => api_optional_string($current, "sku", "SKU", 100),
            "description" => api_optional_string($current, "description", "Mô tả", 2000),
            "quantity" => (int) $product["quantity"],
            "unit_price" => (float) $price,
            "image_path" => $product["image_path"],
            "status" => api_status($current, $product["status"]),
        ];
        product_update($id, $payload);
        if (lab_vuln("MASS_ASSIGN")) {
            unset($input["quantity"]);
            lab_assign_all("products", $id, $input);
        }
        lab_audit("allow", "product_updated", "product", $id);
        api_success(product_find($id));
    }
    if ($method === "DELETE") {
        lab_require_permission($user, "products.delete", "delete_product", "product", $id);
        $images = product_image_all($id);
        product_delete($id);
        foreach ($images as $image) {
            if (!product_image_path_in_use((string) $image["image_path"])) {
                delete_product_image((string) $image["image_path"]);
            }
        }
        lab_audit("allow", "product_deleted", "product", $id);
        api_success(["message" => "Đã xóa sản phẩm."]);
    }
    throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
}
