<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/boot.php';
$loader->sync();$release=host_release('demo');require $release['path'].'/backend/scripts/worker.php';
