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
     * Administrator cancellation of an approved request.
     *
     * @return array{changed:bool, request:array<string, mixed>|null}
     */
    public function cancelApproved(int $requestId, int $actorId, ?string $cancellationNote): array
    {
        return $this->cancelApprovedInternal($requestId, $actorId, 'admin', $cancellationNote, null);
    }

    /**
     * User cancellation of their own approved request.
     *
     * @return array{changed:bool, request:array<string, mixed>|null}
     */
    public function cancelApprovedByUser(int $requestId, int $userId, ?string $cancellationNote): array
    {
        return $this->cancelApprovedInternal($requestId, $userId, 'user', $cancellationNote, $userId);
    }

    /**
     * Cancels an approved request and restores annual-leave usage through
     * reversal ledger entries. Original approval metadata is intentionally
     * preserved; cancellation metadata is stored separately.
     *
     * @return array{changed:bool, request:array<string, mixed>|null}
     */
    private function cancelApprovedInternal(
        int $requestId,
        int $actorId,
        string $source,
        ?string $cancellationNote,
        ?int $requiredOwnerId,
    ): array {
        if (!in_array($source, ['user', 'admin'], true)) {
            return ['changed' => false, 'request' => null];
        }

        $this->pdo->beginTransaction();

        try {
            $request = $this->lockedRequest($requestId);
            if (
                $request === null
                || $request['status'] !== 'approved'
                || ($requiredOwnerId !== null && (int) $request['user_id'] !== $requiredOwnerId)
            ) {
                $this->pdo->rollBack();
                return ['changed' => false, 'request' => $request];
            }

            if ((int) $request['deducts_annual_leave'] === 1) {
                $usageStatement = $this->pdo->prepare(
                    "SELECT leave_year, -SUM(amount) AS amount "
                    . "FROM annual_leave_ledger "
                    . "WHERE reference_request_id = :request_id AND transaction_type = 'usage' "
                    . "GROUP BY leave_year ORDER BY leave_year ASC"
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
                        'note' => sprintf(
                            '%s %s 취소 복원',
                            (string) $request['leave_type_name'],
                            $source === 'user' ? '사용자' : '관리자',
                        ),
                        'created_by' => $actorId,
                    ]);
                }
            }

            $cancelledAt = date('Y-m-d H:i:s');
            $update = $this->pdo->prepare(
                "UPDATE leave_requests SET status = 'cancelled', "
                . 'cancelled_by = :cancelled_by, cancelled_at = :cancelled_at, '
                . 'cancellation_source = :cancellation_source, cancellation_note = :cancellation_note '
                . "WHERE id = :id AND status = 'approved'"
            );
            $update->execute([
                'cancelled_by' => $actorId,
                'cancelled_at' => $cancelledAt,
                'cancellation_source' => $source,
                'cancellation_note' => $cancellationNote,
                'id' => $requestId,
            ]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['changed' => false, 'request' => $request];
            }

            $this->pdo->commit();
            $request['status'] = 'cancelled';
            $request['cancelled_by'] = $actorId;
            $request['cancelled_at'] = $cancelledAt;
            $request['cancellation_source'] = $source;
            $request['cancellation_note'] = $cancellationNote;

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
