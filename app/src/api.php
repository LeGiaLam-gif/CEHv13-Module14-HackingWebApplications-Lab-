<?php

class ApiHttpException extends RuntimeException {
    private int $status;
    private array $details;

    public function __construct(string $message, int $status, array $details = []) {
        parent::__construct($message);
        $this->status = $status;
        $this->details = $details;
    }

    public function status(): int {
        return $this->status;
    }

    public function details(): array {
        return $this->details;
    }
}

function api_respond(int $status, array $body = []): void {
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");
    if ($status !== 204) {
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

function api_success($data, int $status = 200, array $meta = []): void {
    $body = ["data" => $data];
    if ($meta) {
        $body["meta"] = $meta;
    }
    api_respond($status, $body);
}

function api_error(string $message, int $status, array $details = []): void {
    $error = ["message" => $message];
    if ($details) {
        $error["details"] = $details;
    }
    api_respond($status, ["error" => $error]);
}

function api_request_data(): array {
    $contentType = strtolower(trim(explode(";", $_SERVER["CONTENT_TYPE"] ?? "")[0]));
    if ($contentType !== "application/json") {
        throw new ApiHttpException("Content-Type phải là application/json.", 415);
    }

    $raw = file_get_contents("php://input");
    try {
        $data = json_decode($raw === false ? "" : $raw, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new ApiHttpException("Nội dung JSON không hợp lệ.", 400);
    }

    if (!$data instanceof stdClass) {
        throw new ApiHttpException("Nội dung yêu cầu phải là một JSON object.", 400);
    }

    return (array) $data;
}

function api_require_admin(): void {
    if (empty($_SESSION["admin_id"])) {
        throw new ApiHttpException("Vui lòng đăng nhập để sử dụng API.", 401);
    }
}

function api_verify_csrf(): void {
    $submitted = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    if ($submitted === "") {
        $submitted = $_POST["_csrf_token"] ?? "";
    }
    if (!is_string($submitted) || empty($_SESSION["_csrf_token"])
        || !hash_equals($_SESSION["_csrf_token"], $submitted)) {
        throw new ApiHttpException("CSRF token không hợp lệ hoặc đã hết hạn.", 419);
    }
}

function api_id(string $value): int {
    if (!preg_match('/^[1-9][0-9]*$/D', $value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
        throw new ApiHttpException("Mã tài nguyên không hợp lệ.", 400);
    }
    return (int) $value;
}

function api_integer($value): ?int {
    if (!is_int($value) && !is_string($value)) {
        return null;
    }
    if (is_string($value) && !preg_match('/^-?[0-9]+$/D', $value)) {
        return null;
    }
    $number = filter_var($value, FILTER_VALIDATE_INT);
    return $number === false ? null : $number;
}

function api_required_string(array $input, string $key, string $label, int $maxLength): string {
    $value = $input[$key] ?? null;
    if (!is_string($value)) {
        throw new ApiHttpException($label . " phải là chuỗi.", 422);
    }
    $value = trim($value);
    if ($value === "") {
        throw new ApiHttpException($label . " không được để trống.", 422);
    }
    if (preg_match('/^.{0,' . $maxLength . '}$/us', $value) !== 1) {
        throw new ApiHttpException($label . " vượt quá độ dài cho phép.", 422);
    }
    return $value;
}

function api_optional_string(array $input, string $key, string $label, int $maxLength): ?string {
    $value = $input[$key] ?? null;
    if ($value === null || $value === "") {
        return null;
    }
    if (!is_string($value)) {
        throw new ApiHttpException($label . " phải là chuỗi.", 422);
    }
    $value = trim($value);
    if (preg_match('/^.{0,' . $maxLength . '}$/us', $value) !== 1) {
        throw new ApiHttpException($label . " vượt quá độ dài cho phép.", 422);
    }
    return $value === "" ? null : $value;
}

function api_status(array $input, $default = 1): int {
    $value = $input["status"] ?? $default;
    if ($value === true || $value === 1 || $value === "1") {
        return 1;
    }
    if ($value === false || $value === 0 || $value === "0") {
        return 0;
    }
    throw new ApiHttpException("Trạng thái chỉ nhận true/false hoặc 1/0.", 422);
}

function api_resource_payload(string $resource, array $input, ?array $current = null): array {
    $input = $current === null ? $input : array_merge($current, $input);

    if ($resource === "categories" || $resource === "brands") {
        $name = api_required_string($input, "name", "Tên", 150);
        $description = api_optional_string($input, "description", "Mô tả", 2000);
        $status = api_status($input, $current["status"] ?? 1);
        if ($resource === "brands" && brand_name_exists($name, (int) ($current["id"] ?? 0))) {
            throw new ApiHttpException("Tên thương hiệu đã tồn tại.", 409);
        }
        return [$name, $description, $status];
    }

    if ($resource === "customers") {
        $customer = [
            "name" => api_required_string($input, "name", "Tên khách hàng", 150),
            "phone" => api_required_string($input, "phone", "Số điện thoại", 30),
            "email" => api_optional_string($input, "email", "Email", 150),
            "address" => api_required_string($input, "address", "Địa chỉ", 500),
            "notes" => api_optional_string($input, "notes", "Ghi chú", 500),
        ];
        if ($customer["email"] !== null && filter_var($customer["email"], FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiHttpException("Email không hợp lệ.", 422);
        }
        return $customer;
    }

    if ($resource === "products") {
        $name = api_required_string($input, "name", "Tên sản phẩm", 200);
        $categoryId = api_integer($input["category_id"] ?? null);
        if ($categoryId === null || $categoryId <= 0 || !category_find($categoryId)) {
            throw new ApiHttpException("Danh mục sản phẩm không hợp lệ.", 422);
        }

        $brandValue = $input["brand_id"] ?? null;
        if ($brandValue === "" || $brandValue === 0 || $brandValue === "0") {
            $brandId = null;
        } elseif ($brandValue === null) {
            $brandId = null;
        } else {
            $brandId = api_integer($brandValue);
            if ($brandId === null || $brandId <= 0 || !brand_find($brandId)) {
                throw new ApiHttpException("Thương hiệu không hợp lệ.", 422);
            }
            $brandId = (int) $brandId;
        }

        $quantity = api_integer($input["quantity"] ?? null);
        if ($quantity === null || $quantity < 0 || $quantity > 2147483647) {
            throw new ApiHttpException("Số lượng phải là số nguyên từ 0 đến 2147483647.", 422);
        }
        if ($current !== null && array_key_exists("quantity", $input)
            && (int) $quantity !== (int) $current["quantity"]) {
            throw new ApiHttpException("Không thể cập nhật tồn kho trực tiếp; hãy tạo giao dịch tại /api/inventory.", 422);
        }
        $price = $input["unit_price"] ?? null;
        if ((!is_string($price) && !is_int($price) && !is_float($price))
            || !is_numeric($price) || !is_finite((float) $price) || (float) $price < 0
            || (float) $price > 9999999999999.99) {
            throw new ApiHttpException("Đơn giá phải là số không âm hợp lệ.", 422);
        }

        return [
            "category_id" => (int) $categoryId,
            "brand_id" => $brandId,
            "name" => $name,
            "sku" => api_optional_string($input, "sku", "SKU", 100),
            "description" => api_optional_string($input, "description", "Mô tả", 2000),
            "quantity" => (int) $quantity,
            "unit_price" => (float) $price,
            "image_path" => $current["image_path"] ?? null,
            "status" => api_status($input, $current["status"] ?? 1),
        ];
    }

    throw new ApiHttpException("Loại tài nguyên không được hỗ trợ.", 404);
}

function api_list(string $resource): array {
    if ($resource === "categories") {
        return category_all();
    }
    if ($resource === "brands") {
        return brand_all();
    }
    if ($resource === "customers") {
        return customer_all();
    }
    if ($resource === "orders") {
        return order_all();
    }
    if ($resource === "inventory") {
        return inventory_movements();
    }
    if ($resource === "activity-logs") {
        return activity_log_recent();
    }
    throw new ApiHttpException("Không tìm thấy endpoint API.", 404);
}

function api_find(string $resource, int $id): ?array {
    if ($resource === "categories") {
        return category_find($id);
    }
    if ($resource === "brands") {
        return brand_find($id);
    }
    if ($resource === "customers") {
        return customer_find($id);
    }
    if ($resource === "products") {
        $product = product_find($id);
        if ($product !== null) {
            $product["images"] = product_image_all($id);
        }
        return $product;
    }
    if ($resource === "orders") {
        return order_find($id);
    }
    throw new ApiHttpException("Không tìm thấy endpoint API.", 404);
}

function api_collection(string $resource): void {
    $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
    if ($resource === "products" && $method === "GET") {
        $keyword = $_GET["keyword"] ?? "";
        if (!is_string($keyword)) {
            throw new ApiHttpException("Từ khóa không hợp lệ.", 400);
        }
        $categoryId = filter_var($_GET["category_id"] ?? 0, FILTER_VALIDATE_INT);
        $brandId = filter_var($_GET["brand_id"] ?? 0, FILTER_VALIDATE_INT);
        $page = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($_GET["per_page"] ?? 20, FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId < 0 || $brandId === false || $brandId < 0
            || $page === false || $page < 1 || $perPage === false || $perPage < 1) {
            throw new ApiHttpException("Tham số lọc hoặc phân trang không hợp lệ.", 400);
        }
        $perPage = min(100, $perPage);
        $total = product_count_filtered(trim($keyword), $categoryId, $brandId);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            throw new ApiHttpException("Trang yêu cầu không tồn tại.", 400);
        }
        $products = product_paginate(
            trim($keyword),
            $categoryId,
            $perPage,
            ($page - 1) * $perPage,
            $brandId
        );
        foreach ($products as &$product) {
            $product["images"] = product_image_all((int) $product["id"]);
        }
        unset($product);
        api_success($products, 200, [
            "page" => $page,
            "per_page" => $perPage,
            "total" => $total,
            "total_pages" => $totalPages,
        ]);
    }

    if ($method === "GET") {
        api_success(api_list($resource));
    }
    if (!in_array($method, ["POST"], true)) {
        throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
    }

    $input = api_request_data();
    if ($resource === "categories" || $resource === "brands") {
        [$name, $description, $status] = api_resource_payload($resource, $input);
        if ($resource === "categories") {
            $id = category_create($name, $description, $status);
            activity_log_create("create", "category", $id, "Tạo danh mục \"" . $name . "\" qua REST API.");
        } else {
            $id = brand_create($name, $description, $status);
            activity_log_create("create", "brand", $id, "Tạo thương hiệu \"" . $name . "\" qua REST API.");
        }
        api_success(api_find($resource, $id), 201);
    }
    if ($resource === "products") {
        $product = api_resource_payload("products", $input);
        $id = product_create($product);
        activity_log_create("create", "product", $id, "Tạo sản phẩm \"" . $product["name"] . "\" qua REST API.");
        api_success(api_find("products", $id), 201);
    }
    if ($resource === "customers") {
        $customer = api_resource_payload("customers", $input);
        $id = customer_create($customer);
        activity_log_create("create", "customer", $id, "Tạo khách hàng \"" . $customer["name"] . "\" qua REST API.");
        api_success(api_find("customers", $id), 201);
    }
    if ($resource === "orders") {
        $customer = [
            "customer_id" => $input["customer_id"] ?? null,
            "name" => $input["customer_name"] ?? null,
            "phone" => $input["customer_phone"] ?? null,
            "address" => $input["shipping_address"] ?? null,
        ];
        $itemsInput = $input["items"] ?? null;
        if (!is_array($itemsInput) || !$itemsInput) {
            throw new ApiHttpException("Đơn hàng phải có danh sách items.", 422);
        }
        $items = [];
        foreach ($itemsInput as $item) {
            if (!$item instanceof stdClass) {
                throw new ApiHttpException("Mỗi item phải là một JSON object.", 422);
            }
            $item = (array) $item;
            $productId = api_integer($item["product_id"] ?? null);
            $quantity = api_integer($item["quantity"] ?? null);
            if ($productId === null || $productId <= 0
                || $quantity === null || $quantity <= 0
                || isset($items[$productId])) {
                throw new ApiHttpException("Danh sách sản phẩm hoặc số lượng không hợp lệ.", 422);
            }
            $items[$productId] = $quantity;
        }
        $id = order_create($customer, $items, (int) $_SESSION["admin_id"]);
        activity_log_create("create", "order", $id, "Tạo đơn hàng #" . $id . " qua REST API.");
        api_success(api_find("orders", $id), 201);
    }
    if ($resource === "inventory") {
        $productId = api_integer($input["product_id"] ?? null);
        $quantity = api_integer($input["quantity"] ?? null);
        $type = $input["movement_type"] ?? null;
        $note = api_optional_string($input, "note", "Ghi chú", 500) ?? "";
        if ($productId === null || $productId <= 0
            || $quantity === null || $quantity <= 0 || $quantity > 2147483647
            || !is_string($type) || !in_array($type, ["in", "out"], true)) {
            throw new ApiHttpException("Sản phẩm, số lượng hoặc loại giao dịch không hợp lệ.", 422);
        }
        inventory_create_movement(
            (int) $productId,
            $type,
            (int) $quantity,
            $note,
            (int) $_SESSION["admin_id"]
        );
        activity_log_create(
            "inventory_" . $type,
            "product",
            (int) $productId,
            ($type === "in" ? "Nhập" : "Xuất") . " " . $quantity . " sản phẩm qua REST API."
        );
        api_success(["message" => "Đã ghi nhận giao dịch kho."]);
    }

    throw new ApiHttpException("Không tìm thấy endpoint API.", 404);
}

function api_item(string $resource, int $id): void {
    $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
    $current = api_find($resource, $id);
    if ($current === null) {
        throw new ApiHttpException("Không tìm thấy tài nguyên.", 404);
    }
    if ($method === "GET") {
        api_success($current);
    }

    if ($method === "PUT" || $method === "PATCH") {
        $input = api_request_data();
        if ($resource === "categories" || $resource === "brands") {
            [$name, $description, $status] = api_resource_payload($resource, $input, $current);
            if ($resource === "categories") {
                category_update($id, $name, $description, $status);
            } else {
                brand_update($id, $name, $description, $status);
            }
            $entityType = ["categories" => "category", "brands" => "brand"][$resource];
            activity_log_create("update", $entityType, $id, "Cập nhật qua REST API.");
        } elseif ($resource === "products") {
            $product = api_resource_payload("products", $input, $current);
            product_update($id, $product);
            activity_log_create("update", "product", $id, "Cập nhật sản phẩm \"" . $product["name"] . "\" qua REST API.");
        } elseif ($resource === "customers") {
            $customer = api_resource_payload("customers", $input, $current);
            customer_update($id, $customer);
            activity_log_create("update", "customer", $id, "Cập nhật khách hàng \"" . $customer["name"] . "\" qua REST API.");
        } elseif ($resource === "orders") {
            $status = $input["status"] ?? null;
            if (!is_string($status)) {
                throw new ApiHttpException("Trường status là bắt buộc và phải là chuỗi.", 422);
            }
            order_update_status($id, $status, (int) $_SESSION["admin_id"]);
            activity_log_create(
                $status === "cancelled" ? "cancel" : "status",
                "order",
                $id,
                "Cập nhật trạng thái đơn hàng từ \"" . $current["status"] . "\" sang \"" . $status . "\" qua REST API."
            );
        } else {
            throw new ApiHttpException("Không thể cập nhật loại tài nguyên này.", 405);
        }
        api_success(api_find($resource, $id));
    }

    if ($method === "DELETE") {
        if ($resource === "categories") {
            category_delete($id);
        } elseif ($resource === "brands") {
            if (brand_product_count($id) > 0) {
                throw new ApiHttpException("Không thể xóa thương hiệu đang được gán cho sản phẩm.", 409);
            }
            brand_delete($id);
        } elseif ($resource === "customers") {
            $stmt = db()->prepare("DELETE FROM customers WHERE id = :id");
            $stmt->execute(["id" => $id]);
        } elseif ($resource === "products") {
            $images = product_image_all($id);
            product_delete($id);
            foreach ($images as $image) {
                if (!product_image_path_in_use((string) $image["image_path"])) {
                    delete_product_image((string) $image["image_path"]);
                }
            }
        } else {
            throw new ApiHttpException("Không thể xóa loại tài nguyên này.", 405);
        }
        $entityType = [
            "categories" => "category",
            "brands" => "brand",
            "customers" => "customer",
            "products" => "product",
        ][$resource];
        activity_log_create("delete", $entityType, $id, "Xóa qua REST API.");
        api_success(["message" => "Đã xóa tài nguyên."]);
    }

    throw new ApiHttpException("Phương thức không được hỗ trợ.", 405);
}

function api_dispatch(string $path): void {
    if ($path === "/api/auth/login" && ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        try {
            $input = api_request_data();
            $username = $input["username"] ?? null;
            $password = $input["password"] ?? null;
            if (!is_string($username) || !is_string($password)) {
                throw new ApiHttpException("Username và password phải là chuỗi.", 422);
            }
            $result = admin_login(trim($username), $password);
            if (!$result["success"]) {
                throw new ApiHttpException("Thông tin đăng nhập không chính xác.", 401);
            }
            activity_log_create("login", "admin", (int) $_SESSION["admin_id"], "Đăng nhập qua REST API.");
            api_success(["csrf_token" => csrf_token()]);
        } catch (ApiHttpException $e) {
            api_error($e->getMessage(), $e->status(), $e->details());
        } catch (Throwable $e) {
            error_log("REST API error: " . $e->getMessage());
            api_error("Đã xảy ra lỗi khi xử lý yêu cầu.", 500);
        }
    }

    try {
        if ($path === "/api/auth/csrf-token" && ($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") {
            api_require_admin();
            api_success(["csrf_token" => csrf_token()]);
        }

        api_require_admin();
        $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
        if (in_array($method, ["POST", "PUT", "PATCH", "DELETE"], true)) {
            api_verify_csrf();
        }

        if ($path === "/api/auth/logout" && $method === "POST") {
            activity_log_create("logout", "admin", (int) $_SESSION["admin_id"], "Đăng xuất qua REST API.");
            $_SESSION = [];
            session_destroy();
            api_success(["message" => "Đã đăng xuất."]);
        }

        $segments = explode("/", trim(substr($path, strlen("/api")), "/"));
        $resource = $segments[0] ?? "";
        $allowed = ["products", "categories", "brands", "customers", "orders", "inventory", "activity-logs"];
        if (!in_array($resource, $allowed, true) || count($segments) > 2 || $resource === "") {
            throw new ApiHttpException("Không tìm thấy endpoint API.", 404);
        }

        if ($resource === "inventory" && count($segments) === 2) {
            throw new ApiHttpException("Không tìm thấy endpoint API.", 404);
        }
        if ($resource === "activity-logs" && count($segments) === 2) {
            throw new ApiHttpException("Không tìm thấy endpoint API.", 404);
        }

        if (count($segments) === 1) {
            api_collection($resource);
        }
        api_item($resource, api_id($segments[1]));
    } catch (ApiHttpException $e) {
        if ($e->status() === 405) {
            header("Allow: GET, POST, PUT, PATCH, DELETE");
        }
        api_error($e->getMessage(), $e->status(), $e->details());
    } catch (DomainException $e) {
        api_error($e->getMessage(), 422);
    } catch (PDOException $e) {
        if (($e->errorInfo[0] ?? $e->getCode()) === "23000") {
            api_error("Thao tác vi phạm ràng buộc dữ liệu hoặc giá trị đã tồn tại.", 409);
        }
        error_log("REST API database error: " . $e->getMessage());
        api_error("Đã xảy ra lỗi cơ sở dữ liệu.", 500);
    } catch (Throwable $e) {
        error_log("REST API error: " . $e->getMessage());
        api_error("Đã xảy ra lỗi khi xử lý yêu cầu.", 500);
    }
}
