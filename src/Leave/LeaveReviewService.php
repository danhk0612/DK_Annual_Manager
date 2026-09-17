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

            if (!is_array($request) || $request['status'] !== 'pending') {
                $this->pdo->rollBack();
                return ['changed' => false, 'request' => is_array($request) ? $request : null];
            }

            $status = $action === 'approve' ? 'approved' : 'rejected';

            if ($status === 'approved' && (int) $request['deducts_annual_leave'] === 1) {
                $usageStatement = $this->pdo->prepare(
                    'SELECT YEAR(leave_date) AS leave_year, SUM(amount) AS amount '
                    . 'FROM leave_request_days WHERE leave_request_id = :request_id '
                    . 'GROUP BY YEAR(leave_date) ORDER BY leave_year ASC'
                );
                $usageStatement->execute(['request_id' => $requestId]);

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
                        'ledger_key' => sprintf('request:%d:usage:%d', $requestId, $leaveYear),
                        'reference_request_id' => $requestId,
                        'note' => sprintf('%s 승인 사용', (string) $request['leave_type_name']),
                        'created_by' => $reviewerId,
                    ]);
                }
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
}
