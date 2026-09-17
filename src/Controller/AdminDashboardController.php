<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

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

        return Response::html($this->view->render('admin', [
            'title' => '관리자',
            'year' => $year,
            'metrics' => $this->reports->dashboardMetrics($year),
            'recentAudit' => $this->audit->recent(10),
        ]));
    }
}
