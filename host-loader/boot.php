<?php
declare(strict_types=1);
require_once __DIR__.'/ReleaseLoader.php';
require_once __DIR__.'/HostingStorage.php';
$hostRoot = dirname(__DIR__);
$hostStorage = HostingStorage::locate($hostRoot);
$loader = new ReleaseLoader($hostRoot, storage: $hostStorage->directory);
$base=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/index.php'));if($base==='/'||$base==='.')$base='';
if(!defined('CHOPPRO_HOST_ROOT'))define('CHOPPRO_HOST_ROOT',$hostRoot);
if(!defined('CHOPPRO_HOST_BASE'))define('CHOPPRO_HOST_BASE',$base);
if(!defined('CHOPPRO_STORAGE_ROOT'))define('CHOPPRO_STORAGE_ROOT',$hostStorage->directory.'/runtime');
if(!defined('CHOPPRO_STORAGE_MODE'))define('CHOPPRO_STORAGE_MODE',$hostStorage->mode);
function host_release(string $space='portal',?string $sha=null):array
{
    global $loader;$release=$sha?['sha'=>$sha,'path'=>$loader->path($sha)]:$loader->current($space);
    if(!defined('CHOPPRO_RELEASE_ROOT'))define('CHOPPRO_RELEASE_ROOT',$release['path']);if(!defined('CHOPPRO_RELEASE_SHA'))define('CHOPPRO_RELEASE_SHA',$release['sha']);return$release;
}
