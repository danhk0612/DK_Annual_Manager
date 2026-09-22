<?php
/** @var list<array<string, mixed>> $logs */
/** @var list<string> $actions */
/** @var string $query */
/** @var string $selectedAction */
$actionLabels = [
    'user.created' => '직원 추가',
    'user.updated' => '직원 수정',
    'profile.hire_date_updated' => '본인 입사일 수정',
    'leave.request_created' => '휴가 신청',
    'leave.request_cancelled' => '휴가 신청 취소',
    'leave.request_approved' => '휴가 승인',
    'leave.request_rejected' => '휴가 반려',
    'leave.request_approval_cancelled' => '휴가 승인 취소',
    'annual_leave.synced' => '연차 발생 동기화',
    'annual_leave.adjusted' => '연차 원장 조정',
    'annual_leave.total_override_set' => '총 연차 고정',
    'annual_leave.total_override_cleared' => '총 연차 고정 해제',
    'holiday.synced' => '공휴일 동기화',
    'holiday.created' => '휴일 추가',
    'holiday.deleted' => '휴일 삭제',
    'settings.appearance_updated' => '화면 설정 변경',
    'settings.workweek_updated' => '근무 요일 변경',
    'settings.telegram_credentials_updated' => 'Telegram 연결 변경',
    'settings.telegram_company_chat_updated' => '회사 그룹 변경',
    'settings.telegram_company_chat_selected' => '회사 그룹 선택',
    'settings.telegram_company_chat_tested' => '회사 그룹 테스트',
    'settings.holiday_api_updated' => '공휴일 API 변경',
    'settings.logo_updated' => '회사 로고 변경',
    'settings.logo_removed' => '회사 로고 복원',
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Audit Log</p>
        <h1><i class="bi bi-clock-history"></i><span>변경 이력</span></h1>
        <p>주요 데이터 변경 작업을 검색하고 전체 변경 이력을 검색하고 페이지 단위로 확인합니다.</p>
    </div>
    <a class="button" href="/admin"><i class="bi bi-speedometer2"></i><span>관리자 홈</span></a>
</section>

<section class="panel admin-list-panel">
    <form class="filter-toolbar" method="get" action="/admin/audit">
        <label class="filter-grow">
            <span>검색</span>
            <div class="input-with-icon">
                <i class="bi bi-search"></i>
                <input name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="작업자, 작업명, 대상, 상세 내용, IP">
            </div>
        </label>
        <label>
            <span>작업</span>
            <select name="action">
                <option value="">전체 작업</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedAction === $action ? 'selected' : '' ?>>
                        <?= htmlspecialchars($actionLabels[$action] ?? $action, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button class="button primary" type="submit"><i class="bi bi-search"></i><span>검색</span></button>
            <?php if ($query !== '' || $selectedAction !== ''): ?>
                <a class="button" href="/admin/audit"><i class="bi bi-arrow-counterclockwise"></i><span>초기화</span></a>
            <?php endif; ?>
        </div>
    </form>

    <div class="list-result-head">
        <span><strong><?= count($logs) ?>건</strong> 표시</span>
        <?php if ($query !== '' || $selectedAction !== ''): ?><span class="muted">검색 조건이 적용되었습니다.</span><?php endif; ?>
    </div>

    <div class="table-wrap">
        <table class="data-table" data-paginate data-page-size="20">
            <thead>
            <tr><th>일시</th><th>작업자</th><th>작업</th><th>대상</th><th>내용</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php if ($logs === []): ?>
                <tr><td colspan="6" class="muted">조건에 맞는 변경 이력이 없습니다.</td></tr>
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
                    <td><strong><?= htmlspecialchars($actionLabels[(string) $log['action']] ?? (string) $log['action'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars(trim((string) ($log['target_type'] ?? '') . ' #' . (string) ($log['target_id'] ?? ''), ' #'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="audit-details"><?= htmlspecialchars(implode(', ', $detailParts), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($log['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
