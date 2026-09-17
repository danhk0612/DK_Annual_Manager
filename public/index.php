<?php

declare(strict_types=1);

use DKAnnual\Config;
use DKAnnual\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new Config(dirname(__DIR__) . '/config/config.php');
date_default_timezone_set((string) $config->get('app.timezone', 'Asia/Seoul'));

$pdo = Database::connect($config);
$pdo->query('SELECT 1')->fetchColumn();

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $config->get('app.name', 'DK Annual Manager'), ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <main>
        <h1>DK Annual Manager</h1>
        <p>애플리케이션 및 데이터베이스 연결이 정상입니다.</p>
    </main>
</body>
</html>
