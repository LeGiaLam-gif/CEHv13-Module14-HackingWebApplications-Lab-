<?php
$host = getenv('DB_HOST') ?: 'mysql';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'admin_product_db';
$dbUser = getenv('DB_USERNAME') ?: 'admin_user';
$dbPass = getenv('DB_PASSWORD') ?: 'lab_db_password';

$attempts = 60;
$delay = 2;

for ($attempt = 1; $attempt <= $attempts; $attempt++) {
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        echo "Database ready after {$attempt} attempt(s).\n";
        exit(0);
    } catch (Throwable $e) {
        if ($attempt === $attempts) {
            fwrite(STDERR, "Database not ready: " . $e->getMessage() . "\n");
            exit(1);
        }
        sleep($delay);
    }
}
