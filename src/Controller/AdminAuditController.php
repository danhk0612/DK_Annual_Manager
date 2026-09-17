<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\View\View;

final class AdminAuditController
{
    public function __construct(
        private readonly AuditLogRepository $audit,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin-audit', [
            'title' => '변경 이력',
            'logs' => $this->audit->recent(200),
        ]));
    }
}
