<?php
/** @var int $year */
/** @var string $query */
/** @var string $statusFilter */
/** @var string $departmentFilter */
/** @var string $leaveTypeFilter */
/** @var list<string> $departments */
/** @var array<string, string> $leaveTypes */
/** @var list<array<string, mixed>> $annualSummary */
/** @var list<array<string, mixed>> $monthlySummary */
/** @var array<int, float> $graphTotals */
/** @var list<array<string, mixed>> $exportUsers */
/** @var mixed $error */

$monthly = [];
$types = [];
foreach ($monthlySummary as $row) {
    $month = (int) $row['month_number'];
    $code = (string) $row['code'];
    $types[$code] = (string) $row['name'];
    $monthly[$month][$code] = (float) $row['amount'];
}

$maxGraph = max(1.0, ...array_values($graphTotals));
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Reports</p>
        <h1>휴가 집계</h1>
        <p>직원별 연차 현황과 월별 승인 휴가를 검색·필터하고 추이를 확인합니다.</p>
    </div>
    <a class="button" href="/admin">관리자 대시보드</a>
</section>

<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><i class="bi bi-x-circle"></i><span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>

<section class="panel filter-panel">
    <form class="filter-grid" method="get" action="/admin/reports">
        <label>
            연도
            <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>">
        </label>
        <label class="filter-search">
            검색
            <input type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="이름 · 부서 · 직책">
        </label>
        <label>
            상태
            <select name="status">
                <option value="">전체</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>활성</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>승인 대기</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>비활성</option>
            </select>
        </label>
        <label>
            부서
            <select name="department">
                <option value="">전체</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?>" <?= $departmentFilter === $department ? 'selected' : '' ?>>
                        <?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            휴가 종류
            <select name="leave_type">
                <option value="">전체</option>
                <?php foreach ($leaveTypes as $code => $name): ?>
                    <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $leaveTypeFilter === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button class="button primary" type="submit">적용</button>
            <a class="button" href="/admin/reports?year=<?= $year ?>">초기화</a>
        </div>
    </form>
</section>

<section class="panel export-panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Excel export</p>
            <h2><i class="bi bi-file-earmark-excel"></i><span>휴가 집계 엑셀 출력</span></h2>
            <p>특정 사용자 또는 전체 사용자의 휴가 신청 내역을 원하는 기간으로 내려받습니다.</p>
        </div>
    </div>

    <form class="export-form admin-export-form" method="get" action="/admin/reports/export" data-export-form>
        <label>
            대상
            <select name="user_id">
                <option value="all">전체 사용자</option>
                <?php foreach ($exportUsers as $exportUser): ?>
                    <option value="<?= (int) $exportUser['id'] ?>">
                        <?= htmlspecialchars((string) $exportUser['name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($exportUser['department'])): ?> · <?= htmlspecialchars((string) $exportUser['department'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            기간
            <select name="period" data-export-period>
                <option value="year">연간</option>
                <option value="month">월간</option>
                <option value="all">전체</option>
            </select>
        </label>
        <label data-export-year-wrap>
            연도
            <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>">
        </label>
        <label data-export-month-wrap>
            월
            <select name="month">
                <?php for ($exportMonth = 1; $exportMonth <= 12; $exportMonth++): ?>
                    <option value="<?= $exportMonth ?>" <?= $exportMonth === (int) date('n') ? 'selected' : '' ?>><?= $exportMonth ?>월</option>
                <?php endfor; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button class="button primary" type="submit"><i class="bi bi-download"></i><span>엑셀 다운로드</span></button>
        </div>
    </form>
    <p class="form-hint">내보내기 파일에는 신청 상태, 휴가 종류, 원래 신청 일수, 선택 기간 내 실제 일수, 승인 정보와 취소 정보가 포함됩니다.</p>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Trend</p>
            <h2><?= $year ?>년 월별 승인 휴가</h2>
        </div>
        <?php if ($leaveTypeFilter !== ''): ?>
            <span class="filter-chip"><?= htmlspecialchars($leaveTypes[$leaveTypeFilter] ?? $leaveTypeFilter, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>

    <div class="progress-chart" role="img" aria-label="<?= $year ?>년 월별 승인 휴가 사용량">
        <?php for ($month = 1; $month <= 12; $month++): ?>
            <?php $amount = (float) ($graphTotals[$month] ?? 0); ?>
            <div class="progress-chart-row">
                <span><?= $month ?>월</span>
                <progress max="<?= htmlspecialchars((string) $maxGraph, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $amount, ENT_QUOTES, 'UTF-8') ?>"></progress>
                <strong><?= number_format($amount, 1) ?>일</strong>
            </div>
        <?php endfor; ?>
    </div>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Employees</p>
            <h2><?= $year ?>년 직원별 연차 현황</h2>
        </div>
        <span class="count-badge"><?= count($annualSummary) ?>명</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>직원</th>
                <th>부서</th>
                <th>직책</th>
                <th>입사일</th>
                <th>상태</th>
                <th>발생</th>
                <th>이월</th>
                <th>조정</th>
                <th>복원</th>
                <th>사용</th>
                <th>잔여</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($annualSummary === []): ?>
                <tr><td colspan="11" class="muted">조건에 맞는 직원이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($annualSummary as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars((string) ($row['department'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($row['position'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($row['hire_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= number_format((float) $row['granted'], 1) ?></td>
                    <td><?= number_format((float) $row['carryover'], 1) ?></td>
                    <td><?= number_format((float) $row['adjustment'], 1) ?></td>
                    <td><?= number_format((float) $row['reversal'], 1) ?></td>
                    <td><?= number_format((float) $row['used'], 1) ?></td>
                    <td class="metric-cell"><strong><?= number_format((float) $row['balance'], 1) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Monthly details</p>
            <h2>월별 휴가 종류 집계</h2>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>월</th>
                <?php foreach ($types as $code => $name): ?>
                    <th><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></th>
                <?php endforeach; ?>
                <th>합계</th>
            </tr>
            </thead>
            <tbody>
            <?php for ($month = 1; $month <= 12; $month++): ?>
                <?php $total = array_sum($monthly[$month] ?? []); ?>
                <tr>
                    <td><?= $month ?>월</td>
                    <?php foreach ($types as $code => $name): ?>
                        <td><?= number_format((float) ($monthly[$month][$code] ?? 0), 1) ?></td>
                    <?php endforeach; ?>
                    <td class="metric-cell"><strong><?= number_format((float) $total, 1) ?></strong></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <p class="form-hint">반차는 0.5일로 합산하며 승인된 실제 휴가 날짜만 포함합니다.</p>
</section>
