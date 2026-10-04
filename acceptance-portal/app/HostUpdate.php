<?php
declare(strict_types=1);
final class HostUpdate
{
    public static function ensure(bool $force=false):array
    {
        if(!defined('CHOPPRO_HOST_ROOT'))return[];global $loader;$state=$loader->state();$sha=$state['portal']??'';$statusPath=CHOPPRO_STORAGE_ROOT.'/update.json';$old=$loader->json($statusPath);
        if(!$force&&($old['release']??'')===$sha&&($old['status']??'')==='ready')return$old;
        if(!$force&&($old['retry_after']??0)>time())return$old;
        $lock=fopen($loader->private.'/database-update.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return['status'=>'updating'];}
        try{
            @set_time_limit(240);$release=$loader->current('portal');require_once $release['path'].'/backend/src/Core/Autoloader.php';$config=\Choppro\Stage2\Config::load();$result=(new \Choppro\Stage2\Migrator(new \Choppro\Stage2\Db($config),$config))->apply();$loader->promote();$out=['status'=>'ready','release'=>$release['sha'],'at'=>gmdate('c'),'database'=>$result];$loader->atomic($statusPath,json_encode($out,JSON_UNESCAPED_UNICODE));return$out;
        }catch(Throwable $e){error_log('CHOPPRO automatic database update: '.$e->getMessage());$out=['status'=>'retry','release'=>$sha,'retry_after'=>time()+60,'message'=>'Обновление базы пока не завершено. Предыдущая версия демо продолжает работать. Повторите обновление.'];$loader->atomic($statusPath,json_encode($out,JSON_UNESCAPED_UNICODE));return$out;}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
