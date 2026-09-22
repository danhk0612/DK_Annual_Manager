<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI에서만 실행할 수 있습니다.\n");
    exit(2);
}

$confirm = $argv[1] ?? '';
if ($confirm !== '--confirm=RESET-INSTALL') {
    fwrite(STDERR, "주의: 이 명령은 휴가관리 DB의 모든 데이터를 삭제합니다.\n");
    fwrite(STDERR, "실행하려면 --confirm=RESET-INSTALL 을 지정하세요.\n");
    exit(2);
}

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
$configPath = $root . '/config/config.php';

if (!is_file($autoload) || !is_file($configPath)) {
    fwrite(STDERR, "vendor/autoload.php 또는 config/config.php가 없습니다.\n");
    exit(1);
}

require $autoload;

try {
    $config = new Config($configPath);
    $pdo = Database::connect($config);

    $tables = [
        'audit_logs',
        'app_settings',
        'holidays',
        'annual_leave_ledger',
        'leave_request_days',
        'leave_requests',
        'leave_types',
        'users',
    ];

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
            printf("[DROP] %s\n", $table);
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    $brandingDir = $root . '/public/uploads/branding';
    foreach (glob($brandingDir . '/company-logo.*') ?: [] as $file) {
        @unlink($file);
    }

    fwrite(STDOUT, "\n초기화 완료. config/config.php와 Composer 설치 파일은 유지했습니다.\n");
    fwrite(STDOUT, "브라우저에서 /setup 을 열어 신규 설치 과정을 시작하세요.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "초기화 실패: " . $exception->getMessage() . "\n");
    exit(1);
}
