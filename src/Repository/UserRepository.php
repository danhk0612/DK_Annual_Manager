<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

use PDO;

final class UserRepository extends AbstractRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /** @return array<string, mixed>|null */
    public function findByTelegramUserId(int $telegramUserId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE telegram_user_id = :telegram_user_id LIMIT 1');
        $statement->execute(['telegram_user_id' => $telegramUserId]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $statement = $this->pdo->query(
            "SELECT * FROM users
             ORDER BY
                CASE status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 ELSE 2 END,
                name ASC,
                id ASC"
        );

        return $statement->fetchAll();
    }

    /** @return list<int> */
    public function activeAdminTelegramIds(): array
    {
        $statement = $this->pdo->query(
            "SELECT telegram_user_id FROM users "
            . "WHERE role = 'admin' AND status = 'active' AND telegram_user_id IS NOT NULL"
        );

        return array_map(
            static fn (array $row): int => (int) $row['telegram_user_id'],
            $statement->fetchAll(),
        );
    }

    public function createFromTelegram(
        int $telegramUserId,
        string $name,
        ?string $username,
        string $role = 'user',
        string $status = 'pending',
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, telegram_user_id, telegram_username, hire_date, role, status) '
            . 'VALUES (:name, :telegram_user_id, :telegram_username, NULL, :role, :status)'
        );
        $statement->execute([
            'name' => $name,
            'telegram_user_id' => $telegramUserId,
            'telegram_username' => $username,
            'role' => $role,
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createManaged(
        string $name,
        ?string $department,
        ?string $position,
        ?string $hireDate,
        ?string $employmentEndDate,
        ?int $telegramUserId,
        string $role,
        string $status,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(name, telegram_user_id, department, position, hire_date, employment_end_date, role, status) '
            . 'VALUES (:name, :telegram_user_id, :department, :position, :hire_date, :employment_end_date, :role, :status)'
        );
        $statement->execute([
            'name' => $name,
            'telegram_user_id' => $telegramUserId,
            'department' => $department,
            'position' => $position,
            'hire_date' => $hireDate,
            'employment_end_date' => $employmentEndDate,
            'role' => $role,
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateManaged(
        int $id,
        string $name,
        ?string $department,
        ?string $position,
        ?string $hireDate,
        ?string $employmentEndDate,
        ?int $telegramUserId,
        string $role,
        string $status,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE users SET '
            . 'name = :name, telegram_user_id = :telegram_user_id, department = :department, position = :position, '
            . 'hire_date = :hire_date, employment_end_date = :employment_end_date, role = :role, status = :status '
            . 'WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'telegram_user_id' => $telegramUserId,
            'department' => $department,
            'position' => $position,
            'hire_date' => $hireDate,
            'employment_end_date' => $employmentEndDate,
            'role' => $role,
            'status' => $status,
        ]);
    }

    public function updateOwnProfile(
        int $id,
        string $hireDate,
        ?string $department,
        ?string $position,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE users SET hire_date = :hire_date, department = :department, position = :position WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'hire_date' => $hireDate,
            'department' => $department,
            'position' => $position,
        ]);
    }

    public function syncTelegramProfile(int $id, string $name, ?string $username): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET name = :name, telegram_username = :telegram_username WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'telegram_username' => $username,
        ]);
    }

    public function activateAsAdmin(int $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE users SET role = 'admin', status = 'active' WHERE id = :id"
        );
        $statement->execute(['id' => $id]);
    }
}
