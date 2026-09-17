<?php
/** @var string $heading */
/** @var string $message */
/** @var int|null $telegramUserId */
?>
<section class="panel narrow">
    <p class="eyebrow">Telegram Authentication</p>
    <h1><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($telegramUserId !== null): ?>
        <p>Telegram User ID: <strong><?= htmlspecialchars((string) $telegramUserId, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <p>최초 관리자인 경우 이 ID를 `telegram.bootstrap_admin_telegram_ids`에 추가한 뒤 다시 로그인하면 관리자 계정으로 활성화됩니다.</p>
    <?php endif; ?>
    <a class="button" href="/login">로그인으로 돌아가기</a>
</section>
