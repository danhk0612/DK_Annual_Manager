<?php
/** @var int $year */
/** @var array<string, int|float> $metrics */
/** @var list<array<string, mixed>> $pendingPreview */
/** @var list<array<string, mixed>> $currentLeaves */
/** @var list<array<string, mixed>> $upcomingLeaves */
/** @var list<array{month_number:int, amount:float}> $monthlyTotals */
/** @var list<array<string, mixed>> $recentAudit */

$monthlyMap = array_fill(1, 12, 0.0);
foreach ($monthlyTotals as $row) {
    $monthlyMap[(int) $row['month_number']] = (float) $row['amount'];
}
$maxMonthly = max(1.0, ...array_values($monthlyMap));
?>
<section class="page-head dashboard-head">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>관리자 대시보드</h1>
        <p>승인 대기, 현재 휴가 중인 직원, 예정 휴가, 연차 현황과 운영 설정을 한 화면에서 확인합니다.</p>
    </div>
    <div class="page-actions">
        <a class="button primary" href="/admin/requests"><i class="bi bi-check2-square"></i> 승인 처리</a>
        <a class="button" href="/admin/reports"><i class="bi bi-bar-chart-line"></i> 휴가 집계</a>
        <a class="button" href="/admin/settings"><i class="bi bi-gear"></i> 환경 설정</a>
    </div>
</section>

<section class="summary-grid admin-summary">
    <a class="summary-card emphasis summary-link" href="/admin/requests">
        <span><i class="bi bi-hourglass-split"></i> 승인 대기</span>
        <strong><?= (int) $metrics['pending_requests'] ?>건</strong>
        <small>처리가 필요한 신청</small>
    </a>
    <div class="summary-card">
        <span><i class="bi bi-calendar2-check"></i> 오늘 휴가</span>
        <strong><?= (int) ($metrics['on_leave_today'] ?? 0) ?>건</strong>
        <small>승인된 일정 기준</small>
    </div>
    <div class="summary-card">
        <span><i class="bi bi-people"></i> 활성 직원</span>
        <strong><?= (int) $metrics['active_users'] ?>명</strong>
        <small>현재 활성 계정</small>
    </div>
    <div class="summary-card">
        <span><i class="bi bi-arrow-up-right-circle"></i> <?= $year ?>년 사용</span>
        <strong><?= number_format((float) $metrics['used'], 1) ?>일</strong>
        <small>승인된 연차·반차</small>
    </div>
    <div class="summary-card">
        <span><i class="bi bi-wallet2"></i> <?= $year ?>년 잔여</span>
        <strong><?= number_format((float) $metrics['balance'], 1) ?>일</strong>
        <small>전체 직원 합계</small>
    </div>
    <div class="summary-card">
        <span><i class="bi bi-calendar-month"></i> 이번 달 휴가</span>
        <strong><?= number_format((float) $metrics['approved_this_month'], 1) ?>일</strong>
        <small>모든 승인 휴가</small>
    </div>
</section>

