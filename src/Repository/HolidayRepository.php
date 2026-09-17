<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

final class HolidayRepository extends AbstractRepository
{
    /** @return list<string> */
    public function datesBetween(string $startDate, string $endDate): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT holiday_date FROM holidays '
            . 'WHERE holiday_date BETWEEN :start_date AND :end_date '
            . 'AND is_public_holiday = 1 '
            . 'ORDER BY holiday_date ASC'
        );
        $statement->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return array_map(
            static fn (array $row): string => (string) $row['holiday_date'],
            $statement->fetchAll(),
        );
    }

    /** @return list<array<string, mixed>> */
    public function entriesBetween(string $startDate, string $endDate): array
    {
        $statement = $this->pdo->prepare(
            'SELECT holiday_date, name, source FROM holidays '
            . 'WHERE holiday_date BETWEEN :start_date AND :end_date '
            . 'AND is_public_holiday = 1 '
            . 'ORDER BY holiday_date ASC, name ASC'
        );
        $statement->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $statement->fetchAll();
    }
}
