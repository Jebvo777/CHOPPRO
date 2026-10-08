<?php
declare(strict_types=1);
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if(preg_match('~/(?:\.[^/]+|INSTALL[^/]*|installation[^/]*)~',$path)||str_contains($path,'..')){http_response_code(403);exit;}
if(preg_match('~^/demo/(admin|client|platform|jobs|mobile)/?$~',$path,$m)){$_GET['app']=$m[1];$_SERVER['SCRIPT_NAME']='/demo.php';require $_SERVER['DOCUMENT_ROOT'].'/demo.php';return true;}
if($path==='/'){$_SERVER['SCRIPT_NAME']='/index.php';require $_SERVER['DOCUMENT_ROOT'].'/index.php';return true;}
return false;
