<?php

declare(strict_types=1);

namespace DKAnnual\Leave;

use DateTimeImmutable;
use DKAnnual\Repository\AnnualLeaveLedgerRepository;

final class AnnualLeaveService
{
    public function __construct(
        private readonly AnnualLeaveCalculator $calculator,
        private readonly AnnualLeaveLedgerRepository $ledger,
    ) {
    }

    /** @param array<string, mixed> $user */
    public function resyncAccruals(array $user, DateTimeImmutable $asOf, ?int $createdBy): int
    {
        $this->ledger->deleteAutomaticGrantsForUser((int) $user['id']);
        return $this->syncAccruals($user, $asOf, $createdBy);
    }

    /** @param array<string, mixed> $user */
    public function syncAccruals(array $user, DateTimeImmutable $asOf, ?int $createdBy): int
    {
        $hireDateValue = $user['hire_date'] ?? null;
        if (!is_string($hireDateValue) || $hireDateValue === '') {
            return 0;
        }

        $hireDate = new DateTimeImmutable($hireDateValue);
        $employmentEndDate = null;
        if (isset($user['employment_end_date']) && is_string($user['employment_end_date']) && $user['employment_end_date'] !== '') {
            $employmentEndDate = new DateTimeImmutable($user['employment_end_date']);
        }

        $userId = (int) $user['id'];
        $inserted = 0;

        foreach ($this->calculator->accrualEvents($hireDate, $asOf, $employmentEndDate) as $event) {
            $ledgerKey = sprintf(
                'user:%d:%s:%s',
                $userId,
                $event['kind'],
                $event['date'],
            );

            if ($this->ledger->insertGrantIfMissing(
                $userId,
                (int) substr($event['date'], 0, 4),
                (float) $event['amount'],
                $ledgerKey,
                $event['note'],
                $createdBy,
            )) {
                $inserted++;
            }
        }

        return $inserted;
    }
}
