<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Leave\LeaveReviewService;
use DKAnnual\Migration\MigrationRunner;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Repository\ReportingRepository;
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

    public function testLeaveExportRowsUseActualLeaveDatesAndUserScope(): void
    {
        $this->pdo->exec(
            "INSERT INTO users (name, role, status) VALUES "
            . "('직원A', 'user', 'active'), ('직원B', 'user', 'active')"
        );
        $userA = (int) $this->pdo->query("SELECT id FROM users WHERE name = '직원A'")->fetchColumn();
        $userB = (int) $this->pdo->query("SELECT id FROM users WHERE name = '직원B'")->fetchColumn();
        $leaveTypeId = (int) $this->pdo->query("SELECT id FROM leave_types WHERE code = 'V'")->fetchColumn();

        $requests = new LeaveRequestRepository($this->pdo);
        $requestA = $requests->create(
            $userA,
            $leaveTypeId,
            '2026-09-30',
            '2026-10-01',
            2.0,
            null,
            '월 경계 테스트',
            ['2026-09-30', '2026-10-01'],
            1.0,
        );
        $requests->create(
            $userB,
            $leaveTypeId,
            '2026-09-30',
            '2026-09-30',
            1.0,
            null,
            '다른 사용자',
            ['2026-09-30'],
            1.0,
        );

        $reports = new ReportingRepository($this->pdo);

        $september = $reports->leaveExportRows($userA, '2026-09-01', '2026-09-30');
        self::assertCount(1, $september);
        self::assertSame($requestA, (int) $september[0]['id']);
        self::assertSame(2.0, (float) $september[0]['requested_amount']);
        self::assertSame(1.0, (float) $september[0]['period_amount']);

        $year = $reports->leaveExportRows($userA, '2026-01-01', '2026-12-31');
        self::assertCount(1, $year);
        self::assertSame(2.0, (float) $year[0]['period_amount']);

        $allUsersSeptember = $reports->leaveExportRows(null, '2026-09-01', '2026-09-30');
        self::assertCount(2, $allUsersSeptember);
    }

    public function testCurrentAndUpcomingDashboardLeavesDoNotOverlap(): void
    {
        $this->pdo->exec(
            "INSERT INTO users (name, role, status) VALUES "
            . "('관리자', 'admin', 'active'), "
            . "('현재휴가', 'user', 'active'), "
            . "('예정휴가', 'user', 'active'), "
            . "('대기휴가', 'user', 'active')"
        );

        $adminId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '관리자'")->fetchColumn();
        $currentUserId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '현재휴가'")->fetchColumn();
        $futureUserId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '예정휴가'")->fetchColumn();
        $pendingUserId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '대기휴가'")->fetchColumn();
        $leaveTypeId = (int) $this->pdo->query("SELECT id FROM leave_types WHERE code = 'V'")->fetchColumn();

        $requests = new LeaveRequestRepository($this->pdo);
        $reviewer = new LeaveReviewService($this->pdo);

        $currentRequestId = $requests->create(
            $currentUserId,
            $leaveTypeId,
            '2026-09-21',
            '2026-09-23',
            3.0,
            null,
            '현재 진행 중',
            ['2026-09-21', '2026-09-22', '2026-09-23'],
            1.0,
        );
        self::assertTrue($reviewer->review($currentRequestId, 'approve', $adminId, null)['changed']);

        $futureRequestId = $requests->create(
            $futureUserId,
            $leaveTypeId,
            '2026-09-24',
            '2026-09-24',
            1.0,
            null,
            '다가오는 휴가',
            ['2026-09-24'],
            1.0,
        );
        self::assertTrue($reviewer->review($futureRequestId, 'approve', $adminId, null)['changed']);

        $requests->create(
            $pendingUserId,
            $leaveTypeId,
            '2026-09-22',
            '2026-09-22',
            1.0,
            null,
            '승인 대기',
            ['2026-09-22'],
            1.0,
        );

        $reports = new ReportingRepository($this->pdo);

        $current = $reports->approvedLeavesOnDate('2026-09-22');
        self::assertCount(1, $current);
        self::assertSame($currentRequestId, (int) $current[0]['id']);
        self::assertSame('현재휴가', $current[0]['user_name']);
        self::assertSame(1.0, (float) $current[0]['today_amount']);

        $upcoming = $reports->upcomingApprovedLeaves('2026-09-23', '2026-10-06', 8);
        self::assertCount(1, $upcoming);
        self::assertSame($futureRequestId, (int) $upcoming[0]['id']);
        self::assertSame('예정휴가', $upcoming[0]['user_name']);
        self::assertSame('2026-09-24', $upcoming[0]['first_leave_date']);
    }

    public function testClosedLeaveHistoryCleanupPreservesPendingAndApprovedOnly(): void
    {
        $this->pdo->exec(
            "INSERT INTO users (name, role, status) VALUES "
            . "('관리자', 'admin', 'active'), ('직원', 'user', 'active')"
        );
        $adminId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '관리자'")->fetchColumn();
        $userId = (int) $this->pdo->query("SELECT id FROM users WHERE name = '직원'")->fetchColumn();
        $leaveTypeId = (int) $this->pdo->query("SELECT id FROM leave_types WHERE code = 'V'")->fetchColumn();

        $requests = new LeaveRequestRepository($this->pdo);
        $reviewer = new LeaveReviewService($this->pdo);

        $pendingId = $requests->create(
            $userId, $leaveTypeId, '2026-10-01', '2026-10-01', 1.0, null,
            '대기 유지', ['2026-10-01'], 1.0,
        );

        $approvedId = $requests->create(
            $userId, $leaveTypeId, '2026-10-02', '2026-10-02', 1.0, null,
            '승인 유지', ['2026-10-02'], 1.0,
        );
        self::assertTrue($reviewer->review($approvedId, 'approve', $adminId, null)['changed']);

        $rejectedId = $requests->create(
            $userId, $leaveTypeId, '2026-10-03', '2026-10-03', 1.0, null,
            '반려 삭제', ['2026-10-03'], 1.0,
        );
        self::assertTrue($reviewer->review($rejectedId, 'reject', $adminId, '반려')['changed']);

        $cancelledId = $requests->create(
            $userId, $leaveTypeId, '2026-10-04', '2026-10-04', 1.0, null,
            '취소 삭제', ['2026-10-04'], 1.0,
        );
        self::assertTrue($reviewer->review($cancelledId, 'approve', $adminId, null)['changed']);
        self::assertTrue($reviewer->cancelApproved($cancelledId, $adminId, '취소')['changed']);

        $auditInsert = $this->pdo->prepare(
            "INSERT INTO audit_logs (actor_user_id, action, target_type, target_id) "
            . "VALUES (:actor, 'test.leave', 'leave_request', :target)"
        );
        foreach ([$pendingId, $approvedId, $rejectedId, $cancelledId] as $id) {
            $auditInsert->execute(['actor' => $adminId, 'target' => $id]);
        }
        $auditInsert->execute(['actor' => $adminId, 'target' => 999999]);

        self::assertSame(2, $requests->closedHistoryCount());

        $result = $requests->purgeClosedHistory();

        self::assertSame(2, $result['requests']);
        self::assertSame(2, $result['days']);
        self::assertSame(2, $result['ledger']);
        self::assertSame(3, $result['audit']);
        self::assertSame(0, $requests->closedHistoryCount());

        $remaining = $this->pdo->query(
            "SELECT id, status FROM leave_requests ORDER BY id"
        )->fetchAll();
        self::assertSame(
            [
                ['id' => $pendingId, 'status' => 'pending'],
                ['id' => $approvedId, 'status' => 'approved'],
            ],
            array_map(
                static fn (array $row): array => ['id' => (int) $row['id'], 'status' => (string) $row['status']],
                $remaining,
            ),
        );

        self::assertSame(2, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM leave_request_days'
        )->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM annual_leave_ledger WHERE reference_request_id = ' . $approvedId
        )->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM annual_leave_ledger WHERE reference_request_id = ' . $cancelledId
        )->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'test.leave'"
        )->fetchColumn());
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
