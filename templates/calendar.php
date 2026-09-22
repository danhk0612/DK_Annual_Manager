<?php
/** @var string $month */
/** @var DateTimeImmutable $start */
/** @var DateTimeImmutable $end */
/** @var bool $isAdmin */
/** @var list<array<string, mixed>> $entries */
/** @var list<array<string, mixed>> $monthlyRequests */
/** @var list<array<string, mixed>> $holidays */

$entriesByDate = [];
foreach ($entries as $entry) {
    $entriesByDate[(string) $entry['leave_date']][] = $entry;
}
$holidaysByDate = [];
foreach ($holidays as $holiday) {
    $holidaysByDate[(string) $holiday['holiday_date']][] = $holiday;
}

$statusLabels = [
    'pending' => '승인 대기',
    'approved' => '승인',
];

$previous = $start->modify('-1 month')->format('Y-m');
$next = $start->modify('+1 month')->format('Y-m');
$leading = (int) $start->format('N') - 1;
$days = (int) $end->format('j');
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= $isAdmin ? 'Team calendar' : 'My calendar' ?></p>
        <h1><?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?> 휴가 달력</h1>
        <p><?= $isAdmin ? '관리자는 전체 직원의 승인 대기 및 승인 일정을 확인합니다.' : '내 승인 대기 및 승인 일정만 표시됩니다.' ?></p>
    </div>
    <a class="button primary" href="/leave">휴가 신청</a>
</section>

<div class="calendar-nav">
    <a class="button" href="/calendar?month=<?= $previous ?>">이전 달</a>
    <a class="button" href="/calendar?month=<?= date('Y-m') ?>">이번 달</a>
    <a class="button" href="/calendar?month=<?= $next ?>">다음 달</a>
</div>

<div class="calendar-scroll">
    <div class="calendar-grid weekday-head">
        <?php foreach (['월', '화', '수', '목', '금', '토', '일'] as $weekday): ?>
            <div><?= $weekday ?></div>
        <?php endforeach; ?>
    </div>
    <div class="calendar-grid">
        <?php for ($i = 0; $i < $leading; $i++): ?>
            <div class="calendar-day empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $days; $day++):
            $date = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $dateObject = new DateTimeImmutable($date);
            $isWeekend = (int) $dateObject->format('N') >= 6;
        ?>
            <div class="calendar-day <?= $isWeekend ? 'weekend' : '' ?>">
                <div class="day-number">
                    <strong><?= $day ?></strong>
                    <?php if (isset($holidaysByDate[$date])): ?>
                        <span class="holiday-name"><?= htmlspecialchars((string) $holidaysByDate[$date][0]['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <?php foreach ($entriesByDate[$date] ?? [] as $entry): ?>
                    <?php
                    $halfDayPeriod = (string) ($entry['half_day_period'] ?? '');
                    $halfDayLabel = $halfDayPeriod === 'am' ? ' 오전' : ($halfDayPeriod === 'pm' ? ' 오후' : '');
                    ?>
                    <div class="calendar-event <?= htmlspecialchars((string) $entry['status'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php if ($isAdmin): ?>
                            <strong><?= htmlspecialchars((string) $entry['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php endif; ?>
                        <span><?= htmlspecialchars((string) $entry['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?> · <?= htmlspecialchars($statusLabels[(string) $entry['status']] ?? (string) $entry['status'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<section class="panel calendar-list">
    <h2>이달 휴가</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <?php if ($isAdmin): ?><th>직원</th><?php endif; ?>
                <th>종류</th>
                <th>기간</th>
                <th>일수</th>
                <th>상태</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($monthlyRequests === []): ?>
                <tr><td colspan="<?= $isAdmin ? 5 : 4 ?>" class="muted">이달 휴가가 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($monthlyRequests as $item): ?>
                <?php
                $halfDayPeriod = (string) ($item['half_day_period'] ?? '');
                $halfDayLabel = $halfDayPeriod === 'am' ? ' · 오전' : ($halfDayPeriod === 'pm' ? ' · 오후' : '');
                ?>
                <tr>
                    <?php if ($isAdmin): ?><td><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?></td><?php endif; ?>
                    <td><?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?></td>
                    <td><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars((string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= number_format((float) $item['requested_amount'], 1) ?></td>
                    <td><span class="badge <?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabels[(string) $item['status']] ?? (string) $item['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
