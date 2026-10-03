<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo json_encode($repository->viewStatus(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
