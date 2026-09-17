<?php
/** @var list<array<string, mixed>> $requests */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Leave approvals</p>
        <h1>휴가 승인</h1>
        <p>승인 대기 중인 휴가 신청을 확인하고 승인 또는 반려합니다.</p>
    </div>
    <a class="button" href="/admin">관리자 홈</a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($requests === []): ?>
    <section class="panel"><p>승인 대기 중인 신청이 없습니다.</p></section>
<?php endif; ?>

<div class="approval-list">
<?php foreach ($requests as $item): ?>
    <section class="panel approval-card">
        <div class="approval-summary">
            <div>
                <span class="muted">#<?= (int) $item['id'] ?></span>
                <h2><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars((string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?> · <?= number_format((float) $item['requested_amount'], 1) ?>일</p>
                <?php if (!empty($item['reason'])): ?><p>사유: <?= htmlspecialchars((string) $item['reason'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </div>
            <span class="badge pending">pending</span>
        </div>
        <form method="post" action="/admin/requests/review">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="request_id" value="<?= (int) $item['id'] ?>">
            <label>
                관리자 메모
                <input name="review_note" maxlength="1000">
            </label>
            <div class="form-actions">
                <button class="button primary" type="submit" name="action" value="approve">승인</button>
                <button class="button" type="submit" name="action" value="reject">반려</button>
            </div>
        </form>
    </section>
<?php endforeach; ?>
</div>
