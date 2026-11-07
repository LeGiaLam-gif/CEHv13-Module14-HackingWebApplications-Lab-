<?php

declare(strict_types=1);

$dbBootstrap = __DIR__ . '/../../app/src/db.php';
if (!is_file($dbBootstrap)) {
    $dbBootstrap = '/var/www/html/src/db.php';
}
require_once $dbBootstrap;

if ((string) getenv('APP_ENV') !== 'local') {
    fwrite(STDERR, "Refusing: APP_ENV must be local\n");
    exit(1);
}

function lab_seed_insert_roles(PDO $pdo): void {
    $roles = [
        [1, 'admin'],
        [2, 'staff_catalog'],
        [3, 'staff_orders'],
        [4, 'customer'],
    ];
    $stmt = $pdo->prepare("INSERT INTO roles (id, name) VALUES (:id, :name) ON DUPLICATE KEY UPDATE name = VALUES(name)");
    foreach ($roles as [$id, $name]) {
        $stmt->execute(['id' => $id, 'name' => $name]);
    }
}

function lab_seed_insert_permissions(PDO $pdo): void {
    $permissions = [
        [1, 'products.update', 'Cập nhật sản phẩm'],
        [2, 'products.set_price', 'Thiết lập giá'],
        [3, 'products.delete', 'Xóa sản phẩm'],
        [4, 'orders.read_all', 'Xem tất cả đơn hàng'],
        [5, 'orders.update_status', 'Cập nhật trạng thái đơn hàng'],
        [6, 'orders.set_payment', 'Cập nhật trạng thái thanh toán'],
        [7, 'users.read_all', 'Xem tất cả người dùng'],
        [8, 'users.manage', 'Quản lý người dùng'],
        [9, 'users.delete', 'Xóa người dùng'],
        [10, 'audit.read', 'Xem nhật ký kiểm toán'],
    ];
    $stmt = $pdo->prepare("INSERT INTO permissions (id, code, description) VALUES (:id, :code, :description) ON DUPLICATE KEY UPDATE code = VALUES(code), description = VALUES(description)");
    foreach ($permissions as [$id, $code, $description]) {
        $stmt->execute(['id' => $id, 'code' => $code, 'description' => $description]);
    }
}

function lab_seed_insert_role_permissions(PDO $pdo): void {
    $data = [
        [1, 1], [1, 2], [1, 3], [1, 4], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [1, 10],
        [2, 1], [2, 2], [2, 3], [2, 7],
        [3, 4], [3, 5], [3, 6],
    ];
    $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id) ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)");
    foreach ($data as [$roleId, $permissionId]) {
        $stmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
    }
}

function lab_seed_insert_users(PDO $pdo): void {
    $users = [
        [1, 'lab_admin', 'lab_admin@lab.test', 'Admin@Lab2026', 'Lab Admin', '0900000001', 1, 0.00],
        [2, 'lab_staff_cat', 'lab_staff_cat@lab.test', 'StaffCat@Lab2026', 'Staff Catalog', '0900000002', 2, 0.00],
        [3, 'lab_staff_ord', 'lab_staff_ord@lab.test', 'StaffOrd@Lab2026', 'Staff Orders', '0900000003', 3, 0.00],
        [4, 'alice', 'alice@lab.test', 'Alice@Lab2026', 'Alice Nguyen', '0900000004', 4, 0.00],
        [5, 'bob', 'bob@lab.test', 'Bob@Lab2026', 'Bob Tran', '0900000005', 4, 500000.00],
        [6, 'carol', 'carol@lab.test', 'Carol@Lab2026', 'Carol Le', '0900000006', 4, 0.00],
    ];
    $stmt = $pdo->prepare(
        "INSERT INTO users (id, username, email, password_hash, full_name, phone, role_id, account_status, store_credit)
         VALUES (:id, :username, :email, :password_hash, :full_name, :phone, :role_id, 'active', :store_credit)
         ON DUPLICATE KEY UPDATE username = VALUES(username), email = VALUES(email), password_hash = VALUES(password_hash), full_name = VALUES(full_name), phone = VALUES(phone), role_id = VALUES(role_id), account_status = VALUES(account_status), store_credit = VALUES(store_credit)"
    );
    foreach ($users as [$id, $username, $email, $password, $fullName, $phone, $roleId, $storeCredit]) {
        $stmt->execute([
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $fullName,
            'phone' => $phone,
            'role_id' => $roleId,
            'store_credit' => $storeCredit,
        ]);
    }
}

