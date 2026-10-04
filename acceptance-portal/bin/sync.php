<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__).'/app/Repository.php';
$repository = new Repository(require dirname(__DIR__).'/config.php');
$repository->synchronize(in_array('--force', $argv, true));
echo json_encode($repository->viewStatus(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
exit(empty($repository->state['error']) ? 0 : 1);
