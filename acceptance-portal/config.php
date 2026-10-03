<?php
declare(strict_types=1);

// No token is shipped. Environment variables are read only on the server.
$token = getenv('CHOPPRO_GITHUB_TOKEN') ?: getenv('GITHUB_TOKEN') ?: '';
return [
    'owner' => 'Jebvo777',
    'repository' => 'CHOPPRO',
    'branch' => getenv('CHOPPRO_GITHUB_BRANCH') ?: '',
    'token' => $token,
    'interval' => max(60, (int)(getenv('CHOPPRO_SYNC_INTERVAL') ?: ($token ? 60 : 300))),
    'cache_dir' => getenv('CHOPPRO_CACHE_DIR') ?: __DIR__.'/storage',
    'bootstrap_dir' => __DIR__.'/bootstrap',
    'manifest_path' => 'acceptance-portal/portal.json',
    'max_file_bytes' => 10 * 1024 * 1024,
    'max_snapshot_bytes' => 60 * 1024 * 1024,
    'max_files' => 2000,
    'timeout' => 8,
];
