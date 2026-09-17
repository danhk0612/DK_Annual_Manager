<?php
/** @var int $year */
/** @var list<array<string, mixed>> $annualSummary */
/** @var list<array<string, mixed>> $monthlySummary */
$monthly = [];
$types = [];
foreach ($monthlySummary as $row) {
    $month = (int) $row['month_number'];
    $code = (string) $row['code'];
    $types[$code] = (string) $row['name'];
    $monthly[$month][$code] = (float) $row['amount'];
}
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Reports</p>
        <h1>휴가 집계</h1>
        <p>연차 원장과 승인된 휴가 일정을 기준으로 집계합니다.</p>
    </div>
    <a class="button" href="/admin">관리자 홈</a>
</section>

<section class="panel">
    <form class="form-grid compact" method="get" action="/admin/reports">
        <label>
            집계 연도
            <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>">
        </label>
        <div class="form-actions"><button class="button primary" type="submit">조회</button></div>
    </form>
</section>

<section class="panel">
    <h2><?= $year ?>년 직원별 연차 현황</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>직원</th><th>입사일</th><th>상태</th><th>발생</th><th>이월</th><th>조정</th><th>복원</th><th>사용</th><th>잔여</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($annualSummary as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($row['hire_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= number_format((float) $row['granted'], 1) ?></td>
                    <td><?= number_format((float) $row['carryover'], 1) ?></td>
                    <td><?= number_format((float) $row['adjustment'], 1) ?></td>
                    <td><?= number_format((float) $row['reversal'], 1) ?></td>
                    <td><?= number_format((float) $row['used'], 1) ?></td>
                    <td><strong><?= number_format((float) $row['balance'], 1) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2><?= $year ?>년 월별 승인 휴가</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>월</th>
                <?php foreach ($types as $code => $name): ?>
                    <th><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>)</th>
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
                    <td><strong><?= number_format((float) $total, 1) ?></strong></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <p class="muted">반차는 0.5일로 합산하며, 승인된 신청의 실제 휴가 날짜만 집계합니다.</p>
</section>
