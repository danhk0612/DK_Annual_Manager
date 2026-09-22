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
                <?php
                $halfDayPeriod = (string) ($item['half_day_period'] ?? '');
                $halfDayLabel = $halfDayPeriod === 'am' ? ' · 오전' : ($halfDayPeriod === 'pm' ? ' · 오후' : '');
                $annualBalance = (float) ($item['annual_balance'] ?? 0);
                $isInsufficient = (int) ($item['deducts_annual_leave'] ?? 0) === 1
                    && (float) $item['requested_amount'] > $annualBalance;
                ?>
                <h2><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?></h2>
                <p><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars((string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?> · <?= number_format((float) $item['requested_amount'], 1) ?>일</p>
                <?php if ((int) ($item['deducts_annual_leave'] ?? 0) === 1): ?>
                    <p>현재 잔여 연차: <?= number_format($annualBalance, 1) ?>일</p>
                <?php endif; ?>
                <?php if ($isInsufficient): ?>
                    <div class="notice warning compact-notice">잔여 연차보다 신청 일수가 많습니다. 필요 시 관리자 재량으로 승인할 수 있습니다.</div>
                <?php endif; ?>
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
