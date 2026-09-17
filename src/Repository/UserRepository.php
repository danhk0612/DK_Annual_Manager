<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

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
}
