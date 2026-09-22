<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Leave\LeaveDateCalculator;
use DKAnnual\Leave\LeaveReviewService;
use DKAnnual\Repository\AnnualLeaveLedgerRepository;
use DKAnnual\Repository\AppSettingRepository;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Repository\LeaveTypeRepository;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Telegram\LeaveNotificationService;
use DKAnnual\View\View;

final class LeaveController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly LeaveTypeRepository $leaveTypes,
        private readonly HolidayRepository $holidays,
        private readonly LeaveRequestRepository $requests,
        private readonly AnnualLeaveService $annualLeave,
        private readonly AnnualLeaveLedgerRepository $ledger,
        private readonly AppSettingRepository $settings,
        private readonly LeaveDateCalculator $dates,
        private readonly LeaveNotificationService $notifications,
        private readonly LeaveReviewService $reviewer,
        private readonly AuditLogRepository $audit,
        private readonly View $view,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $this->annualLeave->syncAccruals($user, new DateTimeImmutable('today'), null);
        $year = (int) date('Y');

        return Response::html($this->view->render('leave', [
            'title' => '휴가 신청',
            'user' => $user,
            'leaveTypes' => $this->leaveTypes->active(),
            'annualBalance' => $this->ledger->balanceForUserYear((int) $user['id'], $year),
            'reasonCategories' => $this->reasonCategories(),
            'today' => date('Y-m-d'),
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'warning' => $request->input('warning'),
            'error' => $request->input('error'),
        ]));
    }

    public function history(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $isAdmin = ($user['role'] ?? null) === 'admin';
        $query = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $yearInput = trim((string) $request->input('year', ''));
        $year = ctype_digit($yearInput) && (int) $yearInput >= 2000 && (int) $yearInput <= 2100
            ? (int) $yearInput
            : null;

        return Response::html($this->view->render('leave-history', [
            'title' => $isAdmin ? '전체 휴가 신청 내역' : '휴가 신청 내역',
            'requests' => $this->requests->searchHistory(
                $isAdmin ? null : (int) $user['id'],
                $query,
                $status,
                $year,
            ),
            'isAdmin' => $isAdmin,
            'currentUserId' => (int) $user['id'],
            'query' => $query,
            'selectedStatus' => $status,
            'selectedYear' => $year,
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
        ]));
    }

    public function create(Request $request): Response
    {
        $actor = $this->auth->user();
        if ($actor === null) {
            return Response::redirect('/login');
        }

        $returnTo = $this->safeReturnTo((string) $request->input('return_to', '/leave'));
        $subject = $actor;
        $targetUserId = $this->positiveInt($request->input('target_user_id'));
        $isAdminProxy = false;

        if ($targetUserId !== null) {
            if (($actor['role'] ?? null) !== 'admin') {
                return $this->redirectError('다른 직원의 휴가는 관리자만 등록할 수 있습니다.', $returnTo);
            }

            $target = $this->users->findById($targetUserId);
            if ($target === null || ($target['status'] ?? null) !== 'active') {
                return $this->redirectError('대리 신청할 활성 직원을 찾을 수 없습니다.', $returnTo);
            }
            $subject = $target;
            $isAdminProxy = true;
        }

        if (trim((string) ($subject['hire_date'] ?? '')) === '') {
            return $this->redirectError(
                $isAdminProxy
                    ? '입사일이 등록되지 않은 직원은 휴가를 등록할 수 없습니다. 직원 정보에서 입사일을 먼저 입력해 주세요.'
                    : '입사일이 등록되지 않아 휴가를 신청할 수 없습니다. 내 정보에서 입사일을 먼저 입력해 주세요.',
                $returnTo,
            );
        }

        $this->annualLeave->syncAccruals($subject, new DateTimeImmutable('today'), (int) $actor['id']);

        $leaveTypeId = $this->positiveInt($request->input('leave_type_id'));
        $leaveType = $leaveTypeId !== null ? $this->leaveTypes->findById($leaveTypeId) : null;
        $start = $this->date($request->input('start_date'));
        $end = $this->date($request->input('end_date'));
        $reasonCategory = trim((string) $request->input('reason_category', ''));
        $reasonDetail = trim((string) $request->input('reason_detail', ''));
        $halfDayPeriod = trim((string) $request->input('half_day_period', ''));

        if ($leaveType === null || $start === null || $end === null || $end < $start) {
            return $this->redirectError('휴가 종류와 신청 기간을 확인해 주세요.', $returnTo);
        }
        if (!$isAdminProxy && !in_array($reasonCategory, $this->reasonCategories(), true)) {
            return $this->redirectError('신청 사유를 선택해 주세요.', $returnTo);
        }

        $leaveCode = (string) $leaveType['code'];
        if ($leaveCode === 'H') {
            if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
                return $this->redirectError('반차는 하루만 신청할 수 있습니다.', $returnTo);
            }
            if (!in_array($halfDayPeriod, ['am', 'pm'], true)) {
                return $this->redirectError('오전/오후 반차를 선택해 주세요.', $returnTo);
            }
        } else {
            $halfDayPeriod = '';
        }

        $adminDateOverride = false;

        if ($isAdminProxy) {
            // Administrator proxy entry is an authoritative record. Use the
            // selected calendar dates exactly as entered and skip workday,
            // holiday and overlap eligibility checks.
            $leaveDates = $this->dates->calendarDates($start, $end);
        } else {
            $holidayDates = $this->holidays->datesBetween(
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            );
            $workingWeekdays = $this->settings->workingWeekdays();
            $leaveDates = $this->dates->workingDates(
                $start,
                $end,
                $holidayDates,
                $workingWeekdays,
            );

            if ($leaveDates === []) {
                $allowHistoricalException = ($actor['role'] ?? null) === 'admin'
                    && (string) $request->input('admin_date_exception', '0') === '1'
                    && (int) $leaveType['deducts_annual_leave'] === 0
                    && $start->format('Y-m-d') === $end->format('Y-m-d');

                if ($allowHistoricalException) {
                    $leaveDates = [$start->format('Y-m-d')];
                    $adminDateOverride = true;
                } else {
                    return $this->redirectError(
                        $this->noWorkingDateMessage($start, $end, $workingWeekdays),
                        $returnTo,
                    );
                }
            }

            if ($this->requests->hasOpenDays((int) $subject['id'], $leaveDates)) {
                return $this->redirectError('이미 신청 중이거나 승인된 날짜가 포함되어 있습니다.', $returnTo);
            }
        }

        $dailyAmount = (float) $leaveType['default_amount'];
        $requestedAmount = count($leaveDates) * $dailyAmount;
        $reason = '';
        if (in_array($reasonCategory, $this->reasonCategories(), true)) {
            $reason = $reasonCategory . ($reasonDetail !== '' ? ' - ' . $reasonDetail : '');
        } elseif ($reasonDetail !== '') {
            $reason = $reasonDetail;
        }
        $reason = $reason !== '' ? $reason : null;

        $warnings = [];
        if (!$isAdminProxy && (int) $leaveType['deducts_annual_leave'] === 1) {
            $amountByYear = [];
            foreach ($leaveDates as $leaveDate) {
                $year = (int) substr($leaveDate, 0, 4);
                $amountByYear[$year] = ($amountByYear[$year] ?? 0.0) + $dailyAmount;
            }

            foreach ($amountByYear as $year => $amount) {
                $balance = $this->ledger->balanceForUserYear((int) $subject['id'], (int) $year);
                if ($amount > $balance) {
                    $warnings[] = sprintf(
                        $isAdminProxy
                            ? '%d년 잔여 연차 %.1f일보다 등록 %.1f일이 많습니다. 관리자 대리 등록이므로 즉시 승인되어 잔여 연차가 음수가 될 수 있습니다.'
                            : '%d년 잔여 연차 %.1f일보다 신청 %.1f일이 많습니다. 신청은 접수되며 관리자가 최종 판단합니다.',
                        $year,
                        $balance,
                        $amount,
                    );
                }
            }
        }
        $balanceWarning = $warnings !== [] ? implode(' ', $warnings) : null;

        $requestId = $this->requests->create(
            (int) $subject['id'],
            (int) $leaveType['id'],
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $requestedAmount,
            $halfDayPeriod !== '' ? $halfDayPeriod : null,
            $reason,
            $leaveDates,
            $dailyAmount,
        );

        $this->audit->record((int) $actor['id'], 'leave.request_created', 'leave_request', $requestId, [
            'user_id' => (int) $subject['id'],
            'created_for_other_user' => (int) $actor['id'] !== (int) $subject['id'],
            'leave_type' => $leaveCode,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'amount' => $requestedAmount,
            'half_day_period' => $halfDayPeriod !== '' ? $halfDayPeriod : null,
            'balance_warning' => $balanceWarning,
            'admin_date_exception' => $adminDateOverride,
            'admin_direct_entry' => $isAdminProxy,
        ], $this->ip($request));

        if ($isAdminProxy) {
            $reviewResult = $this->reviewer->review(
                $requestId,
                'approve',
                (int) $actor['id'],
                null,
            );

            if (!$reviewResult['changed'] || $reviewResult['request'] === null) {
                return $this->redirectError('관리자 대리 등록의 자동 승인 처리에 실패했습니다.', $returnTo);
            }

            $this->audit->record(
                (int) $actor['id'],
                'leave.request_approved',
                'leave_request',
                $requestId,
                [
                    'status' => 'approved',
                    'source' => 'admin_proxy',
                    'automatic' => true,
                ],
                $this->ip($request),
            );

            // Administrator direct registration is an internal authoritative entry.
            // Do not send Telegram notifications for this path.
            $message = sprintf(
                '%s님의 %s %.1f일을 등록하고 즉시 승인했습니다.',
                (string) $subject['name'],
                (string) $leaveType['name'],
                $requestedAmount,
            );
        } else {
            $this->notifications->notifyAdminsOfRequest([
                'id' => $requestId,
                'user_name' => (string) $subject['name'],
                'leave_type_name' => (string) $leaveType['name'],
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'requested_amount' => $requestedAmount,
                'half_day_period' => $halfDayPeriod !== '' ? $halfDayPeriod : null,
                'reason' => $reason,
                'balance_warning' => $balanceWarning,
            ]);

            $message = sprintf('%s %.1f일을 신청했습니다.', (string) $leaveType['name'], $requestedAmount);
        }

        $query = ['message' => $message];
        if ($balanceWarning !== null) {
            $query['warning'] = $balanceWarning;
        }

        return Response::redirect($this->appendQuery($returnTo, $query));
    }

    public function cancel(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $returnTo = $this->safeReturnTo((string) $request->input('return_to', '/leave/history'));
        $requestId = $this->positiveInt($request->input('request_id'));
        $deleted = $requestId !== null
            ? $this->requests->deletePending($requestId, (int) $user['id'])
            : null;

        if ($deleted === null) {
            return $this->redirectError('삭제할 수 있는 승인 대기 신청을 찾지 못했습니다.', $returnTo);
        }

        $this->audit->record(
            (int) $user['id'],
            'leave.pending_request_deleted',
            'leave_request',
            $requestId,
            [
                'leave_type' => (string) ($deleted['leave_code'] ?? ''),
                'start_date' => (string) ($deleted['start_date'] ?? ''),
                'end_date' => (string) ($deleted['end_date'] ?? ''),
                'amount' => (float) ($deleted['requested_amount'] ?? 0),
            ],
            $this->ip($request),
        );

        return Response::redirect($this->appendQuery($returnTo, [
            'message' => '승인 대기 신청을 취소하고 삭제했습니다.',
        ]));
    }

    public function cancelApproved(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $returnTo = $this->safeReturnTo((string) $request->input('return_to', '/leave/history'));
        $requestId = $this->positiveInt($request->input('request_id'));
        $note = trim((string) $request->input('cancellation_note', ''));

        if ($requestId === null) {
            return $this->redirectError('취소할 승인 휴가를 확인해 주세요.', $returnTo);
        }

        $result = $this->reviewer->cancelApprovedByUser(
            $requestId,
            (int) $user['id'],
            $note !== '' ? $note : null,
        );

        if (!$result['changed'] || $result['request'] === null) {
            return $this->redirectError('본인의 승인 상태 휴가를 찾지 못했습니다.', $returnTo);
        }

        $this->audit->record(
            (int) $user['id'],
            'leave.approved_request_cancelled_by_user',
            'leave_request',
            $requestId,
            [
                'status' => 'cancelled',
                'cancellation_source' => 'user',
                'cancellation_note' => $note !== '' ? $note : null,
            ],
            $this->ip($request),
        );

        $this->notifications->notifyAdminsOfApprovedCancellation($result['request']);
        $this->notifications->notifyCompanyOfCancelledLeave($result['request']);

        return Response::redirect($this->appendQuery($returnTo, [
            'message' => '승인된 휴가를 취소했습니다. 차감된 연차가 있다면 자동으로 복원되었습니다.',
        ]));
    }

    /** @param list<int> $workingWeekdays */
    private function noWorkingDateMessage(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $workingWeekdays,
    ): string {
        $weekdayNames = [1 => '월', 2 => '화', 3 => '수', 4 => '목', 5 => '금', 6 => '토', 7 => '일'];
        $configuredDays = array_map(
            static fn (int $day): string => $weekdayNames[$day] ?? (string) $day,
            $workingWeekdays,
        );

        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            $date = $start->format('Y-m-d');
            $weekday = (int) $start->format('N');

            if (!in_array($weekday, $workingWeekdays, true)) {
                return sprintf(
                    '%s(%s)은 현재 비근무 요일입니다. 설정된 근무 요일: %s.',
                    $date,
                    $weekdayNames[$weekday] ?? (string) $weekday,
                    implode('·', $configuredDays),
                );
            }

            $holidayEntries = $this->holidays->entriesBetween($date, $date);
            $excluded = array_values(array_filter(
                $holidayEntries,
                static fn (array $item): bool => (int) ($item['is_public_holiday'] ?? 0) === 1,
            ));
            if ($excluded !== []) {
                $names = array_map(
                    static fn (array $item): string => (string) ($item['name'] ?? '휴일'),
                    $excluded,
                );
                return sprintf(
                    '%s은 휴가 계산 제외 휴일로 등록되어 있습니다: %s.',
                    $date,
                    implode(', ', array_unique($names)),
                );
            }
        }

        return sprintf(
            '신청 기간에 계산 가능한 근무일이 없습니다. 현재 근무 요일은 %s이며 공휴일/회사 휴무일은 제외됩니다.',
            implode('·', $configuredDays),
        );
    }

    /** @return list<string> */
    private function reasonCategories(): array
    {
        return ['개인 사유', '가족 행사', '병원/건강', '업무 관련', '기타'];
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        $value = trim((string) ($value ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = (string) ($value ?? '');
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function safeReturnTo(string $value): string
    {
        $value = trim($value);
        $parts = parse_url($value);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])) {
            return '/leave';
        }

        $path = (string) ($parts['path'] ?? '');
        if (!in_array($path, ['/leave', '/leave/history', '/calendar', '/admin/requests'], true)) {
            return '/leave';
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        return $path . $query;
    }

    /** @param array<string, string> $query */
    private function appendQuery(string $url, array $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    private function redirectError(string $message, string $returnTo): Response
    {
        return Response::redirect($this->appendQuery($returnTo, ['error' => $message]));
    }

    private function ip(Request $request): ?string
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        return $ip !== '' ? $ip : null;
    }
}
