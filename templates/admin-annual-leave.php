<?php
/** @var list<array<string, mixed>> $users */
/** @var array<string, mixed>|null $selectedUser */
/** @var int $year */
/** @var list<array<string, mixed>> $entries */
/** @var float $balance */
/** @var float $totalEntitlement */
/** @var float|null $overrideAmount */
/** @var bool $manageOpen */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Annual leave ledger</p>
        <h1><i class="bi bi-calendar2-check"></i><span>연차 관리</span></h1>
        <p>직원별 연차 현황은 목록에서 확인하고, 세부 조정 기능은 관리 레이어에서 처리합니다.</p>
    </div>
    <a class="button" href="/admin"><i class="bi bi-speedometer2"></i><span>관리자 홈</span></a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><i class="bi bi-check-circle"></i><span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><i class="bi bi-x-circle"></i><span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>

<section class="panel admin-list-panel">
    <div class="list-toolbar">
        <div>
            <p class="eyebrow">Employees</p>
            <h2><i class="bi bi-list-ul"></i><span><?= $year ?>년 직원별 연차</span></h2>
        </div>
        <form class="toolbar-form" method="get" action="/admin/annual-leave">
            <label>
                <span>연도</span>
                <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>">
            </label>
            <button class="button" type="submit"><i class="bi bi-search"></i><span>조회</span></button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>직원</th>
                <th>부서 · 직책</th>
                <th>입사일</th>
                <th>권한</th>
                <th>상태</th>
                <th class="table-action-column">기능</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr><td colspan="6" class="muted">관리할 직원이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td>
                        <?= htmlspecialchars((string) ($user['department'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        <span class="muted">· <?= htmlspecialchars((string) ($user['position'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td><?= htmlspecialchars((string) ($user['hire_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= ($user['role'] ?? null) === 'admin' ? '관리자' : '사용자' ?></td>
                    <td><span class="badge <?= htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <a class="button small" href="/admin/annual-leave?user_id=<?= (int) $user['id'] ?>&year=<?= $year ?>&manage=1"><i class="bi bi-sliders"></i><span>연차 관리</span></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($selectedUser !== null): ?>
<dialog class="modal-dialog wide-dialog" data-annual-leave-dialog <?= $manageOpen ? 'data-auto-open="1"' : '' ?>>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Annual leave detail</p>
                <h2><i class="bi bi-person-vcard"></i><span><?= htmlspecialchars((string) $selectedUser['name'], ENT_QUOTES, 'UTF-8') ?> · <?= $year ?>년 연차</span></h2>
                <p>자동 발생, 총 연차 고정, 이월·조정과 원장 내역을 한 곳에서 관리합니다.</p>
            </div>
            <button class="icon-button" type="button" data-close-annual-leave-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="summary-grid modal-summary-grid">
            <div class="summary-card">
                <span>직원</span>
                <strong><?= htmlspecialchars((string) $selectedUser['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars((string) ($selectedUser['department'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($selectedUser['position'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small>
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
                    <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                    <button class="button" type="submit" <?= empty($selectedUser['hire_date']) ? 'disabled' : '' ?>><i class="bi bi-arrow-repeat"></i><span>발생분 동기화</span></button>
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
                    <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
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
                        <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
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
                    <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
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
