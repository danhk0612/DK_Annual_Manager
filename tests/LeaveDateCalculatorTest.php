<?php

declare(strict_types=1);

use DKAnnual\Leave\LeaveDateCalculator;
use PHPUnit\Framework\TestCase;

final class LeaveDateCalculatorTest extends TestCase
{
    public function testWeekendDaysAreExcluded(): void
    {
        $calculator = new LeaveDateCalculator();

        self::assertSame(
            ['2026-09-18', '2026-09-21'],
            $calculator->workingDates(
                new DateTimeImmutable('2026-09-18'),
                new DateTimeImmutable('2026-09-21'),
                [],
            )
        );
    }

    public function testRegisteredHolidayIsExcluded(): void
    {
        $calculator = new LeaveDateCalculator();

        self::assertSame(
            ['2026-09-21', '2026-09-23'],
            $calculator->workingDates(
                new DateTimeImmutable('2026-09-21'),
                new DateTimeImmutable('2026-09-23'),
                ['2026-09-22'],
            )
        );
    }

    public function testCustomWorkingWeekdaysAreApplied(): void
    {
        $calculator = new LeaveDateCalculator();

        self::assertSame(
            ['2026-09-19', '2026-09-21'],
            $calculator->workingDates(
                new DateTimeImmutable('2026-09-18'),
                new DateTimeImmutable('2026-09-21'),
                [],
                [1, 6],
            )
        );
    }

    public function testJanuaryNinth2026IsAWorkingDayWithDefaultSchedule(): void
    {
        $calculator = new LeaveDateCalculator();

        self::assertSame(
            ['2026-01-09'],
            $calculator->workingDates(
                new DateTimeImmutable('2026-01-09'),
                new DateTimeImmutable('2026-01-09'),
                [],
            )
        );
    }

    public function testReverseRangeProducesNoDates(): void
    {
        $calculator = new LeaveDateCalculator();

        self::assertSame(
            [],
            $calculator->workingDates(
                new DateTimeImmutable('2026-09-23'),
                new DateTimeImmutable('2026-09-21'),
                [],
            )
        );
    }
}
