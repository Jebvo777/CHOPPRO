<?php
declare(strict_types=1);
$app=$app??'admin';
if(!in_array($app,['admin','client','platform','jobs'],true)){http_response_code(404);exit;}
$base=defined('CHOPPRO_HOST_BASE')?CHOPPRO_HOST_BASE:'';
$release=defined('CHOPPRO_RELEASE_SHA')?CHOPPRO_RELEASE_SHA:'';
$asset=fn($file)=>$base.'/asset.php?'.http_build_query(['path'=>'web/shared/'.$file,'release'=>$release]);
$title=['admin'=>'Управление охраной','client'=>'Кабинет заказчика','platform'=>'Управление платформой','jobs'=>'Биржа труда'][$app];
header('Content-Type: text/html; charset=utf-8');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Cache-Control: no-store');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=htmlspecialchars($title)?> · ЧОППРО</title><link rel="stylesheet" href="<?=htmlspecialchars($asset('app.css'))?>"><script src="<?=htmlspecialchars($asset('qr.js'))?>" defer></script><script src="<?=htmlspecialchars($asset('app.js'))?>" defer></script></head><body data-app="<?=$app?>" data-base="<?=htmlspecialchars($base)?>" data-api="<?=htmlspecialchars($base.'/demo-api.php')?>"><div id="app"><div class="loading"><span class="brand-mark">Ч</span><h1>ЧОППРО</h1><p>Загрузка рабочего пространства…</p></div></div><div id="toast" role="status" aria-live="polite"></div><dialog id="dialog"></dialog></body></html>
