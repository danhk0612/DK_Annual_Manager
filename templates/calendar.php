<?php
/** @var string $month */
/** @var DateTimeImmutable $start */
/** @var DateTimeImmutable $end */
/** @var list<array<string, mixed>> $entries */
/** @var list<array<string, mixed>> $holidays */

$entriesByDate = [];
foreach ($entries as $entry) {
    $entriesByDate[(string) $entry['leave_date']][] = $entry;
}
$holidaysByDate = [];
foreach ($holidays as $holiday) {
    $holidaysByDate[(string) $holiday['holiday_date']][] = $holiday;
}

$previous = $start->modify('-1 month')->format('Y-m');
$next = $start->modify('+1 month')->format('Y-m');
$leading = (int) $start->format('N') - 1;
$days = (int) $end->format('j');
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Team calendar</p>
        <h1><?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?> 휴가 달력</h1>
        <p>승인 대기 및 승인된 휴가 일정을 함께 표시합니다.</p>
    </div>
    <a class="button primary" href="/leave">휴가 신청</a>
</section>

<div class="calendar-nav">
    <a class="button" href="/calendar?month=<?= $previous ?>">이전 달</a>
    <a class="button" href="/calendar?month=<?= date('Y-m') ?>">이번 달</a>
    <a class="button" href="/calendar?month=<?= $next ?>">다음 달</a>
</div>

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
                <div class="calendar-event <?= htmlspecialchars((string) $entry['status'], ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= htmlspecialchars((string) $entry['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars((string) $entry['leave_type_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $entry['status'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endfor; ?>
</div>