function lab_seed_insert_customers(PDO $pdo): void {
    $customers = [
        [1, 4, 'Alice Nguyen', '0900000004', 'alice@lab.test', '12 Nguyễn Huệ, Quận 1, TP.HCM', 'Lab customer A'],
        [2, 5, 'Bob Tran', '0900000005', 'bob@lab.test', '88 Trần Phú, Hải Châu, Đà Nẵng', 'Lab customer B'],
        [3, 6, 'Carol Le', '0900000006', 'carol@lab.test', '15 Hoàng Diệu, Ba Đình, Hà Nội', 'Lab customer C'],
    ];
    $stmt = $pdo->prepare(
        "INSERT INTO customers (id, user_id, name, phone, email, address, notes)
         VALUES (:id, :user_id, :name, :phone, :email, :address, :notes)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), name = VALUES(name), phone = VALUES(phone), email = VALUES(email), address = VALUES(address), notes = VALUES(notes)"
    );
    foreach ($customers as [$id, $userId, $name, $phone, $email, $address, $notes]) {
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'notes' => $notes,
        ]);
    }
}

function lab_seed_insert_addresses(PDO $pdo): void {
    $addresses = [
        [1, 1, 'home', '12 Nguyễn Huệ, Quận 1', 'TP.HCM', '0900000004', 1],
        [2, 1, 'office', '45 Lê Lợi, Quận 1', 'TP.HCM', '0900000004', 0],
        [3, 2, 'home', '88 Trần Phú, Hải Châu', 'Đà Nẵng', '0900000005', 1],
        [4, 2, 'office', '20 Bạch Đằng, Hải Châu', 'Đà Nẵng', '0900000005', 0],
        [5, 3, 'home', '15 Hoàng Diệu, Ba Đình', 'Hà Nội', '0900000006', 1],
    ];
    $stmt = $pdo->prepare(
        "INSERT INTO addresses (id, customer_id, label, line1, city, phone, is_default)
         VALUES (:id, :customer_id, :label, :line1, :city, :phone, :is_default)
         ON DUPLICATE KEY UPDATE customer_id = VALUES(customer_id), label = VALUES(label), line1 = VALUES(line1), city = VALUES(city), phone = VALUES(phone), is_default = VALUES(is_default)"
    );
    foreach ($addresses as [$id, $customerId, $label, $line1, $city, $phone, $isDefault]) {
        $stmt->execute([
            'id' => $id,
            'customer_id' => $customerId,
            'label' => $label,
            'line1' => $line1,
            'city' => $city,
            'phone' => $phone,
            'is_default' => $isDefault,
        ]);
    }
}

function lab_seed_insert_products(PDO $pdo): void {
    $products = [
        [1, 1, 'Laptop Dell Inspiron 15', 'LAP-DELL-001', 'Laptop văn phòng cơ bản', 10, 15500000, 1],
        [2, 2, 'iPhone Demo 128GB', 'PHONE-IP-001', 'Sản phẩm demo', 5, 21900000, 1],
        [3, 3, 'Chuột không dây Logitech', 'ACC-MOUSE-001', 'Chuột không dây dùng cho văn phòng', 30, 350000, 1],
        [4, 1, 'Laptop Asus Vivobook 14', 'LAP-ASUS-001', 'Laptop mỏng nhẹ cho sinh viên', 8, 12900000, 1],
        [5, 2, 'Samsung Galaxy A55 128GB', 'PHONE-SS-001', 'Sản phẩm demo', 12, 9800000, 1],
        [6, 3, 'Tai nghe Sony WH-CH520', 'ACC-HEAD-001', 'Tai nghe không dây', 25, 1150000, 1],
    ];
    $stmt = $pdo->prepare(
        "INSERT INTO products (id, category_id, name, sku, description, quantity, unit_price, status)
         VALUES (:id, :category_id, :name, :sku, :description, :quantity, :unit_price, :status)
         ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), name = VALUES(name), sku = VALUES(sku), description = VALUES(description), quantity = VALUES(quantity), unit_price = VALUES(unit_price), status = VALUES(status)"
    );
    foreach ($products as [$id, $categoryId, $name, $sku, $description, $quantity, $unitPrice, $status]) {
        $stmt->execute([
            'id' => $id,
            'category_id' => $categoryId,
            'name' => $name,
            'sku' => $sku,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'status' => $status,
        ]);
    }
}

