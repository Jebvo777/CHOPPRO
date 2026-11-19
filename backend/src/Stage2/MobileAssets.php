<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class MobileAssets
{
    public static function serve(array $query):never
    {
        $name=(string)($query['name']??'');
        if(!preg_match('/^(index-[a-f0-9]{32}\.js|icon\.[a-f0-9]{32}\.png|Golos(?:Regular|Medium|Semibold|Bold)\.[a-f0-9]{32}\.ttf|favicon\.ico)$/D',$name)){
            http_response_code(404);
            exit;
        }
        $path=dirname(__DIR__,3).'/web/mobile/build/'.$name;
        $gzip=$path.'.gz';
        if(!is_file($path)&&!is_file($gzip)){
            http_response_code(404);
            exit;
        }
        $type=match(pathinfo($name,PATHINFO_EXTENSION)){
            'js'=>'text/javascript; charset=utf-8','png'=>'image/png','ttf'=>'font/ttf','ico'=>'image/x-icon',
        };
        header('Content-Type: '.$type);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=300');
        if(is_file($gzip)){
            header('Vary: Accept-Encoding');
            if(str_contains($_SERVER['HTTP_ACCEPT_ENCODING']??'','gzip')){
                header('Content-Encoding: gzip');
                readfile($gzip);
            }else{
                echo gzdecode(file_get_contents($gzip));
            }
        }else{
            readfile($path);
        }
        exit;
    }
}
