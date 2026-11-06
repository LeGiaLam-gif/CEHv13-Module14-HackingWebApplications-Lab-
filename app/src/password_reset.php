<?php
require_once __DIR__ . "/db.php";

class PasswordResetMailException extends RuntimeException {}

function password_reset_request(string $email): void {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;

    $stmt = db()->prepare("SELECT id, email, full_name FROM admins WHERE email = :email LIMIT 1");
    $stmt->execute(["email" => $email]);
    $admin = $stmt->fetch();
    if (!$admin) return;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare("SELECT id FROM admins WHERE id = :id FOR UPDATE");
        $lock->execute(["id" => $admin["id"]]);
        $resetStmt = $pdo->prepare("
            SELECT id
            FROM admin_password_resets
            WHERE admin_id = :admin_id
              AND created_at > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 MINUTE)
            FOR UPDATE
        ");
        $resetStmt->execute(["admin_id" => $admin["id"]]);
        $previousRequest = $resetStmt->fetchColumn();

        if ($previousRequest) {
            $pdo->commit();
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash("sha256", $token);
        $delete = $pdo->prepare("DELETE FROM admin_password_resets WHERE admin_id = :admin_id");
        $delete->execute(["admin_id" => $admin["id"]]);
        $insert = $pdo->prepare("
            INSERT INTO admin_password_resets (admin_id, token_hash, expires_at)
            VALUES (:admin_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE))
        ");
        $insert->execute([
            "admin_id" => $admin["id"],
            "token_hash" => $tokenHash,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $config = require __DIR__ . "/config.php";
    $resetUrl = rtrim($config["app"]["url"], "/") . "/reset-password?token=" . rawurlencode($token);
    $name = trim((string) ($admin["full_name"] ?? ""));
    $greeting = $name !== "" ? "Xin chào {$name}," : "Xin chào,";
    $body = "{$greeting}\r\n\r\n"
        . "Bạn vừa yêu cầu đặt lại mật khẩu quản trị. Mở liên kết dưới đây để tạo mật khẩu mới:\r\n"
        . "{$resetUrl}\r\n\r\n"
        . "Liên kết chỉ có hiệu lực trong 30 phút và chỉ sử dụng được một lần. "
        . "Nếu bạn không yêu cầu thao tác này, hãy bỏ qua email.\r\n";

    try {
        smtp_send_mail($config["mail"], $admin["email"], "Đặt lại mật khẩu quản trị", $body);
    } catch (PasswordResetMailException $e) {
        try {
            $delete = db()->prepare("DELETE FROM admin_password_resets WHERE token_hash = :token_hash");
            $delete->execute(["token_hash" => $tokenHash]);
        } catch (Throwable $cleanupError) {
            error_log("Không thể thu hồi token đặt lại mật khẩu sau lỗi SMTP: " . $cleanupError->getMessage());
        }
        throw $e;
    }
}

function password_reset_token_is_valid(string $token): bool {
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) return false;
    $stmt = db()->prepare("
        SELECT id
        FROM admin_password_resets
        WHERE token_hash = :token_hash AND expires_at > CURRENT_TIMESTAMP
        LIMIT 1
    ");
    $stmt->execute(["token_hash" => hash("sha256", $token)]);
    return (bool) $stmt->fetchColumn();
}

function password_reset_complete(string $token, string $password): bool {
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) return false;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT id, admin_id
            FROM admin_password_resets
            WHERE token_hash = :token_hash AND expires_at > CURRENT_TIMESTAMP
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(["token_hash" => hash("sha256", $token)]);
        $reset = $stmt->fetch();
        if (!$reset) {
            $pdo->rollBack();
            return false;
        }

        $update = $pdo->prepare("UPDATE admins SET password_hash = :password_hash WHERE id = :id");
        $update->execute([
            "password_hash" => password_hash($password, PASSWORD_DEFAULT),
            "id" => $reset["admin_id"],
        ]);
        $delete = $pdo->prepare("DELETE FROM admin_password_resets WHERE id = :id");
        $delete->execute(["id" => $reset["id"]]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function smtp_send_mail(array $mail, string $recipient, string $subject, string $body): void {
    $host = trim($mail["host"]);
    $port = $mail["port"];
    $username = $mail["username"];
    $password = $mail["password"];
    $encryption = $mail["encryption"];
    $fromAddress = $mail["from_address"];

    if ($host === "" || $username === "" || $password === "" || $fromAddress === ""
        || $port < 1 || $port > 65535 || !in_array($encryption, ["tls", "ssl"], true)
        || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        throw new PasswordResetMailException("SMTP chưa được cấu hình đầy đủ hoặc không hợp lệ.");
    }

    $context = stream_context_create([
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
            "peer_name" => $host,
        ],
    ]);
    $transport = $encryption === "ssl" ? "ssl://" : "tcp://";
    $socket = @stream_socket_client(
        $transport . $host . ":" . $port,
        $errorCode,
        $errorMessage,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if ($socket === false) {
        throw new PasswordResetMailException("Không thể kết nối SMTP ({$errorCode}): {$errorMessage}");
    }

    stream_set_timeout($socket, 15);
    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, "EHLO " . (gethostname() ?: "localhost"), [250]);
        if ($encryption === "tls") {
            smtp_command($socket, "STARTTLS", [220]);
            $cryptoEnabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new PasswordResetMailException("Không thể thiết lập kết nối TLS tới máy chủ SMTP.");
            }
            smtp_command($socket, "EHLO " . (gethostname() ?: "localhost"), [250]);
        }

        smtp_command($socket, "AUTH LOGIN", [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, "MAIL FROM:<{$fromAddress}>", [250]);
        smtp_command($socket, "RCPT TO:<{$recipient}>", [250, 251]);
        smtp_command($socket, "DATA", [354]);

        $encodedName = "=?UTF-8?B?" . base64_encode($mail["from_name"]) . "?=";
        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers = [
            "Date: " . date(DATE_RFC2822),
            "From: {$encodedName} <{$fromAddress}>",
            "To: <{$recipient}>",
            "Subject: {$encodedSubject}",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n"
            . preg_replace('/(?m)^\./', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", $body))
            . "\r\n.\r\n";
        smtp_write($socket, $message);
        smtp_expect($socket, [250]);
        smtp_write($socket, "QUIT\r\n");
    } finally {
        fclose($socket);
    }
}

function smtp_command($socket, string $command, array $expectedCodes): void {
    smtp_write($socket, $command . "\r\n");
    smtp_expect($socket, $expectedCodes);
}

function smtp_write($socket, string $data): void {
    $written = 0;
    $length = strlen($data);
    while ($written < $length) {
        $result = fwrite($socket, substr($data, $written));
        if ($result === false || $result === 0) {
            throw new PasswordResetMailException("Kết nối SMTP bị ngắt khi gửi dữ liệu.");
        }
        $written += $result;
    }
}

function smtp_expect($socket, array $expectedCodes): void {
    $response = "";
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new PasswordResetMailException("Không nhận được phản hồi hợp lệ từ máy chủ SMTP.");
        }
        $response = $line;
    } while (preg_match('/\A\d{3}-/', $line) === 1);

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new PasswordResetMailException("Máy chủ SMTP từ chối yêu cầu ({$code}): " . trim($response));
    }
}
