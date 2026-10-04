<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/Core/Autoloader.php';
$config=\Choppro\Stage2\Config::load();
$db=new \Choppro\Stage2\Db($config);
echo \Choppro\Stage2\Support::json((new \Choppro\Stage2\Migrator($db,$config))->apply(!in_array('--no-seed',$argv,true))).PHP_EOL;
