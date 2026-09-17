<?php
/** @var list<array<string, mixed>> $users */
/** @var array<string, mixed>|null $selectedUser */
/** @var int $year */
/** @var list<array<string, mixed>> $entries */
/** @var float $balance */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Annual leave ledger</p>
        <h1>연차 관리</h1>
        <p>입사일 기준 법정 발생분과 이월·수동 조정을 원장으로 관리합니다.</p>
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
        <span>입사일</span>
        <strong><?= htmlspecialchars((string) ($selectedUser['hire_date'] ?? '미입력'), ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <div class="summary-card">
        <span><?= $year ?>년 원장 잔액</span>
        <strong><?= number_format($balance, 2) ?>일</strong>
    </div>
</section>

<section class="panel">
    <h2>발생분 동기화</h2>
    <p>현재 날짜까지의 입사일 기준 발생분을 원장에 추가합니다. 이미 생성된 발생분은 중복 추가되지 않습니다.</p>
    <form method="post" action="/admin/annual-leave/sync">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
        <button class="button primary" type="submit" <?= empty($selectedUser['hire_date']) ? 'disabled' : '' ?>>발생분 동기화</button>
    </form>
</section>

<section class="panel">
    <h2>이월 / 수동 조정</h2>
    <form class="form-grid" method="post" action="/admin/annual-leave/adjust">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
        <input type="hidden" name="year" value="<?= $year ?>">
        <label>
            유형
            <select name="transaction_type">
                <option value="carryover">이월</option>
                <option value="adjustment">수동 조정</option>
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
