<?php
/** @var list<array<string, mixed>> $leaveTypes */
/** @var list<string> $reasonCategories */
/** @var string $today */
/** @var string $csrfToken */
/** @var string|null $returnTo */
/** @var list<array<string, mixed>>|null $targetUsers */
/** @var float|null $annualBalance */

$returnTo = isset($returnTo) && is_string($returnTo) ? $returnTo : '/leave';
$targetUsers = isset($targetUsers) && is_array($targetUsers) ? $targetUsers : [];
$annualBalance = isset($annualBalance) ? (float) $annualBalance : null;
?>
<form class="form-grid leave-form" method="post" action="/leave/create" data-leave-form>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">

    <?php if ($targetUsers !== []): ?>
        <label class="span-2">
            직원
            <select name="target_user_id" required>
                <?php foreach ($targetUsers as $targetUser): ?>
                    <option value="<?= (int) $targetUser['id'] ?>">
                        <?= htmlspecialchars((string) $targetUser['name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($targetUser['department'])): ?>
                            · <?= htmlspecialchars((string) $targetUser['department'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>

    <label>
        휴가 종류
        <select name="leave_type_id" required data-leave-type>
            <?php foreach ($leaveTypes as $type): ?>
                <option
                    value="<?= (int) $type['id'] ?>"
                    data-leave-code="<?= htmlspecialchars((string) $type['code'], ENT_QUOTES, 'UTF-8') ?>"
                >
                    <?= htmlspecialchars((string) $type['name'], ENT_QUOTES, 'UTF-8') ?>
                    <?= (int) $type['deducts_annual_leave'] === 1
                        ? '(' . number_format((float) $type['default_amount'], 1) . '일 차감)'
                        : '(연차 미차감)' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        반차 구분
        <select name="half_day_period" data-half-day-select disabled>
            <option value="am">오전</option>
            <option value="pm">오후</option>
        </select>
    </label>

    <label>
        시작일
        <input
            type="date"
            name="start_date"
            required
            value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
            data-start-date
        >
    </label>

    <label>
        종료일
        <input
            type="date"
            name="end_date"
            required
            value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
            min="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
            data-end-date
        >
        <input
            type="hidden"
            name="end_date"
            value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
            data-end-date-hidden
            disabled
        >
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
        <button class="button primary" type="submit">휴가 신청</button>
    </div>

    <p class="form-hint span-2">
        <?php if ($annualBalance !== null): ?>
            현재 잔여 연차 <strong><?= number_format($annualBalance, 1) ?>일</strong>.
        <?php endif; ?>
        잔여 연차보다 많이 신청해도 접수되며, 부족한 경우 관리자에게 경고가 표시됩니다.
    </p>
</form>
