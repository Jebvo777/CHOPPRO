<?php
declare(strict_types=1);
require_once __DIR__.'/ReleaseLoader.php';
$hostRoot=dirname(__DIR__);$loader=new ReleaseLoader($hostRoot);
$base=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/index.php'));if($base==='/'||$base==='.')$base='';
if(!defined('CHOPPRO_HOST_ROOT'))define('CHOPPRO_HOST_ROOT',$hostRoot);
if(!defined('CHOPPRO_HOST_BASE'))define('CHOPPRO_HOST_BASE',$base);
if(!defined('CHOPPRO_STORAGE_ROOT'))define('CHOPPRO_STORAGE_ROOT',$hostRoot.'/.private/runtime');
function host_release(string $space='portal',?string $sha=null):array
{
    global $loader;$release=$sha?['sha'=>$sha,'path'=>$loader->path($sha)]:$loader->current($space);
    $lease=fopen($release['path'].'/.lease','c');flock($lease,LOCK_SH);register_shutdown_function(static function()use($lease){flock($lease,LOCK_UN);fclose($lease);});
    if(!defined('CHOPPRO_RELEASE_ROOT'))define('CHOPPRO_RELEASE_ROOT',$release['path']);if(!defined('CHOPPRO_RELEASE_SHA'))define('CHOPPRO_RELEASE_SHA',$release['sha']);return$release;
}
