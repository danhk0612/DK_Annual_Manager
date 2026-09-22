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
            return Response::redirect('/login?return_to=' . rawurlencode($this->returnTo($request)));
        }

        return $next($request);
    }

    private function returnTo(Request $request): string
    {
        $uri = trim((string) $request->server('REQUEST_URI', $request->path()));
        if ($uri === '' || !str_starts_with($uri, '/') || str_starts_with($uri, '//')) {
            return '/';
        }

        return $uri;
    }
}
