<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/Autoloader.php';

use Choppro\Security\Uuid;

$uuid = Uuid::v4();
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
    fwrite(STDERR, "UUID smoke test failed\n");
    exit(1);
}

echo "Smoke tests passed\n";
