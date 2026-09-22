<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Leave\LeaveReviewService;
use DKAnnual\Migration\MigrationRunner;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Setup\SetupService;
use PHPUnit\Framework\TestCase;

final class DatabaseIntegrationTest extends TestCase
{
    private PDO $pdo;
    private string $configPath;

    protected function setUp(): void
    {
        $dsn = getenv('DK_TEST_DB_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('DK_TEST_DB_DSN is not configured.');
        }

        $user = (string) (getenv('DK_TEST_DB_USER') ?: 'root');
        $password = (string) (getenv('DK_TEST_DB_PASSWORD') ?: '');

        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->resetSchema();

        $this->configPath = tempnam(sys_get_temp_dir(), 'dkannual-db-test-');
        self::assertNotFalse($this->configPath);
        file_put_contents($this->configPath, <<<'PHP'
<?php
return [
    'app' => ['timezone' => 'Asia/Seoul'],
    'leave' => ['year_basis' => 'anniversary', 'max_statutory_days' => 25],
];
PHP);

        $setup = new SetupService(
            $this->pdo,
            new Config($this->configPath),
            dirname(__DIR__),
        );
        $setup->initializeSchema();
    }

    protected function tearDown(): void
    {
        if (isset($this->configPath) && is_file($this->configPath)) {
            @unlink($this->configPath);
        }
    }

    public function testFreshSchemaHasNoPendingMigrations(): void
    {
        $runner = new MigrationRunner($this->pdo, dirname(__DIR__) . '/database/migrations');

        self::assertSame([], $runner->pending());
    }

    public function testMigrationsAreRepeatableOnLatestSchema(): void
    {
        $this->pdo->exec('DELETE FROM schema_migrations');

        $runner = new MigrationRunner($this->pdo, dirname(__DIR__) . '/database/migrations');
        $executed = $runner->migrate();

        self::assertSame([
            '20260917_001_users_hire_date_nullable.sql',
            '20260917_002_annual_leave_ledger_key.sql',
            '20260922_003_leave_usability.sql',
        ], $executed);
        self::assertSame([], $runner->pending());

        foreach ([
            ['users', 'department'],
            ['users', 'position'],
            ['leave_requests', 'half_day_period'],
            ['annual_leave_ledger', 'ledger_key'],
        ] as [$table, $column]) {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns '
                . 'WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
            );
            $statement->execute(['table_name' => $table, 'column_name' => $column]);
            self::assertSame(1, (int) $statement->fetchColumn(), $table . '.' . $column);
        }
    }

    public function testApprovalAndCancellationUpdateAnnualLeaveLedgerAtomically(): void
    {
        $this->pdo->exec(
            "INSERT INTO users (name, role, status) VALUES "
            . "('관리자', 'admin', 'active'), ('직원', 'user', 'active')"
        );
        $adminId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '관리자'")->fetchColumn();
        $userId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '직원'")->fetchColumn();
        $leaveTypeId = (int) $this->pdo->query("SELECT id FROM leave_types WHERE code = 'V'")->fetchColumn();

        $requests = new LeaveRequestRepository($this->pdo);
        $requestId = $requests->create(
            $userId,
            $leaveTypeId,
            '2026-09-24',
            '2026-09-24',
            1.0,
            null,
            '통합 테스트',
            ['2026-09-24'],
            1.0,
        );

        $reviewer = new LeaveReviewService($this->pdo);
        $approved = $reviewer->review($requestId, 'approve', $adminId, null);

        self::assertTrue($approved['changed']);
        self::assertSame('approved', $approved['request']['status']);
        self::assertSame(
            -1.0,
            (float) $this->pdo->query(
                'SELECT COALESCE(SUM(amount), 0) FROM annual_leave_ledger WHERE reference_request_id = ' . $requestId
            )->fetchColumn(),
        );

        $cancelled = $reviewer->cancelApproved($requestId, $adminId, '통합 테스트 취소');

        self::assertTrue($cancelled['changed']);
        self::assertSame('cancelled', $cancelled['request']['status']);
        self::assertSame(
            0.0,
            (float) $this->pdo->query(
                'SELECT COALESCE(SUM(amount), 0) FROM annual_leave_ledger WHERE reference_request_id = ' . $requestId
            )->fetchColumn(),
        );
    }

    private function resetSchema(): void
    {
        $tables = [
            'audit_logs',
            'app_settings',
            'holidays',
            'annual_leave_ledger',
            'leave_request_days',
            'leave_requests',
            'leave_types',
            'users',
            'schema_migrations',
        ];

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                $this->pdo->exec('DROP TABLE IF EXISTS ' . $table);
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
