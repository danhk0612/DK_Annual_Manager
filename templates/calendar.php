<?php
/** @var array<string, mixed> $user */
/** @var string $month */
/** @var DateTimeImmutable $start */
/** @var DateTimeImmutable $end */
/** @var bool $isAdmin */
/** @var list<array<string, mixed>> $entries */
/** @var list<array<string, mixed>> $monthlyRequests */
/** @var list<array<string, mixed>> $holidays */
/** @var list<array<string, mixed>> $leaveTypes */
/** @var float $annualBalance */
/** @var list<string> $reasonCategories */
/** @var string $today */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $warning */
/** @var mixed $error */

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

$returnTo = '/calendar?month=' . rawurlencode($month);
$targetUsers = [];
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= $isAdmin ? 'Team calendar' : 'My calendar' ?></p>
        <h1><?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?> 휴가 달력</h1>
        <p><?= $isAdmin ? '전체 직원의 승인 대기·승인 일정을 한눈에 확인합니다.' : '내 일정과 휴가 상태를 한눈에 확인합니다.' ?></p>
    </div>
    <div class="page-actions">
        <button class="button primary" type="button" data-open-leave-dialog>휴가 신청</button>
        <a class="button" href="/leave/history">신청 내역</a>
    </div>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($warning) && $warning !== ''): ?>
    <div class="notice warning"><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="calendar-toolbar">
    <div class="calendar-nav">
        <a class="button" href="/calendar?month=<?= $previous ?>">이전 달</a>
        <a class="button" href="/calendar?month=<?= date('Y-m') ?>">이번 달</a>
        <a class="button" href="/calendar?month=<?= $next ?>">다음 달</a>
    </div>
    <?php if (!$isAdmin): ?>
        <div class="inline-stat">
            <span>잔여 연차</span>
            <strong><?= number_format($annualBalance, 1) ?>일</strong>
        </div>
    <?php endif; ?>
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
            $isPublicHoliday = false;
            foreach ($holidaysByDate[$date] ?? [] as $holiday) {
                if ((int) ($holiday['is_public_holiday'] ?? 0) === 1) {
                    $isPublicHoliday = true;
                    break;
                }
            }
            $isToday = $date === date('Y-m-d');
        ?>
            <div class="calendar-day <?= $isWeekend ? 'weekend' : '' ?> <?= $isToday ? 'today' : '' ?>">
                <div class="day-number">
                    <?php if (!$isWeekend && !$isPublicHoliday): ?>
                        <button
                            class="calendar-date-trigger"
                            type="button"
                            data-open-leave-dialog
                            data-leave-date="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>"
                            title="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?> 휴가 신청"
                        ><?= $day ?></button>
                    <?php else: ?>
                        <strong><?= $day ?></strong>
                    <?php endif; ?>

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
                        <span><?= htmlspecialchars((string) $entry['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?></span>
                        <small><?= htmlspecialchars($statusLabels[(string) $entry['status']] ?? (string) $entry['status'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<section class="panel calendar-list">
    <div class="section-head">
        <div>
            <p class="eyebrow">This month</p>
            <h2>이달 휴가</h2>
        </div>
        <span class="count-badge"><?= count($monthlyRequests) ?>건</span>
    </div>

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
                    <?php if ($isAdmin): ?><td><strong><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?></strong></td><?php endif; ?>
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

<dialog class="modal-dialog" data-leave-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">New leave</p>
                <h2>휴가 신청</h2>
                <p>달력 날짜를 누르면 시작일이 자동으로 선택됩니다.</p>
            </div>
            <button class="icon-button" type="button" data-close-dialog aria-label="닫기">×</button>
        </div>
        <?php require __DIR__ . '/_leave-form.php'; ?>
    </div>
</dialog>
