<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

final class AnnualLeaveLedgerRepository extends AbstractRepository
{
    public function insertGrantIfMissing(
        int $userId,
        int $leaveYear,
        float $amount,
        string $ledgerKey,
        string $note,
        ?int $createdBy,
    ): bool {
        $statement = $this->pdo->prepare(
            "INSERT IGNORE INTO annual_leave_ledger "
            . "(user_id, leave_year, transaction_type, amount, ledger_key, note, created_by) "
            . "VALUES (:user_id, :leave_year, 'grant', :amount, :ledger_key, :note, :created_by)"
        );
        $statement->execute([
            'user_id' => $userId,
            'leave_year' => $leaveYear,
            'amount' => $amount,
            'ledger_key' => $ledgerKey,
            'note' => $note,
            'created_by' => $createdBy,
        ]);

        return $statement->rowCount() === 1;
    }

    public function addManual(
        int $userId,
        int $leaveYear,
        string $transactionType,
        float $amount,
        ?string $note,
        ?int $createdBy,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO annual_leave_ledger '
            . '(user_id, leave_year, transaction_type, amount, note, created_by) '
            . 'VALUES (:user_id, :leave_year, :transaction_type, :amount, :note, :created_by)'
        );
        $statement->execute([
            'user_id' => $userId,
            'leave_year' => $leaveYear,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'note' => $note,
            'created_by' => $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteAutomaticGrantsForUser(int $userId): int
    {
        $statement = $this->pdo->prepare(
            "DELETE FROM annual_leave_ledger "
            . "WHERE user_id = :user_id AND transaction_type = 'grant' "
            . "AND ledger_key LIKE :ledger_key"
        );
        $statement->execute([
            'user_id' => $userId,
            'ledger_key' => sprintf('user:%d:%%', $userId),
        ]);

        return $statement->rowCount();
    }

    public function setOverrideAdjustment(
        int $userId,
        int $leaveYear,
        float $amount,
        string $note,
        ?int $createdBy,
    ): void {
        $ledgerKey = sprintf('override:%d:%d', $userId, $leaveYear);
        $statement = $this->pdo->prepare(
            "INSERT INTO annual_leave_ledger "
            . "(user_id, leave_year, transaction_type, amount, ledger_key, note, created_by) "
            . "VALUES (:user_id, :leave_year, 'adjustment', :amount, :ledger_key, :note, :created_by) "
            . "ON DUPLICATE KEY UPDATE amount = VALUES(amount), note = VALUES(note), "
            . "created_by = VALUES(created_by), created_at = CURRENT_TIMESTAMP"
        );
        $statement->execute([
            'user_id' => $userId,
            'leave_year' => $leaveYear,
            'amount' => $amount,
            'ledger_key' => $ledgerKey,
            'note' => $note,
            'created_by' => $createdBy,
        ]);
    }

    public function deleteOverrideAdjustment(int $userId, int $leaveYear): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM annual_leave_ledger WHERE ledger_key = :ledger_key'
        );
        $statement->execute(['ledger_key' => sprintf('override:%d:%d', $userId, $leaveYear)]);
    }

    public function nonUsageTotalExcludingOverride(int $userId, int $leaveYear): float
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM annual_leave_ledger "
            . "WHERE user_id = :user_id AND leave_year = :leave_year "
            . "AND transaction_type <> 'usage' "
            . "AND (ledger_key IS NULL OR ledger_key <> :override_key)"
        );
        $statement->execute([
            'user_id' => $userId,
            'leave_year' => $leaveYear,
            'override_key' => sprintf('override:%d:%d', $userId, $leaveYear),
        ]);

        return (float) $statement->fetchColumn();
    }

    public function nonUsageTotalForUserYear(int $userId, int $leaveYear): float
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM annual_leave_ledger "
            . "WHERE user_id = :user_id AND leave_year = :leave_year "
            . "AND transaction_type <> 'usage'"
        );
        $statement->execute([
            'user_id' => $userId,
            'leave_year' => $leaveYear,
        ]);

        return (float) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function entriesForUserYear(int $userId, int $leaveYear): array
    {
        $statement = $this->pdo->prepare(
            'SELECT l.*, u.name AS created_by_name '
            . 'FROM annual_leave_ledger l '
            . 'LEFT JOIN users u ON u.id = l.created_by '
            . 'WHERE l.user_id = :user_id AND l.leave_year = :leave_year '
            . 'ORDER BY l.created_at ASC, l.id ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'leave_year' => $leaveYear,
        ]);

        return $statement->fetchAll();
    }

    public function balanceForUserYear(int $userId, int $leaveYear): float
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) '
            . 'FROM annual_leave_ledger '
            . 'WHERE user_id = :user_id AND leave_year = :leave_year'
        );
        $statement->execute([
            'user_id' => $userId,
            'leave_year' => $leaveYear,
        ]);

        return (float) $statement->fetchColumn();
    }
}