function lab_seed_insert_orders(PDO $pdo): void {
    $orders = [
        [1, 1, 'Alice Nguyen', '0900000004', '12 Nguyễn Huệ, Quận 1, TP.HCM', 'completed', 'paid', 15850000],
        [2, 1, 'Alice Nguyen', '0900000004', '45 Lê Lợi, Quận 1, TP.HCM', 'new', 'unpaid', 2300000],
        [3, 2, 'Bob Tran', '0900000005', '88 Trần Phú, Hải Châu, Đà Nẵng', 'processing', 'paid', 22600000],
        [4, 2, 'Bob Tran', '0900000005', '20 Bạch Đằng, Hải Châu, Đà Nẵng', 'new', 'unpaid', 10950000],
        [5, 3, 'Carol Le', '0900000006', '15 Hoàng Diệu, Ba Đình, Hà Nội', 'confirmed', 'unpaid', 14400000],
    ];
    $stmt = $pdo->prepare(
        "INSERT INTO orders (id, customer_id, customer_name, customer_phone, shipping_address, status, payment_status, total_amount)
         VALUES (:id, :customer_id, :customer_name, :customer_phone, :shipping_address, :status, :payment_status, :total_amount)
         ON DUPLICATE KEY UPDATE customer_id = VALUES(customer_id), customer_name = VALUES(customer_name), customer_phone = VALUES(customer_phone), shipping_address = VALUES(shipping_address), status = VALUES(status), payment_status = VALUES(payment_status), total_amount = VALUES(total_amount)"
    );
    foreach ($orders as [$id, $customerId, $customerName, $customerPhone, $shippingAddress, $status, $paymentStatus, $totalAmount]) {
        $stmt->execute([
            'id' => $id,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'shipping_address' => $shippingAddress,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total_amount' => $totalAmount,
        ]);
    }
}

function lab_seed_insert_order_items(PDO $pdo): void {
    $items = [
        [1, 1, 'Laptop Dell Inspiron 15', 'LAP-DELL-001', 1, 15500000, 15500000],
        [1, 3, 'Chuột không dây Logitech', 'ACC-MOUSE-001', 1, 350000, 350000],
        [2, 6, 'Tai nghe Sony WH-CH520', 'ACC-HEAD-001', 2, 1150000, 2300000],
        [3, 2, 'iPhone Demo 128GB', 'PHONE-IP-001', 1, 21900000, 21900000],
        [3, 3, 'Chuột không dây Logitech', 'ACC-MOUSE-001', 2, 350000, 700000],
        [4, 5, 'Samsung Galaxy A55 128GB', 'PHONE-SS-001', 1, 9800000, 9800000],
        [4, 6, 'Tai nghe Sony WH-CH520', 'ACC-HEAD-001', 1, 1150000, 1150000],
        [5, 4, 'Laptop Asus Vivobook 14', 'LAP-ASUS-001', 1, 12900000, 12900000],
        [5, 3, 'Chuột không dây Logitech', 'ACC-MOUSE-001', 1, 350000, 350000],
        [5, 6, 'Tai nghe Sony WH-CH520', 'ACC-HEAD-001', 1, 1150000, 1150000],
    ];
    $stmt = $pdo->prepare(
        "INSERT INTO order_items (id, order_id, product_id, product_name, product_sku, quantity, unit_price, line_total)
         VALUES (:id, :order_id, :product_id, :product_name, :product_sku, :quantity, :unit_price, :line_total)
         ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), product_id = VALUES(product_id), product_name = VALUES(product_name), product_sku = VALUES(product_sku), quantity = VALUES(quantity), unit_price = VALUES(unit_price), line_total = VALUES(line_total)"
    );
    $id = 1;
    foreach ($items as [$orderId, $productId, $productName, $productSku, $quantity, $unitPrice, $lineTotal]) {
        $stmt->execute([
            'id' => $id,
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => $productName,
            'product_sku' => $productSku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);
        $id++;
    }
}

function lab_reset_lab_data(PDO $pdo): void {
    $pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE customer_id IN (SELECT id FROM customers WHERE user_id IS NOT NULL))");
    $pdo->exec("DELETE FROM orders WHERE customer_id IN (SELECT id FROM customers WHERE user_id IS NOT NULL)");
    $pdo->exec("DELETE FROM addresses WHERE customer_id IN (SELECT id FROM customers WHERE user_id IS NOT NULL)");
    $pdo->exec("DELETE FROM customers WHERE user_id IS NOT NULL");
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@lab.test'");
    $pdo->exec("UPDATE products SET quantity = CASE id WHEN 1 THEN 10 WHEN 2 THEN 5 WHEN 3 THEN 30 WHEN 4 THEN 8 WHEN 5 THEN 12 WHEN 6 THEN 25 ELSE quantity END WHERE id IN (1,2,3,4,5,6)");
}

$pdo = db();
$resetRequested = in_array('--reset', $argv, true);

$pdo->beginTransaction();
try {
    if ($resetRequested) {
        lab_reset_lab_data($pdo);
    }

    lab_seed_insert_roles($pdo);
    lab_seed_insert_permissions($pdo);
    lab_seed_insert_role_permissions($pdo);
    lab_seed_insert_users($pdo);
    lab_seed_insert_customers($pdo);
    lab_seed_insert_addresses($pdo);
    lab_seed_insert_products($pdo);
    lab_seed_insert_orders($pdo);
    lab_seed_insert_order_items($pdo);

    $pdo->commit();
    echo "Lab seed synchronized successfully.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Lab seed failed: " . $e->getMessage() . "\n");
    exit(1);
}