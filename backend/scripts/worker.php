<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/Core/Autoloader.php';
$config=\Choppro\Stage2\Config::load();
echo \Choppro\Stage2\Support::json((new \Choppro\Stage2\Worker(new \Choppro\Stage2\Db($config),$config))->tick()).PHP_EOL;
