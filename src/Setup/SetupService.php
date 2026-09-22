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

    /** @var list<string> */
    private const RESET_TABLES = [
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
        $this->migrateCompanyChatSetting($settings);
        $completed = $settings->get('setup.completed', '0') === '1';

        if (!$completed) {
            $this->config->set('telegram.client_id', '');
            $this->config->set('telegram.client_secret', '');
            $this->config->set('telegram.bot_token', '');
            $this->config->set('telegram.bootstrap_admin_telegram_ids', []);
            $this->config->set('telegram.company_chat_id', '');
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

        $managedCompanyChat = trim((string) $settings->get('telegram.company_chat_id', ''));
        if ($managedCompanyChat !== '') {
            $this->config->set('telegram.company_chat_id', $managedCompanyChat);
        }
    }

    public function clearSetupKey(): void
    {
        $path = $this->rootPath . '/storage/setup.key';
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function resetInstallation(): string
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (self::RESET_TABLES as $table) {
                $this->pdo->exec('DROP TABLE IF EXISTS ' . $table);
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = :table_name'
        );
        foreach (self::RESET_TABLES as $table) {
            $statement->execute(['table_name' => $table]);
            if ((int) $statement->fetchColumn() !== 0) {
                throw new RuntimeException('service data reset verification failed');
            }
        }

        $brandingDir = $this->rootPath . '/public/uploads/branding';
        foreach (glob($brandingDir . '/company-logo.*') ?: [] as $file) {
            @unlink($file);
        }

        $storageDir = $this->rootPath . '/storage';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            throw new RuntimeException('setup storage directory creation failed');
        }
        @chmod($storageDir, 0755);

        $setupKey = bin2hex(random_bytes(24));
        $setupKeyHash = hash('sha256', $setupKey);
        $setupKeyPath = $storageDir . '/setup.key';
        if (file_put_contents($setupKeyPath, $setupKeyHash . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('setup access key write failed');
        }
        @chmod($setupKeyPath, 0644);

        return $setupKey;
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
        $this->migrateCompanyChatSetting($settings);

        $telegram = trim((string) $settings->get('telegram.client_id', '')) !== ''
            && trim((string) $settings->get('telegram.client_secret', '')) !== ''
            && trim((string) $settings->get('telegram.bot_token', '')) !== '';

        $chat = trim((string) $settings->get('telegram.company_chat_id', '')) !== '';
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

    private function migrateCompanyChatSetting(AppSettingRepository $settings): void
    {
        if (trim((string) $settings->get('telegram.company_chat_id', '')) !== '') {
            return;
        }

        $legacy = $settings->lineList('telegram.admin_chat_ids');
        if ($legacy !== []) {
            $settings->set('telegram.company_chat_id', $legacy[0], null);
            $settings->delete('telegram.admin_chat_ids');
            return;
        }

        $freshSetupInProgress = $settings->get('setup.started_at', null) !== null
            && $settings->get('setup.completed', '0') !== '1';
        if ($freshSetupInProgress) {
            return;
        }

        $configured = trim((string) $this->config->get('telegram.company_chat_id', ''));
        if ($configured !== '') {
            $settings->set('telegram.company_chat_id', $configured, null);
            return;
        }

        $legacyConfigured = $this->config->get('telegram.admin_chat_ids', []);
        if (is_array($legacyConfigured)) {
            foreach ($legacyConfigured as $chatId) {
                $chatId = trim((string) $chatId);
                if ($chatId !== '') {
                    $settings->set('telegram.company_chat_id', $chatId, null);
                    return;
                }
            }
        }
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
