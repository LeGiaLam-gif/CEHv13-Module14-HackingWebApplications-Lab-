<?php

function lab_user_find_by_login(string $login): ?array {
    $stmt = db()->prepare(
        "SELECT u.*, r.name AS role_name
         FROM users u INNER JOIN roles r ON r.id = u.role_id
         WHERE u.username = :username OR u.email = :email LIMIT 1"
    );
    $stmt->execute(["username" => $login, "email" => $login]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function lab_user_find(int $id): ?array {
    $stmt = db()->prepare(
        "SELECT u.*, r.name AS role_name
         FROM users u INNER JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id LIMIT 1"
    );
    $stmt->execute(["id" => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function lab_user_current(): ?array {
    if (empty($_SESSION["user_id"])) {
        return null;
    }
    return lab_user_find((int) $_SESSION["user_id"]);
}

function lab_user_create(array $input): int {
    $username = api_required_string($input, "username", "Username", 100);
    $email = api_required_string($input, "email", "Email", 150);
    $password = $input["password"] ?? null;
    if (!is_string($password) || strlen($password) < 12) {
        throw new ApiHttpException("Mật khẩu phải có ít nhất 12 ký tự.", 422, ["fields" => ["password"]]);
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new ApiHttpException("Email không hợp lệ.", 422, ["fields" => ["email"]]);
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/D', $username)) {
        throw new ApiHttpException("Username không hợp lệ.", 422, ["fields" => ["username"]]);
    }

    $roleStmt = db()->prepare("SELECT id FROM roles WHERE name = 'customer' LIMIT 1");
    $roleStmt->execute();
    $roleId = $roleStmt->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException("Lab roles have not been seeded.");
    }
    $fullName = api_optional_string($input, "full_name", "Họ tên", 150);
    $phone = api_optional_string($input, "phone", "Số điện thoại", 30);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, email, password_hash, full_name, phone, role_id, account_status)
             VALUES (:username, :email, :password_hash, :full_name, :phone, :role_id, 'active')"
        );
        $stmt->execute([
            "username" => $username,
            "email" => $email,
            "password_hash" => password_hash($password, PASSWORD_DEFAULT),
            "full_name" => $fullName,
            "phone" => $phone,
            "role_id" => (int) $roleId,
        ]);
        $id = (int) $pdo->lastInsertId();
        $customer = $pdo->prepare(
            "INSERT INTO customers (user_id, name, phone, email, address, notes)
             VALUES (:user_id, :name, :phone, :email, '', 'Lab v2 account')"
        );
        $customer->execute([
            "user_id" => $id,
            "name" => $fullName ?: $username,
            "phone" => $phone ?? "",
            "email" => $email,
        ]);

        if (lab_vuln("MASS_ASSIGN")) {
            lab_assign_all("users", $id, $input);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function lab_users_all(): array {
    return db()->query(
        "SELECT u.id, u.username, u.email, u.full_name, u.phone, u.role_id,
                u.account_status, u.store_credit, u.created_at, r.name AS role_name
         FROM users u INNER JOIN roles r ON r.id = u.role_id ORDER BY u.id"
    )->fetchAll();
}

function lab_user_update_profile(int $id, array $input): void {
    $sets = [];
    $values = ["id" => $id];
    foreach (["full_name", "phone", "account_status"] as $field) {
        if (array_key_exists($field, $input)) {
            $sets[] = $field . " = :" . $field;
            if ($field === "account_status") {
                $values[$field] = $input[$field];
            } else {
                $values[$field] = api_optional_string($input, $field, $field, $field === "phone" ? 30 : 150);
            }
        }
    }
    if ($sets) {
        $stmt = db()->prepare("UPDATE users SET " . implode(", ", $sets) . " WHERE id = :id");
        $stmt->execute($values);
    }
    if (lab_vuln("MASS_ASSIGN")) {
        lab_assign_all("users", $id, $input);
    }
}
