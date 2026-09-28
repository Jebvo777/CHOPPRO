<?php

declare(strict_types=1);

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_NAME') ?: 'choppro',
    'username' => getenv('DB_USER') ?: 'choppro',
    'password' => getenv('DB_PASSWORD') ?: '',
];
