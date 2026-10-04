<?php
declare(strict_types=1);
require __DIR__.'/boot.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$status=isset($_GET['check'])?$loader->sync():$loader->json($loader->private.'/sync.json');$s=$loader->state();$schema=$loader->json($loader->private.'/runtime/schema.json');echo json_encode(['portal'=>$s['portal']??null,'demo'=>$s['demo']??null,'database_version'=>$schema['version']??0,'checked_at'=>$status['checked_at']??null,'error'=>$status['error']??null,'pending'=>$status['pending']??null],JSON_UNESCAPED_UNICODE);
