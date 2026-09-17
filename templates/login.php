<?php
/** @var bool $configured */
?>
<section class="panel narrow">
    <p class="eyebrow">Authentication</p>
    <h1>Telegram 로그인</h1>
    <?php if ($configured): ?>
        <p>Telegram 계정으로 로그인합니다. 최초 로그인 사용자는 관리자 승인 전까지 대기 상태로 등록됩니다.</p>
        <a class="button primary" href="/auth/telegram/start">Telegram으로 계속</a>
    <?php else: ?>
        <p>`config/config.php`에 Telegram Client ID, Client Secret, Redirect URI를 설정해야 합니다.</p>
    <?php endif; ?>
    <a class="button" href="/">돌아가기</a>
</section>
