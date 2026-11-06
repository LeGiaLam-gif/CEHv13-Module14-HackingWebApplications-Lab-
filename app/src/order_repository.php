<?php
require_once __DIR__ . "/db.php";

/**
 * Danh sách trạng thái đơn hàng được hệ thống hỗ trợ.
 */
function order_status_labels(): array {
    return [
        "new" => "Mới",
        "confirmed" => "Đã xác nhận",
        "processing" => "Đang xử lý",
        "shipping" => "Đang giao",
        "completed" => "Hoàn thành",
        "cancelled" => "Đã hủy",
    ];
}

/**
 * Chỉ cho phép chuyển sang đúng trạng thái kế tiếp trong quy trình.
 */
function order_status_next_options(string $status): array {
    $nextStatus = [
        "new" => "confirmed",
        "confirmed" => "processing",
        "processing" => "shipping",
        "shipping" => "completed",
    ][$status] ?? null;

    return $nextStatus === null
        ? []
        : [$nextStatus => order_status_labels()[$nextStatus]];
}

/**
 * Chỉ đơn hàng chưa giao mới được phép hủy.
 */
function order_can_cancel(string $status): bool {
    return in_array($status, ["new", "confirmed", "processing"], true);
}

/**
 * Chuẩn hóa một số nguyên dương từ dữ liệu form.
 * Không chấp nhận float, số âm, số 0, mảng hoặc chuỗi chứa ký tự khác.
 */
function order_require_positive_int($value, string $fieldName): int {
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value)) {
        $value = trim($value);
        if ($value === "" || !preg_match('/^[0-9]+$/D', $value)) {
            throw new DomainException($fieldName . " không hợp lệ.");
        }

        $normalized = ltrim($value, "0");
        if ($normalized === "") {
            throw new DomainException($fieldName . " phải lớn hơn 0.");
        }

        // Kiểm tra tràn số trước khi ép kiểu sang integer.
        $maxValue = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($maxValue)
            || (strlen($normalized) === strlen($maxValue) && strcmp($normalized, $maxValue) > 0)) {
            throw new DomainException($fieldName . " vượt quá giới hạn cho phép.");
        }

        $number = (int) $normalized;
    } else {
        throw new DomainException($fieldName . " không hợp lệ.");
    }

    if ($number <= 0) {
        throw new DomainException($fieldName . " phải lớn hơn 0.");
    }

    return $number;
}

/**
 * Lấy trường văn bản từ form, từ chối array/object để tránh ép kiểu ngoài ý muốn.
 */
function order_require_text($value, string $fieldName): string {
    if (!is_string($value) && !is_int($value)) {
        throw new DomainException($fieldName . " không hợp lệ.");
    }

    $value = trim((string) $value);
    if ($value === "") {
        throw new DomainException("Vui lòng nhập " . strtolower($fieldName) . ".");
    }

    return $value;
}

/**
 * customer_id có thể bằng 0 hoặc rỗng đối với khách mua không đăng ký.
 */
function order_parse_customer_id($value): int {
    if ($value === null || $value === "") {
        return 0;
    }

    if (is_int($value) && $value === 0) {
        return 0;
    }

    if (is_string($value)) {
        $value = trim($value);
        if ($value === "0") {
            return 0;
        }
    }

    return order_require_positive_int($value, "Mã khách hàng");
}

/**
 * Kiểm tra và chuẩn hóa danh sách sản phẩm gửi lên.
 * Kết quả có dạng [product_id => quantity], cả hai đều là số nguyên dương.
 */
function order_normalize_items(array $items): array {
    if ($items === []) {
        throw new DomainException("Đơn hàng phải có ít nhất một sản phẩm.");
    }

    $normalizedItems = [];

    foreach ($items as $productId => $quantity) {
        $productId = order_require_positive_int($productId, "Mã sản phẩm");
        $quantity = order_require_positive_int($quantity, "Số lượng sản phẩm");

        if (array_key_exists($productId, $normalizedItems)) {
            throw new DomainException("Danh sách đơn hàng chứa sản phẩm bị trùng.");
        }

        $normalizedItems[$productId] = $quantity;
    }

    // Khóa sản phẩm theo thứ tự ID để giảm nguy cơ deadlock khi nhiều đơn
    // hàng cùng cập nhật các sản phẩm giống nhau.
    ksort($normalizedItems, SORT_NUMERIC);

    return $normalizedItems;
}

