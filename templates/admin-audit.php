<?php
/** @var list<array<string, mixed>> $logs */
$actionLabels = [
    'user.created' => '직원 추가',
    'user.updated' => '직원 수정',
    'profile.hire_date_updated' => '본인 입사일 수정',
    'leave.request_created' => '휴가 신청',
    'leave.request_cancelled' => '휴가 신청 취소',
    'leave.request_approved' => '휴가 승인',
    'leave.request_rejected' => '휴가 반려',
    'annual_leave.synced' => '연차 발생 동기화',
    'annual_leave.adjusted' => '연차 원장 조정',
    'holiday.synced' => '공휴일 동기화',
    'holiday.created' => '휴일 추가',
    'holiday.deleted' => '휴일 삭제',
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Audit Log</p>
        <h1>변경 이력</h1>
        <p>주요 데이터 변경 작업의 최근 200건을 표시합니다.</p>
    </div>
    <a class="button" href="/admin">관리자 홈</a>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>일시</th><th>작업자</th><th>작업</th><th>대상</th><th>내용</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php if ($logs === []): ?>
                <tr><td colspan="6" class="muted">기록된 변경 이력이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <?php
                $details = [];
                if (is_string($log['details_json'] ?? null) && $log['details_json'] !== '') {
                    $decoded = json_decode((string) $log['details_json'], true);
                    $details = is_array($decoded) ? $decoded : [];
                }
                $detailParts = [];
                foreach ($details as $key => $value) {
                    if (is_scalar($value) || $value === null) {
                        $detailParts[] = (string) $key . '=' . ($value === null ? '-' : (string) $value);
                    }
                }
                ?>
                <tr>
                    <td><?= htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($log['actor_name'] ?? '시스템'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($actionLabels[(string) $log['action']] ?? (string) $log['action'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(trim((string) ($log['target_type'] ?? '') . ' #' . (string) ($log['target_id'] ?? ''), ' #'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="audit-details"><?= htmlspecialchars(implode(', ', $detailParts), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($log['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
