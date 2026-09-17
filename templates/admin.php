<?php
/** @var int $year */
/** @var array<string, int|float> $metrics */
/** @var list<array<string, mixed>> $recentAudit */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>관리자</h1>
        <p><?= $year ?>년 휴가 현황과 관리 기능을 확인합니다.</p>
    </div>
</section>

<section class="summary-grid admin-summary">
    <div class="summary-card"><span>활성 직원</span><strong><?= (int) $metrics['active_users'] ?>명</strong></div>
    <div class="summary-card"><span>승인 대기</span><strong><?= (int) $metrics['pending_requests'] ?>건</strong></div>
    <div class="summary-card"><span><?= $year ?>년 발생</span><strong><?= number_format((float) $metrics['granted'], 1) ?>일</strong></div>
    <div class="summary-card"><span><?= $year ?>년 사용</span><strong><?= number_format((float) $metrics['used'], 1) ?>일</strong></div>
    <div class="summary-card"><span><?= $year ?>년 잔여</span><strong><?= number_format((float) $metrics['balance'], 1) ?>일</strong></div>
    <div class="summary-card"><span>이번 달 승인 휴가</span><strong><?= number_format((float) $metrics['approved_this_month'], 1) ?>일</strong></div>
</section>

<section class="card-grid">
    <a class="menu-card" href="/admin/requests"><strong>휴가 승인</strong><span>승인 대기 신청을 확인하고 승인 또는 반려합니다.</span></a>
    <a class="menu-card" href="/admin/users"><strong>직원 관리</strong><span>직원, 입사일, Telegram 연결, 권한과 상태를 관리합니다.</span></a>
    <a class="menu-card" href="/admin/annual-leave"><strong>연차 관리</strong><span>법정 발생분을 동기화하고 이월·수동 조정 원장을 관리합니다.</span></a>
    <a class="menu-card" href="/admin/holidays"><strong>공휴일 관리</strong><span>한국 공휴일을 갱신하고 회사 휴무일과 수동 휴일을 관리합니다.</span></a>
    <a class="menu-card" href="/admin/reports"><strong>휴가 집계</strong><span>직원별 연차 잔여와 월별 휴가 사용량을 조회합니다.</span></a>
    <a class="menu-card" href="/admin/audit"><strong>변경 이력</strong><span>직원·휴가·연차·공휴일의 주요 변경 기록을 확인합니다.</span></a>
</section>

<section class="panel">
    <div class="section-head">
        <h2>최근 변경</h2>
        <a class="button small" href="/admin/audit">전체 보기</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>일시</th><th>작업자</th><th>작업</th><th>대상</th></tr></thead>
            <tbody>
            <?php if ($recentAudit === []): ?><tr><td colspan="4" class="muted">기록된 변경 이력이 없습니다.</td></tr><?php endif; ?>
            <?php foreach ($recentAudit as $log): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($log['actor_name'] ?? '시스템'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $log['action'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(trim((string) ($log['target_type'] ?? '') . ' #' . (string) ($log['target_id'] ?? ''), ' #'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
