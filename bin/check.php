<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Database;
use DKAnnual\Migration\MigrationRunner;
use DKAnnual\Setup\SetupService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI에서만 실행할 수 있습니다.\n");
    exit(2);
}

$root = dirname(__DIR__);
$production = in_array('--production', $argv, true);
$failures = 0;
$warnings = 0;

$print = static function (string $status, string $message): void {
    printf("[%-4s] %s\n", $status, $message);
};
$pass = static function (string $message) use ($print): void { $print('PASS', $message); };
$warn = static function (string $message) use ($print, &$warnings): void { $warnings++; $print('WARN', $message); };
$fail = static function (string $message) use ($print, &$failures): void { $failures++; $print('FAIL', $message); };

if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
    $pass('PHP ' . PHP_VERSION);
} else {
    $fail('PHP 8.2 이상이 필요합니다. 현재: ' . PHP_VERSION);
}

foreach (['curl', 'json', 'pdo', 'pdo_mysql', 'simplexml'] as $extension) {
    extension_loaded($extension)
        ? $pass('PHP extension: ' . $extension)
        : $fail('PHP extension이 없습니다: ' . $extension);
}

$lockPath = $root . '/composer.lock';
if (is_file($lockPath)) {
    $pass('composer.lock 존재');
} elseif ($production) {
    $fail('운영 배포에는 composer.lock이 필요합니다.');
} else {
    $warn('composer.lock이 없어 의존성 설치 결과가 재현되지 않을 수 있습니다.');
}

$autoloadPath = $root . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    $fail('vendor/autoload.php가 없습니다. composer install을 실행하세요.');
    exit(1);
}
require $autoloadPath;

$configPath = $root . '/config/config.php';
if (!is_file($configPath)) {
    $fail('config/config.php가 없습니다. config.example.php를 복사해 설정하세요.');
    exit(1);
}
$pass('config/config.php 존재');

