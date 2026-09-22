<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\View\View;

final class CalendarController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly LeaveRequestRepository $requests,
        private readonly HolidayRepository $holidays,
        private readonly AnnualLeaveService $annualLeave,
        private readonly View $view,
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

        return Response::html($this->view->render('calendar', [
            'title' => '휴가 달력',
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
