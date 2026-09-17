<?php

declare(strict_types=1);

namespace DKAnnual\Http;

final class Router
{
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
            return Response::html('<h1>404</h1><p>페이지를 찾을 수 없습니다.</p>', 404);
        }

        $next = $route['handler'];

        foreach (array_reverse($route['middleware']) as $middleware) {
            $previous = $next;
            $next = static fn (Request $currentRequest): Response => $middleware->handle($currentRequest, $previous);
        }

        return $next($request);
    }
}
