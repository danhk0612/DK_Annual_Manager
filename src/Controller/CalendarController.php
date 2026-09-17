<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\View\View;

final class CalendarController
{
    public function __construct(
        private readonly LeaveRequestRepository $requests,
        private readonly HolidayRepository $holidays,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $month = $this->month((string) $request->input('month', date('Y-m')));
        $start = new DateTimeImmutable($month . '-01');
        $end = $start->modify('last day of this month');

        return Response::html($this->view->render('calendar', [
            'title' => '휴가 달력',
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'entries' => $this->requests->calendarEntries(
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
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
