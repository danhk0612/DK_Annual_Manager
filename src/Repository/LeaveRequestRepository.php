<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

use Throwable;

final class LeaveRequestRepository extends AbstractRepository
{
    /**
     * @param list<string> $leaveDates
     */
    public function create(
        int $userId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        float $requestedAmount,
        ?string $reason,
        array $leaveDates,
        float $dailyAmount,
    ): int {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO leave_requests '
                . '(user_id, leave_type_id, start_date, end_date, requested_amount, reason, status) '
                . "VALUES (:user_id, :leave_type_id, :start_date, :end_date, :requested_amount, :reason, 'pending')"
            );
            $statement->execute([
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'requested_amount' => $requestedAmount,
                'reason' => $reason,
            ]);

            $requestId = (int) $this->pdo->lastInsertId();
            $dayStatement = $this->pdo->prepare(
                'INSERT INTO leave_request_days (leave_request_id, leave_date, amount) '
                . 'VALUES (:leave_request_id, :leave_date, :amount)'
            );

            foreach ($leaveDates as $leaveDate) {
                $dayStatement->execute([
                    'leave_request_id' => $requestId,
                    'leave_date' => $leaveDate,
                    'amount' => $dailyAmount,
                ]);
            }

            $this->pdo->commit();
            return $requestId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<string> $dates */
    public function hasOpenDays(int $userId, array $dates): bool
    {
        if ($dates === []) {
            return false;
        }

        $placeholders = [];
        $params = ['user_id' => $userId];

        foreach ($dates as $index => $date) {
            $key = 'date_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $date;
        }

        $statement = $this->pdo->prepare(
            'SELECT 1 FROM leave_request_days d '
            . 'INNER JOIN leave_requests r ON r.id = d.leave_request_id '
            . "WHERE r.user_id = :user_id AND r.status IN ('pending', 'approved') "
            . 'AND d.leave_date IN (' . implode(', ', $placeholders) . ') '
            . 'LIMIT 1'
        );
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.*, t.code AS leave_code, t.name AS leave_type_name '
            . 'FROM leave_requests r '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . 'WHERE r.user_id = :user_id '
            . 'ORDER BY r.created_at DESC, r.id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function pendingForAdmin(): array
    {
        $statement = $this->pdo->query(
            'SELECT r.*, u.name AS user_name, u.telegram_user_id, '
            . 't.code AS leave_code, t.name AS leave_type_name, t.deducts_annual_leave '
            . 'FROM leave_requests r '
            . 'INNER JOIN users u ON u.id = r.user_id '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . "WHERE r.status = 'pending' "
            . 'ORDER BY r.created_at ASC, r.id ASC'
        );

        return $statement->fetchAll();
    }

    public function cancelPending(int $requestId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE leave_requests SET status = 'cancelled' "
            . "WHERE id = :id AND user_id = :user_id AND status = 'pending'"
        );
        $statement->execute([
            'id' => $requestId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() === 1;
    }

    /** @return list<array<string, mixed>> */
    public function calendarEntries(string $startDate, string $endDate): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.leave_date, d.amount, r.id AS request_id, r.status, '
            . 'u.id AS user_id, u.name AS user_name, '
            . 't.code AS leave_code, t.name AS leave_type_name '
            . 'FROM leave_request_days d '
            . 'INNER JOIN leave_requests r ON r.id = d.leave_request_id '
            . 'INNER JOIN users u ON u.id = r.user_id '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . 'WHERE d.leave_date BETWEEN :start_date AND :end_date '
            . "AND r.status IN ('pending', 'approved') "
            . 'ORDER BY d.leave_date ASC, u.name ASC, r.id ASC'
        );
        $statement->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $statement->fetchAll();
    }
}
