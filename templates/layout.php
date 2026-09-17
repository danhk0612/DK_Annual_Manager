<?php
/** @var string|null $title */
/** @var string $content */
$title = isset($title) && is_string($title) ? $title : 'DK Annual Manager';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · DK Annual Manager</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">DK Annual Manager</a>
    <nav class="topnav">
        <a href="/">홈</a>
        <a href="/calendar">달력</a>
        <a href="/leave">휴가 신청</a>
        <a href="/profile">내 정보</a>
        <a href="/admin">관리</a>
    </nav>
</header>
<main class="container">
    <?= $content ?>
</main>
</body>
</html>
