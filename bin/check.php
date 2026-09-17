<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Database;
use Throwable;

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
} catch (Throwable $exception) {
    $fail('환경 확인 중 오류: ' . $exception->getMessage());
}

printf("\n결과: FAIL %d / WARN %d\n", $failures, $warnings);
exit($failures > 0 ? 1 : 0);
