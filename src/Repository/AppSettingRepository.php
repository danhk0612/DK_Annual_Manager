<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

final class AppSettingRepository extends AbstractRepository
{
    public function get(string $key, ?string $default = null): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => $key]);
        $value = $statement->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    public function set(string $key, ?string $value, ?int $updatedBy): void
    {
        if ($value === null) {
            $this->delete($key);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, updated_by) '
            . 'VALUES (:setting_key, :setting_value, :updated_by) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), '
            . 'updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_by' => $updatedBy,
        ]);
    }

    /** @param array<string, string|null> $values */
    public function setMany(array $values, ?int $updatedBy): void
    {
        $this->pdo->beginTransaction();

        try {
            foreach ($values as $key => $value) {
                $this->set($key, $value, $updatedBy);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(string $key): void
    {
        $statement = $this->pdo->prepare('DELETE FROM app_settings WHERE setting_key = :setting_key');
        $statement->execute(['setting_key' => $key]);
    }

    /** @return list<string> */
    public function lineList(string $key): array
    {
        $value = trim((string) $this->get($key, ''));
        if ($value === '') {
            return [];
        }

        $items = preg_split('/[\r\n,]+/', $value) ?: [];
        $result = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item !== '') {
                $result[$item] = $item;
            }
        }

        return array_values($result);
    }

    /** @return list<int> */
    public function workingWeekdays(): array
    {
        $raw = trim((string) $this->get('work.weekdays', '1,2,3,4,5'));
        $values = preg_split('/[\s,]+/', $raw) ?: [];
        $days = [];

        foreach ($values as $value) {
            if (ctype_digit($value)) {
                $day = (int) $value;
                if ($day >= 1 && $day <= 7) {
                    $days[$day] = $day;
                }
            }
        }

        if ($days === []) {
            return [1, 2, 3, 4, 5];
        }

        ksort($days);
        return array_values($days);
    }

    /** @param list<int> $weekdays */
    public function setWorkingWeekdays(array $weekdays, int $updatedBy): void
    {
        $days = [];
        foreach ($weekdays as $weekday) {
            if ($weekday >= 1 && $weekday <= 7) {
                $days[$weekday] = $weekday;
            }
        }

        if ($days === []) {
            throw new \InvalidArgumentException('At least one working weekday is required.');
        }

        ksort($days);
        $this->set('work.weekdays', implode(',', array_values($days)), $updatedBy);
    }

    public function annualLeaveOverride(int $userId, int $year): ?float
    {
        $statement = $this->pdo->prepare(
            'SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => $this->overrideKey($userId, $year)]);
        $value = $statement->fetchColumn();

        if ($value === false || !is_numeric((string) $value)) {
            return null;
        }

        return (float) $value;
    }

    /** @return array<int, float> */
    public function annualLeaveOverridesForUser(int $userId): array
    {
        $prefix = sprintf('annual_leave_override.%d.', $userId);
        $statement = $this->pdo->prepare(
            'SELECT setting_key, setting_value FROM app_settings '
            . 'WHERE LOCATE(:prefix, setting_key) = 1 ORDER BY setting_key ASC'
        );
        $statement->execute(['prefix' => $prefix]);

        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $key = (string) $row['setting_key'];
            $year = (int) substr($key, strlen($prefix));
            if ($year >= 2000 && $year <= 2100 && is_numeric((string) $row['setting_value'])) {
                $result[$year] = (float) $row['setting_value'];
            }
        }

        return $result;
    }

    public function setAnnualLeaveOverride(int $userId, int $year, float $amount, int $updatedBy): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, updated_by) '
            . 'VALUES (:setting_key, :setting_value, :updated_by) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), '
            . 'updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'setting_key' => $this->overrideKey($userId, $year),
            'setting_value' => number_format($amount, 2, '.', ''),
            'updated_by' => $updatedBy,
        ]);
    }

    public function clearAnnualLeaveOverride(int $userId, int $year): void
    {
        $statement = $this->pdo->prepare('DELETE FROM app_settings WHERE setting_key = :setting_key');
        $statement->execute(['setting_key' => $this->overrideKey($userId, $year)]);
    }

    private function overrideKey(int $userId, int $year): string
    {
        return sprintf('annual_leave_override.%d.%d', $userId, $year);
    }
}
