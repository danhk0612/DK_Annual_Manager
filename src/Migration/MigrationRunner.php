<?php

declare(strict_types=1);

namespace DKAnnual\Migration;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationPath,
    ) {
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $this->ensureTrackingTable();
        $applied = $this->appliedMigrations();
        $executed = [];

        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }

            $sql = (string) file_get_contents($file);
            foreach ($this->splitSql($sql) as $statement) {
                $this->pdo->exec($statement);
            }

            $record = $this->pdo->prepare(
                'INSERT INTO schema_migrations (migration_name) VALUES (:migration_name)'
            );
            $record->execute(['migration_name' => $name]);
            $executed[] = $name;
        }

        return $executed;
    }

    /** @return list<string> */
    public function pending(): array
    {
        $this->ensureTrackingTable();
        $applied = $this->appliedMigrations();
        $pending = [];

        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);
            if (!isset($applied[$name])) {
                $pending[] = $name;
            }
        }

        return $pending;
    }

    private function ensureTrackingTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'migration_name VARCHAR(190) PRIMARY KEY, '
            . 'applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, true> */
    private function appliedMigrations(): array
    {
        $rows = $this->pdo->query('SELECT migration_name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row] = true;
        }

        return $result;
    }

    /** @return list<string> */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationPath)) {
            throw new RuntimeException('Migration directory not found: ' . $this->migrationPath);
        }

        $files = glob(rtrim($this->migrationPath, '/\\') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return array_values($files);
    }

    /** @return list<string> */
    private function splitSql(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            $buffer .= $line . "\n";
            if (str_ends_with(rtrim($line), ';')) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = rtrim($statement, " \t\n\r\0\x0B;");
                }
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }
}
