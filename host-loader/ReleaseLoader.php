<?php
declare(strict_types=1);
final class ReleaseLoader
{
    public string $private;
    public function __construct(public string $root){$this->private=$root.'/.private';}
    public function json(string $path):array{if(!is_file($path))return[];try{return json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR)?:[];}catch(Throwable){return[];}}
    public function atomic(string $path,string $text):void{if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);$temp=$path.'.'.bin2hex(random_bytes(5)).'.tmp';if(file_put_contents($temp,$text,LOCK_EX)!==strlen($text)||!rename($temp,$path))throw new RuntimeException('Не удалось сохранить состояние');chmod($path,0600);}
    public function state():array{return $this->json($this->private.'/current.json');}
    public function path(string $sha):string{if(!preg_match('/^[a-f0-9]{40}$/',$sha))throw new RuntimeException('Некорректная версия');$path=$this->private.'/releases/'.$sha;if(!is_file($path.'/.release.json'))throw new RuntimeException('Версия не подготовлена');return$path;}
    public function current(string $space='portal'):array{$s=$this->state();$sha=$s[$space]??$s['portal']??'';return['sha'=>$sha,'path'=>$this->path($sha)];}
    public function allowed(string $path):bool
    {
        if(str_contains($path,'..')||str_contains($path,'\\')||str_starts_with($path,'/')||preg_match('/[\x00-\x1f]/',$path))return false;
        if(in_array($path,['README.md','site-release.json'],true))return true;
        return preg_match('~^(backend/(src|config|database|public|scripts|assets)/|web/|docs/|prototypes/|acceptance-portal/(app/|assets/|prototypes/|(?:index|config|api|file|prototype)\.php$|portal\.json$|robots\.txt$))~u',$path)===1&&!preg_match('~\.(?:pdf|part\d+)$~i',$path);
    }
    public function request(string $url):string
    {
        if(!str_starts_with($url,'https://api.github.com/repos/Jebvo777/CHOPPRO/')&&!str_starts_with($url,'https://raw.githubusercontent.com/Jebvo777/CHOPPRO/'))throw new RuntimeException('Недопустимый источник');
        $c=curl_init($url);$headers=['Accept: application/vnd.github+json','User-Agent: CHOPPRO-Shared-Host/2','X-GitHub-Api-Version: 2022-11-28'];$token=getenv('CHOPPRO_GITHUB_TOKEN')?:'';if($token&&str_starts_with($url,'https://api.github.com/'))$headers[]='Authorization: Bearer '.$token;
        curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);if($body===false||$code!==200)throw new RuntimeException($code===403||$code===429?'GitHub временно ограничил запросы. Используется сохранённая версия.':'GitHub временно недоступен. Используется сохранённая версия.');if(strlen($body)>12*1024*1024)throw new RuntimeException('Размер файла превышает ограничение');return$body;
    }
    public function sync(bool $force=false):array
    {
        $status=$this->json($this->private.'/sync.json');if(!$force&&($status['next_check']??0)>time())return$status;
        $lock=fopen($this->private.'/sync.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return['busy'=>true];}
        try{
            $status=$this->json($this->private.'/sync.json');if(!$force&&($status['next_check']??0)>time())return$status;
            @set_time_limit(180);$base='https://api.github.com/repos/Jebvo777/CHOPPRO/';$commits=json_decode($this->request($base.'commits?sha=main&per_page=1'),true,512,JSON_THROW_ON_ERROR);$commit=$commits[0]??[];$sha=$commit['sha']??'';if(!preg_match('/^[a-f0-9]{40}$/',$sha))throw new RuntimeException('Не удалось определить main');
            $state=$this->state();$status=['checked_at'=>time(),'next_check'=>time()+300,'head'=>$sha,'error'=>null];
            if(($state['portal']??'')!==$sha){
                $runs=json_decode($this->request($base.'actions/runs?head_sha='.$sha.'&per_page=30'),true,512,JSON_THROW_ON_ERROR);$valid=false;foreach($runs['workflow_runs']??[]as$run)if(($run['name']??'')==='Stage 2 acceptance'&&$run['conclusion']==='success'&&$run['event']==='push'&&$run['head_branch']==='main')$valid=true;
                if(!$valid){$status['pending']='Новая версия main проходит проверку. Работает последняя проверенная версия.';$this->atomic($this->private.'/sync.json',json_encode($status,JSON_UNESCAPED_UNICODE));return$status;}
                $result=json_decode($this->request($base.'git/trees/'.$commit['commit']['tree']['sha'].'?recursive=1'),true,512,JSON_THROW_ON_ERROR);if($result['truncated']??true)throw new RuntimeException('Дерево репозитория неполное');$entries=[];foreach($result['tree']as$e)if($e['type']==='blob'&&$e['mode']==='100644'&&$this->allowed($e['path']))$entries[$e['path']]=$e;
                $this->prepare($sha,$entries,$commit);
                $manifest=$this->json($this->path($sha).'/site-release.json');$schema=$this->json($this->private.'/runtime/schema.json')['version']??0;
                $new=['portal'=>$sha,'demo'=>(int)$schema>=(int)$manifest['schema']?$sha:($state['demo']??$state['portal']??$sha),'previous'=>$state['portal']??null,'installed_at'=>time()];
                $this->atomic($this->private.'/current.json',json_encode($new));$status['updated']=true;
            }
            $this->atomic($this->private.'/sync.json',json_encode($status,JSON_UNESCAPED_UNICODE));return$status;
        }catch(Throwable $e){$status=['checked_at'=>time(),'next_check'=>time()+600,'error'=>$e->getMessage()];$this->atomic($this->private.'/sync.json',json_encode($status,JSON_UNESCAPED_UNICODE));return$status;}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function prepare(string $sha,array $entries,array $commit):void
    {
        $final=$this->private.'/releases/'.$sha;if(is_file($final.'/.release.json'))return;if(count($entries)>2000)throw new RuntimeException('Слишком много файлов');$stage=$this->private.'/releases/.staging-'.$sha.'-'.bin2hex(random_bytes(4));mkdir($stage,0700,true);$size=0;
        foreach($entries as$path=>$e){$size+=(int)$e['size'];if($size>100*1024*1024||(int)$e['size']>10*1024*1024)throw new RuntimeException('Размер комплекта превышает ограничение');$object=$this->private.'/objects/'.$e['sha'];if(!is_file($object)||sha1('blob '.filesize($object)."\0".file_get_contents($object))!==$e['sha']){$content=$this->request('https://raw.githubusercontent.com/Jebvo777/CHOPPRO/'.$sha.'/'.implode('/',array_map('rawurlencode',explode('/',$path))));if(sha1('blob '.strlen($content)."\0".$content)!==$e['sha'])throw new RuntimeException('Контрольная сумма не совпала: '.$path);$this->atomic($object,$content);}
            $dest=$stage.'/'.$path;if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);if(!copy($object,$dest))throw new RuntimeException('Не удалось подготовить комплект');
        }
        foreach(['site-release.json','acceptance-portal/index.php','backend/public/index.php','web/shared/app.php']as$required)if(!isset($entries[$required]))throw new RuntimeException('В комплекте отсутствует '.$required);
        $manifest=$this->json($stage.'/site-release.json');if(($manifest['format']??0)!==1||version_compare(PHP_VERSION,$manifest['php']??'8.3','<'))throw new RuntimeException('Версия PHP хостинга не совместима с обновлением');
        $this->catalog($stage,$sha,$entries,$commit);$this->atomic($stage.'/.release.json',json_encode(['sha'=>$sha,'files'=>$entries,'created_at'=>time()]));if(!rename($stage,$final))throw new RuntimeException('Не удалось опубликовать комплект');
    }
    public function catalog(string $dir,string $sha,array $entries,array $commit):void
    {
        $cache=$dir.'/.portal-cache';mkdir($cache.'/blobs',0700,true);$files=[];
        foreach($entries as$path=>$e)if($path==='README.md'||str_starts_with($path,'docs/')||str_starts_with($path,'prototypes/')||$path==='acceptance-portal/portal.json'){$files[$path]=$e;copy($dir.'/'.$path,$cache.'/blobs/'.$e['sha']);}
        $manifest=$this->json($dir.'/acceptance-portal/portal.json');$snapshot=['sha'=>$sha,'branch'=>'main','tree'=>$entries,'files'=>$files,'manifest'=>$manifest,'synced_at'=>time(),'message'=>$commit['commit']['message']??'Этап 2','commit_date'=>$commit['commit']['committer']['date']??gmdate('c'),'commits'=>[['sha'=>$sha,'message'=>$commit['commit']['message']??'Этап 2','date'=>$commit['commit']['committer']['date']??gmdate('c')]]];$this->atomic($cache.'/current.json',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$this->atomic($cache.'/state.json',json_encode(['checked_at'=>time(),'next_check_at'=>time()+300,'error'=>null]));
    }
    public function promote():void{$state=$this->state();$manifest=$this->json($this->path($state['portal']).'/site-release.json');$schema=$this->json($this->private.'/runtime/schema.json');if(($schema['version']??0)<$manifest['schema'])throw new RuntimeException('Схема БД ещё не готова');$state['demo']=$state['portal'];$this->atomic($this->private.'/current.json',json_encode($state));}
}
