<?php

declare(strict_types=1);

namespace Choppro\Middleware;

use Choppro\Http\Request;
use Choppro\Http\Response;

final class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $authorization = $request->headers['Authorization'] ?? $request->headers['authorization'] ?? null;
        if (!is_string($authorization) || !str_starts_with($authorization, 'Bearer ')) {
            return Response::problem(401, 'AUTH_REQUIRED', 'Требуется авторизация');
        }

        return $next($request);
    }
}
