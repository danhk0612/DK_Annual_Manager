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
    <script src="/assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/">
            <span class="brand-mark">DK</span>
            <span>Annual Manager</span>
        </a>
        <nav class="topnav" aria-label="주 메뉴">
            <a href="/calendar">달력</a>
            <a href="/calendar?request=1" class="nav-primary">휴가 신청</a>
            <a href="/leave/history">신청 내역</a>
            <a href="/profile">내 정보</a>
            <a href="/admin">관리</a>
        </nav>
    </div>
</header>
<main class="container">
    <?= $content ?>
</main>
</body>
</html>
