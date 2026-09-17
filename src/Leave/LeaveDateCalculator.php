<?php

declare(strict_types=1);

namespace DKAnnual\Leave;

use DateInterval;
use DateTimeImmutable;

final class LeaveDateCalculator
{
    /**
     * @param list<string> $holidayDates
     * @return list<string>
     */
    public function workingDates(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $holidayDates,
    ): array {
        if ($end < $start) {
            return [];
        }

        $holidaySet = array_fill_keys($holidayDates, true);
        $dates = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $dayOfWeek = (int) $cursor->format('N');

            if ($dayOfWeek < 6 && !isset($holidaySet[$date])) {
                $dates[] = $date;
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $dates;
    }
}
