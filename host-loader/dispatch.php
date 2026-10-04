<?php
declare(strict_types=1);
require __DIR__.'/boot.php';
$route=$hostRoute??'index';
try{
    $demo=in_array($route,['demo','demo-api'],true);$release=host_release($demo?'demo':'portal');
    if($route==='demo'){$app=$_GET['app']??'admin';if(!in_array($app,['admin','client','platform','jobs'],true)){http_response_code(404);exit;}require $release['path'].'/web/'.$app.'/index.php';}
    else{$path=['index'=>'acceptance-portal/index.php','api'=>'acceptance-portal/api.php','file'=>'acceptance-portal/file.php','prototype'=>'acceptance-portal/prototype.php','demo-api'=>'backend/public/index.php'][$route]??null;if(!$path){http_response_code(404);exit;}require $release['path'].'/'.$path;}
}catch(Throwable $e){http_response_code(503);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>ЧОППРО</title><h1>ЧОППРО</h1><p>Не удалось открыть рабочую версию. Откройте управление порталом для проверки комплекта.</p><a href="control.php">Управление порталом</a></html>';error_log('CHOPPRO loader: '.$e->getMessage());}
