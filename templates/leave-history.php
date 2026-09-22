<?php
/** @var list<array<string, mixed>> $requests */
/** @var bool $isAdmin */
/** @var int $currentUserId */
/** @var string $query */
/** @var string $selectedStatus */
/** @var int|null $selectedYear */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */

$statusLabels = [
    'pending' => '승인 대기',
    'approved' => '승인',
    'rejected' => '반려',
    'cancelled' => '취소',
];

$returnQuery = [];
if ($query !== '') {
    $returnQuery['q'] = $query;
}
if ($selectedStatus !== '') {
    $returnQuery['status'] = $selectedStatus;
}
if ($selectedYear !== null) {
    $returnQuery['year'] = $selectedYear;
}
$historyReturnTo = '/leave/history' . ($returnQuery !== [] ? '?' . http_build_query($returnQuery) : '');
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Leave history</p>
        <h1><i class="bi bi-list-check"></i><span><?= $isAdmin ? '전체 휴가 신청 내역' : '휴가 신청 내역' ?></span></h1>
        <p><?= $isAdmin ? '전 직원의 휴가 신청 상태와 처리 결과를 검색해 확인합니다.' : '내 휴가 신청 상태와 처리 결과를 검색해 확인합니다.' ?></p>
    </div>
    <a class="button primary" href="<?= $isAdmin ? '/admin/requests' : '/calendar?request=1' ?>"><i class="bi <?= $isAdmin ? 'bi-check2-square' : 'bi-plus-circle' ?>"></i><span><?= $isAdmin ? '승인 관리' : '휴가 신청' ?></span></a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><i class="bi bi-check-circle"></i><span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><i class="bi bi-x-circle"></i><span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>

<section class="panel admin-list-panel">
    <form class="filter-toolbar leave-history-filter" method="get" action="/leave/history">
        <label class="filter-grow">
            <span>검색</span>
            <div class="input-with-icon">
                <i class="bi bi-search"></i>
                <input name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= $isAdmin ? '직원, 부서, 휴가 종류, 사유, 관리자 메모' : '휴가 종류, 사유, 관리자 메모' ?>">
            </div>
        </label>
        <label>
            <span>상태</span>
            <select name="status">
                <option value="">전체 상태</option>
                <?php foreach ($statusLabels as $status => $label): ?>
                    <option value="<?= $status ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>연도</span>
            <input type="number" name="year" min="2000" max="2100" placeholder="전체" value="<?= $selectedYear !== null ? $selectedYear : '' ?>">
        </label>
        <div class="filter-actions">
            <button class="button primary" type="submit"><i class="bi bi-search"></i><span>검색</span></button>
            <?php if ($query !== '' || $selectedStatus !== '' || $selectedYear !== null): ?>
                <a class="button" href="/leave/history"><i class="bi bi-arrow-counterclockwise"></i><span>초기화</span></a>
            <?php endif; ?>
        </div>
    </form>

    <div class="list-result-head">
        <span><strong><?= count($requests) ?>건</strong> 검색 결과</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>신청일</th>
                <?php if ($isAdmin): ?><th>직원</th><?php endif; ?>
                <th>종류</th>
                <th>기간</th>
                <th>일수</th>
                <th>사유</th>
                <th>상태</th>
                <th class="table-action-column">기능</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($requests === []): ?>
                <tr><td colspan="<?= $isAdmin ? 8 : 7 ?>" class="muted">조건에 맞는 신청 내역이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($requests as $item): ?>
                <?php
                $halfDayPeriod = (string) ($item['half_day_period'] ?? '');
                $halfDayLabel = $halfDayPeriod === 'am' ? ' · 오전' : ($halfDayPeriod === 'pm' ? ' · 오후' : '');
                $isOwnRequest = (int) $item['user_id'] === $currentUserId;
                $cancellationSource = (string) ($item['cancellation_source'] ?? '');
                $cancellationSourceLabel = $cancellationSource === 'user'
                    ? '사용자 취소'
                    : ($cancellationSource === 'admin' ? '관리자 취소' : '');
                ?>
                <tr id="request-<?= (int) $item['id'] ?>">
                    <td><?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <strong><?= htmlspecialchars((string) ($item['user_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($item['department'])): ?><span class="muted"> · <?= htmlspecialchars((string) $item['department'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?></td>
                    <td><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars((string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= number_format((float) $item['requested_amount'], 1) ?></td>
                    <td class="table-text-clip"><?= htmlspecialchars((string) ($item['reason'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabels[(string) $item['status']] ?? (string) $item['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($item['status'] === 'cancelled' && $cancellationSourceLabel !== ''): ?>
                            <span class="muted-line"><?= htmlspecialchars($cancellationSourceLabel, ENT_QUOTES, 'UTF-8') ?><?= !empty($item['cancelled_at']) ? ' · ' . htmlspecialchars((string) $item['cancelled_at'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                            <?php if (!empty($item['cancellation_note'])): ?>
                                <span class="muted-line">사유: <?= htmlspecialchars((string) $item['cancellation_note'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item['status'] === 'pending' && $isOwnRequest): ?>
                            <form method="post" action="/leave/cancel" data-confirm-message="승인 전 신청을 취소하면 신청 내역에서 완전히 삭제됩니다. 계속하시겠습니까?">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($historyReturnTo, ENT_QUOTES, 'UTF-8') ?>">
                                <button class="button small danger-ghost" type="submit"><i class="bi bi-trash3"></i><span>신청 취소</span></button>
                            </form>
                        <?php elseif ($item['status'] === 'approved' && $isOwnRequest): ?>
                            <form class="cancel-approved-form" method="post" action="/leave/cancel-approved" data-confirm-message="승인된 휴가를 취소하시겠습니까? 차감된 연차는 자동 복원되고 관리자에게 알림이 전송됩니다.">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($historyReturnTo, ENT_QUOTES, 'UTF-8') ?>">
                                <input name="cancellation_note" maxlength="1000" placeholder="취소 사유 (선택)">
                                <button class="button small danger-ghost" type="submit"><i class="bi bi-calendar-x"></i><span>승인 휴가 취소</span></button>
                            </form>
                        <?php elseif ($isAdmin && $item['status'] === 'pending'): ?>
                            <a class="button small" href="/admin/requests"><i class="bi bi-check2-square"></i><span>처리</span></a>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
