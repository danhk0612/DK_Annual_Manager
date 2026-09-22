<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Repository\ReportingRepository;
use DKAnnual\View\View;

final class AdminDashboardController
{
    public function __construct(
        private readonly ReportingRepository $reports,
        private readonly AuditLogRepository $audit,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $year = (int) date('Y');
        $today = new DateTimeImmutable('today');

        return Response::html($this->view->render('admin', [
            'title' => '관리자 대시보드',
            'year' => $year,
            'metrics' => $this->reports->dashboardMetrics($year),
            'pendingPreview' => $this->reports->pendingRequestPreview(6),
            'upcomingLeaves' => $this->reports->upcomingApprovedLeaves(
                $today->format('Y-m-d'),
                $today->modify('+14 days')->format('Y-m-d'),
                8,
            ),
            'monthlyTotals' => $this->reports->monthlyApprovedTotals($year),
            'recentAudit' => $this->audit->recent(8),
        ]));
    }
}
