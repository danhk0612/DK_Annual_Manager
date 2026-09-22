<?php
/** @var list<array<string, mixed>> $users */
/** @var array<string, mixed>|null $selectedUser */
/** @var int $year */
/** @var list<array<string, mixed>> $entries */
/** @var float $balance */
/** @var float $totalEntitlement */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Annual leave ledger</p>
        <h1>연차 관리</h1>
        <p>입사일 기준 발생분을 자동 반영하고, 필요하면 관리자가 연간 총 연차를 직접 보정할 수 있습니다.</p>
    </div>
    <a class="button" href="/admin">관리자 홈</a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="panel">
    <form class="form-grid compact" method="get" action="/admin/annual-leave">
        <label>
            직원
            <select name="user_id">
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['id'] ?>" <?= $selectedUser !== null && (int) $selectedUser['id'] === (int) $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            연도
            <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>">
        </label>
        <div class="form-actions">
            <button class="button" type="submit">조회</button>
        </div>
    </form>
</section>

<?php if ($selectedUser !== null): ?>
<section class="summary-grid">
    <div class="summary-card">
        <span>직원</span>
        <strong><?= htmlspecialchars((string) $selectedUser['name'], ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <div class="summary-card">
        <span><?= $year ?>년 총 연차</span>
        <strong><?= number_format($totalEntitlement, 1) ?>일</strong>
    </div>
    <div class="summary-card">
        <span><?= $year ?>년 잔여 연차</span>
        <strong><?= number_format($balance, 1) ?>일</strong>
    </div>
</section>

<section class="panel">
    <h2>자동 발생</h2>
    <p>이 화면을 열 때도 현재 날짜까지의 입사일 기준 발생분이 자동 동기화됩니다. 아래 버튼은 필요할 때 수동으로 다시 확인하는 용도입니다.</p>
    <form method="post" action="/admin/annual-leave/sync">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
        <button class="button" type="submit" <?= empty($selectedUser['hire_date']) ? 'disabled' : '' ?>>발생분 다시 동기화</button>
    </form>
</section>

<section class="panel">
    <h2>연간 총 연차 수동 설정</h2>
    <p>이미 사용한 연차는 유지하고, 해당 연도의 총 연차가 입력값이 되도록 자동으로 조정 원장을 추가합니다.</p>
    <form class="form-grid" method="post" action="/admin/annual-leave/set-total">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
        <input type="hidden" name="year" value="<?= $year ?>">
        <label>
            총 연차
            <input type="number" name="total_amount" min="0" max="365" step="0.5" required value="<?= number_format($totalEntitlement, 1, '.', '') ?>">
        </label>
        <label>
            메모
            <input name="note" maxlength="255" placeholder="예: 회사 정책에 따른 조정">
        </label>
        <div class="form-actions">
            <button class="button primary" type="submit">총 연차 설정</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>이월 / 추가 조정</h2>
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
        <div class="form-actions">
            <button class="button" type="submit">원장 추가</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2><?= $year ?>년 원장</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>등록일</th>
                <th>유형</th>
                <th>일수</th>
                <th>메모</th>
                <th>처리자</th>
            </tr>
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
<?php endif; ?>
