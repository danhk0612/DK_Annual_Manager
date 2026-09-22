<?php

declare(strict_types=1);

namespace DKAnnual\Leave;

use DateInterval;
use DateTimeImmutable;

final class LeaveDateCalculator
{
    /**
     * @param list<string> $holidayDates
     * @param list<int> $workingWeekdays ISO-8601 weekday numbers, Monday=1 ... Sunday=7
     * @return list<string>
     */
    public function workingDates(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $holidayDates,
        array $workingWeekdays = [1, 2, 3, 4, 5],
    ): array {
        if ($end < $start) {
            return [];
        }

        $holidaySet = array_fill_keys($holidayDates, true);
        $workingDaySet = array_fill_keys($workingWeekdays, true);
        $dates = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $dayOfWeek = (int) $cursor->format('N');

            if (isset($workingDaySet[$dayOfWeek]) && !isset($holidaySet[$date])) {
                $dates[] = $date;
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $dates;
    }
}
