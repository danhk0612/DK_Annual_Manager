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
