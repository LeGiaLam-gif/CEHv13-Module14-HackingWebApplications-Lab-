<?php

function lab_audit(string $decision, ?string $reason = null, ?string $targetType = null, ?int $targetId = null): void {
    $GLOBALS["lab_audit_recorded"] = true;
    try {
        $stmt = db()->prepare(
            "INSERT INTO security_audit_log
                (actor_user_id, method, route, target_type, target_id, decision, reason, lab_mode, ip_address)
             VALUES
                (:actor, :method, :route, :target_type, :target_id, :decision, :reason, :mode, :ip)"
        );
        $stmt->execute([
            "actor" => isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null,
            "method" => substr((string) ($_SERVER["REQUEST_METHOD"] ?? "GET"), 0, 10),
            "route" => substr((string) parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH), 0, 255),
            "target_type" => $targetType,
            "target_id" => $targetId,
            "decision" => $decision,
            "reason" => $reason === null ? null : substr($reason, 0, 255),
            "mode" => lab_mode(),
            "ip" => substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45),
        ]);
    } catch (Throwable $e) {
        error_log("Lab audit write failed: " . $e->getMessage());
    }
}
