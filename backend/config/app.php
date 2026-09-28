<?php

declare(strict_types=1);

return [
    'environment' => getenv('APP_ENV') ?: 'development',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
    'url' => getenv('APP_URL') ?: 'http://localhost:8080',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
];
