<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Database;
use DKAnnual\Migration\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI에서만 실행할 수 있습니다.\n");
    exit(2);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$configPath = $root . '/config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "config/config.php가 없습니다.\n");
    exit(1);
}

try {
    $config = new Config($configPath);
    $pdo = Database::connect($config);
    $runner = new MigrationRunner($pdo, $root . '/database/migrations');
    $executed = $runner->migrate();

    if ($executed === []) {
        echo "적용할 migration이 없습니다. DB가 최신 상태입니다.\n";
        exit(0);
    }

    foreach ($executed as $migration) {
        echo "[APPLIED] {$migration}\n";
    }

    printf("완료: %d개 migration 적용\n", count($executed));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] Migration 적용 실패: ' . $exception::class . "\n");
    exit(1);
}
