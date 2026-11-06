<?php
require_once __DIR__ . "/db.php";

function activity_log_create(string $action, string $entityType, ?int $entityId, string $description): void {
    $adminId = isset($_SESSION["admin_id"]) ? (int) $_SESSION["admin_id"] : null;
    $actorName = trim((string) ($_SESSION["admin_full_name"] ?? ""));
    if ($actorName === "") {
        $actorName = (string) ($_SESSION["admin_username"] ?? "Admin");
    }
    $ipAddress = $_SERVER["REMOTE_ADDR"] ?? null;

    $stmt = db()->prepare(
        "INSERT INTO admin_activity_logs (admin_id, actor_name, action, entity_type, entity_id, description, ip_address)
         VALUES (:admin_id, :actor_name, :action, :entity_type, :entity_id, :description, :ip_address)"
    );
    $stmt->execute([
        "admin_id" => $adminId,
        "actor_name" => $actorName,
        "action" => $action,
        "entity_type" => $entityType,
        "entity_id" => $entityId,
        "description" => $description,
        "ip_address" => $ipAddress !== null ? substr($ipAddress, 0, 45) : null,
    ]);
}

function activity_log_recent(int $limit = 200): array {
    $stmt = db()->prepare(
        "SELECT id, admin_id, actor_name, action, entity_type, entity_id, description, ip_address, created_at
         FROM admin_activity_logs
         ORDER BY id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(":limit", max(1, min(500, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
