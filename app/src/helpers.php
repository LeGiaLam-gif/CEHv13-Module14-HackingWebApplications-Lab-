<?php
function e(?string $value): string { return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8"); }
function redirect(string $path): void { header("Location: " . $path); exit; }
function is_post(): bool { return $_SERVER["REQUEST_METHOD"] === "POST"; }

function csrf_token(): string {
    if (empty($_SESSION["_csrf_token"])) $_SESSION["_csrf_token"] = bin2hex(random_bytes(32));
    return $_SESSION["_csrf_token"];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}
function verify_csrf(): void {
    $token = $_POST["_csrf_token"] ?? "";
    if (empty($_SESSION["_csrf_token"]) || !hash_equals($_SESSION["_csrf_token"], $token)) {
        http_response_code(419); die("CSRF token không hợp lệ.");
    }
}
function upload_product_images(array $files): array {
    $config = require __DIR__ . "/config.php";
    if (!isset($files["error"])) return [];

    $fileKeys = ["name", "type", "tmp_name", "error", "size"];
    foreach ($fileKeys as $key) {
        if (!array_key_exists($key, $files) || !is_array($files[$key])) {
            throw new RuntimeException("Dữ liệu hình ảnh tải lên không hợp lệ.");
        }
    }

    $uploads = [];
    foreach ($files["error"] as $index => $error) {
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException("Dung lượng mỗi hình ảnh không được vượt quá 2MB.");
        }
        if ($error === UPLOAD_ERR_PARTIAL) throw new RuntimeException("Hình ảnh tải lên chưa hoàn tất.");
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException("Upload hình ảnh thất bại.");
        $uploads[] = [
            "name" => $files["name"][$index] ?? "",
            "tmp_name" => $files["tmp_name"][$index] ?? "",
            "size" => $files["size"][$index] ?? 0,
        ];
    }
    if (count($uploads) > 10) throw new RuntimeException("Mỗi lần chỉ được tải lên tối đa 10 hình ảnh.");
    if (!$uploads) return [];

    $allowed = ["image/jpeg"=>"jpg","image/png"=>"png","image/webp"=>"webp","image/gif"=>"gif"];
    $dir = __DIR__ . "/../public/uploads/products";
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Không thể tạo thư mục lưu hình ảnh.");
    }

    $imagePaths = [];
    try {
        foreach ($uploads as $file) {
            if (!is_int($file["size"]) && (!is_string($file["size"]) || !ctype_digit($file["size"]))) {
                throw new RuntimeException("Dung lượng hình ảnh không hợp lệ.");
            }
            if ((int) $file["size"] > $config["app"]["upload_max_size"]) {
                throw new RuntimeException("Dung lượng mỗi hình ảnh không được vượt quá 2MB.");
            }
            if (!is_string($file["tmp_name"]) || !is_file($file["tmp_name"])) {
                throw new RuntimeException("Tệp hình ảnh tải lên không hợp lệ.");
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
            if (!array_key_exists($mime, $allowed)) {
                throw new RuntimeException("Chỉ cho phép JPG, PNG, WEBP hoặc GIF.");
            }
            $name = date("YmdHis") . "_" . bin2hex(random_bytes(8)) . "." . $allowed[$mime];
            if (!move_uploaded_file($file["tmp_name"], $dir . "/" . $name)) {
                throw new RuntimeException("Không thể lưu hình ảnh.");
            }
            $imagePaths[] = "uploads/products/" . $name;
        }
    } catch (Throwable $e) {
        foreach ($imagePaths as $imagePath) delete_product_image($imagePath);
        throw $e;
    }

    return $imagePaths;
}
function delete_product_image(?string $imagePath): void {
    if (!$imagePath) return;
    $fullPath = __DIR__ . "/../public/" . $imagePath;
    if (is_file($fullPath) && !unlink($fullPath)) {
        throw new RuntimeException("Không thể xóa hình ảnh khỏi thư mục tải lên.");
    }
}
