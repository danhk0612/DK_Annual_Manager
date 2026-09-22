<?php
/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $leaveTypes */
/** @var float $annualBalance */
/** @var list<string> $reasonCategories */
/** @var string $today */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $warning */
/** @var mixed $error */

$returnTo = '/leave';
$targetUsers = [];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Leave request</p>
        <h1>휴가 신청</h1>
        <p>기간과 사유를 입력해 휴가를 신청합니다. 주말과 등록된 공휴일은 자동으로 제외됩니다.</p>
    </div>
    <div class="page-actions">
        <a class="button" href="/leave/history">신청 내역</a>
        <a class="button" href="/calendar">달력으로 돌아가기</a>
    </div>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($warning) && $warning !== ''): ?>
    <div class="notice warning"><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="summary-grid leave-summary">
    <div class="summary-card emphasis">
        <span><?= (int) date('Y') ?>년 잔여 연차</span>
        <strong><?= number_format($annualBalance, 1) ?>일</strong>
    </div>
    <div class="summary-card">
        <span>연차 차감</span>
        <strong>연차 · 반차</strong>
    </div>
    <div class="summary-card">
        <span>연차 미차감</span>
        <strong>공가 · 병가 · 대체휴가</strong>
    </div>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">New request</p>
            <h2>새 신청</h2>
        </div>
    </div>
    <?php require __DIR__ . '/_leave-form.php'; ?>
</section>
