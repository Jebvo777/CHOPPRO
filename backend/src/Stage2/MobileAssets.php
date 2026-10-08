<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class MobileAssets
{
    public static function serve(array $query):never
    {
        $name=(string)($query['name']??'');if(!preg_match('/^(index-[a-f0-9]{32}\.js|icon\.[a-f0-9]{32}\.png|favicon\.ico)$/',$name)){http_response_code(404);exit;}$root=dirname(__DIR__,3).'/web/mobile/build/';$path=$root.$name;$gzip=$path.'.gz';$type=str_ends_with($name,'.js')?'text/javascript; charset=utf-8':(str_ends_with($name,'.png')?'image/png':'image/x-icon');header('Content-Type: '.$type);header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=300');if(is_file($gzip)){if(str_contains($_SERVER['HTTP_ACCEPT_ENCODING']??'','gzip')){header('Content-Encoding: gzip');header('Vary: Accept-Encoding');readfile($gzip);}else echo gzdecode(file_get_contents($gzip));exit;}if(is_file($path)){readfile($path);exit;}http_response_code(404);exit;
    }
}
