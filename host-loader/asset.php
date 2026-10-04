<?php
declare(strict_types=1);
require __DIR__.'/boot.php';
$path=$_GET['path']??'';$sha=$_GET['release']??null;
if(!preg_match('~^(?:web/shared|acceptance-portal/assets)/[a-zA-Z0-9_.-]+\.(?:css|js|png|svg|woff2)$~',$path)){http_response_code(404);exit;}
try{$release=host_release('portal',$sha);$file=$release['path'].'/'.$path;if(!is_file($file)){http_response_code(404);exit;}$ext=pathinfo($path,PATHINFO_EXTENSION);header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','svg'=>'image/svg+xml','woff2'=>'font/woff2'][$ext]??'application/octet-stream'));header('X-Content-Type-Options: nosniff');header('Cache-Control: public,max-age=31536000,immutable');readfile($file);}catch(Throwable){http_response_code(404);}
