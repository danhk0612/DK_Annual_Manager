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

        $settings = new AppSettingRepository($this->pdo);
        return $settings->get('setup.completed', '0') === '1';
    }

    public function initializeSchema(): void
    {
        if ($this->schemaReady()) {
            return;
        }

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
    }

    public function applyManagedConfig(): void
    {
        if (!$this->schemaReady()) {
            return;
        }

        $settings = new AppSettingRepository($this->pdo);

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

        $telegram = trim((string) $settings->get('telegram.client_id', (string) $this->config->get('telegram.client_id', ''))) !== ''
            && trim((string) $settings->get('telegram.client_secret', (string) $this->config->get('telegram.client_secret', ''))) !== ''
            && trim((string) $settings->get('telegram.bot_token', (string) $this->config->get('telegram.bot_token', ''))) !== '';

        $chat = $settings->get('telegram.admin_chat_ids', null) !== null
            ? $settings->lineList('telegram.admin_chat_ids') !== []
            : is_array($this->config->get('telegram.admin_chat_ids', []))
                && $this->config->get('telegram.admin_chat_ids', []) !== [];

        $admin = $settings->lineList('telegram.bootstrap_admin_telegram_ids') !== [];
        if (!$admin) {
            $configured = $this->config->get('telegram.bootstrap_admin_telegram_ids', []);
            $admin = is_array($configured) && $configured !== [];
        }

        $holiday = trim((string) $settings->get(
            'holiday_api.service_key',
            (string) $this->config->get('holiday_api.service_key', ''),
        )) !== '';

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
