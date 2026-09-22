<?php
/** @var list<array<string, mixed>> $users */
/** @var int $activeAdminCount */
/** @var int $year */
/** @var array<int, array{used:float,total:float,balance:float}> $annualLeaveSummaries */
/** @var array<string, mixed>|null $selectedAnnualUser */
/** @var list<array<string, mixed>> $entries */
/** @var float $balance */
/** @var float $totalEntitlement */
/** @var float|null $overrideAmount */
/** @var bool $manageAnnualLeaveOpen */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */

$statusLabels = [
    'pending' => '승인 대기',
    'active' => '활성',
    'inactive' => '비활성',
];

$formatLeaveDays = static function (float $value): string {
    if (abs($value - round($value)) < 0.001) {
        return (string) (int) round($value);
    }

    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
};
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Administration</p>
        <h1><i class="bi bi-people"></i><span>직원 관리</span></h1>
        <p>직원 정보, Telegram 연결, 권한, 계정 상태와 연차를 한 목록에서 관리합니다.</p>
    </div>
    <div class="page-actions">
        <button class="button primary" type="button" data-open-user-dialog data-user-mode="create"><i class="bi bi-person-plus"></i><span>직원 추가</span></button>
        <a class="button" href="/admin"><i class="bi bi-speedometer2"></i><span>관리자 홈</span></a>
    </div>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><i class="bi bi-check-circle"></i><span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><i class="bi bi-x-circle"></i><span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>

