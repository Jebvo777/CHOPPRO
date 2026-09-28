<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/Autoloader.php';

use Choppro\Core\App;
use Choppro\Database\Connection;
use Choppro\Http\Request;
use Choppro\Http\Response;
use Choppro\Http\Router;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

try {
    $config = require dirname(__DIR__) . '/config/app.php';
    $database = Connection::fromConfig(require dirname(__DIR__) . '/config/database.php');

    $router = new Router();
    $router->get('/api/v1/health', static function () use ($database): Response {
        $database->pdo()->query('SELECT 1');

        return Response::json([
            'status' => 'ok',
            'service' => 'choppro-api',
            'version' => '0.1.0-stage1',
            'database' => 'ok',
            'time' => gmdate('c'),
        ]);
    });

    $router->get('/api/v1/version', static fn (): Response => Response::json([
        'name' => 'ЧОППРО',
        'api' => 'v1',
        'version' => '0.1.0-stage1',
    ]));

    $app = new App($router, $config);
    $response = $app->handle(Request::fromGlobals());
    $response->send();
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'type' => 'about:blank',
        'title' => 'Internal Server Error',
        'status' => 500,
        'code' => 'INTERNAL_ERROR',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
