<?php

declare(strict_types=1);

namespace DKAnnual\Middleware;

use DKAnnual\Auth\Auth;
use DKAnnual\Http\MiddlewareInterface;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;

final class RequireAdminMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->user() === null) {
            return Response::redirect('/login');
        }

        if (!$this->auth->isAdmin()) {
            return Response::html('<h1>403</h1><p>관리자 권한이 필요합니다.</p>', 403);
        }

        return $next($request);
    }
}
