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
            '20260922_004_leave_cancellation_metadata.sql',
        ], $executed);
        self::assertSame([], $runner->pending());

        foreach ([
            ['users', 'department'],
            ['users', 'position'],
            ['leave_requests', 'half_day_period'],
            ['leave_requests', 'cancelled_by'],
            ['leave_requests', 'cancelled_at'],
            ['leave_requests', 'cancellation_source'],
            ['leave_requests', 'cancellation_note'],
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

    public function testCompleteResetRemovesLeaveRequestsAndAllServiceTables(): void
    {
        $this->pdo->exec(
            "INSERT INTO users (name, role, status) VALUES "
            . "('관리자', 'admin', 'active'), ('직원', 'user', 'active')"
        );
        $userId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '직원'")->fetchColumn();
        $leaveTypeId = (int) $this->pdo->query("SELECT id FROM leave_types WHERE code = 'V'")->fetchColumn();

        $requests = new LeaveRequestRepository($this->pdo);
        $requests->create(
            $userId,
            $leaveTypeId,
            '2026-09-24',
            '2026-09-24',
            1.0,
            null,
            '초기화 검증',
            ['2026-09-24'],
            1.0,
        );
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM leave_requests')->fetchColumn());

        $testRoot = sys_get_temp_dir() . '/dkannual-reset-' . bin2hex(random_bytes(6));
        mkdir($testRoot . '/database', 0777, true);
        mkdir($testRoot . '/storage', 0777, true);
        mkdir($testRoot . '/public/uploads/branding', 0777, true);
        copy(dirname(__DIR__) . '/database/schema.sql', $testRoot . '/database/schema.sql');

        $setup = new SetupService(
            $this->pdo,
            new Config($this->configPath),
            $testRoot,
        );
        $setup->resetInstallation();

        foreach ([
            'audit_logs',
            'app_settings',
            'holidays',
            'annual_leave_ledger',
            'leave_request_days',
            'leave_requests',
            'leave_types',
            'users',
            'schema_migrations',
        ] as $table) {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables '
                . 'WHERE table_schema = DATABASE() AND table_name = :table_name'
            );
            $statement->execute(['table_name' => $table]);
            self::assertSame(0, (int) $statement->fetchColumn(), $table . ' should be removed by complete reset');
        }

        $setup->initializeSchema();
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM leave_requests')->fetchColumn());

        @unlink($testRoot . '/storage/setup.key');
        @unlink($testRoot . '/database/schema.sql');
        @rmdir($testRoot . '/public/uploads/branding');
        @rmdir($testRoot . '/public/uploads');
        @rmdir($testRoot . '/public');
        @rmdir($testRoot . '/storage');
        @rmdir($testRoot . '/database');
        @rmdir($testRoot);
    }

    public function testPendingUserCancellationDeletesRequestAndDays(): void
    {
        $this->pdo->exec(
            "INSERT INTO users (name, role, status) VALUES ('직원', 'user', 'active')"
        );
        $userId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '직원'")->fetchColumn();
        $leaveTypeId = (int) $this->pdo->query("SELECT id FROM leave_types WHERE code = 'V'")->fetchColumn();

        $requests = new LeaveRequestRepository($this->pdo);
        $requestId = $requests->create(
            $userId,
            $leaveTypeId,
            '2026-09-28',
            '2026-09-29',
            2.0,
            null,
            '대기 취소 테스트',
            ['2026-09-28', '2026-09-29'],
            1.0,
        );

        self::assertSame(1, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM leave_requests WHERE id = ' . $requestId
        )->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM leave_request_days WHERE leave_request_id = ' . $requestId
        )->fetchColumn());

        $deleted = $requests->deletePending($requestId, $userId);

        self::assertNotNull($deleted);
        self::assertSame('pending', $deleted['status']);
        self::assertSame(0, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM leave_requests WHERE id = ' . $requestId
        )->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM leave_request_days WHERE leave_request_id = ' . $requestId
        )->fetchColumn());
    }

    public function testUserCanCancelOnlyOwnApprovedLeaveAndApprovalMetadataIsPreserved(): void
    {
        $this->pdo->exec(
            "INSERT INTO users (name, role, status) VALUES "
            . "('관리자', 'admin', 'active'), ('직원', 'user', 'active'), ('다른직원', 'user', 'active')"
        );
        $adminId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '관리자'")->fetchColumn();
        $userId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '직원'")->fetchColumn();
        $otherUserId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '다른직원'")->fetchColumn();
        $leaveTypeId = (int) $this->pdo->query("SELECT id FROM leave_types WHERE code = 'V'")->fetchColumn();

        $requests = new LeaveRequestRepository($this->pdo);
        $requestId = $requests->create(
            $userId,
            $leaveTypeId,
            '2026-09-30',
            '2026-09-30',
            1.0,
            null,
            '사용자 승인 취소 테스트',
            ['2026-09-30'],
            1.0,
        );

        $reviewer = new LeaveReviewService($this->pdo);
        $approved = $reviewer->review($requestId, 'approve', $adminId, '승인 메모 유지');

        self::assertTrue($approved['changed']);
        self::assertFalse($reviewer->cancelApprovedByUser($requestId, $otherUserId, '권한 없음')['changed']);

        $cancelled = $reviewer->cancelApprovedByUser($requestId, $userId, '개인 일정 변경');

        self::assertTrue($cancelled['changed']);
        self::assertSame('cancelled', $cancelled['request']['status']);
        self::assertSame('user', $cancelled['request']['cancellation_source']);
        self::assertSame('개인 일정 변경', $cancelled['request']['cancellation_note']);
        self::assertSame(
            0.0,
            (float) $this->pdo->query(
                'SELECT COALESCE(SUM(amount), 0) FROM annual_leave_ledger WHERE reference_request_id = ' . $requestId
            )->fetchColumn(),
        );

        $stored = $this->pdo->query(
            'SELECT status, reviewed_by, review_note, cancelled_by, cancellation_source, cancellation_note '
            . 'FROM leave_requests WHERE id = ' . $requestId
        )->fetch();

        self::assertIsArray($stored);
        self::assertSame('cancelled', $stored['status']);
        self::assertSame($adminId, (int) $stored['reviewed_by']);
        self::assertSame('승인 메모 유지', $stored['review_note']);
        self::assertSame($userId, (int) $stored['cancelled_by']);
        self::assertSame('user', $stored['cancellation_source']);
        self::assertSame('개인 일정 변경', $stored['cancellation_note']);
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
        $approved = $reviewer->review($requestId, 'approve', $adminId, '기존 승인 메모');

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

        $stored = $this->pdo->query(
            'SELECT reviewed_by, review_note, cancelled_by, cancellation_source, cancellation_note '
            . 'FROM leave_requests WHERE id = ' . $requestId
        )->fetch();
        self::assertIsArray($stored);
        self::assertSame($adminId, (int) $stored['reviewed_by']);
        self::assertSame('기존 승인 메모', $stored['review_note']);
        self::assertSame($adminId, (int) $stored['cancelled_by']);
        self::assertSame('admin', $stored['cancellation_source']);
        self::assertSame('통합 테스트 취소', $stored['cancellation_note']);
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
