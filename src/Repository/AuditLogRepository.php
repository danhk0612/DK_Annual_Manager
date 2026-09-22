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
        return $this->search('', '', $limit);
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query = '', string $action = '', int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));
        $sql = 'SELECT a.id, a.action, a.target_type, a.target_id, a.details_json, a.ip_address, a.created_at, '
            . 'u.name AS actor_name '
            . 'FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_user_id '
            . 'WHERE 1=1 ';
        $params = [];

        $query = trim($query);
        if ($query !== '') {
            $sql .= 'AND (u.name LIKE :q_actor OR a.action LIKE :q_action OR a.target_type LIKE :q_target '
                . 'OR CAST(a.target_id AS CHAR) LIKE :q_target_id OR a.details_json LIKE :q_details OR a.ip_address LIKE :q_ip) ';
            $like = '%' . $query . '%';
            $params = [
                'q_actor' => $like,
                'q_action' => $like,
                'q_target' => $like,
                'q_target_id' => $like,
                'q_details' => $like,
                'q_ip' => $like,
            ];
        }

        $action = trim($action);
        if ($action !== '') {
            $sql .= 'AND a.action = :filter_action ';
            $params['filter_action'] = $action;
        }

        $sql .= 'ORDER BY a.id DESC LIMIT ' . $limit;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return list<string> */
    public function actionNames(): array
    {
        $statement = $this->pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC');
        return array_map(
            static fn (array $row): string => (string) $row['action'],
            $statement->fetchAll(),
        );
    }
}
