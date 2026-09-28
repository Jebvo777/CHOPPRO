<?php

declare(strict_types=1);

namespace Choppro\Core;

use Choppro\Http\Request;
use Choppro\Http\Response;
use Choppro\Http\Router;

final class App
{
    public function __construct(
        private readonly Router $router,
        private readonly array $config,
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }
}
