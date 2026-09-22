<?php
/** @var string|null $title */
/** @var string $content */
/** @var string $appName */
/** @var string $logoPath */
/** @var string $theme */
/** @var string $primaryColor */
/** @var string $currentPath */
/** @var string $currentQuery */

$title = isset($title) && is_string($title) ? $title : $appName;
$requestShortcut = $currentPath === '/calendar' && str_contains($currentQuery, 'request=1');

$isCalendar = ($currentPath === '/' || $currentPath === '/calendar') && !$requestShortcut;
$isLeaveRequest = $currentPath === '/leave' || $requestShortcut;
$isLeaveHistory = $currentPath === '/leave/history';
$isProfile = $currentPath === '/profile';
$isAdmin = str_starts_with($currentPath, '/admin');

$appCssVersion = (string) (@filemtime(dirname(__DIR__) . '/public/assets/app.css') ?: '1');
$appJsVersion = (string) (@filemtime(dirname(__DIR__) . '/public/assets/app.js') ?: '1');
?>
<!doctype html>
<html lang="ko" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/app-icon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/app.css?v=<?= rawurlencode($appCssVersion) ?>">
    <link rel="stylesheet" href="/theme.css">
    <script src="/assets/app.js?v=<?= rawurlencode($appJsVersion) ?>" defer></script>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/calendar">
            <?php if ($logoPath !== ''): ?>
                <span class="brand-logo-frame custom"><img class="brand-logo" src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt=""></span>
            <?php else: ?>
                <span class="brand-logo-frame"><img class="brand-logo" src="/assets/app-icon.svg" alt=""></span>
            <?php endif; ?>
            <span class="brand-name"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <nav class="topnav" aria-label="주 메뉴">
            <a href="/calendar" class="<?= $isCalendar ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i><span>달력</span>
            </a>
            <a href="/calendar?request=1" class="nav-primary <?= $isLeaveRequest ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i><span>휴가 신청</span>
            </a>
            <a href="/leave/history" class="<?= $isLeaveHistory ? 'active' : '' ?>">
                <i class="bi bi-list-check"></i><span>신청 내역</span>
            </a>
            <a href="/profile" class="<?= $isProfile ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i><span>내 정보</span>
            </a>
            <a href="/admin" class="<?= $isAdmin ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i><span>관리</span>
            </a>
        </nav>
    </div>
</header>
<main class="container">
    <?= $content ?>
</main>
</body>
</html>
