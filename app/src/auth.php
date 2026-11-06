<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";
$config = require __DIR__ . "/config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_name($config["app"]["session_name"]);
    session_start();
}

function admin_login(string $username, string $password): array {
    if ($username === "" || $password === "") {
        return [
            "success" => false,
            "message" => "Vui lòng nhập tài khoản và mật khẩu."
        ];
    }

    $stmt = db()->prepare("
        SELECT * FROM admins
        WHERE username = :u OR email = :u
        LIMIT 1
    ");
    $stmt->execute(["u" => $username]);

    $admin = $stmt->fetch();

    if (!$admin) {
        return [
            "success" => false,
            "message" => "Tài khoản không tồn tại."
        ];
    }

    $passwordHash = $admin["password_hash"];

    // 1. Verify password using modern PHP password hashing
    $passwordVerified = password_verify($password, $passwordHash);

    // 2. Fallback for legacy SHA-256 passwords
    if (!$passwordVerified) {
        $legacyHash = hash("sha256", $password);

        if (hash_equals($passwordHash, $legacyHash)) {
            $passwordVerified = true;

            // 3. Upgrade legacy hash to modern password hash
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            $updateStmt = db()->prepare("
                UPDATE admins
                SET password_hash = :password_hash
                WHERE id = :id
            ");

            $updateStmt->execute([
                "password_hash" => $newHash,
                "id" => $admin["id"]
            ]);
        }
    }

    if (!$passwordVerified) {
        return [
            "success" => false,
            "message" => "Mật khẩu không chính xác."
        ];
    }

    // 4. Regenerate session ID after successful login
    session_regenerate_id(true);

    $_SESSION["admin_id"] = $admin["id"];
    $_SESSION["admin_username"] = $admin["username"];
    $_SESSION["admin_full_name"] = $admin["full_name"];

    return [
        "success" => true,
        "message" => "Đăng nhập thành công."
    ];
}

function require_admin(): void {
    if (empty($_SESSION["admin_id"])) redirect("/login");
}
function admin_logout(): void {
    $_SESSION = [];
    session_destroy();
    redirect("/login");
}
