<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Security\Csrf;
use DKAnnual\View\View;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('home', [
            'title' => '대시보드',
            'user' => $this->auth->user(),
            'csrfToken' => $this->csrf->token(),
        ]));
    }
}
