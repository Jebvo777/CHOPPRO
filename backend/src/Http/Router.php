<?php

declare(strict_types=1);

namespace Choppro\Http;

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET ' . $path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST ' . $path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $key = $request->method . ' ' . $request->path;
        $handler = $this->routes[$key] ?? null;

        if ($handler === null) {
            return Response::problem(404, 'ROUTE_NOT_FOUND', 'Маршрут не найден');
        }

        $response = $handler($request);
        if (!$response instanceof Response) {
            return Response::problem(500, 'INVALID_HANDLER_RESPONSE', 'Некорректный ответ обработчика');
        }

        return $response;
    }
}
