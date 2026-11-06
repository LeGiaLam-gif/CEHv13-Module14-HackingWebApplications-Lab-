<?php
if (!function_exists("env_value")) {
    function env_value(string $key, string $default = ""): string {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}
return [
    "app" => [
        "name" => env_value("APP_NAME", "PHP Admin Product Manager"),
        "url" => env_value("APP_URL", "http://localhost:8090"),
        "session_name" => env_value("SESSION_NAME", "ADMIN_PRODUCT_SESSION"),
        "upload_max_size" => (int) env_value("UPLOAD_MAX_SIZE", "2097152"),
    ],
    "db" => [
        "host" => env_value("DB_HOST", "mysql"),
        "port" => env_value("DB_PORT", "3306"),
        "database" => env_value("DB_DATABASE", "admin_product_db"),
        "username" => env_value("DB_USERNAME", "admin_user"),
        "password" => env_value("DB_PASSWORD", "admin_password"),
    ],
    "mail" => [
        "host" => env_value("SMTP_HOST"),
        "port" => (int) env_value("SMTP_PORT", "587"),
        "username" => env_value("SMTP_USERNAME"),
        "password" => env_value("SMTP_PASSWORD"),
        "encryption" => env_value("SMTP_ENCRYPTION", "tls"),
        "from_address" => env_value("MAIL_FROM_ADDRESS"),
        "from_name" => env_value("MAIL_FROM_NAME", "PHP Admin Product Manager"),
    ],
];