<section class="dashboard-overview-grid">
    <section class="panel dashboard-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Pending</p>
                <h2><i class="bi bi-inbox"></i> 승인 대기</h2>
            </div>
            <a class="button small" href="/admin/requests">전체 보기</a>
        </div>

        <div class="compact-list">
            <?php if ($pendingPreview === []): ?>
                <div class="empty-state"><i class="bi bi-check2-circle"></i><span>승인 대기 중인 신청이 없습니다.</span></div>
            <?php endif; ?>
            <?php foreach ($pendingPreview as $item): ?>
                <?php
                $half = (string) ($item['half_day_period'] ?? '');
                $halfLabel = $half === 'am' ? ' · 오전' : ($half === 'pm' ? ' · 오후' : '');
                ?>
                <a class="compact-list-item" href="/admin/requests">
                    <div>
                        <strong><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfLabel ?></span>
                    </div>
                    <div class="list-meta">
                        <span><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= number_format((float) $item['requested_amount'], 1) ?>일</strong>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel dashboard-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Today</p>
                <h2><i class="bi bi-person-walking"></i> 현재 휴가 중</h2>
            </div>
            <a class="button small" href="/calendar">달력 보기</a>
        </div>

        <div class="compact-list">
            <?php if ($currentLeaves === []): ?>
                <div class="empty-state"><i class="bi bi-person-check"></i><span>오늘 휴가 중인 직원이 없습니다.</span></div>
            <?php endif; ?>
            <?php foreach ($currentLeaves as $item): ?>
                <?php
                $half = (string) ($item['half_day_period'] ?? '');
                $halfLabel = $half === 'am' ? ' · 오전' : ($half === 'pm' ? ' · 오후' : '');
                $startDate = (string) ($item['start_date'] ?? '');
                $endDate = (string) ($item['end_date'] ?? '');
                $dateLabel = $startDate === $endDate ? $startDate : $startDate . ' ~ ' . $endDate;
                ?>
                <div class="compact-list-item">
                    <div>
                        <strong><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>
                            <?= htmlspecialchars((string) ($item['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?= !empty($item['department']) ? ' · ' : '' ?>
                            <?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfLabel ?>
                        </span>
                    </div>
                    <div class="list-meta">
                        <span><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <strong>오늘 <?= number_format((float) ($item['today_amount'] ?? 0), 1) ?>일</strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel dashboard-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Next 14 days</p>
                <h2><i class="bi bi-calendar-week"></i> 다가오는 휴가</h2>
            </div>
            <a class="button small" href="/calendar">달력 보기</a>
        </div>

        <div class="compact-list">
            <?php if ($upcomingLeaves === []): ?>
                <div class="empty-state"><i class="bi bi-calendar2-x"></i><span>내일부터 14일 이내 예정된 승인 휴가가 없습니다.</span></div>
            <?php endif; ?>
            <?php foreach ($upcomingLeaves as $item): ?>
                <?php
                $half = (string) ($item['half_day_period'] ?? '');
                $halfLabel = $half === 'am' ? ' · 오전' : ($half === 'pm' ? ' · 오후' : '');
                ?>
                <div class="compact-list-item">
                    <div>
                        <strong><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>
                            <?= htmlspecialchars((string) ($item['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?= !empty($item['department']) ? ' · ' : '' ?>
                            <?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfLabel ?>
                        </span>
                    </div>
                    <div class="list-meta">
                        <span><?= htmlspecialchars((string) ($item['first_leave_date'] ?? $item['start_date']), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= number_format((float) $item['requested_amount'], 1) ?>일</strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel dashboard-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Quick actions</p>
                <h2><i class="bi bi-grid-1x2"></i> 관리 메뉴</h2>
            </div>
        </div>
        <div class="quick-grid">
            <a class="quick-action" href="/admin/requests"><i class="bi bi-check2-square"></i><strong>휴가 승인</strong><span>승인·취소·대리 신청</span></a>
            <a class="quick-action" href="/admin/users"><i class="bi bi-people"></i><strong>직원 관리</strong><span>직원·연차·권한</span></a>
            <a class="quick-action" href="/admin/holidays"><i class="bi bi-calendar2-event"></i><strong>공휴일</strong><span>공휴일·회사 휴무</span></a>
            <a class="quick-action" href="/admin/reports"><i class="bi bi-bar-chart-line"></i><strong>휴가 집계</strong><span>검색·필터·그래프</span></a>
            <a class="quick-action" href="/admin/settings"><i class="bi bi-gear"></i><strong>환경 설정</strong><span>브랜딩·Telegram·테마</span></a>
            <a class="quick-action" href="/admin/audit"><i class="bi bi-clock-history"></i><strong>변경 이력</strong><span>감사 로그</span></a>
        </div>
    </section>
</section>

<div class="dashboard-lower-grid">
    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Trend</p>
                <h2><i class="bi bi-graph-up"></i> <?= $year ?>년 월별 승인 휴가</h2>
            </div>
            <a class="button small" href="/admin/reports">상세 집계</a>
        </div>
        <div class="progress-chart compact-chart">
            <?php for ($month = 1; $month <= 12; $month++): ?>
                <?php $amount = (float) $monthlyMap[$month]; ?>
                <div class="progress-chart-row">
                    <span><?= $month ?>월</span>
                    <progress max="<?= htmlspecialchars((string) $maxMonthly, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $amount, ENT_QUOTES, 'UTF-8') ?>"></progress>
                    <strong><?= number_format($amount, 1) ?>일</strong>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Recent changes</p>
                <h2><i class="bi bi-clock-history"></i> 최근 변경</h2>
            </div>
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
</div>
