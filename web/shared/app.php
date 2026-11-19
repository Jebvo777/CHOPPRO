<?php
declare(strict_types=1);
$app=$app??'admin';
if(!in_array($app,['admin','client','platform','jobs'],true)){http_response_code(404);exit;}
$base=defined('CHOPPRO_HOST_BASE')?CHOPPRO_HOST_BASE:'';
$release=defined('CHOPPRO_RELEASE_SHA')?CHOPPRO_RELEASE_SHA:'';
$asset=fn($file)=>$base.'/asset.php?'.http_build_query(['path'=>'web/shared/'.$file,'release'=>$release]);
$title=['admin'=>'Управление охраной','client'=>'Кабинет заказчика','platform'=>'Управление платформой','jobs'=>'Биржа труда'][$app];
header('Content-Type: text/html; charset=utf-8');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Cache-Control: no-store');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; frame-src 'self' blob: https://yandex.ru https://yandex.com https://*.yandex.ru https://*.yandex.com");
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=htmlspecialchars($title)?> · ЧОППРО</title><link rel="icon" href="<?=htmlspecialchars($asset('favicon.png'))?>"><link rel="stylesheet" href="<?=htmlspecialchars($asset('app.css'))?>"><link rel="stylesheet" href="<?=htmlspecialchars($asset('ux.css'))?>"><link rel="stylesheet" href="<?=htmlspecialchars($asset('workspace.css'))?>"><script src="<?=htmlspecialchars($asset('qr.js'))?>" defer></script><script src="<?=htmlspecialchars($asset('app.js'))?>" defer></script><script src="<?=htmlspecialchars($asset('map.js'))?>" defer></script><script src="<?=htmlspecialchars($asset('ux.js'))?>" defer></script><script src="<?=htmlspecialchars($asset('workspace.js'))?>" defer></script><script src="<?=htmlspecialchars($asset('stage34.js'))?>" defer></script><link rel="stylesheet" href="<?=htmlspecialchars($asset('stage34.css'))?>"><link rel="stylesheet" href="<?=htmlspecialchars($asset('ui-kit.css'))?>">
<style>
@font-face{font-family:"Golos Text";font-style:normal;font-weight:400 900;font-display:swap;src:url("<?=$asset('GolosText-latin.woff2')?>") format("woff2")}
@font-face{font-family:"Golos Text";font-style:normal;font-weight:400 900;font-display:swap;src:url("<?=$asset('GolosText-cyrillic.woff2')?>") format("woff2");unicode-range:U+0301,U+0400-045F,U+0490-0491,U+04B0-04B1,U+2116}
:root{--cp-select-icon:url("<?=$asset('ui-select.svg')?>");--cp-card-arrow:url("<?=$asset('ui-card-arrow.svg')?>");--cp-card-arrow-hover:url("<?=$asset('ui-card-arrow-hover.svg')?>");--cp-location-icon:url("<?=$asset('ui-location.svg')?>")}
</style></head><body data-app="<?=$app?>" data-base="<?=htmlspecialchars($base)?>" data-api="<?=htmlspecialchars($base.'/demo-api.php')?>" data-brand="<?=htmlspecialchars($asset('brand.png'))?>"><div id="app"><div class="loading"><span class="brand-mark">Ч</span><h1>ЧОППРО</h1><p>Загрузка рабочего пространства…</p></div></div><div id="toast" role="status" aria-live="polite"></div><dialog id="dialog"></dialog></body></html>
