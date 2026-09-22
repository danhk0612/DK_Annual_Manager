<?php

declare(strict_types=1);

namespace DKAnnual\Middleware;

use DKAnnual\Http\MiddlewareInterface;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Session\Session;

final class SetupAccessMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly string $setupKeyPath,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->session->get('setup_authorized', false) === true) {
            return $next($request);
        }

        $expected = $this->readSetupKey();
        if ($expected === null) {
            return Response::html($this->deniedHtml(
                '초기 설정 키가 없습니다.',
                'NAS 터미널에서 php84 bin/setup-key.php 를 실행한 뒤 출력된 URL로 접속하세요.',
            ), 503);
        }

        $provided = trim((string) $request->input('setup_key', ''));
        if ($provided !== '' && hash_equals($expected, $provided)) {
            $this->session->set('setup_authorized', true);
            $this->session->regenerate();

            if ($request->method() === 'GET' && $request->path() === '/setup') {
                return Response::redirect('/setup');
            }

            return $next($request);
        }

        return Response::html($this->deniedHtml(
            '초기 설정 접근이 잠겨 있습니다.',
            'NAS 터미널에서 php84 bin/setup-key.php 를 실행하고 출력된 /setup?setup_key=... 주소를 사용하세요.',
        ), 403);
    }

    private function readSetupKey(): ?string
    {
        if (!is_file($this->setupKeyPath)) {
            return null;
        }

        $key = trim((string) file_get_contents($this->setupKeyPath));
        return $key !== '' ? $key : null;
    }

    private function deniedHtml(string $title, string $message): string
    {
        return '<!doctype html><html lang="ko"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>초기 설정 보호</title>'
            . '<link rel="icon" type="image/svg+xml" href="/assets/app-icon.svg">'
            . '<link rel="stylesheet" href="/assets/app.css"></head><body>'
            . '<main class="container"><section class="panel narrow">'
            . '<p class="eyebrow">Setup protection</p><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</section></main></body></html>';
    }
}
