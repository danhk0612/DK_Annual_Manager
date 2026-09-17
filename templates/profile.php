<?php
/** @var array<string, mixed>|null $user */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="panel narrow">
    <p class="eyebrow">My profile</p>
    <h1>내 정보</h1>

    <?php if (is_string($message) && $message !== ''): ?>
        <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (is_string($error) && $error !== ''): ?>
        <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($user !== null): ?>
        <dl class="details">
            <div><dt>이름</dt><dd><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Telegram</dt><dd><?= htmlspecialchars((string) ($user['telegram_username'] ?? $user['telegram_user_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>권한</dt><dd><?= $user['role'] === 'admin' ? '관리자' : '사용자' ?></dd></div>
        </dl>

        <form method="post" action="/profile/hire-date">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>
                입사일
                <input type="date" name="hire_date" required value="<?= htmlspecialchars((string) ($user['hire_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <button class="button primary" type="submit">입사일 저장</button>
        </form>
    <?php endif; ?>
</section>
