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
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Leave request</p>
        <h1>휴가 신청</h1>
        <p>주말과 등록된 공휴일은 신청 일수에서 자동으로 제외됩니다.</p>
    </div>
    <div class="page-actions">
        <a class="button" href="/leave/history">신청 내역</a>
        <a class="button" href="/calendar">휴가 달력</a>
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
    <div class="summary-card">
        <span><?= (int) date('Y') ?>년 잔여 연차</span>
        <strong><?= number_format($annualBalance, 1) ?>일</strong>
    </div>
    <div class="summary-card">
        <span>차감 휴가</span>
        <strong>연차 · 반차</strong>
    </div>
    <div class="summary-card">
        <span>비차감 휴가</span>
        <strong>공가 · 병가 · 대체휴가</strong>
    </div>
</section>

<section class="panel">
    <h2>새 신청</h2>
    <form class="form-grid" method="post" action="/leave/create" data-leave-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <label>
            휴가 종류
            <select name="leave_type_id" required data-leave-type>
                <?php foreach ($leaveTypes as $type): ?>
                    <option
                        value="<?= (int) $type['id'] ?>"
                        data-leave-code="<?= htmlspecialchars((string) $type['code'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <?= htmlspecialchars((string) $type['name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ((int) $type['deducts_annual_leave'] === 1): ?>
                            (<?= number_format((float) $type['default_amount'], 1) ?>일 차감)
                        <?php else: ?>
                            (연차 미차감)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label data-half-day-field hidden>
            반차 구분
            <select name="half_day_period" data-half-day-select>
                <option value="am">오전</option>
                <option value="pm">오후</option>
            </select>
        </label>

        <label>
            시작일
            <input type="date" name="start_date" required value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" data-start-date>
        </label>
        <label>
            종료일
            <input type="date" name="end_date" required value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" min="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" data-end-date>
        </label>

        <label>
            사유
            <select name="reason_category" required>
                <?php foreach ($reasonCategories as $reasonCategory): ?>
                    <option value="<?= htmlspecialchars($reasonCategory, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($reasonCategory, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            추가 사유
            <input name="reason_detail" maxlength="500" placeholder="필요한 경우 추가 내용을 입력하세요.">
        </label>

        <div class="form-actions">
            <button class="button primary" type="submit">신청</button>
        </div>
        <p class="form-hint span-2">잔여 연차보다 많은 연차·반차도 신청할 수 있습니다. 부족한 경우 경고가 표시되며 승인 여부는 관리자가 판단합니다.</p>
    </form>
</section>
