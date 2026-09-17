<?php
/** @var array<string, mixed>|null $user */
/** @var string $csrfToken */
?>
<section class="hero">
    <p class="eyebrow">Annual Leave Management</p>
    <h1>연차·휴가 관리</h1>
    <?php if ($user === null): ?>
        <p>Telegram으로 로그인하여 휴가 일정과 연차를 관리할 수 있습니다.</p>
        <a class="button primary" href="/login">로그인</a>
    <?php else: ?>
        <p><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>님으로 로그인되어 있습니다.</p>
        <div class="hero-actions">
            <a class="button primary" href="/leave">휴가 신청</a>
            <a class="button" href="/calendar">휴가 달력</a>
        </div>
        <form action="/logout" method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button class="button" type="submit">로그아웃</button>
        </form>
    <?php endif; ?>
</section>
