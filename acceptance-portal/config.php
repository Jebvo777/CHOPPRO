<?php
declare(strict_types=1);


$token = getenv('CHOPPRO_GITHUB_TOKEN') ?: getenv('GITHUB_TOKEN') ?: '';
return [
    'owner' => 'Jebvo777',
    'repository' => 'CHOPPRO',
    'branch' => getenv('CHOPPRO_GITHUB_BRANCH') ?: '',
    
    
    'transition_branch' => '',
    'token' => $token,
    'interval' => max(60, (int)(getenv('CHOPPRO_SYNC_INTERVAL') ?: ($token ? 60 : 300))),
    'cache_dir' => defined('CHOPPRO_RELEASE_ROOT') ? CHOPPRO_RELEASE_ROOT.'/.portal-cache' : (getenv('CHOPPRO_CACHE_DIR') ?: __DIR__.'/storage'),
    'bootstrap_dir' => __DIR__.'/bootstrap',
    'manifest_path' => 'acceptance-portal/portal.json',
    'max_file_bytes' => 10 * 1024 * 1024,
    'max_snapshot_bytes' => 60 * 1024 * 1024,
    'max_files' => 2000,
    'timeout' => 8,
];
