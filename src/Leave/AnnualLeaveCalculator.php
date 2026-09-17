<?php

declare(strict_types=1);

namespace DKAnnual\Leave;

use DateTimeImmutable;

final class AnnualLeaveCalculator
{
    public function entitlementForCompletedYears(int $completedYears): float
    {
        if ($completedYears < 1) {
            return 0.0;
        }

        return (float) min(25, 15 + intdiv($completedYears - 1, 2));
    }

    /**
     * Calculates statutory accrual events from tenure dates only.
     *
     * Attendance eligibility is not evaluated here. Callers must only persist
     * these events when the employee is treated as satisfying the applicable
     * monthly full-attendance / annual 80% attendance requirement.
     *
     * @return list<array{date:string, amount:float, kind:string, note:string}>
     */
    public function accrualEvents(
        DateTimeImmutable $hireDate,
        DateTimeImmutable $asOf,
        ?DateTimeImmutable $employmentEndDate = null,
    ): array {
        $cutoff = $asOf;
        if ($employmentEndDate !== null && $employmentEndDate < $cutoff) {
            $cutoff = $employmentEndDate;
        }

        if ($cutoff < $hireDate) {
            return [];
        }

        $events = [];

        for ($month = 1; $month <= 11; $month++) {
            $date = $this->addMonthsClamped($hireDate, $month);
            if ($date > $cutoff) {
                break;
            }

            $events[] = [
                'date' => $date->format('Y-m-d'),
                'amount' => 1.0,
                'kind' => 'monthly',
                'note' => sprintf('입사 후 %d개월 개근 가정 발생', $month),
            ];
        }

        for ($year = 1; ; $year++) {
            $date = $this->addYearsClamped($hireDate, $year);
            if ($date > $cutoff) {
                break;
            }

            $events[] = [
                'date' => $date->format('Y-m-d'),
                'amount' => $this->entitlementForCompletedYears($year),
                'kind' => 'annual',
                'note' => sprintf('계속근로 %d년, 출근율 80%% 이상 가정 발생', $year),
            ];
        }

        usort(
            $events,
            static fn (array $left, array $right): int => strcmp($left['date'], $right['date'])
        );

        return $events;
    }

    private function addMonthsClamped(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');

        $monthIndex = ($year * 12 + ($month - 1)) + $months;
        $targetYear = intdiv($monthIndex, 12);
        $targetMonth = ($monthIndex % 12) + 1;

        return $this->dateClamped($targetYear, $targetMonth, $day);
    }

    private function addYearsClamped(DateTimeImmutable $date, int $years): DateTimeImmutable
    {
        return $this->dateClamped(
            (int) $date->format('Y') + $years,
            (int) $date->format('n'),
            (int) $date->format('j'),
        );
    }

    private function dateClamped(int $year, int $month, int $day): DateTimeImmutable
    {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = (int) $first->format('t');

        return new DateTimeImmutable(sprintf(
            '%04d-%02d-%02d',
            $year,
            $month,
            min($day, $lastDay),
        ));
    }
}
