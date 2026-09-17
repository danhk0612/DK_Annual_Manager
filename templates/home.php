<?php
/** @var array<string, mixed>|null $user */
/** @var string $csrfToken */
?>
<section class="hero">
    <p class="eyebrow">Annual Leave Management</p>
    <h1>연차·휴가 관리</h1>
    <?php if ($user === null): ?>
        <p>Telegram 로그인과 휴가 관리 기능을 연결하기 위한 애플리케이션 기반이 준비되었습니다.</p>
        <a class="button primary" href="/login">로그인</a>
    <?php else: ?>
        <p><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>님으로 로그인되어 있습니다.</p>
        <form action="/logout" method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button class="button" type="submit">로그아웃</button>
        </form>
    <?php endif; ?>
</section>
