<?php

declare(strict_types=1);

namespace DKAnnual\Leave;

use DateTimeImmutable;
use DKAnnual\Repository\AnnualLeaveLedgerRepository;
use DKAnnual\Repository\AppSettingRepository;

final class AnnualLeaveService
{
    public function __construct(
        private readonly AnnualLeaveCalculator $calculator,
        private readonly AnnualLeaveLedgerRepository $ledger,
        private readonly AppSettingRepository $settings,
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
        $userId = (int) $user['id'];
        $hireDateValue = $user['hire_date'] ?? null;
        if (!is_string($hireDateValue) || $hireDateValue === '') {
            foreach ($this->settings->annualLeaveOverridesForUser($userId) as $year => $amount) {
                if ($year <= (int) $asOf->format('Y')) {
                    $this->enforceOverride($userId, $year, $amount, $createdBy);
                }
            }
            return 0;
        }

        $hireDate = new DateTimeImmutable($hireDateValue);
        $employmentEndDate = null;
        if (isset($user['employment_end_date']) && is_string($user['employment_end_date']) && $user['employment_end_date'] !== '') {
            $employmentEndDate = new DateTimeImmutable($user['employment_end_date']);
        }

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

        foreach ($this->settings->annualLeaveOverridesForUser($userId) as $year => $amount) {
            if ($year <= (int) $asOf->format('Y')) {
                $this->enforceOverride($userId, $year, $amount, $createdBy);
            }
        }

        return $inserted;
    }

    public function setOverride(int $userId, int $year, float $amount, int $updatedBy): float
    {
        $this->settings->setAnnualLeaveOverride($userId, $year, $amount, $updatedBy);
        return $this->enforceOverride($userId, $year, $amount, $updatedBy);
    }

    public function clearOverride(int $userId, int $year): void
    {
        $this->settings->clearAnnualLeaveOverride($userId, $year);
        $this->ledger->deleteOverrideAdjustment($userId, $year);
    }

    public function overrideAmount(int $userId, int $year): ?float
    {
        return $this->settings->annualLeaveOverride($userId, $year);
    }

    public function enforceOverride(
        int $userId,
        int $year,
        float $target,
        ?int $createdBy,
    ): float {
        $baseTotal = $this->ledger->nonUsageTotalExcludingOverride($userId, $year);
        $delta = round($target - $baseTotal, 2);

        if (abs($delta) < 0.01) {
            $this->ledger->deleteOverrideAdjustment($userId, $year);
        } else {
            $this->ledger->setOverrideAdjustment(
                $userId,
                $year,
                $delta,
                sprintf('%d년 총 연차 %.2f일 고정', $year, $target),
                $createdBy,
            );
        }

        return $delta;
    }
}
