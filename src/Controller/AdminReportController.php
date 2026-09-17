<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\ReportingRepository;
use DKAnnual\View\View;

final class AdminReportController
{
    public function __construct(
        private readonly ReportingRepository $reports,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $year = $this->yearFrom($request->input('year'));

        return Response::html($this->view->render('admin-reports', [
            'title' => '휴가 집계',
            'year' => $year,
            'annualSummary' => $this->reports->annualUserSummary($year),
            'monthlySummary' => $this->reports->monthlyApprovedLeaveSummary($year),
        ]));
    }

    private function yearFrom(mixed $value): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        return $year !== false && $year >= 2000 && $year <= 2100 ? (int) $year : (int) date('Y');
    }
}
