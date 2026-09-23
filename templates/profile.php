<?php
/** @var array<string, mixed>|null $user */
/** @var int $year */
/** @var array<string, float> $annualSummary */
/** @var list<array{month_number:int, request_count:int, total_amount:float, deducted_amount:float, non_deducted_amount:float}> $monthlyLeaveSummary */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">My profile</p>
        <h1>내 정보</h1>
        <p>개인 정보와 <?= $year ?>년 연차 현황을 확인합니다.</p>
    </div>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($user !== null): ?>
<section class="summary-grid profile-summary">
    <div class="summary-card emphasis">
        <span><?= $year ?>년 전체 연차</span>
        <strong><?= number_format((float) ($annualSummary['total'] ?? 0), 1) ?>일</strong>
    </div>
    <div class="summary-card">
        <span>사용</span>
        <strong><?= number_format((float) ($annualSummary['net_used'] ?? 0), 1) ?>일</strong>
    </div>
    <div class="summary-card">
        <span>잔여</span>
        <strong><?= number_format((float) ($annualSummary['balance'] ?? 0), 1) ?>일</strong>
    </div>
</section>

<section class="panel profile-monthly-summary">
    <div class="section-head">
        <div>
            <p class="eyebrow">Monthly summary</p>
            <h2><i class="bi bi-calendar3"></i><span><?= $year ?>년 월별 휴가 요약</span></h2>
            <p>승인된 휴가를 기준으로 연차 차감 휴가와 미차감 휴가를 구분합니다.</p>
        </div>
    </div>
    <div class="monthly-leave-grid">
        <?php foreach ($monthlyLeaveSummary as $monthSummary): ?>
            <?php $isCurrentMonth = $year === (int) date('Y') && (int) $monthSummary['month_number'] === (int) date('n'); ?>
            <a class="monthly-leave-card <?= $isCurrentMonth ? 'current' : '' ?>" href="/calendar?month=<?= sprintf('%04d-%02d', $year, (int) $monthSummary['month_number']) ?>" aria-label="<?= $year ?>년 <?= (int) $monthSummary['month_number'] ?>월 달력으로 이동">
                <div class="monthly-leave-card-head">
                    <strong><?= (int) $monthSummary['month_number'] ?>월</strong>
                    <span><?= (int) $monthSummary['request_count'] ?>건</span>
                </div>
                <div class="monthly-leave-total"><?= number_format((float) $monthSummary['total_amount'], 1) ?>일</div>
                <div class="monthly-leave-breakdown">
                    <span class="deduct"><i></i>연차 차감 <?= number_format((float) $monthSummary['deducted_amount'], 1) ?></span>
                    <span class="free"><i></i>미차감 <?= number_format((float) $monthSummary['non_deducted_amount'], 1) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel export-panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Excel export</p>
            <h2><i class="bi bi-file-earmark-excel"></i><span>내 휴가 엑셀 출력</span></h2>
            <p>내 휴가를 요약·연/월 통계·기간별 상세·일자별 원본으로 정리한 단일 Excel 보고서로 내려받습니다.</p>
        </div>
    </div>

    <form class="export-form" method="get" action="/profile/export" data-export-form>
        <label>
            기간
            <select name="period" data-export-period>
                <option value="year">연간</option>
                <option value="month">월간</option>
                <option value="all">전체</option>
            </select>
        </label>
        <label data-export-year-wrap>
            연도
            <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>">
        </label>
        <label data-export-month-wrap>
            월
            <select name="month">
                <?php for ($exportMonth = 1; $exportMonth <= 12; $exportMonth++): ?>
                    <option value="<?= $exportMonth ?>" <?= $exportMonth === (int) date('n') ? 'selected' : '' ?>><?= $exportMonth ?>월</option>
                <?php endfor; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button class="button primary" type="submit"><i class="bi bi-download"></i><span>엑셀 다운로드</span></button>
        </div>
    </form>
    <p class="form-hint">보고서에는 요약, 연도·월·휴가종류 통계, 연차 현황, 신청 기록, 실제 휴가일 원본과 연/월별 분리 시트가 포함됩니다.</p>
</section>

<div class="content-grid two-column profile-account-grid">
    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Account</p>
                <h2>계정 정보</h2>
            </div>
        </div>
        <dl class="details">
            <div><dt>Telegram</dt><dd><?= htmlspecialchars((string) ($user['telegram_username'] ?? $user['telegram_user_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>권한</dt><dd><?= $user['role'] === 'admin' ? '관리자' : '사용자' ?></dd></div>
        </dl>
    </section>

    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Employment</p>
                <h2>근무 정보</h2>
            </div>
        </div>
        <form class="form-grid" method="post" action="/profile/save">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label class="span-2">
                이름
                <input name="name" maxlength="100" required value="<?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </label>
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
            <p class="form-hint span-2">이름은 이 시스템에서 표시할 이름입니다. 입사일 저장 시 현재 날짜까지 발생한 연차를 자동 반영하며, 입사일을 변경하면 자동 발생분을 다시 계산합니다.</p>
        </form>
    </section>
</div>
<?php endif; ?>
