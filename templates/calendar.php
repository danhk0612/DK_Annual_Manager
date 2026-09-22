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
/** @var list<int> $workingWeekdays */
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
    'rejected' => '반려',
    'cancelled' => '취소',
];

$statusIcons = [
    'pending' => 'bi-hourglass-split',
    'approved' => 'bi-check-circle-fill',
    'rejected' => 'bi-x-circle-fill',
    'cancelled' => 'bi-dash-circle-fill',
];

$previous = $start->modify('-1 month')->format('Y-m');
$next = $start->modify('+1 month')->format('Y-m');
$leading = (int) $start->format('N') - 1;
$days = (int) $end->format('j');

$approvedCount = 0;
$pendingCount = 0;
foreach ($monthlyRequests as $item) {
    if (($item['status'] ?? null) === 'approved') {
        $approvedCount++;
    } elseif (($item['status'] ?? null) === 'pending') {
        $pendingCount++;
    }
}

$returnTo = '/calendar?month=' . rawurlencode($month);
$targetUsers = [];
$weekdayLabels = [1 => '월', 2 => '화', 3 => '수', 4 => '목', 5 => '금', 6 => '토', 7 => '일'];
$calendarHeading = sprintf('%d년 %d월 휴가 현황', (int) $start->format('Y'), (int) $start->format('n'));
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= $isAdmin ? 'Team calendar' : 'My calendar' ?></p>
        <h1><?= htmlspecialchars($calendarHeading, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= $isAdmin ? '전 직원의 휴가 일정과 신청 상태를 한 화면에서 확인합니다.' : '내 휴가 일정과 신청 상태를 한 화면에서 확인합니다.' ?></p>
    </div>
    <div class="page-actions">
        <button class="button primary" type="button" data-open-leave-dialog><i class="bi bi-plus-circle"></i> 휴가 신청</button>
        <a class="button" href="/leave/history"><i class="bi bi-list-check"></i> <?= $isAdmin ? '전체 신청 내역' : '신청 내역' ?></a>
    </div>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($warning) && $warning !== ''): ?>
    <div class="notice warning"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><i class="bi bi-x-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="calendar-toolbar">
    <div class="calendar-nav">
        <a class="button" href="/calendar?month=<?= $previous ?>"><i class="bi bi-chevron-left"></i> 이전 달</a>
        <a class="button primary" href="/calendar?month=<?= date('Y-m') ?>"><i class="bi bi-calendar-event"></i> 이번 달</a>
        <a class="button" href="/calendar?month=<?= $next ?>">다음 달 <i class="bi bi-chevron-right"></i></a>
    </div>
    <div class="calendar-toolbar-stats">
        <?php if (!$isAdmin): ?>
            <div class="inline-stat"><span>잔여 연차</span><strong><?= number_format($annualBalance, 1) ?>일</strong></div>
        <?php endif; ?>
        <div class="inline-stat neutral"><span>승인</span><strong><?= $approvedCount ?>건</strong></div>
        <div class="inline-stat neutral"><span>대기</span><strong><?= $pendingCount ?>건</strong></div>
    </div>
</div>

