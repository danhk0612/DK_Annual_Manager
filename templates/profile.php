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

        <form class="form-grid" method="post" action="/profile/save">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>
                부서
                <input name="department" maxlength="100" value="<?= htmlspecialchars((string) ($user['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                직책
                <input name="position" maxlength="100" value="<?= htmlspecialchars((string) ($user['position'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="span-2">
                입사일
                <input type="date" name="hire_date" required value="<?= htmlspecialchars((string) ($user['hire_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <div class="form-actions">
                <button class="button primary" type="submit">내 정보 저장</button>
            </div>
            <p class="form-hint span-2">입사일 저장 시 현재 날짜까지 발생한 연차가 자동으로 동기화됩니다. 입사일을 변경하면 기존 자동 발생분을 다시 계산합니다.</p>
        </form>
    <?php endif; ?>
</section>
