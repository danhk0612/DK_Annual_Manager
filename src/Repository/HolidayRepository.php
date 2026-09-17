<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

use RuntimeException;
use Throwable;

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
            . 'ORDER BY holiday_date ASC, name ASC'
        );
        $statement->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allForYear(int $year): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, holiday_date, name, source, is_public_holiday, external_key, updated_at '
            . 'FROM holidays WHERE holiday_date BETWEEN :start_date AND :end_date '
            . 'ORDER BY holiday_date ASC, name ASC'
        );
        $statement->execute([
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
        ]);

        return $statement->fetchAll();
    }

    public function latestPublicApiUpdatedAt(int $year): ?string
    {
        $statement = $this->pdo->prepare(
            "SELECT MAX(updated_at) FROM holidays WHERE source = 'public_api' "
            . 'AND holiday_date BETWEEN :start_date AND :end_date'
        );
        $statement->execute([
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
        ]);
        $value = $statement->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param list<array{holiday_date: string, name: string, external_key: string}> $holidays */
    public function replacePublicApiYear(int $year, array $holidays): int
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare(
                "DELETE FROM holidays WHERE source = 'public_api' "
                . 'AND holiday_date BETWEEN :start_date AND :end_date'
            );
            $delete->execute([
                'start_date' => sprintf('%04d-01-01', $year),
                'end_date' => sprintf('%04d-12-31', $year),
            ]);

            $insert = $this->pdo->prepare(
                "INSERT INTO holidays (holiday_date, name, source, is_public_holiday, external_key) "
                . "VALUES (:holiday_date, :name, 'public_api', 1, :external_key)"
            );

            foreach ($holidays as $holiday) {
                $insert->execute($holiday);
            }

            $this->pdo->commit();
            return count($holidays);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function saveManaged(string $date, string $name, string $source, bool $isPublicHoliday): void
    {
        if (!in_array($source, ['manual', 'company'], true)) {
            throw new RuntimeException('관리 가능한 휴일 유형이 아닙니다.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO holidays (holiday_date, name, source, is_public_holiday) '
            . 'VALUES (:holiday_date, :name, :source, :is_public_holiday) '
            . 'ON DUPLICATE KEY UPDATE is_public_holiday = VALUES(is_public_holiday), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'holiday_date' => $date,
            'name' => $name,
            'source' => $source,
            'is_public_holiday' => $isPublicHoliday ? 1 : 0,
        ]);
    }

    public function deleteManaged(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "DELETE FROM holidays WHERE id = :id AND source IN ('manual', 'company')"
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
