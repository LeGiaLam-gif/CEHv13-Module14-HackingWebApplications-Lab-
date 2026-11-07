<?php

function lab_auth_dispatch(string $path, string $method): bool {
    if ($path === "/api/v2/auth/register" && $method === "POST") {
        $input = lab_filter_input("users", [], "register", api_request_data());
        $id = lab_user_create($input);
        lab_audit("allow", "user_registered", "user", $id);
        api_success(["id" => $id], 201);
    }
    if ($path === "/api/v2/auth/login" && $method === "POST") {
        $input = api_request_data();
        $login = $input["username"] ?? $input["email"] ?? null;
        $password = $input["password"] ?? null;
        if (!is_string($login) || !is_string($password)) {
            throw new ApiHttpException("Username/email và password phải là chuỗi.", 422);
        }
        $user = lab_user_find_by_login(trim($login));
        if (!$user || !password_verify($password, (string) $user["password_hash"])) {
            lab_audit("deny", "invalid_credentials");
            throw new ApiHttpException("Thông tin đăng nhập không chính xác.", 401);
        }
        if ($user["account_status"] !== "active") {
            lab_audit("deny", "account_not_active", "user", (int) $user["id"]);
            throw new ApiHttpException("Tài khoản chưa được kích hoạt hoặc đã bị khóa.", 403);
        }
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int) $user["id"];
        unset($_SESSION["admin_id"]);
        lab_audit("allow", "login", "user", (int) $user["id"]);
        api_success(["csrf_token" => csrf_token()]);
    }
    if ($path === "/api/v2/auth/logout" && $method === "POST") {
        api_verify_csrf();
        lab_audit("allow", "logout", "user", (int) ($_SESSION["user_id"] ?? 0));
        $_SESSION = [];
        session_destroy();
        api_success(["message" => "Đã đăng xuất."]);
    }
    if ($path === "/api/v2/auth/csrf-token" && $method === "GET") {
        lab_require_authenticated_user();
        api_success(["csrf_token" => csrf_token()]);
    }
    return false;
}

function lab_require_authenticated_user(): array {
    $user = lab_user_current();
    if (!$user) {
        throw new ApiHttpException("Vui lòng đăng nhập để sử dụng API.", 401);
    }
    if ($user["account_status"] !== "active") {
        unset($_SESSION["user_id"]);
        lab_audit("deny", "account_not_active", "user", (int) $user["id"]);
        throw new ApiHttpException("Tài khoản chưa được kích hoạt hoặc đã bị khóa.", 403);
    }
    return $user;
}
