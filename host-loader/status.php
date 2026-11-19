<?php
declare(strict_types=1);
require __DIR__.'/boot.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$status = isset($_GET['check']) && CHOPPRO_STORAGE_MODE !== 'unavailable'
    ? $loader->sync()
    : $loader->json($loader->private.'/sync.json');
$s = $loader->state();
$schema = $loader->json(CHOPPRO_STORAGE_ROOT.'/schema.json');
$update = $loader->json(CHOPPRO_STORAGE_ROOT.'/update.json');
echo json_encode([
    'portal'=>$s['portal']??null,
    'demo'=>$s['demo']??null,
    'storage'=>CHOPPRO_STORAGE_MODE,
    'database_version'=>$schema['version']??0,
    'database_status'=>$update['status']??null,
    'checked_at'=>$status['checked_at']??null,
    'error'=>$status['error']??null,
    'pending'=>$status['pending']??null,
], JSON_UNESCAPED_UNICODE);
