<?php

declare(strict_types=1);

use DKAnnual\Leave\AnnualLeaveCalculator;
use PHPUnit\Framework\TestCase;

final class AnnualLeaveCalculatorTest extends TestCase
{
    public function testAnnualEntitlementByCompletedYears(): void
    {
        $calculator = new AnnualLeaveCalculator();

        self::assertSame(0.0, $calculator->entitlementForCompletedYears(0));
        self::assertSame(15.0, $calculator->entitlementForCompletedYears(1));
        self::assertSame(15.0, $calculator->entitlementForCompletedYears(2));
        self::assertSame(16.0, $calculator->entitlementForCompletedYears(3));
        self::assertSame(16.0, $calculator->entitlementForCompletedYears(4));
        self::assertSame(17.0, $calculator->entitlementForCompletedYears(5));
        self::assertSame(25.0, $calculator->entitlementForCompletedYears(21));
        self::assertSame(25.0, $calculator->entitlementForCompletedYears(50));
    }

    public function testFirstYearMonthlyAccrualUsesClampedMonthEndDates(): void
    {
        $calculator = new AnnualLeaveCalculator();
        $events = $calculator->accrualEvents(
            new DateTimeImmutable('2026-01-31'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertCount(3, $events);
        self::assertSame(['2026-02-28', '2026-03-31', '2026-04-30'], array_column($events, 'date'));
        self::assertSame([1.0, 1.0, 1.0], array_column($events, 'amount'));
    }

    public function testFirstAnniversaryContainsElevenMonthlyGrantsAndAnnualGrant(): void
    {
        $calculator = new AnnualLeaveCalculator();
        $events = $calculator->accrualEvents(
            new DateTimeImmutable('2026-01-15'),
            new DateTimeImmutable('2027-01-15'),
        );

        self::assertCount(12, $events);
        $last = $events[array_key_last($events)];
        self::assertSame('2027-01-15', $last['date']);
        self::assertSame('annual', $last['kind']);
        self::assertSame(15.0, $last['amount']);
    }

    public function testEmploymentEndDateStopsFutureAccruals(): void
    {
        $calculator = new AnnualLeaveCalculator();
        $events = $calculator->accrualEvents(
            new DateTimeImmutable('2026-01-10'),
            new DateTimeImmutable('2027-12-31'),
            new DateTimeImmutable('2026-05-20'),
        );

        self::assertCount(4, $events);
        self::assertSame('2026-05-10', $events[array_key_last($events)]['date']);
    }

    public function testLeapDayAnniversaryClampsToFebruaryEnd(): void
    {
        $calculator = new AnnualLeaveCalculator();
        $events = $calculator->accrualEvents(
            new DateTimeImmutable('2024-02-29'),
            new DateTimeImmutable('2025-02-28'),
        );

        $annual = array_values(array_filter($events, static fn (array $event): bool => $event['kind'] === 'annual'));

        self::assertCount(1, $annual);
        self::assertSame('2025-02-28', $annual[0]['date']);
        self::assertSame(15.0, $annual[0]['amount']);
    }
}
