<?php
require_once __DIR__ . "/../src/auth.php";
require_once __DIR__ . "/../src/password_reset.php";
require_once __DIR__ . "/../src/excel_export.php";
require_once __DIR__ . "/../src/category_repository.php";
require_once __DIR__ . "/../src/brand_repository.php";
require_once __DIR__ . "/../src/product_repository.php";
require_once __DIR__ . "/../src/order_repository.php";
require_once __DIR__ . "/../src/customer_repository.php";
require_once __DIR__ . "/../src/inventory_repository.php";
require_once __DIR__ . "/../src/activity_repository.php";
require_once __DIR__ . "/../src/api.php";

$path = rtrim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");
$path = $path === "" ? "/" : $path;

function view(string $view, array $data = []): void {
    extract($data);
    require __DIR__ . "/../views/layouts/header.php";
    require __DIR__ . "/../views/" . $view . ".php";
    require __DIR__ . "/../views/layouts/footer.php";
}
function validate_product_input(array $input): array {
    $errors = [];
    if (trim($input["name"] ?? "") === "") $errors[] = "Tên sản phẩm không được để trống.";
    if ((int)($input["category_id"] ?? 0) <= 0) $errors[] = "Vui lòng chọn danh mục sản phẩm.";
    $brandValue = $input["brand_id"] ?? "";
    if ($brandValue !== "" && (!is_string($brandValue) && !is_int($brandValue) || filter_var($brandValue, FILTER_VALIDATE_INT) === false || (int) $brandValue <= 0 || !brand_find((int) $brandValue))) {
        $errors[] = "Thương hiệu đã chọn không hợp lệ.";
    }
    if ((int)($input["quantity"] ?? -1) < 0) $errors[] = "Số lượng phải lớn hơn hoặc bằng 0.";
    if ((float)($input["unit_price"] ?? -1) < 0) $errors[] = "Đơn giá phải lớn hơn hoặc bằng 0.";
    return $errors;
}
function validate_order_input(array $input): array {
    $errors = [];
    $customer = [];
    $customerIdValue = $input["customer_id"] ?? "";
    $customerIdValue = is_string($customerIdValue) || is_int($customerIdValue) ? (string) $customerIdValue : "";
    $customerId = $customerIdValue === "" ? 0 : filter_var($customerIdValue, FILTER_VALIDATE_INT);
    if ($customerId === false || $customerId < 0) {
        $errors[] = "Khách hàng đã chọn không hợp lệ.";
        $customerId = 0;
    }

    $fields = [
        "customer_name" => ["name", "Tên người nhận", 150],
        "customer_phone" => ["phone", "Số điện thoại", 30],
        "shipping_address" => ["address", "Địa chỉ giao hàng", 500],
    ];

    foreach ($fields as $field => [$key, $label, $maxLength]) {
        $value = $input[$field] ?? "";
        $value = is_string($value) ? trim($value) : "";
        if ($customerId === 0 && $value === "") {
            $errors[] = $label . " không được để trống.";
        } elseif ($value !== "" && preg_match('/^.{0,' . $maxLength . '}$/us', $value) !== 1) {
            $errors[] = $label . " vượt quá độ dài cho phép.";
        }
        $customer[$key] = $value;
    }

    $customer["customer_id"] = $customerId;

    $items = [];
    $submittedItems = $input["items"] ?? [];
    if (!is_array($submittedItems)) {
        $submittedItems = [];
        $errors[] = "Danh sách sản phẩm không hợp lệ.";
    }

    foreach ($submittedItems as $item) {
        if (!is_array($item)) {
            $errors[] = "Một dòng sản phẩm không hợp lệ.";
            continue;
        }
        $productValue = $item["product_id"] ?? "";
        $quantityValue = $item["quantity"] ?? "";
        $productValue = is_string($productValue) || is_int($productValue) ? (string) $productValue : "";
        $quantityValue = is_string($quantityValue) || is_int($quantityValue) ? (string) $quantityValue : "";
        if ($productValue === "" && $quantityValue === "") {
            continue;
        }

        $productId = filter_var($productValue, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
            $errors[] = "Mỗi sản phẩm phải được chọn và có số lượng lớn hơn 0.";
            continue;
        }
        if (isset($items[$productId]) && $items[$productId] > PHP_INT_MAX - $quantity) {
            $errors[] = "Tổng số lượng của một sản phẩm vượt quá giới hạn.";
            continue;
        }
        $items[$productId] = ($items[$productId] ?? 0) + $quantity;
    }

    if (!$items) {
        $errors[] = "Vui lòng thêm ít nhất một sản phẩm vào đơn hàng.";
    }

    return [$errors, $customer, $items];
}
function validate_customer_input(array $input): array {
    $errors = [];
    $values = [];
    $fields = [
        "name" => ["Tên khách hàng", 150, true],
        "phone" => ["Số điện thoại", 30, true],
        "email" => ["Email", 150, false],
        "address" => ["Địa chỉ", 500, true],
        "notes" => ["Ghi chú", 500, false],
    ];

    foreach ($fields as $field => [$label, $maxLength, $required]) {
        $value = $input[$field] ?? "";
        $value = is_string($value) ? trim($value) : "";
        if ($required && $value === "") {
            $errors[] = $label . " không được để trống.";
        } elseif ($value !== "" && preg_match('/^.{0,' . $maxLength . '}$/us', $value) !== 1) {
            $errors[] = $label . " vượt quá độ dài cho phép.";
        }
        if ($field === "email" && $value !== "" && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = "Email không hợp lệ.";
        }
        $values[$field] = $value === "" ? null : $value;
    }
    return [$errors, $values];
}
function validate_inventory_input(array $input): array {
    $errors = [];
    $productValue = $input["product_id"] ?? "";
    $quantityValue = $input["quantity"] ?? "";
    $productValue = is_string($productValue) || is_int($productValue) ? (string) $productValue : "";
    $quantityValue = is_string($quantityValue) || is_int($quantityValue) ? (string) $quantityValue : "";
    $productId = filter_var($productValue, FILTER_VALIDATE_INT);
    $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);
    if ($productId === false || $productId <= 0) $errors[] = "Vui lòng chọn sản phẩm hợp lệ.";
    if ($quantity === false || $quantity <= 0 || $quantity > 2147483647) $errors[] = "Số lượng phải là số nguyên dương hợp lệ.";

    $type = $input["movement_type"] ?? "";
    $type = is_string($type) ? $type : "";
    if (!in_array($type, ["in", "out"], true)) $errors[] = "Vui lòng chọn loại giao dịch hợp lệ.";

    $note = $input["note"] ?? "";
    $note = is_string($note) ? trim($note) : "";
    if (preg_match('/^.{0,500}$/us', $note) !== 1) $errors[] = "Ghi chú vượt quá 500 ký tự.";

    return [$errors, [
        "product_id" => $productId === false ? 0 : $productId,
        "quantity" => $quantity === false ? 0 : $quantity,
        "type" => $type,
        "note" => $note,
    ]];
}

