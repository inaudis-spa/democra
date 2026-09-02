<?php

declare(strict_types=1);

namespace InaudisVote\Core;

final class Audit
{
    public static function log(string $eventType, string $entityType, ?int $entityId = null, array $metadata = [], ?int $userId = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, event_type, entity_type, entity_id, metadata, ip_address, created_at)
             VALUES (:user_id, :event_type, :entity_type, :entity_id, :metadata, :ip_address, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId ?? (isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null),
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
