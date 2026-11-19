<?php
declare(strict_types=1);
require dirname(__DIR__).'/host-loader/ReleaseLoader.php';
$root=(getenv('CHOPPRO_STORAGE_ROOT')?:sys_get_temp_dir()).'/loader-test-'.bin2hex(random_bytes(4));mkdir($root.'/.private/releases',0700,true);$checks=0;
function expect(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$files=['site-release.json'=>json_encode(['format'=>1,'php'=>'8.3','schema'=>3]),'acceptance-portal/index.php'=>'<?php echo "portal one";','acceptance-portal/portal.json'=>'{"sections":[]}','backend/public/index.php'=>'<?php echo "api one";','web/shared/app.php'=>'<?php echo "app one";','README.md'=>'# Initial'];
$head=str_repeat('1',40);$broken=false;$checksumBad=false;$requests=0;
$transport=function(string $url)use(&$files,&$head,&$broken,&$checksumBad,&$requests):string{$requests++;if(str_contains($url,'/commits?'))return json_encode([['sha'=>$head,'commit'=>['tree'=>['sha'=>$head],'message'=>'Test','committer'=>['date'=>gmdate('c')]]]]);if(str_contains($url,'/actions/runs'))return json_encode(['workflow_runs'=>[['name'=>'Stage 2 acceptance','conclusion'=>'success','event'=>'push','head_branch'=>'main']]]);if(str_contains($url,'/git/trees/')){$tree=[];foreach($files as$path=>$content)$tree[]=['path'=>$path,'type'=>'blob','mode'=>'100644','size'=>strlen($content),'sha'=>sha1('blob '.strlen($content)."\0".$content)];return json_encode(['tree'=>$tree,'truncated'=>false]);}if(str_contains($url,'raw.githubusercontent.com')){if($broken)throw new RuntimeException('Simulated interrupted network');$path=rawurldecode(implode('/',array_slice(explode('/',parse_url($url,PHP_URL_PATH)),4)));return$checksumBad?'tampered':$files[$path];}throw new RuntimeException('Unexpected URL');};
$loader=new ReleaseLoader($root,$transport);$loader->sync(true);expect($loader->state()['portal']===$head,'Initial complete publication');$first=$loader->current();expect(file_get_contents($first['path'].'/backend/public/index.php')===$files['backend/public/index.php'],'Pinned request complete');$count=$requests;$loader->sync();expect($requests===$count,'Polling interval');
$head=str_repeat('2',40);$files['backend/public/index.php']='<?php echo "api two";';$files['site-release.json']=json_encode(['format'=>1,'php'=>'8.3','schema'=>4]);$broken=true;$loader->sync(true);expect($loader->state()['portal']===str_repeat('1',40),'Interrupted download keeps old release');$broken=false;$checksumBad=true;$loader->sync(true);expect($loader->state()['portal']===str_repeat('1',40),'Bad checksum keeps old release');$checksumBad=false;$loader->sync(true);expect($loader->state()['portal']===$head,'Complete new portal published');expect($loader->state()['demo']===str_repeat('1',40),'Schema gating keeps old demo');expect(file_get_contents($first['path'].'/backend/public/index.php')==='<?php echo "api one";','Old running request retains immutable files');$loader->atomic($loader->private.'/runtime/schema.json','{"version":4}');$loader->promote();expect($loader->state()['demo']===$head,'Migration promotes demo');
expect(!$loader->allowed('../site.php')&&!$loader->allowed('.private/site.php')&&!$loader->allowed('upload/report.pdf'),'Unsafe and excluded paths');$lock=fopen($loader->private.'/sync.lock','c');flock($lock,LOCK_EX);expect($loader->sync(true)['busy']===true,'Concurrent download lock');flock($lock,LOCK_UN);fclose($lock);

$bundled = str_repeat('a', 40);
$bundledPath = $root.'/.loader/releases/'.$bundled;
mkdir($bundledPath, 0700, true);
file_put_contents($bundledPath.'/.release.json', json_encode(['sha'=>$bundled]));
file_put_contents($root.'/.loader/bundled.json', json_encode(['sha'=>$bundled]));
$installed = new ReleaseLoader($root, $transport);
expect($installed->current()['sha'] === $head, 'An available synchronized release remains active');
$pointer = $installed->private.'/current.json';
unlink($pointer);
expect($installed->current() === ['sha'=>$bundled, 'path'=>$bundledPath], 'The archive starts without a state file');
expect($installed->current('demo')['sha'] === $bundled, 'Demo starts from the archive without a state file');
file_put_contents($pointer, json_encode(['portal'=>str_repeat('f', 40), 'demo'=>false]));
expect($installed->state()['portal'] === $bundled && $installed->state()['demo'] === $bundled, 'Invalid release pointers fall back to the archive');
echo 'PASS '.$checks." release-loader checks\n";
function clean(string $path):void{foreach(scandir($path)as$entry)if($entry!=='.'&&$entry!=='..'){$p=$path.'/'.$entry;if(is_dir($p))clean($p);else unlink($p);}rmdir($path);}clean($root);