if ($path === "/api" || str_starts_with($path, "/api/")) {
    api_dispatch($path);
}

try {
    switch ($path) {
        case "/":
            require_admin(); redirect("/dashboard"); break;

        case "/login":
            if (is_post()) {
                verify_csrf();
                $result = admin_login(trim($_POST["username"] ?? ""), $_POST["password"] ?? "");
                if ($result["success"]) {
                    activity_log_create("login", "admin", (int) $_SESSION["admin_id"], "Đăng nhập hệ thống.");
                    redirect("/dashboard");
                }
                view("auth/login", ["title"=>"Đăng nhập Admin","error"=>$result["message"]]);
            } else {
                view("auth/login", ["title"=>"Đăng nhập Admin","error"=>""]);
            }
            break;

        case "/forgot-password":
            $message = "";
            $error = "";
            if (is_post()) {
                verify_csrf();
                try {
                    $email = $_POST["email"] ?? "";
                    password_reset_request(is_string($email) ? trim($email) : "");
                    $message = "Nếu email thuộc tài khoản quản trị, hướng dẫn đặt lại mật khẩu sẽ được gửi trong ít phút.";
                } catch (PasswordResetMailException $e) {
                    error_log("Gửi email đặt lại mật khẩu thất bại: " . $e->getMessage());
                    $error = "Không thể gửi email lúc này. Vui lòng kiểm tra cấu hình SMTP và thử lại sau.";
                }
            }
            view("auth/forgot_password", ["title"=>"Quên mật khẩu","message"=>$message,"error"=>$error]);
            break;

        case "/reset-password":
            $token = $_GET["token"] ?? $_POST["token"] ?? "";
            $token = is_string($token) ? $token : "";
            $message = "";
            $error = "";
            $tokenValid = password_reset_token_is_valid($token);
            if (is_post()) {
                verify_csrf();
                $password = $_POST["password"] ?? "";
                $confirmation = $_POST["password_confirmation"] ?? "";
                if (!is_string($password) || strlen($password) < 12) {
                    $error = "Mật khẩu mới phải có ít nhất 12 ký tự.";
                } elseif (!is_string($confirmation) || $password !== $confirmation) {
                    $error = "Mật khẩu xác nhận không khớp.";
                } elseif (!$tokenValid || !password_reset_complete($token, $password)) {
                    $tokenValid = false;
                    $error = "Liên kết không hợp lệ, đã hết hạn hoặc đã được sử dụng. Hãy yêu cầu liên kết mới.";
                } else {
                    $message = "Mật khẩu đã được cập nhật. Bạn có thể đăng nhập bằng mật khẩu mới.";
                    $tokenValid = false;
                }
            }
            view("auth/reset_password", [
                "title"=>"Đặt lại mật khẩu",
                "token"=>$token,
                "tokenValid"=>$tokenValid,
                "message"=>$message,
                "error"=>$error,
            ]);
            break;

        case "/logout":
            require_admin();
            activity_log_create("logout", "admin", (int) $_SESSION["admin_id"], "Đăng xuất khỏi hệ thống.");
            admin_logout(); break;

        case "/activity-logs":
            require_admin();
            view("activity_logs/index", [
                "title"=>"Log hoạt động Admin",
                "logs"=>activity_log_recent()
            ]);
            break;

        case "/dashboard":
            require_admin();
            view("dashboard/index", [
                "title"=>"Dashboard",
                "productCount"=>product_count(),
                "categoryCount"=>category_count(),
                "totalQuantity"=>product_total_quantity(),
                "totalValue"=>product_total_inventory_value()
            ]);
            break;

        case "/categories":
            require_admin();
            view("categories/index", ["title"=>"Quản lý danh mục","categories"=>category_all()]);
            break;

        case "/categories/create":
            require_admin();
            if (is_post()) {
                verify_csrf();
                $name=trim($_POST["name"] ?? "");
                if ($name==="") { view("categories/create", ["title"=>"Thêm danh mục","error"=>"Tên danh mục không được để trống."]); break; }
                $categoryId = category_create($name, trim($_POST["description"] ?? ""), isset($_POST["status"])?1:0);
                activity_log_create("create", "category", $categoryId, "Thêm danh mục \"" . $name . "\".");
                redirect("/categories");
            }
            view("categories/create", ["title"=>"Thêm danh mục","error"=>""]);
            break;

        case "/categories/edit":
            require_admin();
            $id=(int)($_GET["id"] ?? 0); $category=category_find($id);
            if (!$category) { http_response_code(404); die("Không tìm thấy danh mục."); }
            if (is_post()) {
                verify_csrf();
                $name=trim($_POST["name"] ?? "");
                if ($name==="") { view("categories/edit", ["title"=>"Sửa danh mục","category"=>$category,"error"=>"Tên danh mục không được để trống."]); break; }
                category_update($id, $name, trim($_POST["description"] ?? ""), isset($_POST["status"])?1:0);
                activity_log_create("update", "category", $id, "Cập nhật danh mục \"" . $name . "\".");
                redirect("/categories");
            }
            view("categories/edit", ["title"=>"Sửa danh mục","category"=>$category,"error"=>""]);
            break;

        case "/categories/delete":
            require_admin(); verify_csrf();
            $categoryId = (int) ($_POST["id"] ?? 0);
            $category = category_find($categoryId);
            category_delete($categoryId);
            if ($category) {
                activity_log_create("delete", "category", $categoryId, "Xóa danh mục \"" . $category["name"] . "\".");
            }
            redirect("/categories"); break;

        case "/brands":
            require_admin();
            view("brands/index", ["title"=>"Quản lý thương hiệu","brands"=>brand_all(),"error"=>""]);
            break;

        case "/brands/create":
            require_admin();
            if (is_post()) {
                verify_csrf();
                $name = trim($_POST["name"] ?? "");
                $description = trim($_POST["description"] ?? "");
                $errors = [];
                if ($name === "") $errors[] = "Tên thương hiệu không được để trống.";
                if (preg_match('/^.{0,150}$/us', $name) !== 1) $errors[] = "Tên thương hiệu không được vượt quá 150 ký tự.";
                if (preg_match('/^.{0,2000}$/us', $description) !== 1) $errors[] = "Mô tả thương hiệu không được vượt quá 2000 ký tự.";
                if ($name !== "" && brand_name_exists($name)) $errors[] = "Tên thương hiệu đã tồn tại.";
                if ($errors) {
                    view("brands/form", [
                        "title"=>"Thêm thương hiệu",
                        "brand"=>["name"=>$name,"description"=>$description,"status"=>isset($_POST["status"]) ? 1 : 0],
                        "errors"=>$errors,
                        "action"=>"/brands/create",
                        "button"=>"Lưu thương hiệu"
                    ]);
                    break;
                }
                $brandId = brand_create($name, $description ?: null, isset($_POST["status"]) ? 1 : 0);
                activity_log_create("create", "brand", $brandId, "Thêm thương hiệu \"" . $name . "\".");
                redirect("/brands");
            }
            view("brands/form", ["title"=>"Thêm thương hiệu","brand"=>[],"errors"=>[],"action"=>"/brands/create","button"=>"Lưu thương hiệu"]);
            break;

        case "/brands/edit":
            require_admin();
            $id = (int) ($_GET["id"] ?? 0);
            $brand = brand_find($id);
            if (!$brand) { http_response_code(404); die("Không tìm thấy thương hiệu."); }
            if (is_post()) {
                verify_csrf();
                $name = trim($_POST["name"] ?? "");
                $description = trim($_POST["description"] ?? "");
                $errors = [];
                if ($name === "") $errors[] = "Tên thương hiệu không được để trống.";
                if (preg_match('/^.{0,150}$/us', $name) !== 1) $errors[] = "Tên thương hiệu không được vượt quá 150 ký tự.";
                if (preg_match('/^.{0,2000}$/us', $description) !== 1) $errors[] = "Mô tả thương hiệu không được vượt quá 2000 ký tự.";
                if ($name !== "" && brand_name_exists($name, $id)) $errors[] = "Tên thương hiệu đã tồn tại.";
                if ($errors) {
                    $brand = [
                        "name"=>$name,
                        "description"=>$description,
                        "status"=>isset($_POST["status"]) ? 1 : 0
                    ];
                    view("brands/form", ["title"=>"Sửa thương hiệu","brand"=>$brand,"errors"=>$errors,"action"=>"/brands/edit?id=" . $id,"button"=>"Cập nhật thương hiệu"]);
                    break;
                }
                brand_update($id, $name, $description ?: null, isset($_POST["status"]) ? 1 : 0);
                activity_log_create("update", "brand", $id, "Cập nhật thương hiệu \"" . $name . "\".");
                redirect("/brands");
            }
            view("brands/form", ["title"=>"Sửa thương hiệu","brand"=>$brand,"errors"=>[],"action"=>"/brands/edit?id=" . $id,"button"=>"Cập nhật thương hiệu"]);
            break;

        case "/brands/delete":
            require_admin(); verify_csrf();
            $brandId = (int) ($_POST["id"] ?? 0);
            $brand = brand_find($brandId);
            if ($brand && brand_product_count($brandId) > 0) {
                view("brands/index", [
                    "title"=>"Quản lý thương hiệu",
                    "brands"=>brand_all(),
                    "error"=>"Không thể xóa thương hiệu đang được gán cho sản phẩm. Hãy đổi thương hiệu của các sản phẩm trước."
                ]);
                break;
            }
            brand_delete($brandId);
            if ($brand) {
                activity_log_create("delete", "brand", $brandId, "Xóa thương hiệu \"" . $brand["name"] . "\".");
            }
            redirect("/brands");
            break;

        case "/customers":
            require_admin();
            view("customers/index", ["title"=>"Quản lý khách hàng","customers"=>customer_all()]);
            break;

        case "/customers/create":
            require_admin();
            if (is_post()) {
                verify_csrf();
                [$errors, $customer] = validate_customer_input($_POST);
                if (!$errors) {
                    $customerId = customer_create($customer);
                    activity_log_create("create", "customer", $customerId, "Thêm khách hàng \"" . $customer["name"] . "\".");
                    redirect("/customers");
                }
                view("customers/form", [
                    "title"=>"Thêm khách hàng","action"=>"/customers/create","isEdit"=>false,
                    "errors"=>$errors,"old"=>$_POST
                ]);
                break;
            }
            view("customers/form", [
                "title"=>"Thêm khách hàng","action"=>"/customers/create","isEdit"=>false,
                "errors"=>[],"old"=>[]
            ]);
            break;

        case "/customers/edit":
            require_admin();
            $id = (int) ($_GET["id"] ?? 0);
            $customer = customer_find($id);
            if (!$customer) { http_response_code(404); die("Không tìm thấy khách hàng."); }
            if (is_post()) {
                verify_csrf();
                [$errors, $values] = validate_customer_input($_POST);
                if (!$errors) {
                    customer_update($id, $values);
                    activity_log_create("update", "customer", $id, "Cập nhật khách hàng \"" . $values["name"] . "\".");
                    redirect("/customers");
                }
                view("customers/form", [
                    "title"=>"Sửa khách hàng","action"=>"/customers/edit?id=" . $id,"isEdit"=>true,
                    "customer"=>$customer,"errors"=>$errors,"old"=>$_POST
                ]);
                break;
            }
            view("customers/form", [
                "title"=>"Sửa khách hàng","action"=>"/customers/edit?id=" . $id,"isEdit"=>true,
                "customer"=>$customer,"errors"=>[]
            ]);
            break;

        case "/inventory":
            require_admin();
            view("inventory/index", ["title"=>"Nhập xuất kho","movements"=>inventory_movements()]);
            break;

        case "/inventory/create":
            require_admin();
            if (is_post()) {
                verify_csrf();
                [$errors, $movement] = validate_inventory_input($_POST);
                if (!$errors) {
                    try {
                        inventory_create_movement(
                            $movement["product_id"],
                            $movement["type"],
                            $movement["quantity"],
                            $movement["note"],
                            (int) $_SESSION["admin_id"]
                        );
                        activity_log_create(
                            "inventory_" . $movement["type"],
                            "product",
                            $movement["product_id"],
                            ($movement["type"] === "in" ? "Nhập" : "Xuất") . " " . $movement["quantity"] . " sản phẩm trong kho (mã sản phẩm #" . $movement["product_id"] . ")."
                        );
                        redirect("/inventory");
                    } catch (DomainException $e) {
                        $errors[] = $e->getMessage();
                    }
                }
                view("inventory/create", [
                    "title"=>"Tạo phiếu nhập / xuất","products"=>inventory_products(),
                    "errors"=>$errors,"old"=>$_POST
                ]);
                break;
            }
            view("inventory/create", [
                "title"=>"Tạo phiếu nhập / xuất","products"=>inventory_products(),
                "errors"=>[],"old"=>[]
            ]);
            break;

        case "/orders":
            require_admin();
            view("orders/index", ["title"=>"Quản lý đơn hàng","orders"=>order_all()]);
            break;

        case "/orders/create":
            require_admin();
            if (is_post()) {
                verify_csrf();
                [$errors, $customer, $items] = validate_order_input($_POST);
                if (!$errors) {
                    try {
                        $orderId = order_create($customer, $items, (int) $_SESSION["admin_id"]);
                        activity_log_create("create", "order", $orderId, "Tạo đơn hàng #" . $orderId . " cho khách \"" . $customer["name"] . "\".");
                        redirect("/orders/show?id=" . $orderId);
                    } catch (DomainException $e) {
                        $errors[] = $e->getMessage();
                    }
                }
                view("orders/create", [
                    "title"=>"Thêm đơn hàng",
                    "products"=>order_available_products(),
                    "customers"=>customer_all(),
                    "errors"=>$errors,
                    "old"=>$_POST
                ]);
                break;
            }
            view("orders/create", [
                "title"=>"Thêm đơn hàng",
                "products"=>order_available_products(),
                "customers"=>customer_all(),
                "errors"=>[],
                "old"=>[]
            ]);
            break;

        case "/orders/status":
            require_admin();

            if (!is_post()) {
                header("Allow: POST");
                http_response_code(405);
                die("Phương thức không được hỗ trợ.");
            }

            verify_csrf();

            $rawOrderId = $_POST["id"] ?? null;
            if (!is_string($rawOrderId) && !is_int($rawOrderId)) {
                http_response_code(400);
                die("Mã đơn hàng không hợp lệ.");
            }

            $orderId = filter_var((string) $rawOrderId, FILTER_VALIDATE_INT);
            if ($orderId === false || $orderId <= 0) {
                http_response_code(400);
                die("Mã đơn hàng không hợp lệ.");
            }

            $status = $_POST["status"] ?? "";
            if (!is_string($status)) {
                http_response_code(400);
                die("Trạng thái đơn hàng không hợp lệ.");
            }

            $order = order_find($orderId);
            if (!$order) {
                http_response_code(404);
                die("Không tìm thấy đơn hàng.");
            }

            try {
                order_update_status($orderId, $status, (int) $_SESSION["admin_id"]);
                $statusLabels = order_status_labels();
                activity_log_create(
                    $status === "cancelled" ? "cancel" : "status",
                    "order",
                    $orderId,
                    "Cập nhật trạng thái đơn hàng #" . $orderId . " từ \"" . ($statusLabels[$order["status"]] ?? $order["status"]) . "\" sang \"" . ($statusLabels[$status] ?? $status) . "\"."
                );
                $_SESSION["order_notice"] = $status === "cancelled"
                    ? "Đơn hàng đã được hủy và tồn kho đã được hoàn lại."
                    : "Trạng thái đơn hàng đã được cập nhật.";
                redirect("/orders/show?id=" . $orderId);
            } catch (DomainException $e) {
                $order = order_find($orderId);
                if (!$order) {
                    http_response_code(404);
                    die("Không tìm thấy đơn hàng.");
                }

                view("orders/show", [
                    "title" => "Chi tiết đơn hàng",
                    "order" => $order,
                    "errors" => [$e->getMessage()],
                    "statusOptions" => order_status_next_options($order["status"]),
                    "canCancel" => order_can_cancel($order["status"]),
                ]);
            }
            break;

        case "/orders/show":
            require_admin();
            $rawOrderId = $_GET["id"] ?? null;
            if (!is_string($rawOrderId) && !is_int($rawOrderId)) {
                http_response_code(404);
                die("Không tìm thấy đơn hàng.");
            }

            $orderId = filter_var((string) $rawOrderId, FILTER_VALIDATE_INT);
            if ($orderId === false || $orderId <= 0) {
                http_response_code(404);
                die("Không tìm thấy đơn hàng.");
            }

            $order = order_find($orderId);
            if (!$order) {
                http_response_code(404);
                die("Không tìm thấy đơn hàng.");
            }
            $notice = $_SESSION["order_notice"] ?? null;
            unset($_SESSION["order_notice"]);
            view("orders/show", [
                "title"=>"Chi tiết đơn hàng",
                "order"=>$order,
                "notice"=>$notice,
                "errors"=>[],
                "statusOptions"=>order_status_next_options($order["status"]),
                "canCancel"=>order_can_cancel($order["status"]),
            ]);
            break;

        case "/products/export":
            require_admin();
            $keywordValue = $_GET["keyword"] ?? "";
            $keyword = is_string($keywordValue) ? trim($keywordValue) : "";
            $categoryId = filter_var($_GET["category_id"] ?? 0, FILTER_VALIDATE_INT);
            $brandId = filter_var($_GET["brand_id"] ?? 0, FILTER_VALIDATE_INT);
            $categoryId = $categoryId === false || $categoryId < 0 ? 0 : $categoryId;
            $brandId = $brandId === false || $brandId < 0 ? 0 : $brandId;

            $products = product_export_filtered($keyword, $categoryId, $brandId);
            $rows = [];
            foreach ($products as $product) {
                $rows[] = [
                    $product["id"],
                    $product["name"],
                    $product["sku"],
                    $product["category_name"],
                    $product["brand_name"],
                    $product["description"],
                    $product["quantity"],
                    $product["unit_price"],
                    $product["status"] ? "Hiển thị" : "Ẩn",
                    $product["created_at"],
                    $product["updated_at"],
                ];
            }
            stream_xlsx(
                "products-" . date("Ymd-His") . ".xlsx",
                ["ID", "Tên sản phẩm", "SKU", "Danh mục", "Thương hiệu", "Mô tả", "Số lượng tồn", "Đơn giá", "Trạng thái", "Ngày tạo", "Cập nhật lần cuối"],
                $rows,
                [0, 6, 7]
            );
            exit;

        case "/products":
            require_admin();
            $keyword=trim($_GET["keyword"] ?? "");
            $categoryId=(int)($_GET["category_id"] ?? 0);
            $brandId=(int)($_GET["brand_id"] ?? 0);
            $perPage=2;
            $requestedPage=filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
            $currentPage=max(1, $requestedPage === false ? 1 : $requestedPage);
            $totalProducts=product_count_filtered($keyword, $categoryId, $brandId);
            $totalPages=max(1, (int)ceil($totalProducts / $perPage));
            $currentPage=min($currentPage, $totalPages);
            $offset=($currentPage - 1) * $perPage;

            view("products/index", [
                "title"=>"Quản lý sản phẩm",
                "products"=>product_paginate($keyword,$categoryId,$perPage,$offset,$brandId),
                "categories"=>category_all(),
                "brands"=>brand_all(),
                "keyword"=>$keyword,
                "categoryId"=>$categoryId,
                "brandId"=>$brandId,
                "currentPage"=>$currentPage,
                "perPage"=>$perPage,
                "totalProducts"=>$totalProducts,
                "totalPages"=>$totalPages
            ]);
            break;

        case "/products/create":
            require_admin();
            if (is_post()) {
                verify_csrf();
                $errors=validate_product_input($_POST);
                if ($errors) { view("products/create", ["title"=>"Thêm sản phẩm","categories"=>category_active_all(),"brands"=>brand_all(),"errors"=>$errors,"old"=>$_POST]); break; }
                try {
                    $imagePaths = upload_product_images($_FILES["images"] ?? []);
                } catch (RuntimeException $e) {
                    view("products/create", ["title"=>"Thêm sản phẩm","categories"=>category_active_all(),"brands"=>brand_all(),"errors"=>[$e->getMessage()],"old"=>$_POST]);
                    break;
                }
                try {
                    db()->beginTransaction();
                    $productId = product_create([
                        "category_id"=>(int)$_POST["category_id"],
                        "brand_id"=>($_POST["brand_id"] ?? "") !== "" ? (int) $_POST["brand_id"] : null,
                        "name"=>trim($_POST["name"]),
                        "sku"=>trim($_POST["sku"] ?? "") ?: null,
                        "description"=>trim($_POST["description"] ?? ""),
                        "quantity"=>(int)$_POST["quantity"],
                        "unit_price"=>(float)$_POST["unit_price"],
                        "image_path"=>$imagePaths[0] ?? null,
                        "status"=>isset($_POST["status"])?1:0
                    ]);
                    product_image_insert_many($productId, $imagePaths);
                    db()->commit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    foreach ($imagePaths as $imagePath) delete_product_image($imagePath);
                    throw $e;
                }
                activity_log_create("create", "product", $productId, "Thêm sản phẩm \"" . trim($_POST["name"]) . "\".");
                redirect("/products");
            }
            view("products/create", ["title"=>"Thêm sản phẩm","categories"=>category_active_all(),"brands"=>brand_all(),"errors"=>[],"old"=>[]]);
            break;

        case "/products/edit":
            require_admin();
            $id=(int)($_GET["id"] ?? 0); $product=product_find($id);
            if (!$product) { http_response_code(404); die("Không tìm thấy sản phẩm."); }
            $images = product_image_all($id);
            if (is_post()) {
                verify_csrf();
                foreach (["name", "category_id", "brand_id", "sku", "description", "unit_price"] as $field) {
                    if (isset($_POST[$field]) && is_string($_POST[$field])) $product[$field] = $_POST[$field];
                }
                $product["status"] = isset($_POST["status"]) ? 1 : 0;
                $errors=validate_product_input($_POST);
                $removeImageIds = $_POST["remove_image_ids"] ?? [];
                if (!is_array($removeImageIds)) {
                    $errors[] = "Danh sách hình ảnh cần xóa không hợp lệ.";
                    $removeImageIds = [];
                } else {
                    foreach ($removeImageIds as $imageId) {
                        if ((!is_string($imageId) && !is_int($imageId)) || filter_var($imageId, FILTER_VALIDATE_INT) === false || (int) $imageId <= 0) {
                            $errors[] = "Danh sách hình ảnh cần xóa không hợp lệ.";
                            $removeImageIds = [];
                            break;
                        }
                    }
                    $removeImageIds = array_values(array_unique(array_map("intval", $removeImageIds)));
                }
                if ($errors) { view("products/edit", ["title"=>"Sửa sản phẩm","categories"=>category_active_all(),"brands"=>brand_all(),"product"=>$product,"images"=>$images,"errors"=>$errors]); break; }
                try {
                    $newImagePaths = upload_product_images($_FILES["images"] ?? []);
                } catch (RuntimeException $e) {
                    view("products/edit", ["title"=>"Sửa sản phẩm","categories"=>category_active_all(),"brands"=>brand_all(),"product"=>$product,"images"=>$images,"errors"=>[$e->getMessage()]]);
                    break;
                }
                try {
                    db()->beginTransaction();
                    $removedImages = product_image_delete_many($id, $removeImageIds);
                    product_image_insert_many($id, $newImagePaths);
                    $images = product_image_all($id);
                    product_update($id, [
                        "category_id"=>(int)$_POST["category_id"],
                        "brand_id"=>($_POST["brand_id"] ?? "") !== "" ? (int) $_POST["brand_id"] : null,
                        "name"=>trim($_POST["name"]),
                        "sku"=>trim($_POST["sku"] ?? "") ?: null,
                        "description"=>trim($_POST["description"] ?? ""),
                        "unit_price"=>(float)$_POST["unit_price"],
                        "image_path"=>$images[0]["image_path"] ?? null,
                        "status"=>isset($_POST["status"])?1:0
                    ]);
                    db()->commit();
                } catch (InvalidArgumentException $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    foreach ($newImagePaths as $imagePath) delete_product_image($imagePath);
                    view("products/edit", ["title"=>"Sửa sản phẩm","categories"=>category_active_all(),"brands"=>brand_all(),"product"=>$product,"images"=>product_image_all($id),"errors"=>[$e->getMessage()]]);
                    break;
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    foreach ($newImagePaths as $imagePath) delete_product_image($imagePath);
                    throw $e;
                }
                foreach ($removedImages as $removedImage) {
                    if (!product_image_path_in_use($removedImage["image_path"])) delete_product_image($removedImage["image_path"]);
                }
                activity_log_create("update", "product", $id, "Cập nhật sản phẩm \"" . trim($_POST["name"]) . "\".");
                redirect("/products");
            }
            view("products/edit", ["title"=>"Sửa sản phẩm","categories"=>category_active_all(),"brands"=>brand_all(),"product"=>$product,"images"=>$images,"errors"=>[]]);
            break;

        case "/products/delete":
            require_admin(); verify_csrf();
            $product=product_find((int)($_POST["id"] ?? 0));
            if ($product) {
                $imagePaths = array_column(product_image_all((int) $product["id"]), "image_path");
                if (!empty($product["image_path"])) $imagePaths[] = $product["image_path"];
                product_delete((int)$product["id"]);
                foreach (array_unique($imagePaths) as $imagePath) {
                    if (!product_image_path_in_use($imagePath)) delete_product_image($imagePath);
                }
                activity_log_create("delete", "product", (int) $product["id"], "Xóa sản phẩm \"" . $product["name"] . "\".");
            }
            redirect("/products"); break;

        default:
            http_response_code(404); echo "404 - Không tìm thấy trang.";
    }
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo "<h2>Lỗi hệ thống</h2><p>Đã xảy ra lỗi khi xử lý yêu cầu. Vui lòng thử lại sau.</p>";
}
