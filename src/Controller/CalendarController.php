<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Repository\AnnualLeaveLedgerRepository;
use DKAnnual\Repository\AppSettingRepository;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Repository\LeaveTypeRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\View\View;

final class CalendarController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly LeaveRequestRepository $requests,
        private readonly HolidayRepository $holidays,
        private readonly LeaveTypeRepository $leaveTypes,
        private readonly AnnualLeaveLedgerRepository $ledger,
        private readonly AnnualLeaveService $annualLeave,
        private readonly AppSettingRepository $settings,
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

        $month = $this->month((string) $request->input('month', date('Y-m')));
        $start = new DateTimeImmutable($month . '-01');
        $end = $start->modify('last day of this month');
        $scopeUserId = ($user['role'] ?? null) === 'admin' ? null : (int) $user['id'];
        $year = (int) date('Y');
        $calendarTitle = sprintf('%d년 %d월 휴가 현황', (int) $start->format('Y'), (int) $start->format('n'));

        return Response::html($this->view->render('calendar', [
            'title' => $calendarTitle,
            'user' => $user,
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'isAdmin' => ($user['role'] ?? null) === 'admin',
            'entries' => $this->requests->calendarEntries(
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $scopeUserId,
            ),
            'monthlyRequests' => $this->requests->calendarRequestList(
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $scopeUserId,
            ),
            'holidays' => $this->holidays->entriesBetween(
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ),
            'leaveTypes' => $this->leaveTypes->active(),
            'annualBalance' => $this->ledger->balanceForUserYear((int) $user['id'], $year),
            'workingWeekdays' => $this->settings->workingWeekdays(),
            'reasonCategories' => ['개인 사유', '가족 행사', '병원/건강', '업무 관련', '기타'],
            'today' => date('Y-m-d'),
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'warning' => $request->input('warning'),
            'error' => $request->input('error'),
        ]));
    }

    private function month(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return date('Y-m');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01');
        return $date !== false && $date->format('Y-m') === $value ? $value : date('Y-m');
    }
}
