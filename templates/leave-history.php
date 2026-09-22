<?php
/** @var list<array<string, mixed>> $requests */
/** @var bool $isAdmin */
/** @var int $currentUserId */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */

$statusLabels = [
    'pending' => '승인 대기',
    'approved' => '승인',
    'rejected' => '반려',
    'cancelled' => '취소',
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Leave history</p>
        <h1><?= $isAdmin ? '전체 휴가 신청 내역' : '휴가 신청 내역' ?></h1>
        <p><?= $isAdmin ? '전 직원의 휴가 신청 상태와 처리 결과를 확인합니다.' : '내 휴가 신청 상태와 처리 결과를 확인합니다.' ?></p>
    </div>
    <a class="button primary" href="<?= $isAdmin ? '/admin/requests' : '/calendar?request=1' ?>"><?= $isAdmin ? '승인 관리' : '휴가 신청' ?></a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>신청일</th>
                <?php if ($isAdmin): ?><th>직원</th><?php endif; ?>
                <th>종류</th>
                <th>기간</th>
                <th>일수</th>
                <th>사유</th>
                <th>상태</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($requests === []): ?>
                <tr><td colspan="<?= $isAdmin ? 8 : 7 ?>" class="muted">신청 내역이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($requests as $item): ?>
                <?php
                $halfDayPeriod = (string) ($item['half_day_period'] ?? '');
                $halfDayLabel = $halfDayPeriod === 'am' ? ' · 오전' : ($halfDayPeriod === 'pm' ? ' · 오후' : '');
                ?>
                <tr>
                    <td><?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <strong><?= htmlspecialchars((string) ($item['user_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($item['department'])): ?><small class="muted"> · <?= htmlspecialchars((string) $item['department'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?></td>
                    <td><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars((string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= number_format((float) $item['requested_amount'], 1) ?></td>
                    <td><?= htmlspecialchars((string) ($item['reason'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabels[(string) $item['status']] ?? (string) $item['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <?php if ($item['status'] === 'pending' && (!$isAdmin || (int) $item['user_id'] === $currentUserId)): ?>
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
