<?php

declare(strict_types=1);

namespace DKAnnual\Middleware;

use DKAnnual\Auth\Auth;
use DKAnnual\Http\MiddlewareInterface;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;

final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->user() === null) {
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
