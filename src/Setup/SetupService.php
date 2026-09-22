<?php

declare(strict_types=1);

namespace DKAnnual\Setup;

use DKAnnual\Config;
use DKAnnual\Repository\AppSettingRepository;
use PDO;
use RuntimeException;

final class SetupService
{
    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'users',
        'leave_types',
        'leave_requests',
        'leave_request_days',
        'annual_leave_ledger',
        'holidays',
        'app_settings',
        'audit_logs',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly Config $config,
        private readonly string $rootPath,
    ) {
    }

    public function schemaReady(): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = :table_name'
        );

        foreach (self::REQUIRED_TABLES as $table) {
            $statement->execute(['table_name' => $table]);
            if ((int) $statement->fetchColumn() !== 1) {
                return false;
            }
        }

        return true;
    }

    public function completed(): bool
    {
        if (!$this->schemaReady()) {
            return false;
        }

        $this->migrateLegacyInstallation();

        $settings = new AppSettingRepository($this->pdo);
        return $settings->get('setup.completed', '0') === '1';
    }

    public function migrateLegacyInstallation(): bool
    {
        if (!$this->schemaReady()) {
            return false;
        }

        $settings = new AppSettingRepository($this->pdo);
        if ($settings->get('setup.completed', null) !== null || $settings->get('setup.started_at', null) !== null) {
            return false;
        }

        $adminCount = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")
            ->fetchColumn();

        if ($adminCount < 1) {
            return false;
        }

        $settings->set('setup.completed', '1', null);
        $settings->set('setup.completed_at', date('c'), null);
        $settings->set('setup.migrated_legacy', '1', null);

        return true;
    }

    public function initializeSchema(): void
    {

        $schemaPath = $this->rootPath . '/database/schema.sql';
        if (!is_file($schemaPath)) {
            throw new RuntimeException('database/schema.sql 파일이 없습니다.');
        }

        $sql = (string) file_get_contents($schemaPath);
        $statements = $this->splitSql($sql);

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($statements as $statement) {
                $this->pdo->exec($statement);
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        if (!$this->schemaReady()) {
            throw new RuntimeException('DB 초기화 후 필수 테이블을 확인하지 못했습니다.');
        }

        $settings = new AppSettingRepository($this->pdo);
        if ($settings->get('setup.started_at', null) === null) {
            $settings->set('setup.started_at', date('c'), null);
        }
    }

    public function applyManagedConfig(): void
    {
        if (!$this->schemaReady()) {
            return;
        }

        $settings = new AppSettingRepository($this->pdo);
        $completed = $settings->get('setup.completed', '0') === '1';

        if (!$completed) {
            $this->config->set('telegram.client_id', '');
            $this->config->set('telegram.client_secret', '');
            $this->config->set('telegram.bot_token', '');
            $this->config->set('telegram.bootstrap_admin_telegram_ids', []);
            $this->config->set('telegram.admin_chat_ids', []);
            $this->config->set('holiday_api.service_key', '');
        }

        $mappings = [
            'telegram.client_id' => 'telegram.client_id',
            'telegram.client_secret' => 'telegram.client_secret',
            'telegram.bot_token' => 'telegram.bot_token',
            'telegram.redirect_uri' => 'telegram.redirect_uri',
            'holiday_api.service_key' => 'holiday_api.service_key',
        ];

        foreach ($mappings as $settingKey => $configKey) {
            $value = $settings->get($settingKey, null);
            if ($value !== null && trim($value) !== '') {
                $this->config->set($configKey, $value);
            }
        }

        $managedAdminIds = $settings->lineList('telegram.bootstrap_admin_telegram_ids');
        if ($managedAdminIds !== []) {
            $this->config->set('telegram.bootstrap_admin_telegram_ids', $managedAdminIds);
        }

        $managedChatsRaw = $settings->get('telegram.admin_chat_ids', null);
        if ($managedChatsRaw !== null) {
            $this->config->set('telegram.admin_chat_ids', $settings->lineList('telegram.admin_chat_ids'));
        }
    }

    public function clearSetupKey(): void
    {
        $path = $this->rootPath . '/storage/setup.key';
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @return array<string, bool> */
    public function status(): array
    {
        $schema = $this->schemaReady();
        if (!$schema) {
            return [
                'schema' => false,
                'telegram' => false,
                'chat' => false,
                'admin' => false,
                'holiday' => false,
                'completed' => false,
            ];
        }

        $settings = new AppSettingRepository($this->pdo);

        $telegram = trim((string) $settings->get('telegram.client_id', '')) !== ''
            && trim((string) $settings->get('telegram.client_secret', '')) !== ''
            && trim((string) $settings->get('telegram.bot_token', '')) !== '';

        $chat = $settings->lineList('telegram.admin_chat_ids') !== [];
        $admin = $settings->lineList('telegram.bootstrap_admin_telegram_ids') !== [];
        $holiday = trim((string) $settings->get('holiday_api.service_key', '')) !== '';

        return [
            'schema' => true,
            'telegram' => $telegram,
            'chat' => $chat,
            'admin' => $admin,
            'holiday' => $holiday,
            'completed' => $settings->get('setup.completed', '0') === '1',
        ];
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
