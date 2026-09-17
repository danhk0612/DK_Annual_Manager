<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

final class LeaveTypeRepository extends AbstractRepository
{
    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        $statement = $this->pdo->query(
            'SELECT * FROM leave_types WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        );

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM leave_types WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
