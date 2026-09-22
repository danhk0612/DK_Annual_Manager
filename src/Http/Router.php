<?php

declare(strict_types=1);

namespace DKAnnual\Http;

final class Router
{
    public function __construct(private readonly string $appName = 'DK Annual Manager')
    {
    }

    /** @var array<string, array<string, array{handler: callable, middleware: list<MiddlewareInterface>}>> */
    private array $routes = [];

    /** @param callable(Request): Response $handler @param list<MiddlewareInterface> $middleware */
    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /** @param callable(Request): Response $handler @param list<MiddlewareInterface> $middleware */
    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** @param callable(Request): Response $handler @param list<MiddlewareInterface> $middleware */
    private function add(string $method, string $path, callable $handler, array $middleware): void
    {
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $route = $this->routes[$request->method()][$request->path()] ?? null;
        if ($route === null) {
            return Response::html(
                '<!doctype html><html lang="ko"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>페이지 없음 · ' . htmlspecialchars($this->appName, ENT_QUOTES, 'UTF-8') . '</title>'
                . '<link rel="icon" type="image/svg+xml" href="/assets/app-icon.svg">'
                . '<link rel="stylesheet" href="/assets/app.css"><link rel="stylesheet" href="/theme.css">'
                . '</head><body><main class="container"><section class="panel narrow">'
                . '<p class="eyebrow">404</p><h1>페이지를 찾을 수 없습니다.</h1>'
                . '<p>주소를 확인하거나 처음 화면으로 돌아가 주세요.</p>'
                . '<a class="button primary" href="/">처음 화면</a>'
                . '</section></main></body></html>',
                404,
            );
        }

        $next = $route['handler'];

        foreach (array_reverse($route['middleware']) as $middleware) {
            $previous = $next;
            $next = static fn (Request $currentRequest): Response => $middleware->handle($currentRequest, $previous);
        }

        return $next($request);
    }
}
