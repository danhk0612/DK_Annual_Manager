<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

final class ReportingRepository extends AbstractRepository
{
    /** @return array<string, int|float> */
    public function dashboardMetrics(int $year): array
    {
        $activeUsers = (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        $pendingRequests = (int) $this->pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();

        $ledger = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN transaction_type = 'grant' THEN amount ELSE 0 END), 0) AS granted,
                COALESCE(-SUM(CASE WHEN transaction_type IN ('usage', 'reversal') THEN amount ELSE 0 END), 0) AS used,
                COALESCE(SUM(amount), 0) AS balance
             FROM annual_leave_ledger
             WHERE leave_year = :leave_year"
        );
        $ledger->execute(['leave_year' => $year]);
        $ledgerRow = $ledger->fetch() ?: [];

        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $today = date('Y-m-d');

        $month = $this->pdo->prepare(
            "SELECT COALESCE(SUM(d.amount), 0)
             FROM leave_request_days d
             INNER JOIN leave_requests r ON r.id = d.leave_request_id
             WHERE r.status = 'approved' AND d.leave_date BETWEEN :start_date AND :end_date"
        );
        $month->execute(['start_date' => $start, 'end_date' => $end]);

        $todayStatement = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT r.id)
             FROM leave_request_days d
             INNER JOIN leave_requests r ON r.id = d.leave_request_id
             WHERE r.status = 'approved' AND d.leave_date = :today"
        );
        $todayStatement->execute(['today' => $today]);

        return [
            'active_users' => $activeUsers,
            'pending_requests' => $pendingRequests,
            'granted' => (float) ($ledgerRow['granted'] ?? 0),
            'used' => (float) ($ledgerRow['used'] ?? 0),
            'balance' => (float) ($ledgerRow['balance'] ?? 0),
            'approved_this_month' => (float) $month->fetchColumn(),
            'on_leave_today' => (int) $todayStatement->fetchColumn(),
        ];
    }

    /** @return array<string, float> */
    public function userAnnualSummary(int $userId, int $year): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN transaction_type = 'grant' THEN amount ELSE 0 END), 0) AS granted,
                COALESCE(SUM(CASE WHEN transaction_type = 'carryover' THEN amount ELSE 0 END), 0) AS carryover,
                COALESCE(SUM(CASE WHEN transaction_type = 'adjustment' THEN amount ELSE 0 END), 0) AS adjustment,
                COALESCE(SUM(CASE WHEN transaction_type = 'reversal' THEN amount ELSE 0 END), 0) AS reversal,
                COALESCE(-SUM(CASE WHEN transaction_type = 'usage' THEN amount ELSE 0 END), 0) AS used,
                COALESCE(SUM(amount), 0) AS balance
             FROM annual_leave_ledger
             WHERE user_id = :user_id AND leave_year = :leave_year"
        );
        $statement->execute(['user_id' => $userId, 'leave_year' => $year]);
        $row = $statement->fetch() ?: [];

        $used = (float) ($row['used'] ?? 0);
        $reversal = (float) ($row['reversal'] ?? 0);
        $balance = (float) ($row['balance'] ?? 0);
        $netUsed = max(0.0, $used - $reversal);

        return [
            'granted' => (float) ($row['granted'] ?? 0),
            'carryover' => (float) ($row['carryover'] ?? 0),
            'adjustment' => (float) ($row['adjustment'] ?? 0),
            'reversal' => $reversal,
            'used' => $used,
            'net_used' => $netUsed,
            'balance' => $balance,
            'total' => $balance + $netUsed,
        ];
    }

    /** @return list<array{month_number:int, request_count:int, total_amount:float, deducted_amount:float, non_deducted_amount:float}> */
    public function userMonthlyLeaveSummary(int $userId, int $year): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                MONTH(d.leave_date) AS month_number,
                COUNT(DISTINCT r.id) AS request_count,
                COALESCE(SUM(d.amount), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN lt.deducts_annual_leave = 1 THEN d.amount ELSE 0 END), 0) AS deducted_amount,
                COALESCE(SUM(CASE WHEN lt.deducts_annual_leave = 0 THEN d.amount ELSE 0 END), 0) AS non_deducted_amount
             FROM leave_request_days d
             INNER JOIN leave_requests r ON r.id = d.leave_request_id
             INNER JOIN leave_types lt ON lt.id = r.leave_type_id
             WHERE r.user_id = :user_id
               AND r.status = 'approved'
               AND d.leave_date BETWEEN :start_date AND :end_date
             GROUP BY MONTH(d.leave_date)
             ORDER BY month_number ASC"
        );
        $statement->execute([
            'user_id' => $userId,
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
        ]);

        $rowsByMonth = [];
        foreach ($statement->fetchAll() as $row) {
            $month = (int) $row['month_number'];
            $rowsByMonth[$month] = [
                'month_number' => $month,
                'request_count' => (int) $row['request_count'],
                'total_amount' => (float) $row['total_amount'],
                'deducted_amount' => (float) $row['deducted_amount'],
                'non_deducted_amount' => (float) $row['non_deducted_amount'],
            ];
        }

        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $result[] = $rowsByMonth[$month] ?? [
                'month_number' => $month,
                'request_count' => 0,
                'total_amount' => 0.0,
                'deducted_amount' => 0.0,
                'non_deducted_amount' => 0.0,
            ];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function annualUserSummary(int $year): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                u.id,
                u.name,
                u.department,
                u.position,
                u.status,
                u.hire_date,
                COALESCE(SUM(CASE WHEN l.transaction_type = 'grant' THEN l.amount ELSE 0 END), 0) AS granted,
                COALESCE(SUM(CASE WHEN l.transaction_type = 'carryover' THEN l.amount ELSE 0 END), 0) AS carryover,
                COALESCE(SUM(CASE WHEN l.transaction_type = 'adjustment' THEN l.amount ELSE 0 END), 0) AS adjustment,
                COALESCE(SUM(CASE WHEN l.transaction_type = 'reversal' THEN l.amount ELSE 0 END), 0) AS reversal,
                COALESCE(-SUM(CASE WHEN l.transaction_type = 'usage' THEN l.amount ELSE 0 END), 0) AS used,
                COALESCE(SUM(l.amount), 0) AS balance
             FROM users u
             LEFT JOIN annual_leave_ledger l ON l.user_id = u.id AND l.leave_year = :leave_year
             GROUP BY u.id, u.name, u.department, u.position, u.status, u.hire_date
             ORDER BY CASE u.status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END, u.name ASC, u.id ASC"
        );
        $statement->execute(['leave_year' => $year]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function monthlyApprovedLeaveSummary(
        int $year,
        string $query = '',
        string $status = '',
        string $department = '',
    ): array {
        $sql = "SELECT
                    MONTH(d.leave_date) AS month_number,
                    lt.code,
                    lt.name,
                    SUM(d.amount) AS amount
                FROM leave_request_days d
                INNER JOIN leave_requests r ON r.id = d.leave_request_id
                INNER JOIN leave_types lt ON lt.id = r.leave_type_id
                INNER JOIN users u ON u.id = r.user_id
                WHERE r.status = 'approved'
                  AND d.leave_date BETWEEN :start_date AND :end_date";

        $params = [
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
        ];

        if ($query !== '') {
            $sql .= " AND (u.name LIKE :query_name OR u.department LIKE :query_department OR u.position LIKE :query_position)";
            $like = '%' . $query . '%';
            $params['query_name'] = $like;
            $params['query_department'] = $like;
            $params['query_position'] = $like;
        }
        if ($status !== '') {
            $sql .= ' AND u.status = :user_status';
            $params['user_status'] = $status;
        }
        if ($department !== '') {
            $sql .= ' AND u.department = :department';
            $params['department'] = $department;
        }

        $sql .= ' GROUP BY MONTH(d.leave_date), lt.id, lt.code, lt.name'
            . ' ORDER BY month_number ASC, lt.sort_order ASC, lt.id ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leaveExportRows(
        ?int $userId = null,
        ?string $startDate = null,
        ?string $endDate = null,
    ): array {
        $sql = "SELECT
                    r.id,
                    r.user_id,
                    u.name AS user_name,
                    u.department,
                    u.position,
                    lt.code AS leave_code,
                    lt.name AS leave_type_name,
                    r.half_day_period,
                    r.start_date,
                    r.end_date,
                    r.requested_amount,
                    r.status,
                    r.reason,
                    r.created_at,
                    r.reviewed_at,
                    r.review_note,
                    reviewer.name AS reviewed_by_name,
                    r.cancelled_at,
                    r.cancellation_source,
                    r.cancellation_note,
                    canceller.name AS cancelled_by_name,
                    COALESCE(SUM(d.amount), 0) AS period_amount
                FROM leave_requests r
                INNER JOIN users u ON u.id = r.user_id
                INNER JOIN leave_types lt ON lt.id = r.leave_type_id
                INNER JOIN leave_request_days d ON d.leave_request_id = r.id
                LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
                LEFT JOIN users canceller ON canceller.id = r.cancelled_by
                WHERE 1 = 1";

        $params = [];

        if ($userId !== null) {
            $sql .= ' AND r.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if ($startDate !== null && $endDate !== null) {
            $sql .= ' AND d.leave_date BETWEEN :start_date AND :end_date';
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }

        $sql .= " GROUP BY
                    r.id,
                    r.user_id,
                    u.name,
                    u.department,
                    u.position,
                    lt.code,
                    lt.name,
                    r.half_day_period,
                    r.start_date,
                    r.end_date,
                    r.requested_amount,
                    r.status,
                    r.reason,
                    r.created_at,
                    r.reviewed_at,
                    r.review_note,
                    reviewer.name,
                    r.cancelled_at,
                    r.cancellation_source,
                    r.cancellation_note,
                    canceller.name
                  ORDER BY r.start_date ASC, u.name ASC, r.id ASC";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function pendingRequestPreview(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $statement = $this->pdo->query(
            "SELECT r.id, r.start_date, r.end_date, r.requested_amount, r.half_day_period,
                    u.name AS user_name, u.department, lt.name AS leave_type_name
             FROM leave_requests r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN leave_types lt ON lt.id = r.leave_type_id
             WHERE r.status = 'pending'
             ORDER BY r.created_at ASC, r.id ASC
             LIMIT " . $limit
        );

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function upcomingApprovedLeaves(string $fromDate, string $toDate, int $limit = 8): array
    {
        $limit = max(1, min(30, $limit));
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT r.id, r.start_date, r.end_date, r.requested_amount, r.half_day_period,
                    u.name AS user_name, u.department, lt.name AS leave_type_name
             FROM leave_requests r
             INNER JOIN leave_request_days d ON d.leave_request_id = r.id
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN leave_types lt ON lt.id = r.leave_type_id
             WHERE r.status = 'approved'
               AND d.leave_date BETWEEN :from_date AND :to_date
             ORDER BY r.start_date ASC, u.name ASC, r.id ASC
             LIMIT " . $limit
        );
        $statement->execute([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return $statement->fetchAll();
    }

    /** @return list<array{month_number:int, amount:float}> */
    public function monthlyApprovedTotals(int $year): array
    {
        $statement = $this->pdo->prepare(
            "SELECT MONTH(d.leave_date) AS month_number, SUM(d.amount) AS amount
             FROM leave_request_days d
             INNER JOIN leave_requests r ON r.id = d.leave_request_id
             WHERE r.status = 'approved'
               AND d.leave_date BETWEEN :start_date AND :end_date
             GROUP BY MONTH(d.leave_date)
             ORDER BY month_number ASC"
        );
        $statement->execute([
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
        ]);

        return array_map(
            static fn (array $row): array => [
                'month_number' => (int) $row['month_number'],
                'amount' => (float) $row['amount'],
            ],
            $statement->fetchAll(),
        );
    }
}
