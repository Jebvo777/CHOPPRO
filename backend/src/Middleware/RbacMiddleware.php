<?php

declare(strict_types=1);

namespace Choppro\Middleware;

use Choppro\Http\Request;
use Choppro\Http\Response;

final class RbacMiddleware
{
    public function __construct(private readonly string $permission)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        // Проверка разрешений подключается к репозиторию ролей на Этапе 2.
        return $next($request);
    }
}
