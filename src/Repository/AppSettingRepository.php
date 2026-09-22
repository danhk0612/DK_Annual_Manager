<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

final class AppSettingRepository extends AbstractRepository
{
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
