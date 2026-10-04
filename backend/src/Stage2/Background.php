<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Background
{
    public function __construct(public array $config){}
    public function run():void
    {
        $root=$this->config['storage'];$worker=is_file($root.'/worker.json')?Support::decode(file_get_contents($root.'/worker.json')):[];
        if(strtotime($worker['last_run']??'1970-01-01')>time()-120)return;
        $lock=fopen($root.'/background.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return;}
        try{$state=is_file($root.'/background.json')?Support::decode(file_get_contents($root.'/background.json')):[];if(($state['next_attempt']??0)>time())return;Support::atomic($root.'/background.json',Support::json(['next_attempt'=>time()+120]));(new Worker(new Db($this->config),$this->config))->tick();}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