/**
 * Lấy thông tin sản phẩm đang kinh doanh và còn hàng.
 */
function order_available_products(): array {
    $stmt = db()->query(
        "SELECT id, name, sku, quantity, unit_price
         FROM products
         WHERE status = 1 AND quantity > 0
         ORDER BY name"
    );

    return $stmt->fetchAll();
}

/**
 * Tạo đơn hàng, chi tiết đơn hàng, trừ kho và ghi lịch sử xuất kho.
 * Tất cả được thực hiện trong cùng một transaction.
 */
function order_create(array $customer, array $items, int $adminId): int {
    $adminId = order_require_positive_int($adminId, "Mã quản trị viên");
    $items = order_normalize_items($items);
    $customerId = order_parse_customer_id($customer["customer_id"] ?? null);

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Nếu chọn khách hàng đã lưu, luôn lấy thông tin từ database,
        // không tin các trường tên, số điện thoại, địa chỉ gửi từ form.
        if ($customerId > 0) {
            $customerStmt = $pdo->prepare(
                "SELECT name, phone, address
                 FROM customers
                 WHERE id = :id
                 LIMIT 1"
            );
            $customerStmt->execute(["id" => $customerId]);
            $savedCustomer = $customerStmt->fetch();

            if (!$savedCustomer) {
                throw new DomainException("Khách hàng đã chọn không còn tồn tại.");
            }

            $customerName = trim((string) $savedCustomer["name"]);
            $customerPhone = trim((string) $savedCustomer["phone"]);
            $shippingAddress = trim((string) $savedCustomer["address"]);
        } else {
            // Khách mua không đăng ký: yêu cầu đủ thông tin giao hàng.
            $customerName = order_require_text($customer["name"] ?? null, "Tên khách hàng");
            $customerPhone = order_require_text($customer["phone"] ?? null, "Số điện thoại");
            $shippingAddress = order_require_text($customer["address"] ?? null, "Địa chỉ giao hàng");
        }

        // Khóa từng sản phẩm trước khi kiểm tra tồn kho để tránh hai đơn
        // hàng đồng thời cùng sử dụng một lượng hàng tồn.
        $products = [];
        $productQuery = $pdo->prepare(
            "SELECT id, name, sku, quantity, unit_price
             FROM products
             WHERE id = :id AND status = 1
             FOR UPDATE"
        );

        foreach ($items as $productId => $quantity) {
            $productQuery->execute(["id" => $productId]);
            $product = $productQuery->fetch();

            if (!$product) {
                throw new DomainException(
                    "Có sản phẩm không còn được kinh doanh. Vui lòng kiểm tra lại đơn hàng."
                );
            }

            $stockQuantity = (int) $product["quantity"];
            if ($quantity > $stockQuantity) {
                throw new DomainException(
                    "Sản phẩm \"" . $product["name"] . "\" chỉ còn " . $stockQuantity . " sản phẩm trong kho."
                );
            }

            $products[$productId] = $product;
        }

        // Tạo đơn hàng trước để lấy ID tham chiếu cho order_items và lịch sử kho.
        $orderStmt = $pdo->prepare(
            "INSERT INTO orders
                (customer_id, customer_name, customer_phone, shipping_address, status, total_amount)
             VALUES
                (:customer_id, :customer_name, :customer_phone, :shipping_address, 'new', 0)"
        );
        $orderStmt->execute([
            "customer_id" => $customerId > 0 ? $customerId : null,
            "customer_name" => $customerName,
            "customer_phone" => $customerPhone,
            "shipping_address" => $shippingAddress,
        ]);

        $orderId = (int) $pdo->lastInsertId();
        if ($orderId <= 0) {
            throw new RuntimeException("Không thể tạo mã đơn hàng.");
        }

        $itemStmt = $pdo->prepare(
            "INSERT INTO order_items
                (order_id, product_id, product_name, product_sku, quantity, unit_price, line_total)
             VALUES
                (:order_id, :product_id, :product_name, :product_sku, :quantity, :unit_price,
                 CAST(:line_unit_price AS DECIMAL(15,2)) * :line_quantity)"
        );

        // Điều kiện quantity >= :minimum_quantity là lớp bảo vệ bổ sung,
        // kể cả khi sau này logic khóa tồn kho bị thay đổi.
        $stockStmt = $pdo->prepare(
            "UPDATE products
             SET quantity = quantity - :quantity
             WHERE id = :id AND quantity >= :minimum_quantity"
        );

        $movementStmt = $pdo->prepare(
            "INSERT INTO inventory_movements
                (product_id, product_name, product_sku, movement_type, quantity,
                 stock_before, stock_after, note, reference_type, reference_id, admin_id)
             VALUES
                (:product_id, :product_name, :product_sku, 'out', :quantity,
                 :stock_before, :stock_after, :note, 'order', :reference_id, :admin_id)"
        );

        foreach ($items as $productId => $quantity) {
            $product = $products[$productId];
            $unitPrice = $product["unit_price"];
            $stockBefore = (int) $product["quantity"];
            $stockAfter = $stockBefore - $quantity;

            $itemStmt->execute([
                "order_id" => $orderId,
                "product_id" => $productId,
                "product_name" => $product["name"],
                "product_sku" => $product["sku"],
                "quantity" => $quantity,
                "unit_price" => $unitPrice,
                "line_unit_price" => $unitPrice,
                "line_quantity" => $quantity,
            ]);

            $stockStmt->execute([
                "quantity" => $quantity,
                "id" => $productId,
                "minimum_quantity" => $quantity,
            ]);

            if ($stockStmt->rowCount() !== 1) {
                throw new DomainException(
                    "Không đủ tồn kho cho sản phẩm \"" . $product["name"] . "\". Vui lòng tải lại đơn hàng."
                );
            }

            $movementStmt->execute([
                "product_id" => $productId,
                "product_name" => $product["name"],
                "product_sku" => $product["sku"],
                "quantity" => $quantity,
                "stock_before" => $stockBefore,
                "stock_after" => $stockAfter,
                "note" => "Xuất kho theo đơn hàng DH-" . str_pad((string) $orderId, 6, "0", STR_PAD_LEFT),
                "reference_id" => $orderId,
                "admin_id" => $adminId,
            ]);
        }

        // Tính tổng tiền trực tiếp từ line_total trong database.
        $totalStmt = $pdo->prepare(
            "UPDATE orders o
             SET total_amount = (
                 SELECT COALESCE(SUM(line_total), 0)
                 FROM order_items
                 WHERE order_id = o.id
             )
             WHERE o.id = :order_id"
        );
        $totalStmt->execute(["order_id" => $orderId]);

        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Lấy danh sách đơn hàng kèm số dòng sản phẩm trong từng đơn.
 */
function order_all(): array {
    $stmt = db()->query(
        "SELECT o.*,
                (SELECT COUNT(*)
                 FROM order_items oi
                 WHERE oi.order_id = o.id) AS item_count
         FROM orders o
         ORDER BY o.id DESC"
    );

    return $stmt->fetchAll();
}

/**
 * Tìm một đơn hàng theo ID và lấy các dòng chi tiết của đơn.
 */
function order_find(int $id): ?array {
    $id = order_require_positive_int($id, "Mã đơn hàng");

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM orders WHERE id = :id LIMIT 1"
    );
    $stmt->execute(["id" => $id]);
    $order = $stmt->fetch();

    if (!$order) {
        return null;
    }

    $itemsStmt = $pdo->prepare(
        "SELECT product_name, product_sku, quantity, unit_price, line_total
         FROM order_items
         WHERE order_id = :order_id
         ORDER BY id"
    );
    $itemsStmt->execute(["order_id" => $id]);
    $order["items"] = $itemsStmt->fetchAll();

    return $order;
}

