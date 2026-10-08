<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class RequestMetrics
{
    public static function record(array $config,float $started):void
    {
        $file=$config['storage'].'/requests.json';$lock=fopen($config['storage'].'/requests.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return;}
        try{$rows=is_file($file)?Support::decode(file_get_contents($file)):[];$rows=array_values(array_filter($rows,fn($r)=>$r[0]>time()-86400));$rows[]=[time(),round((microtime(true)-$started)*1000,2),http_response_code()?:200];Support::atomic($file,Support::json(array_slice($rows,-2000)));}catch(\Throwable$e){error_log('CHOPPRO metrics unavailable');}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function get(array $config):array
    {
        $file=$config['storage'].'/requests.json';$rows=is_file($file)?Support::decode(file_get_contents($file)):[];$rows=array_values(array_filter($rows,fn($r)=>$r[0]>time()-3600));$durations=array_column($rows,1);sort($durations);$percentile=fn($p)=>$durations?$durations[max(0,(int)ceil(count($durations)*$p)-1)]:null;
        return['window_minutes'=>60,'requests'=>count($rows),'errors'=>count(array_filter($rows,fn($r)=>$r[2]>=500)),'p95_ms'=>$percentile(.95),'p99_ms'=>$percentile(.99),'p95_target_ms'=>500,'p99_target_ms'=>2000,'storage_free_bytes'=>disk_free_space($config['storage'])?:null];
    }
}
