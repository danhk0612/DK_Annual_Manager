<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Database;
use DKAnnual\Setup\SetupService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI에서만 실행할 수 있습니다.\n");
    exit(2);
}

$root = dirname(__DIR__);
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
        $fail('DB schema가 초기화되지 않았습니다. 브라우저에서 /setup 을 열어 DB 초기화를 진행하세요.');
        printf("\n결과: FAIL %d / WARN %d\n", $failures, $warnings);
        exit(1);
    }
    $setup->applyManagedConfig();
    $setup->completed()
        ? $pass('초기 서비스 설정 완료')
        : $warn('초기 서비스 설정이 완료되지 않았습니다. /setup 에서 진행하세요.');

    $requiredTables = [
        'users', 'leave_types', 'leave_requests', 'leave_request_days',
        'annual_leave_ledger', 'holidays', 'app_settings', 'audit_logs',
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

    $columnStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
    );
    foreach ([
        ['users', 'department'],
        ['users', 'position'],
        ['leave_requests', 'half_day_period'],
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
    str_starts_with($appUrl, 'https://')
        ? $pass('app.url HTTPS')
        : $warn('운영 환경의 app.url은 HTTPS 사용을 권장합니다.');

    (bool) $config->get('app.session_cookie_secure', false)
        ? $pass('Secure session cookie')
        : $warn('운영 환경에서는 app.session_cookie_secure=true를 권장합니다.');

    trim((string) $config->get('telegram.client_id', '')) !== ''
        ? $pass('Telegram client_id 설정')
        : $warn('Telegram client_id가 비어 있습니다.');
    trim((string) $config->get('telegram.client_secret', '')) !== ''
        ? $pass('Telegram client_secret 설정')
        : $warn('Telegram client_secret이 비어 있습니다.');
    trim((string) $config->get('telegram.bot_token', '')) !== ''
        ? $pass('Telegram bot_token 설정')
        : $warn('Telegram bot_token이 비어 있어 알림을 보낼 수 없습니다.');
    trim((string) $config->get('holiday_api.service_key', '')) !== ''
        ? $pass('공휴일 API 서비스키 설정')
        : $warn('공휴일 API 서비스키가 비어 있습니다.');

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
    } else {
        $warn('Telegram 회사 공용 그룹이 비어 있습니다.');
    }

    $adminTelegramCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND telegram_user_id IS NOT NULL"
    )->fetchColumn();
    $adminTelegramCount > 0
        ? $pass(sprintf('Telegram 관리자 개인 알림 대상: %d명', $adminTelegramCount))
        : $warn('Telegram 개인 알림을 받을 활성 관리자가 없습니다.');
} catch (\Throwable $exception) {
    $fail('환경 확인 중 오류: ' . $exception->getMessage());
}

printf("\n결과: FAIL %d / WARN %d\n", $failures, $warnings);
exit($failures > 0 ? 1 : 0);