/**
 * Cập nhật trạng thái đơn hàng.
 * Khi hủy: khóa đơn, khóa sản phẩm, hoàn kho, ghi lịch sử nhập kho,
 * sau đó cập nhật trạng thái trong cùng một transaction.
 */
function order_update_status(int $id, string $status, int $adminId): void {
    $id = order_require_positive_int($id, "Mã đơn hàng");
    $adminId = order_require_positive_int($adminId, "Mã quản trị viên");

    $labels = order_status_labels();
    if (!isset($labels[$status])) {
        throw new DomainException("Trạng thái đơn hàng không hợp lệ.");
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Khóa đơn hàng để ngăn hai request đồng thời cập nhật cùng một đơn.
        $orderStmt = $pdo->prepare(
            "SELECT id, status
             FROM orders
             WHERE id = :id
             FOR UPDATE"
        );
        $orderStmt->execute(["id" => $id]);
        $order = $orderStmt->fetch();

        if (!$order) {
            throw new DomainException("Không tìm thấy đơn hàng.");
        }

        $currentStatus = (string) $order["status"];

        if ($status === "cancelled") {
            if (!order_can_cancel($currentStatus)) {
                throw new DomainException("Chỉ có thể hủy đơn hàng trước khi đơn được giao.");
            }

            // Tổng hợp theo product_id để mỗi sản phẩm chỉ được khóa/cập nhật một lần.
            $itemsStmt = $pdo->prepare(
                "SELECT product_id, SUM(quantity) AS quantity
                 FROM order_items
                 WHERE order_id = :order_id
                   AND product_id IS NOT NULL
                 GROUP BY product_id
                 ORDER BY product_id"
            );
            $itemsStmt->execute(["order_id" => $id]);
            $orderItems = $itemsStmt->fetchAll();

            $productStmt = $pdo->prepare(
                "SELECT id, name, sku, quantity
                 FROM products
                 WHERE id = :id
                 FOR UPDATE"
            );
            $stockStmt = $pdo->prepare(
                "UPDATE products SET quantity = :quantity WHERE id = :id"
            );
            $movementStmt = $pdo->prepare(
                "INSERT INTO inventory_movements
                    (product_id, product_name, product_sku, movement_type, quantity,
                     stock_before, stock_after, note, reference_type, reference_id, admin_id)
                 VALUES
                    (:product_id, :product_name, :product_sku, 'in', :quantity,
                     :stock_before, :stock_after, :note, 'order', :reference_id, :admin_id)"
            );

            foreach ($orderItems as $item) {
                $productId = (int) $item["product_id"];
                $quantity = order_require_positive_int((string) $item["quantity"], "Số lượng hoàn kho");

                $productStmt->execute(["id" => $productId]);
                $product = $productStmt->fetch();

                // Sản phẩm có thể đã bị xóa sau khi đơn hàng được tạo.
                // Khi đó product_id thường được đặt NULL bởi khóa ngoại.
                if (!$product) {
                    continue;
                }

                $stockBefore = (int) $product["quantity"];
                // Cột quantity trong schema hiện tại được giả định là INT có dấu.
                if ($stockBefore < 0 || $quantity > 2147483647 - $stockBefore) {
                    throw new DomainException(
                        "Không thể hủy đơn vì tồn kho sản phẩm \"" . $product["name"] . "\" sẽ vượt giới hạn."
                    );
                }

                $stockAfter = $stockBefore + $quantity;
                $stockStmt->execute([
                    "quantity" => $stockAfter,
                    "id" => $productId,
                ]);

                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        "Không thể cập nhật tồn kho cho sản phẩm \"" . $product["name"] . "\"."
                    );
                }

                $movementStmt->execute([
                    "product_id" => $productId,
                    "product_name" => $product["name"],
                    "product_sku" => $product["sku"],
                    "quantity" => $quantity,
                    "stock_before" => $stockBefore,
                    "stock_after" => $stockAfter,
                    "note" => "Hoàn kho do hủy đơn hàng DH-" . str_pad((string) $id, 6, "0", STR_PAD_LEFT),
                    "reference_id" => $id,
                    "admin_id" => $adminId,
                ]);
            }
        } elseif (!isset(order_status_next_options($currentStatus)[$status])) {
            throw new DomainException("Không thể chuyển từ trạng thái hiện tại sang trạng thái đã chọn.");
        }

        $updateStmt = $pdo->prepare(
            "UPDATE orders
             SET status = :status
             WHERE id = :id"
        );
        $updateStmt->execute([
            "status" => $status,
            "id" => $id,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
