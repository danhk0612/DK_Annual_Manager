<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\LeaveDateCalculator;
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

        return Response::html($this->view->render('leave', [
            'title' => '휴가 신청',
            'user' => $user,
            'leaveTypes' => $this->leaveTypes->active(),
            'requests' => $user !== null ? $this->requests->forUser((int) $user['id']) : [],
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

        $leaveTypeId = $this->positiveInt($request->input('leave_type_id'));
        $leaveType = $leaveTypeId !== null ? $this->leaveTypes->findById($leaveTypeId) : null;
        $start = $this->date($request->input('start_date'));
        $end = $this->date($request->input('end_date'));
        $reason = trim((string) $request->input('reason', ''));

        if ($leaveType === null || $start === null || $end === null || $end < $start) {
            return $this->redirectError('휴가 종류와 신청 기간을 확인해 주세요.');
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

        $requestId = $this->requests->create(
            (int) $user['id'],
            (int) $leaveType['id'],
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $requestedAmount,
            $reason !== '' ? $reason : null,
            $leaveDates,
            $dailyAmount,
        );

        $this->audit->record((int) $user['id'], 'leave.request_created', 'leave_request', $requestId, [
            'leave_type' => (string) $leaveType['code'],
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'amount' => $requestedAmount,
        ], $this->ip($request));

        $this->notifications->notifyAdminsOfRequest([
            'id' => $requestId,
            'user_name' => (string) $user['name'],
            'leave_type_name' => (string) $leaveType['name'],
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'requested_amount' => $requestedAmount,
            'reason' => $reason,
        ]);

        return Response::redirect('/leave?message=' . rawurlencode(
            sprintf('%s %.1f일을 신청했습니다.', (string) $leaveType['name'], $requestedAmount)
        ));
    }

    public function cancel(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $requestId = $this->positiveInt($request->input('request_id'));
        if ($requestId === null || !$this->requests->cancelPending($requestId, (int) $user['id'])) {
            return $this->redirectError('취소할 수 있는 신청을 찾지 못했습니다.');
        }

        $this->audit->record((int) $user['id'], 'leave.request_cancelled', 'leave_request', $requestId, [], $this->ip($request));

        return Response::redirect('/leave?message=' . rawurlencode('휴가 신청을 취소했습니다.'));
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
