<?php
declare(strict_types=1);
// Export the real PHP output for repeatable UI checks without an external GitHub call.
$root = dirname(__DIR__);
$config = require $root.'/config.php';
$cache = $config['cache_dir'];
if (!is_dir($cache)) mkdir($cache, 0775, true);
file_put_contents($cache.'/state.json', json_encode([
    'next_check_at' => time() + 3600, 'checked_at' => time(), 'error' => null,
]));
$pages = ['overview', 'docs', 'diagrams', 'requirements', 'stories', 'acceptance', 'prototypes', 'github', 'openapi', 'delivery'];
$out = $argv[1] ?? $root.'/tests/rendered';
if (!is_dir($out)) mkdir($out, 0775, true);
foreach ($pages as $page) {
    $command = PHP_BINARY.' -r '.escapeshellarg(
        '$_GET["page"]='.var_export($page, true).';$_SERVER["REQUEST_URI"]="/?page='.$page.'";include '.var_export($root.'/index.php', true).';'
    );
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $html = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0 || $error !== '') throw new RuntimeException($page.': '.$error);
    file_put_contents($out.'/'.$page.'.html', $html);
    echo 'Rendered '.$page.' '.strlen($html).' bytes'.PHP_EOL;
}