<div class="calendar-layout">
    <section class="calendar-main">
        <div class="calendar-scroll">
            <div class="calendar-grid weekday-head">
                <?php foreach ($weekdayLabels as $weekdayNumber => $weekday): ?>
                    <div class="<?= in_array($weekdayNumber, $workingWeekdays, true) ? '' : 'non-working-weekday' ?>"><?= $weekday ?></div>
                <?php endforeach; ?>
            </div>

            <div class="calendar-grid">
                <?php for ($i = 0; $i < $leading; $i++): ?>
                    <div class="calendar-day empty"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $days; $day++):
                    $date = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                    $dateObject = new DateTimeImmutable($date);
                    $weekdayNumber = (int) $dateObject->format('N');
                    $isWorkingWeekday = in_array($weekdayNumber, $workingWeekdays, true);
                    $isNonWorkingDay = !$isWorkingWeekday;
                    $dayHolidays = $holidaysByDate[$date] ?? [];
                    $isPublicHoliday = false;
                    $isCompanyHoliday = false;
                    foreach ($dayHolidays as $holiday) {
                        if ((int) ($holiday['is_public_holiday'] ?? 0) === 1) {
                            $isPublicHoliday = true;
                        }
                        if (($holiday['source'] ?? null) === 'company') {
                            $isCompanyHoliday = true;
                        }
                    }
                    $isToday = $date === date('Y-m-d');
                    $dayClasses = ['calendar-day'];
                    if ($isNonWorkingDay) {
                        $dayClasses[] = 'non-working-day';
                    }
                    if ($isPublicHoliday) {
                        $dayClasses[] = 'public-holiday';
                    }
                    if ($isCompanyHoliday) {
                        $dayClasses[] = 'company-holiday';
                    }
                    if ($isToday) {
                        $dayClasses[] = 'today';
                    }
                ?>
                    <div class="<?= implode(' ', $dayClasses) ?>">
                        <div class="day-number">
                            <?php if ($isWorkingWeekday && !$isPublicHoliday && !$isCompanyHoliday): ?>
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

                            <?php if ($dayHolidays !== []): ?>
                                <span class="holiday-name"><?= htmlspecialchars((string) $dayHolidays[0]['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>

                        <?php foreach ($entriesByDate[$date] ?? [] as $entry): ?>
                            <?php
                            $halfDayPeriod = (string) ($entry['half_day_period'] ?? '');
                            $halfDayLabel = $halfDayPeriod === 'am' ? ' 오전' : ($halfDayPeriod === 'pm' ? ' 오후' : '');
                            $status = (string) $entry['status'];
                            ?>
                            <button
                                type="button"
                                class="calendar-event <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                data-open-request-detail
                                data-request-id="<?= (int) $entry['request_id'] ?>"
                                data-request-user="<?= htmlspecialchars((string) $entry['user_name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-request-department="<?= htmlspecialchars((string) ($entry['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-request-type="<?= htmlspecialchars((string) $entry['leave_type_name'] . $halfDayLabel, ENT_QUOTES, 'UTF-8') ?>"
                                data-request-period="<?= htmlspecialchars((string) $entry['start_date'] . ' ~ ' . (string) $entry['end_date'], ENT_QUOTES, 'UTF-8') ?>"
                                data-request-amount="<?= htmlspecialchars(number_format((float) $entry['requested_amount'], 1) . '일', ENT_QUOTES, 'UTF-8') ?>"
                                data-request-status="<?= htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8') ?>"
                                data-request-reason="<?= htmlspecialchars((string) ($entry['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-request-review-note="<?= htmlspecialchars((string) ($entry['review_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-request-created="<?= htmlspecialchars((string) ($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?php if ($isAdmin): ?>
                                    <strong><?= htmlspecialchars((string) $entry['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php endif; ?>
                                <span><?= htmlspecialchars((string) $entry['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?></span>
                                <small><?= htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8') ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="calendar-legend">
            <span><i class="legend-dot non-working"></i> 비근무일</span>
            <span><i class="legend-dot holiday"></i> 공휴일</span>
            <span><i class="legend-dot company"></i> 회사 휴무</span>
            <span><i class="legend-dot approved"></i> 승인</span>
            <span><i class="legend-dot pending"></i> 승인 대기</span>
        </div>
    </section>

    <aside class="calendar-side">
        <section class="panel calendar-list">
            <div class="section-head">
                <div>
                    <p class="eyebrow">This month</p>
                    <h2><?= $isAdmin ? '전체 신청 내역' : '내 신청 내역' ?></h2>
                </div>
                <span class="count-badge"><?= count($monthlyRequests) ?>건</span>
            </div>

            <?php if ($monthlyRequests === []): ?>
                <div class="empty-state"><i class="bi bi-calendar2-check"></i><span>이달 신청 내역이 없습니다.</span></div>
            <?php else: ?>
                <div class="month-request-list">
                    <?php foreach ($monthlyRequests as $item): ?>
                        <?php
                        $halfDayPeriod = (string) ($item['half_day_period'] ?? '');
                        $halfDayLabel = $halfDayPeriod === 'am' ? ' · 오전' : ($halfDayPeriod === 'pm' ? ' · 오후' : '');
                        $status = (string) $item['status'];
                        ?>
                        <button
                            type="button"
                            class="month-request-item"
                            data-open-request-detail
                            data-request-id="<?= (int) $item['id'] ?>"
                            data-request-user="<?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-request-department="<?= htmlspecialchars((string) ($item['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-request-type="<?= htmlspecialchars((string) $item['leave_type_name'] . $halfDayLabel, ENT_QUOTES, 'UTF-8') ?>"
                            data-request-period="<?= htmlspecialchars((string) $item['start_date'] . ' ~ ' . (string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?>"
                            data-request-amount="<?= htmlspecialchars(number_format((float) $item['requested_amount'], 1) . '일', ENT_QUOTES, 'UTF-8') ?>"
                            data-request-status="<?= htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8') ?>"
                            data-request-reason="<?= htmlspecialchars((string) ($item['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-request-review-note="<?= htmlspecialchars((string) ($item['review_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-request-created="<?= htmlspecialchars((string) ($item['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <span class="month-request-icon <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi <?= htmlspecialchars($statusIcons[$status] ?? 'bi-circle', ENT_QUOTES, 'UTF-8') ?>"></i>
                            </span>
                            <span class="month-request-body">
                                <span class="month-request-title">
                                    <?php if ($isAdmin): ?><strong><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?></strong> · <?php endif; ?>
                                    <?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?>
                                </span>
                                <span class="month-request-meta"><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> · <?= number_format((float) $item['requested_amount'], 1) ?>일</span>
                            </span>
                            <span class="badge <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a class="text-link" href="<?= $isAdmin ? '/admin/requests' : '/leave/history' ?>"><i class="bi bi-arrow-right-circle"></i> <?= $isAdmin ? '전체 신청 내역 보기' : '내 전체 신청 내역 보기' ?></a>
        </section>
    </aside>
</div>

<dialog class="modal-dialog" data-leave-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">New leave</p>
                <h2><i class="bi bi-calendar-plus"></i> 휴가 신청</h2>
                <p>달력 날짜를 누르면 시작일이 자동으로 선택됩니다.</p>
            </div>
            <button class="icon-button" type="button" data-close-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>
        <?php require __DIR__ . '/_leave-form.php'; ?>
    </div>
</dialog>

<dialog class="modal-dialog detail-dialog" data-request-detail-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Leave detail</p>
                <h2><i class="bi bi-card-checklist"></i> 휴가 상세</h2>
            </div>
            <button class="icon-button" type="button" data-close-detail-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="request-detail-grid">
            <div><span>신청번호</span><strong data-detail-id>-</strong></div>
            <div><span>상태</span><strong data-detail-status>-</strong></div>
            <div><span>직원</span><strong data-detail-user>-</strong></div>
            <div><span>종류</span><strong data-detail-type>-</strong></div>
            <div><span>기간</span><strong data-detail-period>-</strong></div>
            <div><span>일수</span><strong data-detail-amount>-</strong></div>
            <div class="span-2"><span>사유</span><strong data-detail-reason>-</strong></div>
            <div class="span-2"><span>관리자 메모</span><strong data-detail-review-note>-</strong></div>
            <div class="span-2"><span>신청 시각</span><strong data-detail-created>-</strong></div>
        </div>
    </div>
</dialog>
