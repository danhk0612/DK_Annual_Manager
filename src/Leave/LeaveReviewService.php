<?php

declare(strict_types=1);

namespace DKAnnual\Leave;

use PDO;
use Throwable;

final class LeaveReviewService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{changed:bool, request:array<string, mixed>|null}
     */
    public function review(int $requestId, string $action, int $reviewerId, ?string $reviewNote): array
    {
        if (!in_array($action, ['approve', 'reject'], true)) {
            return ['changed' => false, 'request' => null];
        }

        $this->pdo->beginTransaction();

        try {
            $request = $this->lockedRequest($requestId);
            if ($request === null || $request['status'] !== 'pending') {
                $this->pdo->rollBack();
                return ['changed' => false, 'request' => $request];
            }

            $status = $action === 'approve' ? 'approved' : 'rejected';

            if ($status === 'approved' && (int) $request['deducts_annual_leave'] === 1) {
                $this->insertUsageEntries($request, $reviewerId);
            }

            $update = $this->pdo->prepare(
                'UPDATE leave_requests SET status = :status, reviewed_by = :reviewed_by, '
                . 'reviewed_at = CURRENT_TIMESTAMP, review_note = :review_note '
                . "WHERE id = :id AND status = 'pending'"
            );
            $update->execute([
                'status' => $status,
                'reviewed_by' => $reviewerId,
                'review_note' => $reviewNote,
                'id' => $requestId,
            ]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['changed' => false, 'request' => $request];
            }

            $this->pdo->commit();
            $request['status'] = $status;
            $request['review_note'] = $reviewNote;

            return ['changed' => true, 'request' => $request];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Cancels an already approved request and restores any annual-leave usage
     * through reversal ledger entries. Changing an approved request is modeled
     * as cancel + new request so the original approval remains auditable.
     *
     * @return array{changed:bool, request:array<string, mixed>|null}
     */
    public function cancelApproved(int $requestId, int $reviewerId, ?string $reviewNote): array
    {
        $this->pdo->beginTransaction();

        try {
            $request = $this->lockedRequest($requestId);
            if ($request === null || $request['status'] !== 'approved') {
                $this->pdo->rollBack();
                return ['changed' => false, 'request' => $request];
            }

            if ((int) $request['deducts_annual_leave'] === 1) {
                $usageStatement = $this->pdo->prepare(
                    'SELECT YEAR(leave_date) AS leave_year, SUM(amount) AS amount '
                    . 'FROM leave_request_days WHERE leave_request_id = :request_id '
                    . 'GROUP BY YEAR(leave_date) ORDER BY leave_year ASC'
                );
                $usageStatement->execute(['request_id' => $requestId]);

                $insertReversal = $this->pdo->prepare(
                    "INSERT IGNORE INTO annual_leave_ledger "
                    . "(user_id, leave_year, transaction_type, amount, ledger_key, reference_request_id, note, created_by) "
                    . "VALUES (:user_id, :leave_year, 'reversal', :amount, :ledger_key, :reference_request_id, :note, :created_by)"
                );

                foreach ($usageStatement->fetchAll() as $usage) {
                    $leaveYear = (int) $usage['leave_year'];
                    $amount = (float) $usage['amount'];
                    $insertReversal->execute([
                        'user_id' => (int) $request['user_id'],
                        'leave_year' => $leaveYear,
                        'amount' => $amount,
                        'ledger_key' => sprintf('request:%d:reversal:%d', $requestId, $leaveYear),
                        'reference_request_id' => $requestId,
                        'note' => sprintf('%s 승인 취소 복원', (string) $request['leave_type_name']),
                        'created_by' => $reviewerId,
                    ]);
                }
            }

            $update = $this->pdo->prepare(
                "UPDATE leave_requests SET status = 'cancelled', reviewed_by = :reviewed_by, "
                . 'reviewed_at = CURRENT_TIMESTAMP, review_note = :review_note '
                . "WHERE id = :id AND status = 'approved'"
            );
            $update->execute([
                'reviewed_by' => $reviewerId,
                'review_note' => $reviewNote,
                'id' => $requestId,
            ]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['changed' => false, 'request' => $request];
            }

            $this->pdo->commit();
            $request['status'] = 'cancelled';
            $request['review_note'] = $reviewNote;

            return ['changed' => true, 'request' => $request];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    private function lockedRequest(int $requestId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.*, u.name AS user_name, u.telegram_user_id, '
            . 't.code AS leave_code, t.name AS leave_type_name, t.deducts_annual_leave '
            . 'FROM leave_requests r '
            . 'INNER JOIN users u ON u.id = r.user_id '
            . 'INNER JOIN leave_types t ON t.id = r.leave_type_id '
            . 'WHERE r.id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $requestId]);
        $request = $statement->fetch();

        return is_array($request) ? $request : null;
    }

    /** @param array<string, mixed> $request */
    private function insertUsageEntries(array $request, int $reviewerId): void
    {
        $usageStatement = $this->pdo->prepare(
            'SELECT YEAR(leave_date) AS leave_year, SUM(amount) AS amount '
            . 'FROM leave_request_days WHERE leave_request_id = :request_id '
            . 'GROUP BY YEAR(leave_date) ORDER BY leave_year ASC'
        );
        $usageStatement->execute(['request_id' => (int) $request['id']]);

        $insertLedger = $this->pdo->prepare(
            "INSERT INTO annual_leave_ledger "
            . "(user_id, leave_year, transaction_type, amount, ledger_key, reference_request_id, note, created_by) "
            . "VALUES (:user_id, :leave_year, 'usage', :amount, :ledger_key, :reference_request_id, :note, :created_by)"
        );

        foreach ($usageStatement->fetchAll() as $usage) {
            $leaveYear = (int) $usage['leave_year'];
            $amount = (float) $usage['amount'];
            $insertLedger->execute([
                'user_id' => (int) $request['user_id'],
                'leave_year' => $leaveYear,
                'amount' => -$amount,
                'ledger_key' => sprintf('request:%d:usage:%d', (int) $request['id'], $leaveYear),
                'reference_request_id' => (int) $request['id'],
                'note' => sprintf('%s 승인 사용', (string) $request['leave_type_name']),
                'created_by' => $reviewerId,
            ]);
        }
    }
}
