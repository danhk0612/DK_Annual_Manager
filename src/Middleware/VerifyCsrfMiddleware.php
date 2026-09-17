<?php

declare(strict_types=1);

namespace DKAnnual\Middleware;

use DKAnnual\Http\MiddlewareInterface;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Security\Csrf;

final class VerifyCsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->input('_csrf');
        if (!is_string($token)) {
            $headerToken = $request->server('HTTP_X_CSRF_TOKEN');
            $token = is_string($headerToken) ? $headerToken : null;
        }

        if (!$this->csrf->validate($token)) {
            return Response::html('<h1>419</h1><p>요청을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.</p>', 419);
        }

        return $next($request);
    }
}