try {
    $config = new Config($configPath);
    $pdo = Database::connect($config);
    $pdo->query('SELECT 1')->fetchColumn();
    $pass('MariaDB 연결');

    $setup = new SetupService($pdo, $config, $root);
    if (!$setup->schemaReady()) {
        $fail('DB schema가 준비되지 않았습니다. 브라우저에서 /setup 을 열어 DB 스키마 생성을 진행하세요.');
        printf("\n모드: %s\n", $production ? 'production' : 'standard');
        printf("결과: FAIL %d / WARN %d\n", $failures, $warnings);
        exit(1);
    }
    $setup->applyManagedConfig();
    if ($setup->completed()) {
        $pass('초기 서비스 설정 완료');
    } elseif ($production) {
        $fail('운영 배포 전에 /setup 초기 서비스 설정을 완료해야 합니다.');
    } else {
        $warn('초기 서비스 설정이 완료되지 않았습니다. /setup 에서 진행하세요.');
    }

    $requiredTables = [
        'users', 'leave_types', 'leave_requests', 'leave_request_days',
        'annual_leave_ledger', 'holidays', 'app_settings', 'audit_logs', 'schema_migrations',
    ];
    $tableStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables '
        . 'WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    foreach ($requiredTables as $table) {
        $tableStatement->execute(['table_name' => $table]);
        (int) $tableStatement->fetchColumn() === 1
            ? $pass('DB table: ' . $table)
            : $fail('DB table이 없습니다: ' . $table);
    }

    $migrationTableStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables '
        . "WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
    );
    $migrationTableStatement->execute();
    if ((int) $migrationTableStatement->fetchColumn() === 1) {
        $pendingMigrations = (new MigrationRunner($pdo, $root . '/database/migrations'))->pending();
        $pendingMigrations === []
            ? $pass('DB migrations 최신')
            : $fail('미적용 DB migration: ' . implode(', ', $pendingMigrations));
    }

    $columnStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
    );
    foreach ([
        ['users', 'department'],
        ['users', 'position'],
        ['leave_requests', 'half_day_period'],
        ['leave_requests', 'cancelled_by'],
        ['leave_requests', 'cancelled_at'],
        ['leave_requests', 'cancellation_source'],
        ['leave_requests', 'cancellation_note'],
    ] as [$table, $column]) {
        $columnStatement->execute(['table_name' => $table, 'column_name' => $column]);
        (int) $columnStatement->fetchColumn() === 1
            ? $pass(sprintf('DB column: %s.%s', $table, $column))
            : $fail(sprintf('DB column이 없습니다: %s.%s (최신 migration 적용 필요)', $table, $column));
    }

    $leaveTypeStatement = $pdo->query(
        "SELECT code, is_active, deducts_annual_leave FROM leave_types WHERE code IN ('P', 'G', 'S', 'A')"
    );
    $leaveTypeMap = [];
    foreach ($leaveTypeStatement->fetchAll() as $leaveTypeRow) {
        $leaveTypeMap[(string) $leaveTypeRow['code']] = $leaveTypeRow;
    }
    isset($leaveTypeMap['G']) && (int) $leaveTypeMap['G']['is_active'] === 1
        && (int) $leaveTypeMap['G']['deducts_annual_leave'] === 0
        ? $pass('휴가 종류: 공가 활성 / 연차 미차감')
        : $fail('공가(G) 설정이 최신 상태가 아닙니다.');
    !isset($leaveTypeMap['P']) || (int) $leaveTypeMap['P']['is_active'] === 0
        ? $pass('휴가 종류: 개인 비활성')
        : $fail('개인(P) 휴가가 아직 활성 상태입니다.');
    foreach (['S' => '병가', 'A' => '대체휴가'] as $code => $name) {
        isset($leaveTypeMap[$code]) && (int) $leaveTypeMap[$code]['is_active'] === 1
            && (int) $leaveTypeMap[$code]['deducts_annual_leave'] === 0
            ? $pass(sprintf('휴가 종류: %s 연차 미차감', $name))
            : $fail(sprintf('%s(%s) 설정이 최신 상태가 아닙니다.', $name, $code));
    }

    $appUrl = trim((string) $config->get('app.url', ''));
    if (str_starts_with($appUrl, 'https://')) {
        $pass('app.url HTTPS');
    } elseif ($production) {
        $fail('운영 환경의 app.url은 HTTPS여야 합니다.');
    } else {
        $warn('운영 환경의 app.url은 HTTPS 사용을 권장합니다.');
    }

    if ((bool) $config->get('app.session_cookie_secure', false)) {
        $pass('Secure session cookie');
    } elseif ($production) {
        $fail('운영 환경에서는 app.session_cookie_secure=true가 필요합니다.');
    } else {
        $warn('운영 환경에서는 app.session_cookie_secure=true를 권장합니다.');
    }

    if ((bool) $config->get('app.debug', false) === false) {
        $pass('app.debug=false');
    } elseif ($production) {
        $fail('운영 환경에서는 app.debug=false가 필요합니다.');
    } else {
        $warn('app.debug=true 상태입니다.');
    }

    foreach ([
        'Telegram client_id 설정' => ['telegram.client_id', 'Telegram client_id가 비어 있습니다.'],
        'Telegram client_secret 설정' => ['telegram.client_secret', 'Telegram client_secret이 비어 있습니다.'],
        'Telegram bot_token 설정' => ['telegram.bot_token', 'Telegram bot_token이 비어 있어 알림을 보낼 수 없습니다.'],
        '공휴일 API 서비스키 설정' => ['holiday_api.service_key', '공휴일 API 서비스키가 비어 있습니다.'],
    ] as $label => [$key, $missingMessage]) {
        if (trim((string) $config->get($key, '')) !== '') {
            $pass($label);
        } elseif ($production) {
            $fail($missingMessage);
        } else {
            $warn($missingMessage);
        }
    }

    $brandingUploadDir = $root . '/public/uploads/branding';
    if (!is_dir($brandingUploadDir)) {
        $fail('브랜딩 업로드 폴더가 없습니다: public/uploads/branding');
    } elseif (!is_writable($brandingUploadDir)) {
        $warn('브랜딩 업로드 폴더가 현재 CLI 사용자에게 쓰기 불가입니다. 웹 서버/PHP 실행 계정도 쓰기 가능한지 확인하세요.');
    } else {
        $pass('브랜딩 업로드 폴더 쓰기 가능');
    }

    $managedChatStatement = $pdo->prepare(
        "SELECT setting_value FROM app_settings WHERE setting_key = 'telegram.company_chat_id' LIMIT 1"
    );
    $managedChatStatement->execute();
    $managedCompanyChat = $managedChatStatement->fetchColumn();
    if ($managedCompanyChat !== false && trim((string) $managedCompanyChat) !== '') {
        $pass('Telegram 회사 공용 그룹: 관리자 설정 사용');
    } elseif (trim((string) $config->get('telegram.company_chat_id', '')) !== '') {
        $pass('Telegram 회사 공용 그룹: config 기본값 사용');
    } elseif ($production) {
        $fail('Telegram 회사 공용 그룹이 비어 있습니다.');
    } else {
        $warn('Telegram 회사 공용 그룹이 비어 있습니다.');
    }

    $adminTelegramCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND telegram_user_id IS NOT NULL"
    )->fetchColumn();
    if ($adminTelegramCount > 0) {
        $pass(sprintf('Telegram 관리자 개인 알림 대상: %d명', $adminTelegramCount));
    } elseif ($production) {
        $fail('Telegram 개인 알림을 받을 활성 관리자가 없습니다.');
    } else {
        $warn('Telegram 개인 알림을 받을 활성 관리자가 없습니다.');
    }

    $activeAdminCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'"
    )->fetchColumn();
    $activeAdminCount > 0
        ? $pass(sprintf('활성 관리자 계정: %d명', $activeAdminCount))
        : $fail('활성 관리자 계정이 없습니다.');

    $workweek = (new \DKAnnual\Repository\AppSettingRepository($pdo))->workingWeekdays();
    $pass('주 근무 요일: ' . implode(',', $workweek));
} catch (\Throwable $exception) {
    $fail('환경 확인 중 오류: ' . $exception::class);
}

printf("\n모드: %s\n", $production ? 'production' : 'standard');
printf("결과: FAIL %d / WARN %d\n", $failures, $warnings);
exit($failures > 0 ? 1 : 0);
