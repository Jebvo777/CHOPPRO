<?php
declare(strict_types=1);
require __DIR__.'/boot.php';
$route=$hostRoute??'index';
try{
    $demo=in_array($route,['demo','demo-api'],true);$release=host_release($demo?'demo':'portal');
    if($route==='demo'){$app=$_GET['app']??'admin';$manifest=$loader->json($release['path'].'/site-release.json');$apps=array_column($manifest['demos']??[],'app');if(!preg_match('/^[a-z][a-z0-9_-]{1,40}$/',$app)||!in_array($app,$apps,true)){http_response_code(404);exit;}require $release['path'].'/web/'.$app.'/index.php';}
    else{$path=['control'=>'acceptance-portal/control.php','index'=>'acceptance-portal/index.php','api'=>'acceptance-portal/api.php','file'=>'acceptance-portal/file.php','prototype'=>'acceptance-portal/prototype.php','demo-api'=>'backend/public/index.php'][$route]??null;if(!$path){http_response_code(404);exit;}require $release['path'].'/'.$path;}
}catch(Throwable $e){http_response_code(503);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>ЧОППРО</title><h1>ЧОППРО</h1><p>Не удалось открыть рабочую версию. Откройте управление порталом для проверки комплекта.</p><a href="control.php">Управление порталом</a></html>';error_log('CHOPPRO loader: '.$e->getMessage());}
