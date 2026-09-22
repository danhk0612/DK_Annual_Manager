<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Leave\LeaveDateCalculator;
use DKAnnual\Repository\AnnualLeaveLedgerRepository;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Repository\LeaveTypeRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Telegram\LeaveNotificationService;
use DKAnnual\View\View;

final class LeaveController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly LeaveTypeRepository $leaveTypes,
        private readonly HolidayRepository $holidays,
        private readonly LeaveRequestRepository $requests,
        private readonly AnnualLeaveService $annualLeave,
        private readonly AnnualLeaveLedgerRepository $ledger,
        private readonly LeaveDateCalculator $dates,
        private readonly LeaveNotificationService $notifications,
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

        return Response::html($this->view->render('leave-history', [
            'title' => '휴가 신청 내역',
            'requests' => $this->requests->forUser((int) $user['id']),
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
        ]));
    }

    public function create(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $this->annualLeave->syncAccruals($user, new DateTimeImmutable('today'), null);

        $leaveTypeId = $this->positiveInt($request->input('leave_type_id'));
        $leaveType = $leaveTypeId !== null ? $this->leaveTypes->findById($leaveTypeId) : null;
        $start = $this->date($request->input('start_date'));
        $end = $this->date($request->input('end_date'));
        $reasonCategory = trim((string) $request->input('reason_category', ''));
        $reasonDetail = trim((string) $request->input('reason_detail', ''));
        $halfDayPeriod = trim((string) $request->input('half_day_period', ''));

        if ($leaveType === null || $start === null || $end === null || $end < $start) {
            return $this->redirectError('휴가 종류와 신청 기간을 확인해 주세요.');
        }
        if (!in_array($reasonCategory, $this->reasonCategories(), true)) {
            return $this->redirectError('신청 사유를 선택해 주세요.');
        }

        $leaveCode = (string) $leaveType['code'];
        if ($leaveCode === 'H') {
            if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
                return $this->redirectError('반차는 하루만 신청할 수 있습니다.');
            }
            if (!in_array($halfDayPeriod, ['am', 'pm'], true)) {
                return $this->redirectError('오전/오후 반차를 선택해 주세요.');
            }
        } else {
            $halfDayPeriod = '';
        }

        $holidayDates = $this->holidays->datesBetween(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        );
        $leaveDates = $this->dates->workingDates($start, $end, $holidayDates);

        if ($leaveDates === []) {
            return $this->redirectError('신청 기간에 휴가로 계산할 평일이 없습니다.');
        }

        if ($this->requests->hasOpenDays((int) $user['id'], $leaveDates)) {
            return $this->redirectError('이미 신청 중이거나 승인된 날짜가 포함되어 있습니다.');
        }

        $dailyAmount = (float) $leaveType['default_amount'];
        $requestedAmount = count($leaveDates) * $dailyAmount;
        $reason = $reasonCategory . ($reasonDetail !== '' ? ' - ' . $reasonDetail : '');

        $warnings = [];
        if ((int) $leaveType['deducts_annual_leave'] === 1) {
            $amountByYear = [];
            foreach ($leaveDates as $leaveDate) {
                $year = (int) substr($leaveDate, 0, 4);
                $amountByYear[$year] = ($amountByYear[$year] ?? 0.0) + $dailyAmount;
            }

            foreach ($amountByYear as $year => $amount) {
                $balance = $this->ledger->balanceForUserYear((int) $user['id'], (int) $year);
                if ($amount > $balance) {
                    $warnings[] = sprintf(
                        '%d년 잔여 연차 %.1f일보다 신청 %.1f일이 많습니다. 신청은 접수되며 관리자가 최종 판단합니다.',
                        $year,
                        $balance,
                        $amount,
                    );
                }
            }
        }
        $balanceWarning = $warnings !== [] ? implode(' ', $warnings) : null;

        $requestId = $this->requests->create(
            (int) $user['id'],
            (int) $leaveType['id'],
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $requestedAmount,
            $halfDayPeriod !== '' ? $halfDayPeriod : null,
            $reason,
            $leaveDates,
            $dailyAmount,
        );

        $this->audit->record((int) $user['id'], 'leave.request_created', 'leave_request', $requestId, [
            'leave_type' => $leaveCode,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'amount' => $requestedAmount,
            'half_day_period' => $halfDayPeriod !== '' ? $halfDayPeriod : null,
            'balance_warning' => $balanceWarning,
        ], $this->ip($request));

        $this->notifications->notifyAdminsOfRequest([
            'id' => $requestId,
            'user_name' => (string) $user['name'],
            'leave_type_name' => (string) $leaveType['name'],
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'requested_amount' => $requestedAmount,
            'half_day_period' => $halfDayPeriod !== '' ? $halfDayPeriod : null,
            'reason' => $reason,
            'balance_warning' => $balanceWarning,
        ]);

        $query = [
            'message' => sprintf('%s %.1f일을 신청했습니다.', (string) $leaveType['name'], $requestedAmount),
        ];
        if ($balanceWarning !== null) {
            $query['warning'] = $balanceWarning;
        }

        return Response::redirect('/leave?' . http_build_query($query));
    }

    public function cancel(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $requestId = $this->positiveInt($request->input('request_id'));
        if ($requestId === null || !$this->requests->cancelPending($requestId, (int) $user['id'])) {
            return Response::redirect('/leave/history?error=' . rawurlencode('취소할 수 있는 신청을 찾지 못했습니다.'));
        }

        $this->audit->record((int) $user['id'], 'leave.request_cancelled', 'leave_request', $requestId, [], $this->ip($request));

        return Response::redirect('/leave/history?message=' . rawurlencode('휴가 신청을 취소했습니다.'));
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

    private function redirectError(string $message): Response
    {
        return Response::redirect('/leave?error=' . rawurlencode($message));
    }

    private function ip(Request $request): ?string
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        return $ip !== '' ? $ip : null;
    }
}
