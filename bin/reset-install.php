<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Database;
use DKAnnual\Setup\SetupService;

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
    $setup = new SetupService($pdo, $config, $root);
    $setupKey = $setup->resetInstallation();

    $setupPath = '/setup?setup_key=' . $setupKey;
    $appUrl = rtrim((string) $config->get('app.url', ''), '/');

    fwrite(STDOUT, "완전 초기화를 완료했습니다. config/config.php와 소스코드는 유지했습니다.\n");
    fwrite(STDOUT, "초기 설정 접근 키도 새로 생성했습니다.\n\n");
    fwrite(STDOUT, "경로: " . $setupPath . "\n");

    if ($appUrl !== '' && filter_var($appUrl, FILTER_VALIDATE_URL) !== false && !str_contains($appUrl, 'example.com')) {
        fwrite(STDOUT, "URL:  " . $appUrl . $setupPath . "\n");
    } else {
        fwrite(STDOUT, "현재 서비스 주소 뒤에 위 경로를 붙여 접속하세요.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "초기화에 실패했습니다. 서버/PHP 로그를 확인하세요. (" . $exception::class . ")\n");
    exit(1);
}
