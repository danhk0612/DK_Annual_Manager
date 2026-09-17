<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\ReportingRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\View\View;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly ReportingRepository $reports,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $year = (int) date('Y');

        return Response::html($this->view->render('home', [
            'title' => '대시보드',
            'user' => $user,
            'year' => $year,
            'annualSummary' => $user !== null
                ? $this->reports->userAnnualSummary((int) $user['id'], $year)
                : null,
            'csrfToken' => $this->csrf->token(),
        ]));
    }
}
