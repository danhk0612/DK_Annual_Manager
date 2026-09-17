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
                COALESCE(-SUM(CASE WHEN transaction_type = 'usage' THEN amount ELSE 0 END), 0) AS used,
                COALESCE(SUM(amount), 0) AS balance
             FROM annual_leave_ledger
             WHERE leave_year = :leave_year"
        );
        $ledger->execute(['leave_year' => $year]);
        $ledgerRow = $ledger->fetch() ?: [];

        $start = sprintf('%04d-%02d-01', (int) date('Y'), (int) date('n'));
        $end = date('Y-m-t');
        $month = $this->pdo->prepare(
            "SELECT COALESCE(SUM(d.amount), 0)
             FROM leave_request_days d
             INNER JOIN leave_requests r ON r.id = d.leave_request_id
             WHERE r.status = 'approved' AND d.leave_date BETWEEN :start_date AND :end_date"
        );
        $month->execute(['start_date' => $start, 'end_date' => $end]);

        return [
            'active_users' => $activeUsers,
            'pending_requests' => $pendingRequests,
            'granted' => (float) ($ledgerRow['granted'] ?? 0),
            'used' => (float) ($ledgerRow['used'] ?? 0),
            'balance' => (float) ($ledgerRow['balance'] ?? 0),
            'approved_this_month' => (float) $month->fetchColumn(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function annualUserSummary(int $year): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                u.id,
                u.name,
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
             GROUP BY u.id, u.name, u.status, u.hire_date
             ORDER BY CASE u.status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END, u.name ASC, u.id ASC"
        );
        $statement->execute(['leave_year' => $year]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function monthlyApprovedLeaveSummary(int $year): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                MONTH(d.leave_date) AS month_number,
                lt.code,
                lt.name,
                SUM(d.amount) AS amount
             FROM leave_request_days d
             INNER JOIN leave_requests r ON r.id = d.leave_request_id
             INNER JOIN leave_types lt ON lt.id = r.leave_type_id
             WHERE r.status = 'approved'
               AND d.leave_date BETWEEN :start_date AND :end_date
             GROUP BY MONTH(d.leave_date), lt.id, lt.code, lt.name
             ORDER BY month_number ASC, lt.sort_order ASC, lt.id ASC"
        );
        $statement->execute([
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
        ]);

        return $statement->fetchAll();
    }
}
