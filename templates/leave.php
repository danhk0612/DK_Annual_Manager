<?php
/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $leaveTypes */
/** @var list<array<string, mixed>> $requests */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Leave request</p>
        <h1>휴가 신청</h1>
        <p>주말과 등록된 공휴일은 신청 일수에서 자동으로 제외됩니다.</p>
    </div>
    <a class="button" href="/calendar">휴가 달력</a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="panel">
    <h2>새 신청</h2>
    <form class="form-grid" method="post" action="/leave/create">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label>
            휴가 종류
            <select name="leave_type_id" required>
                <?php foreach ($leaveTypes as $type): ?>
                    <option value="<?= (int) $type['id'] ?>">
                        <?= htmlspecialchars((string) $type['name'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= number_format((float) $type['default_amount'], 1) ?>일/일)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div></div>
        <label>
            시작일
            <input type="date" name="start_date" required>
        </label>
        <label>
            종료일
            <input type="date" name="end_date" required>
        </label>
        <label class="span-2">
            사유
            <input name="reason" maxlength="1000">
        </label>
        <div class="form-actions">
            <button class="button primary" type="submit">신청</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>내 신청 내역</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>신청일</th>
                <th>종류</th>
                <th>기간</th>
                <th>일수</th>
                <th>상태</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($requests === []): ?>
                <tr><td colspan="6" class="muted">신청 내역이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($requests as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars((string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= number_format((float) $item['requested_amount'], 1) ?></td>
                    <td><span class="badge <?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <?php if ($item['status'] === 'pending'): ?>
                            <form method="post" action="/leave/cancel">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $item['id'] ?>">
                                <button class="button small" type="submit">취소</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