<section class="panel admin-list-panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Employees</p>
            <h2><i class="bi bi-card-list"></i><span>직원 목록</span></h2>
        </div>
        <span class="count-badge"><?= count($users) ?>명</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>이름</th>
                <th>부서 · 직책</th>
                <th>입사일</th>
                <th>연차 (<?= $year ?>)</th>
                <th>Telegram</th>
                <th>권한</th>
                <th>상태</th>
                <th class="table-action-column">기능</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr><td colspan="8" class="muted">등록된 직원이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <?php
                $soleAdmin = $activeAdminCount === 1
                    && ($user['role'] ?? null) === 'admin'
                    && ($user['status'] ?? null) === 'active';
                $telegramLabel = $user['telegram_user_id'] !== null
                    ? (string) $user['telegram_user_id']
                    : '미연결';
                $annualLeave = $annualLeaveSummaries[(int) $user['id']] ?? [
                    'used' => 0.0,
                    'total' => 0.0,
                    'balance' => 0.0,
                ];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td>
                        <?= htmlspecialchars((string) ($user['department'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        <span class="muted">· <?= htmlspecialchars((string) ($user['position'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td><?= htmlspecialchars((string) ($user['hire_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a
                            class="button small"
                            href="/admin/users?leave_user_id=<?= (int) $user['id'] ?>&year=<?= $year ?>&manage_leave=1"
                            title="<?= $year ?>년 연차 관리"
                        ><span><?= $formatLeaveDays((float) $annualLeave['used']) ?>/<?= $formatLeaveDays((float) $annualLeave['total']) ?></span></a>
                    </td>
                    <td>
                        <?= htmlspecialchars($telegramLabel, ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($user['telegram_username'])): ?>
                            <span class="muted">@<?= htmlspecialchars((string) $user['telegram_username'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $user['role'] === 'admin' ? 'approved' : 'neutral' ?>"><?= $user['role'] === 'admin' ? '관리자' : '사용자' ?></span></td>
                    <td><span class="badge <?= htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabels[(string) $user['status']] ?? (string) $user['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <button
                            class="button small"
                            type="button"
                            data-open-user-dialog
                            data-user-mode="edit"
                            data-user-id="<?= (int) $user['id'] ?>"
                            data-user-name="<?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-user-department="<?= htmlspecialchars((string) ($user['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-position="<?= htmlspecialchars((string) ($user['position'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-hire-date="<?= htmlspecialchars((string) ($user['hire_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-end-date="<?= htmlspecialchars((string) ($user['employment_end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-telegram-id="<?= htmlspecialchars((string) ($user['telegram_user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-role="<?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?>"
                            data-user-status="<?= htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8') ?>"
                            data-user-sole-admin="<?= $soleAdmin ? '1' : '0' ?>"
                        ><i class="bi bi-pencil-square"></i><span>수정</span></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="modal-dialog" data-user-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Employee</p>
                <h2 data-user-dialog-title><i class="bi bi-person-gear"></i><span>직원 추가</span></h2>
                <p>직원 기본 정보, Telegram ID, 권한과 상태를 저장합니다.</p>
            </div>
            <button class="icon-button" type="button" data-close-user-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="notice warning" data-user-sole-admin-warning hidden>
            <i class="bi bi-shield-exclamation"></i>
            <span>현재 유일한 활성 관리자입니다. 다른 관리자를 먼저 지정하기 전에는 사용자로 변경하거나 비활성화할 수 없습니다.</span>
        </div>

        <form class="form-grid" method="post" action="/admin/users/save" data-user-form>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" value="" data-user-id>

            <label>
                이름
                <input name="name" required maxlength="100" data-user-name>
            </label>
            <label>
                부서
                <input name="department" maxlength="100" data-user-department>
            </label>
            <label>
                직책
                <input name="position" maxlength="100" data-user-position>
            </label>
            <label>
                입사일
                <input type="date" name="hire_date" data-user-hire-date>
            </label>
            <label>
                퇴사일
                <input type="date" name="employment_end_date" data-user-end-date>
            </label>
            <label>
                Telegram User ID
                <input inputmode="numeric" name="telegram_user_id" data-user-telegram-id>
            </label>
            <label>
                권한
                <select name="role" data-user-role>
                    <option value="user">사용자</option>
                    <option value="admin">관리자</option>
                </select>
            </label>
            <label>
                상태
                <select name="status" data-user-status>
                    <option value="pending">승인 대기</option>
                    <option value="active">활성</option>
                    <option value="inactive">비활성</option>
                </select>
            </label>
            <div class="form-actions span-2">
                <button class="button primary" type="submit"><i class="bi bi-check2-circle"></i><span data-user-submit-label>추가</span></button>
                <button class="button" type="button" data-close-user-dialog>취소</button>
            </div>
        </form>
    </div>
</dialog>

<?php if ($selectedAnnualUser !== null): ?>
<dialog class="modal-dialog wide-dialog" data-annual-leave-dialog <?= $manageAnnualLeaveOpen ? 'data-auto-open="1"' : '' ?>>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Annual leave detail</p>
                <h2><i class="bi bi-person-vcard"></i><span><?= htmlspecialchars((string) $selectedAnnualUser['name'], ENT_QUOTES, 'UTF-8') ?> · <?= $year ?>년 연차</span></h2>
                <p>자동 발생, 총 연차 고정, 이월·조정과 원장 내역을 한 곳에서 관리합니다.</p>
            </div>
            <button class="icon-button" type="button" data-close-annual-leave-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>

        <form class="toolbar-form" method="get" action="/admin/users">
            <input type="hidden" name="leave_user_id" value="<?= (int) $selectedAnnualUser['id'] ?>">
            <input type="hidden" name="manage_leave" value="1">
            <label>
                <span>연도</span>
                <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>">
            </label>
            <button class="button" type="submit"><i class="bi bi-search"></i><span>조회</span></button>
        </form>

        <div class="summary-grid modal-summary-grid">
            <div class="summary-card">
                <span>직원</span>
                <strong><?= htmlspecialchars((string) $selectedAnnualUser['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars((string) ($selectedAnnualUser['department'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($selectedAnnualUser['position'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small>
            </div>
            <div class="summary-card <?= $overrideAmount !== null ? 'emphasis' : '' ?>">
                <span><?= $year ?>년 총 연차</span>
                <strong><?= number_format($totalEntitlement, 1) ?>일</strong>
                <small><?= $overrideAmount !== null ? '관리자 고정값 적용 중' : '자동 계산 + 이월/조정' ?></small>
            </div>
            <div class="summary-card">
                <span><?= $year ?>년 잔여 연차</span>
                <strong><?= number_format($balance, 1) ?>일</strong>
            </div>
        </div>

        <div class="modal-action-grid">
            <section class="subpanel">
                <div class="section-head compact-head">
                    <div>
                        <p class="eyebrow">Accrual</p>
                        <h3>자동 발생</h3>
                    </div>
                </div>
                <p>현재 날짜까지의 입사일 기준 발생분을 다시 확인합니다.</p>
                <form method="post" action="/admin/annual-leave/sync">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $selectedAnnualUser['id'] ?>">
                    <button class="button" type="submit" <?= empty($selectedAnnualUser['hire_date']) ? 'disabled' : '' ?>><i class="bi bi-arrow-repeat"></i><span>발생분 동기화</span></button>
                </form>
            </section>

            <section class="subpanel">
                <div class="section-head compact-head">
                    <div>
                        <p class="eyebrow">Override</p>
                        <h3>연간 총 연차 고정</h3>
                    </div>
                    <?php if ($overrideAmount !== null): ?><span class="badge approved">고정 중</span><?php endif; ?>
                </div>
                <form class="form-grid" method="post" action="/admin/annual-leave/set-total">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $selectedAnnualUser['id'] ?>">
                    <input type="hidden" name="year" value="<?= $year ?>">
                    <label>
                        총 연차
                        <input type="number" name="total_amount" min="0" max="365" step="0.5" required value="<?= number_format($overrideAmount ?? $totalEntitlement, 1, '.', '') ?>">
                    </label>
                    <label>
                        메모
                        <input name="note" maxlength="255" placeholder="회사 정책에 따른 조정">
                    </label>
                    <div class="form-actions span-2">
                        <button class="button primary" type="submit">총 연차 고정</button>
                    </div>
                </form>
                <?php if ($overrideAmount !== null): ?>
                    <form method="post" action="/admin/annual-leave/clear-total" class="secondary-action-form">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $selectedAnnualUser['id'] ?>">
                        <input type="hidden" name="year" value="<?= $year ?>">
                        <button class="button danger-ghost" type="submit">고정 해제</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="subpanel span-2">
                <div class="section-head compact-head">
                    <div>
                        <p class="eyebrow">Adjustment</p>
                        <h3>이월 · 추가 조정</h3>
                    </div>
                </div>
                <form class="form-grid" method="post" action="/admin/annual-leave/adjust">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $selectedAnnualUser['id'] ?>">
                    <input type="hidden" name="year" value="<?= $year ?>">
                    <label>
                        유형
                        <select name="transaction_type">
                            <option value="carryover">이월</option>
                            <option value="adjustment">추가 조정</option>
                        </select>
                    </label>
                    <label>
                        일수
                        <input type="number" name="amount" step="0.5" required placeholder="예: 2 또는 -0.5">
                    </label>
                    <label class="span-2">
                        메모
                        <input name="note" maxlength="255">
                    </label>
                    <div class="form-actions span-2">
                        <button class="button" type="submit">원장 추가</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="subpanel ledger-panel">
            <div class="section-head compact-head">
                <div>
                    <p class="eyebrow">Ledger</p>
                    <h3><?= $year ?>년 원장</h3>
                </div>
                <span class="count-badge"><?= count($entries) ?>건</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr><th>등록일</th><th>유형</th><th>일수</th><th>메모</th><th>처리자</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($entries === []): ?>
                        <tr><td colspan="5" class="muted">원장 내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $entry['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $entry['transaction_type'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format((float) $entry['amount'], 2) ?></td>
                            <td><?= htmlspecialchars((string) ($entry['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($entry['created_by_name'] ?? '자동'), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</dialog>
<?php endif; ?>
