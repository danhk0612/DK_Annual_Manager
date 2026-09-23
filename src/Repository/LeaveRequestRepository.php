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
        ?string $halfDayPeriod,
        ?string $reason,
        array $leaveDates,
        float $dailyAmount,
    ): int {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO leave_requests '
                . '(user_id, leave_type_id, start_date, end_date, requested_amount, half_day_period, reason, status) '
                . "VALUES (:user_id, :leave_type_id, :start_date, :end_date, :requested_amount, :half_day_period, :reason, 'pending')"
            );
            $statement->execute([
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'requested_amount' => $requestedAmount,
                'half_day_period' => $halfDayPeriod,
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
    public function allForAdmin(): array
    {
        return $this->searchHistory(null);
    }

    /** @return list<array<string, mixed>> */
    public function searchHistory(
        ?int $userId,
        string $query = '',
        string $status = '',
        ?int $year = null,
    ): array {
        $sql = 'SELECT r.*, u.name AS user_name, u.department, '
            . 't.code AS leave_code, t.name AS leave_type_name '
            . 'FROM leave_requests r '
            . 'INNER JOIN users u ON u.id = r.user_id '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . 'WHERE 1=1 ';
        $params = [];

        if ($userId !== null) {
            $sql .= 'AND r.user_id = :user_id ';
            $params['user_id'] = $userId;
        }

        $query = trim($query);
        if ($query !== '') {
            $sql .= 'AND (u.name LIKE :q_user OR u.department LIKE :q_department '
                . 'OR t.name LIKE :q_type OR r.reason LIKE :q_reason OR r.review_note LIKE :q_review '
                . 'OR r.cancellation_note LIKE :q_cancel) ';
            $like = '%' . $query . '%';
            $params['q_user'] = $like;
            $params['q_department'] = $like;
            $params['q_type'] = $like;
            $params['q_reason'] = $like;
            $params['q_review'] = $like;
            $params['q_cancel'] = $like;
        }

        if (in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $sql .= 'AND r.status = :status ';
            $params['status'] = $status;
        }

        if ($year !== null && $year >= 2000 && $year <= 2100) {
            $sql .= 'AND YEAR(r.start_date) = :leave_year ';
            $params['leave_year'] = $year;
        }

        $sql .= 'ORDER BY r.created_at DESC, r.id DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function pendingForAdmin(): array
    {
        $statement = $this->pdo->query(
            'SELECT r.*, u.name AS user_name, u.telegram_user_id, '
            . 't.code AS leave_code, t.name AS leave_type_name, t.deducts_annual_leave, '
            . "(SELECT COALESCE(SUM(l.amount), 0) FROM annual_leave_ledger l "
            . "WHERE l.user_id = r.user_id AND l.leave_year = YEAR(r.start_date)) AS annual_balance "
            . 'FROM leave_requests r '
            . 'INNER JOIN users u ON u.id = r.user_id '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . "WHERE r.status = 'pending' "
            . 'ORDER BY r.created_at ASC, r.id ASC'
        );

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function approvedForAdmin(?int $limit = null): array
    {
        $sql = 'SELECT r.*, u.name AS user_name, u.telegram_user_id, '
            . 't.code AS leave_code, t.name AS leave_type_name, t.deducts_annual_leave '
            . 'FROM leave_requests r '
            . 'INNER JOIN users u ON u.id = r.user_id '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . "WHERE r.status = 'approved' "
            . 'ORDER BY r.start_date DESC, r.id DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, min(500, $limit));
        }

        return $this->pdo->query($sql)->fetchAll();
    }

    public function closedHistoryCount(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM leave_requests WHERE status IN ('cancelled', 'rejected')"
        )->fetchColumn();
    }

    /**
     * Permanently removes rejected/cancelled leave history and related traces.
     *
     * @return array{requests:int,days:int,ledger:int,audit:int}
     */
    public function purgeClosedHistory(): array
    {
        $this->pdo->beginTransaction();

        try {
            $requests = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM leave_requests WHERE status IN ('cancelled', 'rejected')"
            )->fetchColumn();
            $days = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM leave_request_days d "
                . "INNER JOIN leave_requests r ON r.id = d.leave_request_id "
                . "WHERE r.status IN ('cancelled', 'rejected')"
            )->fetchColumn();

            $ledgerDelete = $this->pdo->exec(
                "DELETE FROM annual_leave_ledger "
                . "WHERE reference_request_id IN ("
                . "SELECT id FROM leave_requests WHERE status IN ('cancelled', 'rejected')"
                . ")"
            );
            $requestDelete = $this->pdo->exec(
                "DELETE FROM leave_requests WHERE status IN ('cancelled', 'rejected')"
            );
            $auditDelete = $this->pdo->exec(
                "DELETE a FROM audit_logs a "
                . "LEFT JOIN leave_requests r "
                . "ON r.id = a.target_id AND a.target_type = 'leave_request' "
                . "WHERE a.target_type = 'leave_request' "
                . "AND a.target_id IS NOT NULL "
                . "AND r.id IS NULL"
            );

            $this->pdo->commit();

            return [
                'requests' => (int) $requestDelete,
                'days' => $days,
                'ledger' => (int) $ledgerDelete,
                'audit' => (int) $auditDelete,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public function deletePending(int $requestId, int $userId): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $select = $this->pdo->prepare(
                'SELECT r.*, t.code AS leave_code, t.name AS leave_type_name '
                . 'FROM leave_requests r '
                . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
                . "WHERE r.id = :id AND r.user_id = :user_id AND r.status = 'pending' "
                . 'FOR UPDATE'
            );
            $select->execute([
                'id' => $requestId,
                'user_id' => $userId,
            ]);
            $request = $select->fetch();

            if (!is_array($request)) {
                $this->pdo->rollBack();
                return null;
            }

            $delete = $this->pdo->prepare(
                "DELETE FROM leave_requests "
                . "WHERE id = :id AND user_id = :user_id AND status = 'pending'"
            );
            $delete->execute([
                'id' => $requestId,
                'user_id' => $userId,
            ]);

            if ($delete->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }

            $this->pdo->commit();
            return $request;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function calendarEntries(string $startDate, string $endDate, ?int $userId = null): array
    {
        $sql = 'SELECT d.leave_date, d.amount, r.id AS request_id, r.status, r.half_day_period, '
            . 'r.start_date, r.end_date, r.requested_amount, r.reason, r.review_note, r.created_at, '
            . 'u.id AS user_id, u.name AS user_name, u.department, '
            . 't.code AS leave_code, t.name AS leave_type_name, t.deducts_annual_leave '
            . 'FROM leave_request_days d '
            . 'INNER JOIN leave_requests r ON r.id = d.leave_request_id '
            . 'INNER JOIN users u ON u.id = r.user_id '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . 'WHERE d.leave_date BETWEEN :start_date AND :end_date '
            . "AND r.status IN ('pending', 'approved') ";

        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        if ($userId !== null) {
            $sql .= 'AND r.user_id = :user_id ';
            $params['user_id'] = $userId;
        }

        $sql .= 'ORDER BY d.leave_date ASC, u.name ASC, r.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function calendarRequestList(string $startDate, string $endDate, ?int $userId = null): array
    {
        $sql = 'SELECT r.*, u.name AS user_name, u.department, t.code AS leave_code, t.name AS leave_type_name, '
            . 't.deducts_annual_leave '
            . 'FROM leave_requests r '
            . 'INNER JOIN users u ON u.id = r.user_id '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . 'WHERE EXISTS (SELECT 1 FROM leave_request_days d '
            . 'WHERE d.leave_request_id = r.id AND d.leave_date BETWEEN :start_date AND :end_date) ';

        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        if ($userId !== null) {
            $sql .= 'AND r.user_id = :user_id ';
            $params['user_id'] = $userId;
        }

        $sql .= 'ORDER BY r.start_date ASC, u.name ASC, r.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
