<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

use JsonException;

final class AuditLogRepository extends AbstractRepository
{
    /** @param array<string, mixed> $details */
    public function record(
        ?int $actorUserId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        array $details = [],
        ?string $ipAddress = null,
    ): void {
        try {
            $detailsJson = $details === []
                ? null
                : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $detailsJson = null;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs '
            . '(actor_user_id, action, target_type, target_id, details_json, ip_address) '
            . 'VALUES (:actor_user_id, :action, :target_type, :target_id, :details_json, :ip_address)'
        );
        $statement->execute([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details_json' => $detailsJson,
            'ip_address' => $ipAddress !== null ? substr($ipAddress, 0, 45) : null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $statement = $this->pdo->query(
            'SELECT a.id, a.action, a.target_type, a.target_id, a.details_json, a.ip_address, a.created_at, '
            . 'u.name AS actor_name '
            . 'FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_user_id '
            . 'ORDER BY a.id DESC LIMIT ' . $limit
        );

        return $statement->fetchAll();
    }
}
